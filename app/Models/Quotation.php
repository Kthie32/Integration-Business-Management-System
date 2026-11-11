<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\QuotationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStatusHistory;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A price offer to a customer. The number is assigned when the draft is submitted; revisions reuse the
 * original number with an "-R1", "-R2" … suffix and supersede the previous version once submitted.
 */
#[Fillable(['customer_id', 'quote_date', 'valid_until', 'document_discount_type', 'document_discount_value', 'notes', 'terms'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use Auditable, HasFactory, HasStatusHistory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'document_discount_type' => DiscountType::class,
            'document_discount_value' => 'decimal:4',
            'document_discount_amount' => 'decimal:2',
            'quote_date' => 'date',
            'valid_until' => 'date',
            'revision' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'converted_at' => 'datetime',
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
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The version this quotation revises.
     *
     * @return BelongsTo<Quotation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'parent_quotation_id')->withTrashed();
    }

    /**
     * The revision prepared from this quotation, if any (a draft revision may later be deleted).
     *
     * @return HasOne<Quotation, $this>
     */
    public function revisionChild(): HasOne
    {
        return $this->hasOne(Quotation::class, 'parent_quotation_id');
    }

    /**
     * The invoice created from this quotation (drafts included).
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function isDraft(): bool
    {
        return $this->status === QuotationStatus::Draft;
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }

    /**
     * The number without its revision suffix, e.g. "QT-2026-00012" for "QT-2026-00012-R2".
     */
    public function baseNumber(): ?string
    {
        if ($this->number !== null) {
            return preg_replace('/-R\d+$/', '', $this->number);
        }

        return $this->parent?->baseNumber();
    }

    public function displayNumber(): string
    {
        if ($this->number !== null) {
            return $this->number;
        }

        return $this->revision > 0 && $this->baseNumber() !== null
            ? "{$this->baseNumber()}-R{$this->revision} (draft)"
            : "Draft #{$this->id}";
    }

    /**
     * @param  Builder<Quotation>  $query
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
