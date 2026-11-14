<?php

namespace Tests\Feature\Account;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->user = User::factory()->withRole(SystemRole::GeneralEmployee)->create();
    }

    private function createSession(string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $this->user->id,
            'ip_address' => '10.0.0.9',
            'user_agent' => 'Other Device Browser',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_profile_and_security_pages_render(): void
    {
        $this->actingAs($this->user)->get(route('account.profile'))->assertOk()->assertSee($this->user->email);
        $this->actingAs($this->user)->get(route('account.security'))->assertOk()->assertSee('Signed-in devices')->assertDontSee('Two-factor');
    }

    public function test_user_updates_own_profile_including_phone(): void
    {
        $this->actingAs($this->user)->put(route('user-profile-information.update'), [
            'name' => 'New Name',
            'email' => 'New.Email@Example.com',
            'phone' => '0772 123456',
        ])->assertSessionHasNoErrors();

        $this->user->refresh();
        $this->assertSame('New Name', $this->user->name);
        $this->assertSame('new.email@example.com', $this->user->email);
        $this->assertSame('0772 123456', $this->user->phone);
    }

    public function test_user_changes_password_with_current_password(): void
    {
        $this->actingAs($this->user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassw0rd',
            'password_confirmation' => 'NewPassw0rd',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('NewPassw0rd', $this->user->fresh()->password));
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $this->actingAs($this->user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_signing_out_other_devices_requires_password_and_keeps_current_session(): void
    {
        $this->createSession(str_repeat('d', 40));

        $this->actingAs($this->user)->delete(route('account.sessions.destroy-others'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('sessions', ['id' => str_repeat('d', 40)]);

        $this->actingAs($this->user)->delete(route('account.sessions.destroy-others'), ['password' => 'password'])
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('sessions', ['id' => str_repeat('d', 40)]);
    }

    public function test_user_cannot_end_another_users_session(): void
    {
        $other = User::factory()->create();
        $rawId = str_repeat('e', 40);
        DB::table('sessions')->insert([
            'id' => $rawId, 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->user)->delete(route('account.sessions.destroy', hash_hmac('sha256', $rawId, (string) config('app.key'))));

        $this->assertDatabaseHas('sessions', ['id' => $rawId]);
    }
}
