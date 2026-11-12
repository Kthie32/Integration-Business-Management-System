<?php

namespace App\Models;

use App\Enums\ApprovableDocument;
use App\Models\Concerns\Auditable;
use Database\Factories\ApprovalWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An approval route for one document type (FR-REQ-03). When several active workflows exist for a type, the
 * one with the highest minimum amount that the document reaches is used.
 */
#[Fillable(['document_type', 'name', 'description', 'min_amount', 'is_active'])]
class ApprovalWorkflow extends Model
{
    /** @use HasFactory<ApprovalWorkflowFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => ApprovableDocument::class,
            'min_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ApprovalWorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * @param  Builder<ApprovalWorkflow>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
