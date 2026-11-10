<?php

namespace Tests\Feature\HR;

use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $hrOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->hrOfficer = User::factory()->withRole(SystemRole::HumanResourceOfficer)->create();
    }

    public function test_user_without_people_permissions_is_forbidden(): void
    {
        $procurement = User::factory()->withRole(SystemRole::ProcurementOfficer)->create();

        $this->actingAs($procurement)->get(route('departments.index'))->assertForbidden();
    }

    public function test_manager_can_list_but_not_create_departments(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();

        $this->actingAs($manager)->get(route('departments.index'))->assertOk();
        $this->actingAs($manager)->post(route('departments.store'), ['code' => 'FIN', 'name' => 'Finance', 'is_active' => '1'])->assertForbidden();
    }

    public function test_creates_department_with_head(): void
    {
        $head = Employee::factory()->create();

        $this->actingAs($this->hrOfficer)->post(route('departments.store'), [
            'code' => 'fin', 'name' => 'Finance', 'head_employee_id' => $head->id, 'is_active' => '1',
        ])->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['code' => 'FIN', 'name' => 'Finance', 'head_employee_id' => $head->id]);
    }

    public function test_terminated_employee_cannot_be_head(): void
    {
        $former = Employee::factory()->terminated()->create();

        $this->actingAs($this->hrOfficer)->post(route('departments.store'), [
            'code' => 'FIN', 'name' => 'Finance', 'head_employee_id' => $former->id, 'is_active' => '1',
        ])->assertSessionHasErrors(['head_employee_id' => 'Choose a current employee as head.']);
    }

    public function test_code_must_be_unique(): void
    {
        Department::factory()->create(['code' => 'FIN']);

        $this->actingAs($this->hrOfficer)->post(route('departments.store'), ['code' => 'fin', 'name' => 'Other', 'is_active' => '1'])
            ->assertSessionHasErrors('code');
    }

    public function test_department_cannot_be_placed_under_its_own_sub_department(): void
    {
        $parent = Department::factory()->create();
        $child = Department::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->hrOfficer)->from(route('departments.edit', $parent))->put(route('departments.update', $parent), [
            'code' => $parent->code, 'name' => $parent->name, 'parent_id' => $child->id, 'is_active' => '1',
        ])->assertRedirect(route('departments.edit', $parent))->assertSessionHas('error');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_department_with_current_employees_cannot_be_deactivated(): void
    {
        $department = Department::factory()->create();
        Employee::factory()->create(['department_id' => $department->id]);

        $this->actingAs($this->hrOfficer)->put(route('departments.update', $department), [
            'code' => $department->code, 'name' => $department->name, 'is_active' => '0',
        ])->assertSessionHas('error');

        $this->assertTrue($department->fresh()->is_active);
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $department = Department::factory()->create();
        Employee::factory()->terminated()->create(['department_id' => $department->id]);

        $this->actingAs($this->hrOfficer)->delete(route('departments.destroy', $department))->assertSessionHas('error');

        $this->assertNotSoftDeleted($department);
    }

    public function test_empty_department_can_be_deleted(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->hrOfficer)->delete(route('departments.destroy', $department))->assertRedirect(route('departments.index'));

        $this->assertSoftDeleted($department);
    }
}
