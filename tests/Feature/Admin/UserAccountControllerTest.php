<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->admin = $this->userWithRole(SystemRole::SystemAdministrator);
    }

    private function createSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.5',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_deactivating_a_user_ends_their_sessions(): void
    {
        $user = User::factory()->create();
        $this->createSession($user, str_repeat('a', 40));

        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $user))->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->deactivated_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_administrator_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $this->admin))->assertForbidden();

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_administrator_cannot_deactivate_a_super_administrator(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdministrator);

        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $superAdmin))->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_super_administrator_can_deactivate_another_super_administrator(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdministrator);
        $otherSuperAdmin = $this->userWithRole(SystemRole::SuperAdministrator);

        $this->actingAs($superAdmin)->patch(route('admin.users.deactivate', $otherSuperAdmin))->assertSessionHas('success');

        $this->assertFalse($otherSuperAdmin->fresh()->is_active);
    }

    public function test_reactivating_a_user_restores_access(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($this->admin)->patch(route('admin.users.activate', $user))->assertSessionHas('success');

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_sending_a_password_link_notifies_the_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.password-link', $user))->assertSessionHas('success');

        Notification::assertSentTo($user, AccountCreatedNotification::class);
    }

    public function test_user_page_never_exposes_raw_session_ids(): void
    {
        $user = User::factory()->create();
        $rawId = 'rawsessionidentifier1234567890abcdefghij';
        $this->createSession($user, $rawId);

        $this->actingAs($this->admin)->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Test Browser')
            ->assertDontSee($rawId);
    }

    public function test_ending_one_session_by_its_key_removes_it(): void
    {
        $user = User::factory()->create();
        $rawId = str_repeat('b', 40);
        $this->createSession($user, $rawId);
        $key = hash_hmac('sha256', $rawId, (string) config('app.key'));

        $this->actingAs($this->admin)->delete(route('admin.users.sessions.destroy', [$user, $key]))->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => $rawId]);
    }

    public function test_raw_session_id_cannot_be_used_to_end_a_session(): void
    {
        $user = User::factory()->create();
        $rawId = str_repeat('c', 40);
        $this->createSession($user, $rawId);

        $this->actingAs($this->admin)->delete(route('admin.users.sessions.destroy', [$user, $rawId]))->assertSessionHas('error');

        $this->assertDatabaseHas('sessions', ['id' => $rawId]);
    }

    public function test_user_without_session_permission_cannot_end_sessions(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();
        $user = User::factory()->create();

        $this->actingAs($manager)->delete(route('admin.users.sessions.destroy-all', $user))->assertForbidden();
    }
}
