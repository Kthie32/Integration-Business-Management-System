<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    /**
     * Only a Super Administrator may edit a Super Administrator's account.
     */
    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.update') && $this->mayManage($actor, $user);
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $actor->can('users.deactivate') && ! $actor->is($user) && $this->mayManage($actor, $user);
    }

    public function activate(User $actor, User $user): bool
    {
        return $actor->can('users.deactivate') && $this->mayManage($actor, $user);
    }

    public function sendPasswordLink(User $actor, User $user): bool
    {
        return $actor->can('users.update') && $this->mayManage($actor, $user);
    }

    public function manageSessions(User $actor, User $user): bool
    {
        return $actor->can('sessions.revoke') && $this->mayManage($actor, $user);
    }

    private function mayManage(User $actor, User $user): bool
    {
        return $actor->isSuperAdministrator() || ! $user->isSuperAdministrator();
    }
}
