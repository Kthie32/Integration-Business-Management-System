<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnsureTwoFactorIsEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    /**
     * @return array<string, array{SystemRole}>
     */
    public static function rolesRequiringTwoFactor(): array
    {
        return [
            'super administrator' => [SystemRole::SuperAdministrator],
            'system administrator' => [SystemRole::SystemAdministrator],
        ];
    }

    #[DataProvider('rolesRequiringTwoFactor')]
    public function test_administrator_without_two_factor_is_sent_to_security_page(SystemRole $role): void
    {
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('account.security'))
            ->assertSessionHas('warning');

        $this->actingAs($user)->get(route('account.security'))->assertOk();
    }

    public function test_administrator_with_confirmed_two_factor_can_continue(): void
    {
        $user = User::factory()->withTwoFactor()->withRole(SystemRole::SystemAdministrator)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_other_roles_are_not_forced_to_use_two_factor(): void
    {
        $user = User::factory()->withRole(SystemRole::Accountant)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
