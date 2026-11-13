<?php

namespace Tests\Concerns;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalogue and system roles once per test process.
 *
 * RefreshDatabase runs this right after `migrate:fresh`, before any test opens its transaction, so the roles are
 * committed once and every test's changes to them are rolled back. The audit entries written while seeding are
 * removed again, so each test starts with an empty audit log, as it did when the roles were seeded per test.
 */
class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, RoleSeeder::class]);

        DB::table('audit_logs')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
