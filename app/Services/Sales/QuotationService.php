<?php

namespace App\Services\Sales;

use App\Enums\DiscountType;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\SystemMessage;
use App\Services\System\AuditLogger;
use App\Services\System\NumberingService;
use App\Services\System\SettingsService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Quotation lifecycle: drafting, submission (with approval above the configured threshold), sending,
 * the customer's decision, cancellation, revisions, expiry and hand-over to invoicing.
 */
class QuotationService
{
    public function __construct(
        private DocumentCalculator $calculator,
        private NumberingService $numbering,
        private SettingsService $settings,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{customer_id: int, quote_date: string, valid_until: string, document_discount_type?: string|null, document_discount_value?: string|null, notes?: string|null, terms?: string|null, lines: list<array<string, mixed>>}  $data
     */
    public function createDraft(array $data, User $actor): Quotation
    {
        return DB::transaction(function () use ($data, $actor): Quotation {
            $quotation = new Quotation($this->headerAttributes($data));
            $quotation->forceFill([
                'status' => QuotationStatus::Draft,
                'currency' => (string) $this->settings->get('currency', 'code', 'UGX'),
                'created_by' => $actor->id,
            ])->save();
            $quotation->recordStatusChange(null, QuotationStatus::Draft);

            $this->replaceLines($quotation, $data['lines']);

            return $quotation;
        });
    }

    /**
     * @param  array{customer_id: int, quote_date: string, valid_until: string, document_discount_type?: string|null, document_discount_value?: string|null, notes?: string|null, terms?: string|null, lines: list<array<string, mixed>>}  $data
     */
    public function updateDraft(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::Draft], 'Only a draft quotation can be edited.');

            $quotation->update($this->headerAttributes($data));
            $this->replaceLines($quotation, $data['lines']);

            return $quotation;
        });
    }

    public function deleteDraft(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation): void {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::Draft], 'Only a draft quotation can be deleted.');

            if ($quotation->number !== null && $quotation->revision === 0) {
                throw new BusinessRuleException('This quotation already has a number. Cancel it instead of deleting it.');
            }

            $quotation->delete();
        });
    }

    /**
     * Finalise a draft: assign its number and either approve it directly or send it for approval when its
     * total reaches the approval threshold. Submitting a revision supersedes the version it revises.
     */
    public function submit(Quotation $quotation, User $actor): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::Draft], 'Only a draft quotation can be submitted.');

            if (! $quotation->items()->exists()) {
                throw new BusinessRuleException('Add at least one line before submitting.');
            }

            if ($quotation->number === null) {
                $quotation->number = $quotation->revision > 0
                    ? "{$quotation->baseNumber()}-R{$quotation->revision}"
                    : $this->numbering->next(DocumentType::Quotation, $quotation->quote_date);
            }

            if ($quotation->parent_quotation_id !== null) {
                $this->supersedeParent($quotation);
            }

            $quotation->submitted_at = now();

            if (! $this->requiresApproval($quotation)) {
                $quotation->forceFill(['approved_at' => now(), 'approved_by' => null]);
                $quotation->transitionTo(QuotationStatus::Approved, 'Below the approval threshold — no approval needed.');

                return $quotation;
            }

            $quotation->transitionTo(QuotationStatus::PendingApproval);

            $approvers = User::query()->active()->permission('quotations.approve')->whereKeyNot($quotation->created_by)->get();
            Notification::send($approvers, new SystemMessage(
                "Quotation {$quotation->number} awaits approval",
                "{$actor->name} submitted a quotation for {$quotation->customer->name} totalling ".$this->settings->formatMoney((string) $quotation->total).'.',
                route('quotations.show', $quotation),
                'file-earmark-check',
            ));

            return $quotation;
        });
    }

    public function approve(Quotation $quotation, User $actor, ?string $comment = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $comment): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::PendingApproval], 'Only a quotation awaiting approval can be approved.');
            $this->ensureNotAuthor($quotation, $actor);

            $quotation->forceFill(['approved_at' => now(), 'approved_by' => $actor->id]);
            $quotation->transitionTo(QuotationStatus::Approved, $comment);

            $quotation->creator?->notify(new SystemMessage(
                "Quotation {$quotation->number} approved",
                "{$actor->name} approved your quotation. It can now be sent to the customer.",
                route('quotations.show', $quotation),
                'check-circle',
            ));

            return $quotation;
        });
    }

    /**
     * Send a quotation awaiting approval back to its author for changes.
     */
    public function returnToDraft(Quotation $quotation, User $actor, string $comment): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $comment): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::PendingApproval], 'Only a quotation awaiting approval can be returned.');
            $this->ensureNotAuthor($quotation, $actor);

            $quotation->submitted_at = null;
            $quotation->transitionTo(QuotationStatus::Draft, $comment);

            $quotation->creator?->notify(new SystemMessage(
                "Quotation {$quotation->number} returned for changes",
                "{$actor->name}: {$comment}",
                route('quotations.show', $quotation),
                'arrow-counterclockwise',
            ));

            return $quotation;
        });
    }

    public function markSent(Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($quotation): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::Approved], 'Only an approved quotation can be marked as sent.');

            $quotation->sent_at = now();
            $quotation->transitionTo(QuotationStatus::Sent);

            return $quotation;
        });
    }

    public function markAccepted(Quotation $quotation, ?string $comment = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $comment): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::Approved, QuotationStatus::Sent], 'Only an approved or sent quotation can be accepted.');

            if ($quotation->valid_until->lt(today())) {
                throw new BusinessRuleException('This quotation passed its validity date. Prepare a revision with a new date instead.');
            }

            $quotation->accepted_at = now();
            $quotation->transitionTo(QuotationStatus::Accepted, $comment);

            return $quotation;
        });
    }

    public function markRejected(Quotation $quotation, string $reason): Quotation
    {
        return DB::transaction(function () use ($quotation, $reason): Quotation {
            $quotation = $this->lock($quotation);
            $this->ensureStatus($quotation, [QuotationStatus::Approved, QuotationStatus::Sent], 'Only an approved or sent quotation can be marked as rejected.');

            $quotation->transitionTo(QuotationStatus::Rejected, $reason);

            return $quotation;
        });
    }

    public function cancel(Quotation $quotation, string $reason): Quotation
    {
        return DB::transaction(function () use ($quotation, $reason): Quotation {
            $quotation = $this->lock($quotation);

            if (! $quotation->status->isCancellable()) {
                throw new BusinessRuleException('This quotation can no longer be cancelled.');
            }

            $quotation->transitionTo(QuotationStatus::Cancelled, $reason);

            return $quotation;
        });
    }

    /**
     * Prepare a draft revision of a finalised quotation. The original stays as it is until the revision is
     * submitted, at which point it becomes "Superseded".
     */
    public function revise(Quotation $quotation, User $actor): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor): Quotation {
            $quotation = $this->lock($quotation);

            if (! $quotation->status->isRevisable()) {
                throw new BusinessRuleException('Only an approved, sent, rejected or expired quotation can be revised.');
            }

            if ($quotation->revisionChild()->exists()) {
                throw new BusinessRuleException('A revision of this quotation is already being prepared.');
            }

            $quotation->load('items');

            $revision = new Quotation([
                'customer_id' => $quotation->customer_id,
                'quote_date' => today()->toDateString(),
                'valid_until' => today()->addDays($this->validityDays())->toDateString(),
                'document_discount_type' => $quotation->document_discount_type?->value,
                'document_discount_value' => (string) $quotation->document_discount_value,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
            ]);
            $revision->forceFill([
                'status' => QuotationStatus::Draft,
                'revision' => $quotation->revision + 1,
                'parent_quotation_id' => $quotation->id,
                'currency' => $quotation->currency,
                'created_by' => $actor->id,
            ])->save();
            $revision->recordStatusChange(null, QuotationStatus::Draft, "Revision of {$quotation->number}");

            $this->replaceLines($revision, $quotation->items->map(fn ($line): array => [
                'item_id' => $line->item_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_amount' => (string) $line->discount_amount,
                'tax_rate_id' => $line->tax_rate_id,
            ])->all());

            return $revision;
        });
    }

    /**
     * Mark approved or sent quotations whose validity date has passed as expired.
     */
    public function expireOverdue(?CarbonInterface $today = null): int
    {
        $today ??= today();
        $expired = 0;

        Quotation::query()
            ->whereIn('status', [QuotationStatus::Approved->value, QuotationStatus::Sent->value])
            ->whereDate('valid_until', '<', $today->toDateString())
            ->orderBy('id')
            ->each(function (Quotation $quotation) use (&$expired): void {
                DB::transaction(function () use ($quotation, &$expired): void {
                    $quotation = $this->lock($quotation);

                    if (in_array($quotation->status, [QuotationStatus::Approved, QuotationStatus::Sent], true)) {
                        $quotation->transitionTo(QuotationStatus::Expired, 'Validity date passed.');
                        $expired++;
                    }
                });
            });

        return $expired;
    }

    /**
     * The data an invoice draft needs when created from an accepted quotation (used by invoicing).
     *
     * @return array{customer_id: int, quotation_id: int, document_discount_type: string|null, document_discount_value: string, notes: string|null, terms: string|null, lines: list<array{item_id: int|null, description: string, quantity: string, unit: string|null, unit_price: string, discount_amount: string, tax_rate_id: int|null, tax_rate: string}>}
     */
    public function invoiceDraftData(Quotation $quotation): array
    {
        $this->ensureConvertible($quotation);

        return [
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'document_discount_type' => $quotation->document_discount_type?->value,
            'document_discount_value' => (string) $quotation->document_discount_value,
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'lines' => $quotation->items->map(fn ($line): array => [
                'item_id' => $line->item_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_amount' => (string) $line->discount_amount,
                'tax_rate_id' => $line->tax_rate_id,
                'tax_rate' => (string) $line->tax_rate,
            ])->values()->all(),
        ];
    }

    /**
     * Record that an accepted quotation was converted into an invoice. Call inside the transaction that
     * creates the invoice; a quotation converts only once.
     */
    public function markConverted(Quotation $quotation, User $actor, ?string $invoiceReference = null): Quotation
    {
        $quotation = $this->lock($quotation);
        $this->ensureConvertible($quotation);

        $quotation->forceFill(['converted_at' => now(), 'converted_by' => $actor->id])->save();
        $this->auditLogger->log('converted', $quotation, 'Converted to invoice'.($invoiceReference ? " {$invoiceReference}" : ''));

        return $quotation;
    }

    public function requiresApproval(Quotation $quotation): bool
    {
        $threshold = $this->settings->get('finance', 'quotation_approval_threshold');

        if ($threshold === null || $threshold === '') {
            return false;
        }

        return ! Money::of((string) $quotation->total)->lessThan(Money::of((string) $threshold));
    }

    public function validityDays(): int
    {
        return max(1, (int) $this->settings->get('finance', 'quotation_validity_days', 30));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceLines(Quotation $quotation, array $lines): void
    {
        $lines = array_values($lines);

        if ($lines === []) {
            throw new BusinessRuleException('Add at least one line.');
        }

        $taxRates = TaxRate::query()->whereIn('id', array_filter(array_column($lines, 'tax_rate_id')))->get()->keyBy('id');

        $prepared = array_map(function (array $line) use ($taxRates): array {
            $taxRate = filled($line['tax_rate_id'] ?? null) ? $taxRates->get((int) $line['tax_rate_id']) : null;

            return [
                'item_id' => filled($line['item_id'] ?? null) ? (int) $line['item_id'] : null,
                'description' => trim((string) $line['description']),
                'quantity' => (string) $line['quantity'],
                'unit' => filled($line['unit'] ?? null) ? (string) $line['unit'] : null,
                'unit_price' => (string) $line['unit_price'],
                'discount_amount' => filled($line['discount_amount'] ?? null) ? (string) $line['discount_amount'] : '0',
                'tax_rate_id' => $taxRate?->id,
                'tax_rate' => $taxRate !== null ? (string) $taxRate->rate : '0',
            ];
        }, $lines);

        $result = $this->calculator->calculate(
            $prepared,
            $quotation->document_discount_type,
            (string) ($quotation->document_discount_value ?? '0'),
        );

        $quotation->items()->delete();

        foreach ($prepared as $index => $line) {
            $quotation->items()->create([...$line, ...$result['lines'][$index], 'sort_order' => $index]);
        }

        $quotation->forceFill([
            'subtotal' => $result['subtotal'],
            'discount_total' => $result['discount_total'],
            'document_discount_amount' => $result['document_discount_amount'],
            'tax_total' => $result['tax_total'],
            'total' => $result['total'],
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerAttributes(array $data): array
    {
        $type = filled($data['document_discount_type'] ?? null) ? DiscountType::from($data['document_discount_type']) : null;

        return [
            'customer_id' => $data['customer_id'],
            'quote_date' => $data['quote_date'],
            'valid_until' => $data['valid_until'],
            'document_discount_type' => $type?->value,
            'document_discount_value' => $type !== null && filled($data['document_discount_value'] ?? null) ? (string) $data['document_discount_value'] : '0',
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
        ];
    }

    private function supersedeParent(Quotation $revision): void
    {
        $parent = Quotation::query()->lockForUpdate()->findOrFail($revision->parent_quotation_id);

        if ($parent->status === QuotationStatus::Superseded) {
            return;
        }

        if (! $parent->status->isRevisable()) {
            throw new BusinessRuleException("{$parent->number} is now {$parent->status->label()} and can no longer be revised. Delete this draft revision.");
        }

        $parent->transitionTo(QuotationStatus::Superseded, "Replaced by revision {$revision->number}");
    }

    private function ensureConvertible(Quotation $quotation): void
    {
        if ($quotation->status !== QuotationStatus::Accepted) {
            throw new BusinessRuleException('Only an accepted quotation can be converted into an invoice.');
        }

        if ($quotation->isConverted()) {
            throw new BusinessRuleException('This quotation has already been converted into an invoice.');
        }
    }

    private function ensureNotAuthor(Quotation $quotation, User $actor): void
    {
        if ($quotation->created_by === $actor->id) {
            throw new BusinessRuleException('You cannot approve or return a quotation you prepared.');
        }
    }

    /**
     * @param  list<QuotationStatus>  $allowed
     */
    private function ensureStatus(Quotation $quotation, array $allowed, string $message): void
    {
        if (! in_array($quotation->status, $allowed, true)) {
            throw new BusinessRuleException($message);
        }
    }

    private function lock(Quotation $quotation): Quotation
    {
        return Quotation::query()->lockForUpdate()->with('customer')->findOrFail($quotation->id);
    }
}
