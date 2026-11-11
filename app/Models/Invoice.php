<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bill to a customer. The number is assigned when the draft is issued; issued invoices are read-only and
 * change only through payments, payment reversals and voiding. Amount paid and balance are kept in step
 * with the completed payments in the same transaction as each payment or reversal.
 */
#[Fillable(['customer_id', 'warehouse_id', 'invoice_date', 'due_date', 'document_discount_type', 'document_discount_value', 'notes', 'terms'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, HasFactory, HasStatusHistory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'document_discount_type' => DiscountType::class,
            'document_discount_value' => 'decimal:4',
            'document_discount_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class)->withTrashed();
    }

    /**
     * The voided invoice this one corrects.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'copied_from_invoice_id')->withTrashed();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(Invoice::class, 'copied_from_invoice_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('payment_date')->latest('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function completedPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', PaymentStatus::Completed->value);
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * Issued, with money still owed, and past its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_date->lt(today())
            && Money::of((string) $this->balance_due)->isPositive();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    public function hasCompletedPayments(): bool
    {
        return $this->completedPayments()->exists();
    }

    public function displayNumber(): string
    {
        return $this->number ?? "Draft #{$this->id}";
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::openValues());
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopePosted(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::postedValues());
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()
            ->whereDate('due_date', '<', today()->toDateString())
            ->where('balance_due', '>', 0);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner
            ->where('number', 'like', $like)
            ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like)->orWhere('code', 'like', $like)));
    }
}
