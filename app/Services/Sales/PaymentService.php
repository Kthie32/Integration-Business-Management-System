<?php

namespace App\Services\Sales;

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\System\AuditLogger;
use App\Services\System\NumberingService;
use App\Services\System\SettingsService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records customer payments against issued invoices and reverses them. One payment settles one invoice
 * and may not exceed its balance (docs/07 B5). The invoice row is locked for the whole operation, so two
 * people recording payments at once cannot overpay it.
 */
class PaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private NumberingService $numbering,
        private SettingsService $settings,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{payment_date: string, amount: string, method: string, reference?: string|null, payer_name?: string|null, notes?: string|null}  $data
     */
    public function record(Invoice $invoice, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $actor): Payment {
            $invoice = Invoice::query()->lockForUpdate()->with('customer')->findOrFail($invoice->id);

            if (! $invoice->status->isOpen()) {
                throw new BusinessRuleException("Payments can only be recorded against an issued invoice with a balance. {$invoice->displayNumber()} is {$invoice->status->label()}.");
            }

            $amount = Money::of((string) $data['amount']);
            $balance = Money::of((string) $invoice->balance_due);

            if (! $amount->isPositive()) {
                throw new BusinessRuleException('The amount must be greater than zero.');
            }

            if ($amount->greaterThan($balance)) {
                throw new BusinessRuleException('The amount is more than the balance due ('.$this->settings->formatMoney($balance).'). Overpayments are not accepted.');
            }

            $paymentDate = Carbon::parse($data['payment_date'])->startOfDay();

            if ($paymentDate->gt(today())) {
                throw new BusinessRuleException('The payment date cannot be in the future.');
            }

            if ($paymentDate->lt($invoice->invoice_date)) {
                throw new BusinessRuleException('The payment date cannot be before the invoice date.');
            }

            $method = PaymentMethod::from($data['method']);
            $reference = filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null;

            if ($method->requiresReference() && $reference === null) {
                throw new BusinessRuleException("Enter the transaction reference for a {$method->label()} payment.");
            }

            if ($reference !== null && $this->referenceInUse($method, $reference)) {
                throw new BusinessRuleException("A {$method->label()} payment with reference \"{$reference}\" is already recorded.");
            }

            $payment = new Payment;
            $payment->forceFill([
                'number' => $this->numbering->next(DocumentType::Payment, $paymentDate),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'payment_date' => $paymentDate->toDateString(),
                'amount' => $amount->toDecimal(),
                'method' => $method,
                'reference' => $reference,
                'payer_name' => filled($data['payer_name'] ?? null) ? trim((string) $data['payer_name']) : null,
                'notes' => $data['notes'] ?? null,
                'status' => PaymentStatus::Completed,
                'created_by' => $actor->id,
            ])->save();
            $payment->recordStatusChange(null, PaymentStatus::Completed);

            $this->invoices->refreshPaymentStatus($invoice, "Payment {$payment->number} of ".$this->settings->formatMoney($amount));

            return $payment;
        });
    }

    /**
     * Reverse a completed payment (never deleted). The invoice balance and status are restored.
     */
    public function reverse(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            /*
             * Lock the invoice before the payment, in the same order as record(), so they cannot deadlock.
             */
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $payment->isCompleted()) {
                throw new BusinessRuleException('This payment has already been reversed.');
            }

            $payment->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ]);
            $payment->transitionTo(PaymentStatus::Reversed, $reason);

            $this->invoices->refreshPaymentStatus($invoice, "Payment {$payment->number} reversed: {$reason}");
            $this->auditLogger->log('reversed', $payment, "Reversed payment {$payment->number} on invoice {$invoice->number}: {$reason}");

            return $payment;
        });
    }

    private function referenceInUse(PaymentMethod $method, string $reference): bool
    {
        return Payment::query()
            ->completed()
            ->where('method', $method->value)
            ->where('reference', $reference)
            ->exists();
    }
}
