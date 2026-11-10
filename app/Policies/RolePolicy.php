<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    /**
     * The Super Administrator role is never editable, and non-super-administrators cannot change
     * a role they hold themselves (prevents granting yourself extra permissions).
     */
    public function update(User $actor, Role $role): bool
    {
        if (! $actor->can('roles.manage') || $role->isSuperAdministrator()) {
            return false;
        }

        return $actor->isSuperAdministrator() || ! $actor->hasRole($role->name);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $this->update($actor, $role) && ! $role->is_system;
    }
}
