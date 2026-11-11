<?php

namespace App\Services\Sales;

use App\Enums\DiscountType;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SystemMessage;
use App\Services\Inventory\StockService;
use App\Services\Sales\Concerns\WritesDocumentLines;
use App\Services\System\AuditLogger;
use App\Services\System\NumberingService;
use App\Services\System\SettingsService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Invoice lifecycle: drafting (directly, from an accepted quotation or as a corrected copy of a voided
 * invoice), issuing (number + stock out of the chosen warehouse), voiding (stock back in) and the
 * overdue reminders. Payments are handled by PaymentService, which calls refreshPaymentStatus().
 */
class InvoiceService
{
    use WritesDocumentLines;

    public function __construct(
        private DocumentCalculator $calculator,
        private NumberingService $numbering,
        private SettingsService $settings,
        private StockService $stock,
        private QuotationService $quotations,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{customer_id: int, warehouse_id?: int|null, invoice_date: string, due_date?: string|null, document_discount_type?: string|null, document_discount_value?: string|null, notes?: string|null, terms?: string|null, lines: list<array<string, mixed>>}  $data
     */
    public function createDraft(array $data, User $actor, ?Quotation $quotation = null, ?Invoice $copiedFrom = null): Invoice
    {
        return DB::transaction(function () use ($data, $actor, $quotation, $copiedFrom): Invoice {
            $invoice = new Invoice($this->headerAttributes($data));
            $invoice->forceFill([
                'status' => InvoiceStatus::Draft,
                'currency' => (string) $this->settings->get('currency', 'code', 'UGX'),
                'quotation_id' => $quotation?->id,
                'copied_from_invoice_id' => $copiedFrom?->id,
                'created_by' => $actor->id,
            ])->save();

            $comment = match (true) {
                $quotation !== null => "Created from quotation {$quotation->number}",
                $copiedFrom !== null => "Corrected copy of {$copiedFrom->number}",
                default => null,
            };
            $invoice->recordStatusChange(null, InvoiceStatus::Draft, $comment);

            $this->replaceLines($invoice, $data['lines']);

            return $invoice;
        });
    }

    /**
     * @param  array{customer_id: int, warehouse_id?: int|null, invoice_date: string, due_date?: string|null, document_discount_type?: string|null, document_discount_value?: string|null, notes?: string|null, terms?: string|null, lines: list<array<string, mixed>>}  $data
     */
    public function updateDraft(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $invoice = $this->lock($invoice);
            $this->ensureStatus($invoice, [InvoiceStatus::Draft], 'Only a draft invoice can be edited. Issued invoices are changed by voiding them.');

            $invoice->update($this->headerAttributes($data));
            $this->replaceLines($invoice, $data['lines']);

            return $invoice;
        });
    }

    /**
     * Delete a draft. A draft converted from a quotation releases the quotation so it can be invoiced again.
     */
    public function deleteDraft(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = $this->lock($invoice);
            $this->ensureStatus($invoice, [InvoiceStatus::Draft], 'Only a draft invoice can be deleted. Void an issued invoice instead.');

            if ($invoice->quotation_id !== null) {
                $this->quotations->releaseConversion($invoice->quotation, $invoice->displayNumber());
                $invoice->forceFill(['quotation_id' => null])->save();
            }

            $invoice->delete();
        });
    }

    /**
     * Create a draft invoice from an accepted quotation. The quotation is marked as converted in the same
     * transaction, so it can only be invoiced once. Lines keep the tax rate the customer accepted.
     */
    public function createFromQuotation(Quotation $quotation, User $actor): Invoice
    {
        return DB::transaction(function () use ($quotation, $actor): Invoice {
            $quotation = Quotation::query()->lockForUpdate()->with('items', 'customer')->findOrFail($quotation->id);
            $data = $this->quotations->invoiceDraftData($quotation);

            $invoice = $this->createDraft([
                ...$data,
                'warehouse_id' => Warehouse::default()?->id,
                'invoice_date' => today()->toDateString(),
                'due_date' => null,
            ], $actor, $quotation);

            $this->quotations->markConverted($quotation, $actor, $invoice->displayNumber());

            return $invoice;
        });
    }

    /**
     * Start a corrected draft from a voided invoice (void-and-reissue, docs/07 B4).
     */
    public function createCorrectedCopy(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = $this->lock($invoice);
            $this->ensureStatus($invoice, [InvoiceStatus::Void], 'Only a voided invoice can be copied into a corrected draft.');

            $invoice->load('items');

            return $this->createDraft([
                'customer_id' => $invoice->customer_id,
                'warehouse_id' => $invoice->warehouse_id,
                'invoice_date' => today()->toDateString(),
                'due_date' => null,
                'document_discount_type' => $invoice->document_discount_type?->value,
                'document_discount_value' => (string) $invoice->document_discount_value,
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'lines' => self::editableLines($invoice),
            ], $actor, copiedFrom: $invoice);
        });
    }

    /**
     * Finalise a draft: assign its number, lock it, and take stocked items out of the invoice's warehouse.
     * Everything happens in one transaction; if any item is short of stock nothing is issued and the
     * number is released.
     */
    public function issue(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = $this->lock($invoice);
            $this->ensureStatus($invoice, [InvoiceStatus::Draft], 'Only a draft invoice can be issued.');
            $invoice->load('items.item', 'warehouse');

            if ($invoice->items->isEmpty()) {
                throw new BusinessRuleException('Add at least one line before issuing.');
            }

            if (! $invoice->customer->is_active) {
                throw new BusinessRuleException("{$invoice->customer->name} is inactive. Reactivate the customer before issuing.");
            }

            if ($invoice->invoice_date->gt(today())) {
                throw new BusinessRuleException('An invoice cannot be issued with a future date.');
            }

            if (! Money::of((string) $invoice->total)->isPositive()) {
                throw new BusinessRuleException('An invoice must have a total greater than zero.');
            }

            $invoice->number = $this->numbering->next(DocumentType::Invoice, $invoice->invoice_date);
            $invoice->forceFill([
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'amount_paid' => '0',
                'balance_due' => (string) $invoice->total,
            ]);
            $invoice->transitionTo(InvoiceStatus::Issued);

            $this->issueStock($invoice);

            $this->auditLogger->log('issued', $invoice, "Issued invoice {$invoice->number} for ".$this->settings->formatMoney((string) $invoice->total));

            return $invoice;
        });
    }

    /**
     * Cancel an issued invoice that has no completed payments. Stock it issued returns to the warehouse.
     */
    public function void(Invoice $invoice, User $actor, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            $invoice = $this->lock($invoice);
            $this->ensureStatus($invoice, [InvoiceStatus::Issued], 'Only an issued invoice without payments can be voided.');

            if ($invoice->hasCompletedPayments()) {
                throw new BusinessRuleException('This invoice has payments. Reverse them before voiding the invoice.');
            }

            $this->stock->reverseFor($invoice, "Invoice {$invoice->number} voided");

            $invoice->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
                'balance_due' => '0',
            ]);
            $invoice->transitionTo(InvoiceStatus::Void, $reason);

            $this->auditLogger->log('voided', $invoice, "Voided invoice {$invoice->number}: {$reason}");

            return $invoice;
        });
    }

    /**
     * Recalculate amount paid and balance from the invoice's completed payments and move it to the matching
     * status. Must run inside the payment transaction, with the invoice locked.
     */
    public function refreshPaymentStatus(Invoice $invoice, ?string $comment = null): Invoice
    {
        $paid = Money::of((string) ($invoice->completedPayments()->sum('amount') ?: '0'));
        $total = Money::of((string) $invoice->total);

        if ($paid->greaterThan($total)) {
            throw new BusinessRuleException('Payments cannot exceed the invoice total.');
        }

        $invoice->forceFill([
            'amount_paid' => $paid->toDecimal(),
            'balance_due' => $total->minus($paid)->toDecimal(),
        ]);

        $status = match (true) {
            $paid->isZero() => InvoiceStatus::Issued,
            $paid->equals($total) => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };

        if ($status !== $invoice->status) {
            $invoice->transitionTo($status, $comment);
        } else {
            $invoice->save();
        }

        return $invoice;
    }

    /**
     * Notify the people who chase payments about invoices that became overdue since the last check.
     * Each invoice is announced once; returns the number of newly overdue invoices.
     */
    public function notifyNewlyOverdue(?CarbonInterface $today = null): int
    {
        $today ??= today();

        $invoices = Invoice::query()
            ->with('customer')
            ->open()
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('balance_due', '>', 0)
            ->whereNull('overdue_notified_at')
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            return 0;
        }

        $recipients = User::query()->active()->permission(['payments.record', 'invoices.void'])->get();
        $total = Money::sum($invoices->map(fn (Invoice $invoice): string => (string) $invoice->balance_due));
        $examples = $invoices->take(3)->map(fn (Invoice $invoice): string => "{$invoice->number} ({$invoice->customer->name})")->implode(', ');
        $more = $invoices->count() > 3 ? ' and '.($invoices->count() - 3).' more' : '';

        Notification::send($recipients, new SystemMessage(
            $invoices->count() === 1 ? '1 invoice is now overdue' : "{$invoices->count()} invoices are now overdue",
            "{$examples}{$more}. Outstanding: ".$this->settings->formatMoney($total).'.',
            route('invoices.index', ['status' => 'overdue']),
            'exclamation-triangle',
        ));

        Invoice::query()->whereKey($invoices->modelKeys())->update(['overdue_notified_at' => now()]);

        return $invoices->count();
    }

    /**
     * The default due date for an invoice dated $invoiceDate: the customer's payment terms, or the
     * organization's default terms.
     */
    public function defaultDueDate(?Customer $customer, CarbonInterface|string $invoiceDate): Carbon
    {
        $days = $customer?->payment_terms_days ?? (int) $this->settings->get('finance', 'invoice_payment_terms_days', 30);

        return Carbon::parse($invoiceDate)->startOfDay()->addDays(max(0, (int) $days));
    }

    /**
     * Take each stocked item out of the warehouse, one movement per item (lines for the same item are
     * combined), in item order so concurrent invoices lock balances in the same sequence.
     */
    private function issueStock(Invoice $invoice): void
    {
        $quantities = [];

        foreach ($invoice->items as $line) {
            if ($line->item === null || ! $line->item->isStocked()) {
                continue;
            }

            $quantities[$line->item_id] = bcadd($quantities[$line->item_id] ?? '0', (string) $line->quantity, StockService::QUANTITY_SCALE);
        }

        if ($quantities === []) {
            return;
        }

        if ($invoice->warehouse === null) {
            throw new BusinessRuleException('Choose the warehouse the goods leave from before issuing this invoice.');
        }

        ksort($quantities);
        $items = Item::query()->whereKey(array_keys($quantities))->get()->keyBy('id');

        foreach ($quantities as $itemId => $quantity) {
            $this->stock->issue(
                $items[$itemId],
                $invoice->warehouse,
                $quantity,
                StockMovementType::SaleIssue,
                $invoice,
                "Invoice {$invoice->number}",
            );
        }
    }

    /**
     * Write the lines, then keep the draft's balance equal to its total.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceLines(Invoice $invoice, array $lines): void
    {
        $this->writeLines($invoice, $lines);

        $invoice->forceFill(['amount_paid' => '0', 'balance_due' => (string) $invoice->total])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerAttributes(array $data): array
    {
        $type = filled($data['document_discount_type'] ?? null) ? DiscountType::from($data['document_discount_type']) : null;
        $customer = Customer::query()->find($data['customer_id']);
        $dueDate = filled($data['due_date'] ?? null)
            ? $data['due_date']
            : $this->defaultDueDate($customer, $data['invoice_date'])->toDateString();

        if (Carbon::parse($dueDate)->lt(Carbon::parse($data['invoice_date']))) {
            throw new BusinessRuleException('The due date cannot be before the invoice date.');
        }

        return [
            'customer_id' => $data['customer_id'],
            'warehouse_id' => filled($data['warehouse_id'] ?? null) ? (int) $data['warehouse_id'] : null,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $dueDate,
            'document_discount_type' => $type?->value,
            'document_discount_value' => $type !== null && filled($data['document_discount_value'] ?? null) ? (string) $data['document_discount_value'] : '0',
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
        ];
    }

    /**
     * @param  list<InvoiceStatus>  $allowed
     */
    private function ensureStatus(Invoice $invoice, array $allowed, string $message): void
    {
        if (! in_array($invoice->status, $allowed, true)) {
            throw new BusinessRuleException($message);
        }
    }

    private function lock(Invoice $invoice): Invoice
    {
        return Invoice::query()->lockForUpdate()->with('customer')->findOrFail($invoice->id);
    }
}
