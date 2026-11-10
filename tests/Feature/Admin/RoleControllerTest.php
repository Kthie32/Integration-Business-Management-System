<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->admin = $this->userWithRole(SystemRole::SystemAdministrator);
    }

    public function test_user_without_permission_cannot_manage_roles(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();

        $this->actingAs($manager)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_index_lists_system_roles(): void
    {
        $this->actingAs($this->admin)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee(SystemRole::FinanceOfficer->value);
    }

    public function test_creating_a_role_grants_selected_permissions(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'Cashier',
            'description' => 'Front desk payments',
            'permissions' => ['payments.view', 'payments.record'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::findByName('Cashier');
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(['payments.view', 'payments.record'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'permissions_changed', 'auditable_id' => $role->id]);
    }

    public function test_creating_a_role_rejects_unknown_permissions_and_duplicate_names(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => SystemRole::Accountant->value,
            'permissions' => ['everything.allowed'],
        ])->assertSessionHasErrors(['name', 'permissions.0']);
    }

    public function test_updating_a_role_replaces_its_permissions(): void
    {
        $role = Role::findByName(SystemRole::Accountant->value);

        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'Renamed system role',
            'permissions' => ['invoices.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame(SystemRole::Accountant->value, $role->name);
        $this->assertSame(['invoices.view'], $role->permissions->pluck('name')->all());
    }

    public function test_super_administrator_role_cannot_be_edited(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdministrator);
        $role = Role::findByName(SystemRole::SuperAdministrator->value);

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'name' => $role->name,
            'permissions' => [],
        ])->assertForbidden();

        $this->actingAs($superAdmin)->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('always has every permission');
    }

    public function test_administrator_cannot_edit_a_role_they_hold(): void
    {
        $role = Role::findByName(SystemRole::SystemAdministrator->value);

        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => $role->name,
            'permissions' => ['users.view', 'payroll.approve'],
        ])->assertForbidden();
    }

    public function test_custom_role_without_users_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_assigned_to_users_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('error', 'This role is assigned to users. Remove it from all users before deleting it.');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $role = Role::findByName(SystemRole::Accountant->value);

        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }
}
