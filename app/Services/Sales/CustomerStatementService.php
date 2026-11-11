<?php

namespace App\Services\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\System\SettingsService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds a customer's statement of account (FR-PAY-06) for a date range.
 *
 * Every event that changed what the customer owes is a line on the day it happened: an issued invoice
 * (debit, on its invoice date), a payment (credit, on its payment date), a payment reversal (debit, on the
 * day it was reversed) and an invoice void (credit, on the day it was voided). Events before the range
 * form the opening balance, so the closing balance at today's date always equals the outstanding balance
 * on the customer's open invoices.
 */
class CustomerStatementService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{
     *     customer: Customer,
     *     from: CarbonInterface,
     *     to: CarbonInterface,
     *     opening: string,
     *     lines: Collection<int, array{date: CarbonInterface, type: string, reference: string, description: string, debit: string|null, credit: string|null, balance: string, invoice_id: int|null}>,
     *     total_debit: string,
     *     total_credit: string,
     *     closing: string
     * }
     */
    public function build(Customer $customer, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $events = $this->events($customer)->filter(fn (array $event): bool => $event['date']->lte($to));

        $opening = Money::zero();
        $balance = null;
        $totalDebit = Money::zero();
        $totalCredit = Money::zero();
        $lines = collect();

        foreach ($events as $event) {
            $change = Money::of($event['debit'] ?? '0')->minus($event['credit'] ?? '0');

            if ($event['date']->lt($from)) {
                $opening = $opening->plus($change);

                continue;
            }

            $balance = ($balance ?? $opening)->plus($change);
            $totalDebit = $totalDebit->plus($event['debit'] ?? '0');
            $totalCredit = $totalCredit->plus($event['credit'] ?? '0');
            $lines->push([...$event, 'balance' => $balance->toDecimal()]);
        }

        return [
            'customer' => $customer,
            'from' => $from,
            'to' => $to->copy()->startOfDay(),
            'opening' => $opening->toDecimal(),
            'lines' => $lines,
            'total_debit' => $totalDebit->toDecimal(),
            'total_credit' => $totalCredit->toDecimal(),
            'closing' => ($balance ?? $opening)->toDecimal(),
        ];
    }

    /**
     * Every balance-changing event for the customer, oldest first. On the same day, charges come before
     * the payments against them.
     *
     * @return Collection<int, array{date: CarbonInterface, type: string, reference: string, description: string, debit: string|null, credit: string|null, invoice_id: int|null, order: int, sequence: int}>
     */
    private function events(Customer $customer): Collection
    {
        $events = collect();

        Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('number')
            ->whereIn('status', [...InvoiceStatus::postedValues(), InvoiceStatus::Void->value])
            ->get(['id', 'number', 'invoice_date', 'due_date', 'total', 'status', 'voided_at', 'void_reason'])
            ->each(function (Invoice $invoice) use ($events): void {
                $events->push([
                    'date' => $invoice->invoice_date->copy()->startOfDay(),
                    'type' => 'Invoice',
                    'reference' => $invoice->number,
                    'description' => 'Invoice, due '.$this->settings->formatDate($invoice->due_date),
                    'debit' => (string) $invoice->total,
                    'credit' => null,
                    'invoice_id' => $invoice->id,
                    'order' => 1,
                    'sequence' => $invoice->id,
                ]);

                if ($invoice->status === InvoiceStatus::Void && $invoice->voided_at !== null) {
                    $events->push([
                        'date' => $invoice->voided_at->copy()->startOfDay(),
                        'type' => 'Invoice voided',
                        'reference' => $invoice->number,
                        'description' => 'Voided: '.($invoice->void_reason ?? ''),
                        'debit' => null,
                        'credit' => (string) $invoice->total,
                        'invoice_id' => $invoice->id,
                        'order' => 4,
                        'sequence' => $invoice->id,
                    ]);
                }
            });

        Payment::query()
            ->where('customer_id', $customer->id)
            ->with('invoice:id,number', 'receipt:id,payment_id,number')
            ->get()
            ->each(function (Payment $payment) use ($events): void {
                $events->push([
                    'date' => $payment->payment_date->copy()->startOfDay(),
                    'type' => 'Payment',
                    'reference' => $payment->receipt->number ?? $payment->number,
                    'description' => "{$payment->method->label()} for {$payment->invoice->number}".($payment->reference ? " (ref {$payment->reference})" : ''),
                    'debit' => null,
                    'credit' => (string) $payment->amount,
                    'invoice_id' => $payment->invoice_id,
                    'order' => 2,
                    'sequence' => $payment->id,
                ]);

                if ($payment->status === PaymentStatus::Reversed && $payment->reversed_at !== null) {
                    $events->push([
                        'date' => $payment->reversed_at->copy()->startOfDay(),
                        'type' => 'Payment reversed',
                        'reference' => $payment->receipt->number ?? $payment->number,
                        'description' => 'Reversed: '.($payment->reversal_reason ?? ''),
                        'debit' => (string) $payment->amount,
                        'credit' => null,
                        'invoice_id' => $payment->invoice_id,
                        'order' => 3,
                        'sequence' => $payment->id,
                    ]);
                }
            });

        return $events->sortBy([
            fn (array $a, array $b): int => $a['date'] <=> $b['date'],
            fn (array $a, array $b): int => $a['order'] <=> $b['order'],
            fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence'],
        ])->values();
    }
}
