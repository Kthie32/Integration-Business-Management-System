<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the permission catalogue (defined in code) into the database.
 *
 * Safe to run repeatedly: missing permissions are created and permissions removed from the catalogue are deleted.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = PermissionCatalog::all();

        foreach ($catalogue as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Permission::query()->where('guard_name', 'web')->whereNotIn('name', $catalogue)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
