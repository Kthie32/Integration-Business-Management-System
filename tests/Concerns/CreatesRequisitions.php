<?php

namespace Tests\Concerns;

use App\Enums\ApprovalDecision;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Requisition\RequisitionService;

/**
 * Helpers for requisition and approval tests. Requires roles and permissions to be seeded.
 */
trait CreatesRequisitions
{
    /**
     * A department whose head has a user account with the given role.
     *
     * @return array{0: Department, 1: User}
     */
    protected function departmentWithHead(SystemRole $role = SystemRole::DepartmentHead): array
    {
        $department = Department::factory()->create();
        $head = $this->userWithRole($role);
        $this->makeHeadOf($head, $department);

        return [$department->refresh(), $head];
    }

    protected function makeHeadOf(User $user, Department $department): void
    {
        $employee = $user->employee ?? Employee::factory()->create(['department_id' => $department->id]);
        $user->forceFill(['employee_id' => $employee->id])->save();
        $department->forceFill(['head_employee_id' => $employee->id])->save();
    }

    /**
     * @param  list<array<string, mixed>>|null  $lines
     */
    protected function draftRequisition(User $requester, Department $department, ?array $lines = null): Requisition
    {
        return app(RequisitionService::class)->createDraft([
            'department_id' => $department->id,
            'priority' => 'normal',
            'purpose' => 'Supplies for the quarterly field visit',
            'lines' => $lines ?? [['description' => 'Printer paper (ream)', 'quantity' => '10', 'estimated_unit_cost' => '25000']],
        ], null, $requester);
    }

    /**
     * @param  list<array<string, mixed>>|null  $lines
     */
    protected function submittedRequisition(User $requester, Department $department, ?array $lines = null): Requisition
    {
        return app(RequisitionService::class)->submit($this->draftRequisition($requester, $department, $lines), $requester);
    }

    protected function decide(Requisition $requisition, User $actor, ApprovalDecision $decision = ApprovalDecision::Approve, ?string $comment = null): Requisition
    {
        return app(RequisitionService::class)->decide($requisition, $actor, $decision, $comment);
    }
}
