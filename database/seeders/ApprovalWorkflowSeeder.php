<?php

namespace Database\Seeders;

use App\Enums\ApprovableDocument;
use App\Enums\ApproverType;
use App\Enums\SystemRole;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the default requisition route (FR-REQ-03, docs/07 E1): Department Head → Finance Officer → Manager.
 * Does nothing once a requisition workflow exists, so the organization's own configuration is never changed.
 * Requires the RoleSeeder to have run.
 */
class ApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        if (ApprovalWorkflow::query()->where('document_type', ApprovableDocument::Requisition->value)->exists()) {
            return;
        }

        $workflow = ApprovalWorkflow::query()->create([
            'document_type' => ApprovableDocument::Requisition,
            'name' => 'Standard requisition approval',
            'description' => 'Department head, then Finance, then a Manager. Set a minimum amount on the Manager step to involve Managers only for larger requests.',
            'min_amount' => null,
            'is_active' => true,
        ]);

        $workflow->steps()->create(['sequence' => 1, 'name' => 'Department head', 'approver_type' => ApproverType::DepartmentHead]);
        $workflow->steps()->create([
            'sequence' => 2,
            'name' => 'Finance',
            'approver_type' => ApproverType::Role,
            'role_id' => Role::query()->where('name', SystemRole::FinanceOfficer->value)->value('id'),
        ]);
        $workflow->steps()->create([
            'sequence' => 3,
            'name' => 'Management',
            'approver_type' => ApproverType::Role,
            'role_id' => Role::query()->where('name', SystemRole::Manager->value)->value('id'),
        ]);
    }
}
