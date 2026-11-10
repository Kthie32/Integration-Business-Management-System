<?php

namespace Tests\Feature\HR;

use App\Enums\EmployeeStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $hrOfficer;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->hrOfficer = User::factory()->withRole(SystemRole::HumanResourceOfficer)->create();
        $this->department = Department::factory()->create(['name' => 'Finance']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Grace',
            'last_name' => 'Namubiru',
            'gender' => 'female',
            'date_of_birth' => '1990-04-12',
            'phone' => '+256 772 123456',
            'department_id' => $this->department->id,
            'job_title' => 'Accounts Assistant',
            'employment_type' => 'permanent',
            'hire_date' => '2024-01-15',
            'is_resident_for_tax' => '1',
            'nin' => 'cm90123456789a',
            'bank_name' => 'Stanbic Bank',
            'bank_account' => '9030012345678',
            ...$overrides,
        ];
    }

    public function test_user_without_employee_permissions_is_forbidden(): void
    {
        $accountant = User::factory()->withRole(SystemRole::Accountant)->create();

        $this->actingAs($accountant)->get(route('employees.index'))->assertForbidden();
    }

    public function test_registers_employee_with_number_and_initial_status(): void
    {
        $response = $this->actingAs($this->hrOfficer)->post(route('employees.store'), $this->validPayload());

        $employee = Employee::query()->sole();
        $response->assertRedirect(route('employees.show', $employee));
        $this->assertSame('EMP-0001', $employee->employee_number);
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertSame('CM90123456789A', $employee->nin);
        $this->assertDatabaseHas('status_histories', ['model_type' => Employee::class, 'model_id' => $employee->id, 'to_status' => 'active']);
    }

    public function test_contract_staff_need_a_contract_end_date(): void
    {
        $this->actingAs($this->hrOfficer)->post(route('employees.store'), $this->validPayload(['employment_type' => 'contract']))
            ->assertSessionHasErrors(['contract_end_date' => 'Contract employees need a contract end date.']);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->hrOfficer)->post(route('employees.store'), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'department_id', 'job_title', 'employment_type', 'hire_date']);
    }

    public function test_sensitive_details_are_masked_in_the_audit_log(): void
    {
        $this->actingAs($this->hrOfficer)->post(route('employees.store'), $this->validPayload());

        $entry = AuditLog::query()->where('auditable_type', Employee::class)->where('event', 'created')->sole();
        $this->assertSame('[hidden]', $entry->new_values['nin']);
        $this->assertSame('[hidden]', $entry->new_values['bank_account']);
        $this->assertSame('Grace', $entry->new_values['first_name']);
    }

    public function test_update_records_employment_history(): void
    {
        $employee = Employee::factory()->create(['department_id' => $this->department->id, 'job_title' => 'Clerk']);
        $operations = Department::factory()->create(['name' => 'Operations']);

        $this->actingAs($this->hrOfficer)->put(route('employees.update', $employee), $this->validPayload([
            'department_id' => $operations->id,
            'job_title' => 'Supervisor',
            'effective_date' => '2026-11-01',
            'change_notes' => 'Promotion',
        ]))->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employment_histories', [
            'employee_id' => $employee->id, 'field' => 'department_id', 'old_value' => 'Finance', 'new_value' => 'Operations', 'notes' => 'Promotion',
        ]);
        $this->assertDatabaseHas('employment_histories', ['employee_id' => $employee->id, 'field' => 'job_title', 'old_value' => 'Clerk', 'new_value' => 'Supervisor']);
        $this->assertDatabaseMissing('employment_histories', ['employee_id' => $employee->id, 'field' => 'employment_type']);
    }

    public function test_editing_without_salary_permission_keeps_sensitive_details(): void
    {
        $hrClerk = User::factory()->withRole(SystemRole::HumanResourceOfficer)->create();
        $hrClerk->roles->first()->revokePermissionTo('employees.view_salary');
        $employee = Employee::factory()->withSensitiveDetails()->create(['department_id' => $this->department->id]);
        $originalAccount = $employee->bank_account;

        $this->actingAs($hrClerk->fresh())->put(route('employees.update', $employee), $this->validPayload(['bank_account' => '1111111111']))
            ->assertRedirect(route('employees.show', $employee));

        $this->assertSame($originalAccount, $employee->fresh()->bank_account);
    }

    public function test_sensitive_details_are_hidden_without_salary_permission(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();
        $employee = Employee::factory()->withSensitiveDetails()->create();

        $this->actingAs($manager)->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee($employee->fullName())
            ->assertDontSee($employee->nin)
            ->assertDontSee($employee->bank_account);

        $this->actingAs($this->hrOfficer)->get(route('employees.show', $employee))
            ->assertSee($employee->nin)
            ->assertSee($employee->bank_account);
    }

    public function test_links_a_user_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->hrOfficer)->post(route('employees.store'), $this->validPayload(['user_id' => $user->id]));

        $this->assertSame(Employee::query()->sole()->id, $user->fresh()->employee_id);
    }

    public function test_an_account_cannot_be_linked_to_two_employees(): void
    {
        $existing = Employee::factory()->create();
        $user = User::factory()->create();
        $user->forceFill(['employee_id' => $existing->id])->save();

        $this->actingAs($this->hrOfficer)->post(route('employees.store'), $this->validPayload(['user_id' => $user->id]))
            ->assertSessionHasErrors(['user_id' => 'That user account is already linked to another employee.']);
    }

    public function test_department_head_sees_only_their_departments_employees(): void
    {
        $headEmployee = Employee::factory()->create(['department_id' => $this->department->id]);
        $this->department->update(['head_employee_id' => $headEmployee->id]);
        $head = User::factory()->withRole(SystemRole::DepartmentHead)->create();
        $head->forceFill(['employee_id' => $headEmployee->id])->save();

        $colleague = Employee::factory()->create(['department_id' => $this->department->id, 'first_name' => 'Colleague']);
        $outsider = Employee::factory()->create(['first_name' => 'Outsider']);

        $this->actingAs($head)->get(route('employees.index'))
            ->assertOk()
            ->assertSee($colleague->fullName())
            ->assertDontSee($outsider->fullName());

        $this->actingAs($head)->get(route('employees.show', $colleague))->assertOk();
        $this->actingAs($head)->get(route('employees.show', $outsider))->assertForbidden();
    }

    public function test_department_head_without_linked_employee_sees_nobody(): void
    {
        $head = User::factory()->withRole(SystemRole::DepartmentHead)->create();
        $employee = Employee::factory()->create();

        $this->actingAs($head)->get(route('employees.index'))->assertOk()->assertDontSee($employee->fullName());
    }
}
