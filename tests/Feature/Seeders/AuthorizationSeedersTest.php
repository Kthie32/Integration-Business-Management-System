<?php

namespace Tests\Feature\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthorizationSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_creates_every_permission_and_system_role_once(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedRolesAndPermissions();

        $this->assertSame(count(PermissionCatalog::all()), Permission::count());
        $this->assertSame(count(SystemRole::cases()), Role::query()->where('is_system', true)->count());
        $this->assertCount(count(PermissionCatalog::all()), Role::findByName(SystemRole::SuperAdministrator->value)->permissions);
    }

    public function test_reseeding_keeps_permissions_the_organization_changed(): void
    {
        $this->seedRolesAndPermissions();
        Role::findByName(SystemRole::Accountant->value)->syncPermissions(['invoices.view']);

        $this->seedRolesAndPermissions();

        $this->assertSame(['invoices.view'], Role::findByName(SystemRole::Accountant->value)->permissions->pluck('name')->all());
    }

    public function test_super_admin_seeder_creates_the_first_super_administrator_once(): void
    {
        $this->seedRolesAndPermissions();
        config()->set('ibms.super_admin', ['name' => 'Owner', 'email' => 'Owner@Example.com', 'password' => 'Secret123']);

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $users = User::role(SystemRole::SuperAdministrator->value)->get();
        $this->assertCount(1, $users);
        $this->assertSame('owner@example.com', $users->first()->email);
        $this->assertTrue(password_verify('Secret123', $users->first()->password));
    }

    public function test_super_admin_seeder_does_nothing_without_an_email(): void
    {
        $this->seedRolesAndPermissions();
        config()->set('ibms.super_admin.email', null);

        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(0, User::count());
    }
}
