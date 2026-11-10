<?php

namespace Tests\Feature\HR;

use App\Enums\EmployeeStatus;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $hrOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->hrOfficer = User::factory()->withRole(SystemRole::HumanResourceOfficer)->create();
    }

    public function test_user_without_update_permission_cannot_change_status(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();
        $employee = Employee::factory()->create();

        $this->actingAs($manager)->patch(route('employees.status.update', $employee), [
            'status' => 'on_leave', 'effective_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_puts_employee_on_leave_and_records_history(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->hrOfficer)->patch(route('employees.status.update', $employee), [
            'status' => 'on_leave', 'effective_date' => now()->toDateString(), 'reason' => 'Annual leave',
        ])->assertRedirect(route('employees.show', $employee));

        $this->assertSame(EmployeeStatus::OnLeave, $employee->fresh()->status);
        $this->assertDatabaseHas('status_histories', ['model_id' => $employee->id, 'from_status' => 'active', 'to_status' => 'on_leave']);
        $this->assertDatabaseHas('employment_histories', ['employee_id' => $employee->id, 'field' => 'status', 'old_value' => 'Active', 'new_value' => 'On leave']);
    }

    public function test_termination_requires_a_reason(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->hrOfficer)->patch(route('employees.status.update', $employee), [
            'status' => 'terminated', 'effective_date' => now()->toDateString(),
        ])->assertSessionHasErrors(['reason' => 'Give a reason for the termination.']);
    }

    public function test_termination_records_date_and_removes_department_headship(): void
    {
        $employee = Employee::factory()->create();
        $department = Department::factory()->create(['head_employee_id' => $employee->id]);

        $this->actingAs($this->hrOfficer)->patch(route('employees.status.update', $employee), [
            'status' => 'terminated', 'effective_date' => '2026-11-10', 'reason' => 'Contract ended',
        ])->assertRedirect(route('employees.show', $employee));

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Terminated, $employee->status);
        $this->assertSame('2026-11-10', $employee->termination_date->toDateString());
        $this->assertNull($department->fresh()->head_employee_id);
    }

    public function test_terminating_an_employee_with_an_active_account_warns_the_user(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create();
        $user->forceFill(['employee_id' => $employee->id])->save();

        $this->actingAs($this->hrOfficer)->patch(route('employees.status.update', $employee), [
            'status' => 'terminated', 'effective_date' => now()->toDateString(), 'reason' => 'Resigned',
        ])->assertSessionHas('warning');
    }

    public function test_terminated_employee_status_cannot_change(): void
    {
        $employee = Employee::factory()->terminated()->create();

        $this->actingAs($this->hrOfficer)->patch(route('employees.status.update', $employee), [
            'status' => 'active', 'effective_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(EmployeeStatus::Terminated, $employee->fresh()->status);
    }

    public function test_disallowed_transition_is_refused(): void
    {
        $employee = Employee::factory()->create(['status' => EmployeeStatus::OnLeave]);

        $this->actingAs($this->hrOfficer)->patch(route('employees.status.update', $employee), [
            'status' => 'suspended', 'effective_date' => now()->toDateString(),
        ])->assertSessionHas('error');

        $this->assertSame(EmployeeStatus::OnLeave, $employee->fresh()->status);
    }
}
