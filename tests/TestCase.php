<?php

namespace Tests;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\TestDatabaseSeeder;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seeder RefreshDatabase runs once per process after `migrate:fresh`: the permission catalogue and system roles.
     *
     * @var class-string
     */
    protected $seeder = TestDatabaseSeeder::class;

    /**
     * Make sure the permission catalogue and system roles exist.
     *
     * They are normally seeded once per process (see $seeder) and survive every test's rolled-back transaction,
     * so this only clears the permission cache. The seeders run here only if the roles are missing.
     */
    protected function seedRolesAndPermissions(): void
    {
        if (! Role::query()->where('is_system', true)->exists()) {
            $this->runAuthorizationSeeders();

            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Run the permission and role seeders for real (used by the seeder tests).
     */
    protected function runAuthorizationSeeders(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    /**
     * Create an active user holding the given system role.
     */
    protected function userWithRole(SystemRole $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}
