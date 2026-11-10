<?php

namespace Tests;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the permission catalogue and system roles.
     */
    protected function seedRolesAndPermissions(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    /**
     * Create an active user holding the given system role, with two-factor confirmed so mandatory 2FA never interferes.
     */
    protected function userWithRole(SystemRole $role): User
    {
        return User::factory()->withTwoFactor()->withRole($role)->create();
    }
}
