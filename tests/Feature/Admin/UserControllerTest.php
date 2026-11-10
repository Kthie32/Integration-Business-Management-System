<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        Notification::fake();

        $this->admin = $this->userWithRole(SystemRole::SystemAdministrator);
    }

    private function roleId(SystemRole $role): int
    {
        return Role::findByName($role->value)->id;
    }

    public function test_user_without_permission_cannot_open_user_management(): void
    {
        $employee = User::factory()->withRole(SystemRole::GeneralEmployee)->create();

        $this->actingAs($employee)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('admin.users.store'), [])->assertForbidden();
    }

    public function test_index_lists_users_and_filters_by_search(): void
    {
        User::factory()->create(['name' => 'Alice Nakato']);
        User::factory()->create(['name' => 'Brian Okello']);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'Nakato']))
            ->assertOk()
            ->assertSee('Alice Nakato')
            ->assertDontSee('Brian Okello');
    }

    public function test_creating_a_user_assigns_roles_and_emails_a_password_link(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Grace Achieng',
            'email' => 'Grace@Example.com',
            'phone' => '+256 700 000000',
            'roles' => [$this->roleId(SystemRole::Accountant)],
        ]);

        $user = User::query()->where('email', 'grace@example.com')->firstOrFail();

        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertTrue($user->hasRole(SystemRole::Accountant->value));
        $this->assertTrue($user->is_active);
        $this->assertSame($this->admin->id, $user->created_by);
        Notification::assertSentTo($user, AccountCreatedNotification::class);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_id' => $user->id]);
    }

    public function test_creating_a_user_requires_name_email_and_a_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'roles' => 'Select at least one role.']);
    }

    public function test_creating_a_user_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'email' => 'TAKEN@example.com',
            'roles' => [$this->roleId(SystemRole::Accountant)],
        ])->assertSessionHasErrors(['email' => 'The email has already been taken.']);
    }

    public function test_only_a_super_administrator_can_assign_the_super_administrator_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'roles' => [$this->roleId(SystemRole::SuperAdministrator)],
        ])->assertSessionHas('error', 'Only a Super Administrator can assign the Super Administrator role.');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_super_administrator_role_is_not_offered_to_other_administrators(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee(SystemRole::Accountant->value)
            ->assertDontSee(SystemRole::SuperAdministrator->value);
    }

    public function test_updating_a_user_changes_details_and_roles(): void
    {
        $user = User::factory()->withRole(SystemRole::Accountant)->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => 'Renamed',
            'email' => $user->email,
            'roles' => [$this->roleId(SystemRole::FinanceOfficer)],
        ])->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame([SystemRole::FinanceOfficer->value], $user->getRoleNames()->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'roles_changed', 'auditable_id' => $user->id]);
    }

    public function test_administrator_cannot_change_their_own_roles(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => [$this->roleId(SystemRole::SystemAdministrator), $this->roleId(SystemRole::FinanceOfficer)],
        ])->assertSessionHas('error', 'You cannot change your own roles. Ask another administrator.');

        $this->assertSame([SystemRole::SystemAdministrator->value], $this->admin->fresh()->getRoleNames()->all());
    }

    public function test_administrator_cannot_edit_a_super_administrator(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdministrator);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $superAdmin))->assertForbidden();
        $this->actingAs($this->admin)->put(route('admin.users.update', $superAdmin), [
            'name' => 'Hijacked',
            'email' => $superAdmin->email,
            'roles' => [$this->roleId(SystemRole::GeneralEmployee)],
        ])->assertForbidden();
    }

    public function test_removing_the_last_super_administrator_role_is_refused(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdministrator);
        $other = User::factory()->withRole(SystemRole::SuperAdministrator)->create();
        $other->forceFill(['is_active' => false])->save();

        $this->actingAs($superAdmin)->put(route('admin.users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'roles' => [$this->roleId(SystemRole::GeneralEmployee)],
        ])->assertSessionHas('error', 'At least one active Super Administrator must remain.');

        $this->assertTrue($superAdmin->fresh()->isSuperAdministrator());
    }
}
