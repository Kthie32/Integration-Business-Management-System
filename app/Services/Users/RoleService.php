<?php

namespace App\Services\Users;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Business operations on roles and their permissions.
 */
class RoleService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, description?: string|null, permissions?: list<string>}  $data
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = new Role;
            $role->forceFill([
                'name' => $data['name'],
                'guard_name' => 'web',
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ])->save();

            $permissions = $this->normalise($data['permissions'] ?? []);
            $role->syncPermissions($permissions);

            $this->auditLogger->log('permissions_changed', $role, null, [], ['permissions' => $permissions]);

            return $role;
        });
    }

    /**
     * @param  array{name: string, description?: string|null, permissions?: list<string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        if ($role->isSuperAdministrator()) {
            throw new BusinessRuleException('The Super Administrator role always has every permission and cannot be edited.');
        }

        return DB::transaction(function () use ($role, $data): Role {
            $role->forceFill([
                'name' => $role->is_system ? $role->name : $data['name'],
                'description' => $data['description'] ?? null,
            ])->save();

            $previous = $this->normalise($role->permissions()->pluck('name')->all());
            $permissions = $this->normalise($data['permissions'] ?? []);
            $role->syncPermissions($permissions);

            if ($previous !== $permissions) {
                $this->auditLogger->log(
                    'permissions_changed',
                    $role,
                    null,
                    ['removed' => array_values(array_diff($previous, $permissions))],
                    ['added' => array_values(array_diff($permissions, $previous))],
                );
            }

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new BusinessRuleException('System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            throw new BusinessRuleException('This role is assigned to users. Remove it from all users before deleting it.');
        }

        $role->delete();
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function normalise(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }
}
