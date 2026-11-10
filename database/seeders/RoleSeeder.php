<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the system roles with their default permissions.
 *
 * Existing roles keep the permissions the organization has given them; only the Super Administrator
 * role is re-synced, so it always holds every permission in the catalogue.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::query()->where('name', $systemRole->value)->where('guard_name', 'web')->first();
            $isNew = $role === null;

            $role ??= new Role;
            $role->forceFill([
                'name' => $systemRole->value,
                'guard_name' => 'web',
                'description' => $role->description ?? $systemRole->description(),
                'is_system' => true,
            ])->save();

            if ($systemRole === SystemRole::SuperAdministrator) {
                $role->syncPermissions(PermissionCatalog::all());
            } elseif ($isNew) {
                $role->syncPermissions($systemRole->defaultPermissions());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
