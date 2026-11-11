<?php

namespace App\Services\Sales;

use App\Enums\DocumentType;
use App\Enums\ReceiptStatus;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\System\NumberingService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues and voids receipts. Only PaymentService calls this, inside its own transaction, so a receipt
 * exists exactly when its payment does (FR-PAY-04) and is voided exactly when the payment is reversed.
 */
class ReceiptService
{
    public function __construct(private NumberingService $numbering) {}

    public function issueFor(Payment $payment, User $actor): Receipt
    {
        $this->assertInTransaction();

        $receipt = new Receipt;
        $receipt->forceFill([
            'number' => $this->numbering->next(DocumentType::Receipt, $payment->payment_date),
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'customer_id' => $payment->customer_id,
            'receipt_date' => $payment->payment_date->toDateString(),
            'amount' => (string) $payment->amount,
            'status' => ReceiptStatus::Valid,
            'issued_at' => now(),
            'created_by' => $actor->id,
        ])->save();
        $receipt->recordStatusChange(null, ReceiptStatus::Valid, "Issued for payment {$payment->number}");

        return $receipt;
    }

    public function voidFor(Payment $payment, User $actor, string $reason): ?Receipt
    {
        $this->assertInTransaction();

        $receipt = Receipt::query()->lockForUpdate()->where('payment_id', $payment->id)->first();

        if ($receipt === null || ! $receipt->isValid()) {
            return $receipt;
        }

        $receipt->forceFill([
            'voided_at' => now(),
            'voided_by' => $actor->id,
            'void_reason' => $reason,
        ]);
        $receipt->transitionTo(ReceiptStatus::Void, "Payment {$payment->number} reversed: {$reason}");

        return $receipt;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Receipts are issued and voided only inside the payment transaction.');
        }
    }
}
