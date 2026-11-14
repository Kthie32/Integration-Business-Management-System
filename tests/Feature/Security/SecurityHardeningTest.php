<?php

namespace Tests\Feature\Security;

use App\Enums\SystemRole;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Protections added or confirmed by the Phase 14 security review (docs/08).
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_confirmation_is_limited_to_five_attempts_a_minute(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($user)->post(route('password.confirm.store'), ['password' => "wrong-{$attempt}"])->assertSessionHasErrors('password');
        }
        $blocked = $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password']);

        $blocked->assertTooManyRequests();
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_changing_the_password_is_limited_to_five_attempts_a_minute(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;
        $payload = ['current_password' => 'not-the-password', 'password' => 'N3w-Passw0rd!x', 'password_confirmation' => 'N3w-Passw0rd!x'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($user)->put(route('user-password.update'), $payload);
        }
        $blocked = $this->actingAs($user)->put(route('user-password.update'), [...$payload, 'current_password' => 'password']);

        $blocked->assertTooManyRequests();
        $this->assertSame($originalHash, $user->refresh()->password);
    }

    public function test_password_reset_requests_are_limited_per_address(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.email'), ['email' => "person{$attempt}@example.test"]);
        }

        $this->post(route('password.email'), ['email' => 'person6@example.test'])->assertTooManyRequests();
    }

    public function test_pages_send_a_content_security_policy_that_forbids_framing_and_plugins(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->userWithRole(SystemRole::GeneralEmployee);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', SecurityHeaders::CONTENT_SECURITY_POLICY);
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", SecurityHeaders::CONTENT_SECURITY_POLICY);
        $this->assertStringContainsString("object-src 'none'", SecurityHeaders::CONTENT_SECURITY_POLICY);
    }

    public function test_private_files_are_not_reachable_through_a_storage_url(): void
    {
        Storage::disk('local')->put('employees/1/contract.pdf', 'confidential');

        $this->get('/storage/employees/1/contract.pdf')->assertNotFound();
    }

    public function test_csrf_protection_covers_every_web_route(): void
    {
        $web = app(Kernel::class)->getMiddlewareGroups()['web'];
        $neverVerify = new ReflectionProperty(PreventRequestForgery::class, 'neverVerify');

        $this->assertNotEmpty(array_filter($web, fn (string $middleware): bool => is_a($middleware, PreventRequestForgery::class, true)));
        $this->assertSame([], $neverVerify->getValue());
    }
}
