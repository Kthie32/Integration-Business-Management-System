<?php

namespace App\Models;

use App\Enums\ApproverType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered step of a workflow. A step with a minimum amount only applies to documents at or above it.
 */
#[Fillable(['sequence', 'name', 'approver_type', 'role_id', 'user_id', 'min_amount'])]
class ApprovalWorkflowStep extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approver_type' => ApproverType::class,
            'sequence' => 'integer',
            'min_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ApprovalWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The step's name, or a description of its approver when no name was given.
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        return match ($this->approver_type) {
            ApproverType::Role => $this->role?->name ?? 'Role',
            ApproverType::DepartmentHead => 'Department head',
            ApproverType::User => $this->user?->name ?? 'Named user',
            ApproverType::AnyApprover => 'Approver',
        };
    }
}
