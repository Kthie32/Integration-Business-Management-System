<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The customer's proof of one payment. Created only by PaymentService together with its payment
 * (FR-PAY-04) and voided only by reversing that payment (FR-PAY-05).
 */
class Receipt extends Model
{
    use Auditable, HasStatusHistory;

    /**
     * Every attribute is set by ReceiptService; nothing is mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isValid(): bool
    {
        return $this->status === ReceiptStatus::Valid;
    }

    /**
     * @param  Builder<Receipt>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', ReceiptStatus::Valid->value);
    }

    /**
     * @param  Builder<Receipt>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner
            ->where('number', 'like', $like)
            ->orWhereHas('payment', fn (Builder $payment) => $payment->where('number', 'like', $like)->orWhere('reference', 'like', $like))
            ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('number', 'like', $like))
            ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like)));
    }
}
