<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ApproverType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One step of one document's approval trail, copied from its workflow when the document was submitted.
 * Rows are written only by ApprovalService.
 */
class Approval extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'approver_type' => ApproverType::class,
            'round' => 'integer',
            'sequence' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The named approver of a "named user" step.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    /**
     * Who is expected to act, for display.
     */
    public function approverLabel(): string
    {
        return match ($this->approver_type) {
            ApproverType::Role => $this->role?->name ?? 'Deleted role',
            ApproverType::DepartmentHead => 'Department head',
            ApproverType::User => $this->user?->name ?? 'Removed user',
            ApproverType::AnyApprover => 'Any authorised approver',
        };
    }
}
