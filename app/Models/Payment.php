<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Money received against one issued invoice. Payments are never edited or deleted; a mistaken payment is
 * reversed with a reason, which restores the invoice balance.
 */
class Payment extends Model
{
    use Auditable, HasStatusHistory;

    /**
     * Every attribute is set by PaymentService; nothing is mass assignable.
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
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * The generated uniqueness column is not part of the audit trail.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return ['active_reference'];
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * The receipt issued with this payment (FR-PAY-04).
     *
     * @return HasOne<Receipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', PaymentStatus::Completed->value);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner
            ->where('number', 'like', $like)
            ->orWhere('reference', 'like', $like)
            ->orWhere('payer_name', 'like', $like)
            ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('number', 'like', $like))
            ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like)));
    }
}
