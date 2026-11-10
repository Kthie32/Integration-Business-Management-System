<?php

namespace Tests\Feature\Auth;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_active_user_signs_in_and_login_is_recorded(): void
    {
        $user = User::factory()->withRole(SystemRole::GeneralEmployee)->create(['email' => 'jane@example.com']);

        $response = $this->post(route('login.store'), ['email' => 'JANE@example.com', 'password' => 'password']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_recorded(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->post(route('login.store'), ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('event', 'login_failed')->count());
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'jane@example.com']);

        $this->post(route('login.store'), ['email' => 'jane@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Your account has been deactivated. Contact your administrator.']);

        $this->assertGuest();
    }

    public function test_user_deactivated_after_signing_in_is_signed_out_on_next_request(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_signed_in_user_sees_dashboard(): void
    {
        $user = User::factory()->withRole(SystemRole::GeneralEmployee)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee($user->name);
    }

    public function test_login_page_sends_security_headers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
