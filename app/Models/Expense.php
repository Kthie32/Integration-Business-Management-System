<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Money;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Money spent by the organization. `amount` is the total paid including tax; `tax_amount` is the part of
 * it that is tax. Drafts are editable; recorded expenses are numbered, read-only and voided, never deleted.
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, HasFactory, HasStatusHistory;

    public const DISK = 'local';

    /**
     * Every attribute is set by ExpenseService; nothing is mass assignable.
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
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'attachment_size' => 'integer',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * The document this expense came from, e.g. a fulfilled requisition (Phase 11).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
        return $this->status === ExpenseStatus::Draft;
    }

    public function isRecorded(): bool
    {
        return $this->status === ExpenseStatus::Recorded;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'Draft #'.$this->id;
    }

    /**
     * The amount before tax.
     */
    public function netAmount(): string
    {
        return Money::of((string) $this->amount)->minus((string) $this->tax_amount)->toDecimal();
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeRecorded(Builder $query): void
    {
        $query->where('status', ExpenseStatus::Recorded->value);
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner
            ->where('number', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('reference', 'like', $like)
            ->orWhere('payee_name', 'like', $like)
            ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $like)));
    }
}
