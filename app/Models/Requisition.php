<?php

namespace App\Models;

use App\Enums\ApprovableDocument;
use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasStatusHistory;
use App\Models\Contracts\Approvable;
use Database\Factories\RequisitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A request for goods or services raised by an employee for a department (FR-REQ-01). It is numbered on
 * first submission, routed through the approval engine, then fulfilled from stock or by purchase.
 */
class Requisition extends Model implements Approvable
{
    /** @use HasFactory<RequisitionFactory> */
    use Auditable, HasApprovals, HasFactory, HasStatusHistory, SoftDeletes;

    public const DISK = 'local';

    /**
     * Every attribute is set by RequisitionService; nothing is mass assignable.
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
            'status' => RequisitionStatus::class,
            'priority' => RequisitionPriority::class,
            'required_by_date' => 'date',
            'estimated_total' => 'decimal:2',
            'attachment_size' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<RequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<RequisitionFulfilment, $this>
     */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(RequisitionFulfilment::class)->orderBy('id');
    }

    /**
     * Expenses created when purchased lines were fulfilled.
     *
     * @return MorphMany<Expense, $this>
     */
    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'source');
    }

    public function approvableDocument(): ApprovableDocument
    {
        return ApprovableDocument::Requisition;
    }

    public function approvalAmount(): string
    {
        return (string) $this->estimated_total;
    }

    public function approvalRequesterId(): ?int
    {
        return $this->requested_by;
    }

    public function approvalDepartment(): ?Department
    {
        return $this->department;
    }

    public function approvalTitle(): string
    {
        return 'Requisition '.$this->displayNumber();
    }

    public function approvalUrl(): string
    {
        return route('requisitions.show', $this);
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'Draft #'.$this->id;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /**
     * Every approved quantity has been delivered.
     */
    public function isCompletelyFulfilled(): bool
    {
        return $this->items->every(fn (RequisitionItem $item): bool => bccomp($item->remainingQuantity(), '0', 3) <= 0);
    }

    /**
     * @param  Builder<Requisition>  $query
     */
    public function scopePendingApproval(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (RequisitionStatus $status): string => $status->value, RequisitionStatus::pending()));
    }

    /**
     * Requisitions the user may see: everything with view_all or fulfil, otherwise their own, those of
     * departments they head, and those they took part in approving.
     *
     * @param  Builder<Requisition>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('requisitions.view_all') || $user->can('requisitions.fulfil')) {
            return;
        }

        $headed = $user->can('requisitions.view_department') ? $user->headedDepartmentIds() : [];

        $query->where(fn (Builder $inner) => $inner
            ->where('requested_by', $user->id)
            ->when($headed !== [], fn (Builder $q) => $q->orWhereIn('department_id', $headed))
            ->orWhereHas('approvals', fn (Builder $approvals) => $approvals->where('decided_by', $user->id)));
    }

    /**
     * @param  Builder<Requisition>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner
            ->where('number', 'like', $like)
            ->orWhere('purpose', 'like', $like)
            ->orWhereHas('items', fn (Builder $items) => $items->where('description', 'like', $like)));
    }
}
