<?php

namespace App\Models\Contracts;

use App\Enums\ApprovableDocument;
use App\Models\Approval;
use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A document routed through the generic approval engine (FR-APR-01). The engine only needs to know the
 * amount, who asked for it and which department it belongs to; the document keeps its own status.
 */
interface Approvable
{
    public function approvableDocument(): ApprovableDocument;

    /**
     * The amount compared with workflow and step thresholds, as a decimal string.
     */
    public function approvalAmount(): string;

    /**
     * The user who raised the document; they never approve it.
     */
    public function approvalRequesterId(): ?int;

    /**
     * The department whose head resolves "department head" steps.
     */
    public function approvalDepartment(): ?Department;

    /**
     * A short human title, e.g. "Requisition REQ-2026-00001".
     */
    public function approvalTitle(): string;

    public function approvalUrl(): string;

    /**
     * @return MorphMany<Approval, Model>
     */
    public function approvals(): MorphMany;
}
