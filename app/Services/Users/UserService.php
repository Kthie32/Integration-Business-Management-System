<?php

namespace App\Services\Users;

use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Business operations on user accounts: creation, role assignment, activation and session control.
 */
class UserService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Create an account with an unusable random password and email the user a link to set their own.
     *
     * @param  array{name: string, email: string, phone?: string|null, roles: list<int|string>}  $data
     */
    public function create(array $data, User $actor): User
    {
        $roles = $this->resolveAssignableRoles($data['roles'], $actor);

        $user = DB::transaction(function () use ($data, $actor, $roles): User {
            $user = new User([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(40),
            ]);
            $user->forceFill(['is_active' => true, 'created_by' => $actor->id])->save();
            $user->syncRoles($roles);

            $this->auditLogger->log('roles_assigned', $user, 'Initial roles assigned', [], ['roles' => $roles]);

            return $user;
        });

        $this->sendPasswordSetupLink($user);

        return $user;
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, roles: list<int|string>}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $roles = $this->resolveAssignableRoles($data['roles'], $actor);

        return DB::transaction(function () use ($user, $data, $roles, $actor): User {
            $previousRoles = $user->getRoleNames()->sort()->values()->all();

            if ($user->is($actor) && ! $actor->isSuperAdministrator()
                && collect($roles)->sort()->values()->all() !== $previousRoles) {
                throw new BusinessRuleException('You cannot change your own roles. Ask another administrator.');
            }

            if (in_array(SystemRole::SuperAdministrator->value, $previousRoles, true)
                && ! in_array(SystemRole::SuperAdministrator->value, $roles, true)) {
                $this->ensureAnotherActiveSuperAdministratorExists($user);
            }

            $user->update([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
            ]);
            $user->syncRoles($roles);

            $newRoles = collect($roles)->sort()->values()->all();

            if ($previousRoles !== $newRoles) {
                $this->auditLogger->log('roles_changed', $user, null, ['roles' => $previousRoles], ['roles' => $newRoles]);
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new BusinessRuleException('You cannot deactivate your own account.');
        }

        if (! $user->is_active) {
            return;
        }

        DB::transaction(function () use ($user): void {
            if ($user->isSuperAdministrator()) {
                $this->ensureAnotherActiveSuperAdministratorExists($user);
            }

            $user->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
            ])->save();
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

            $this->endAllSessions($user);
        });
    }

    public function activate(User $user): void
    {
        if ($user->is_active) {
            return;
        }

        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
    }

    public function sendPasswordSetupLink(User $user): void
    {
        $token = Password::broker(config('fortify.passwords'))->createToken($user);

        $user->notify(new AccountCreatedNotification($token));

        $this->auditLogger->log('password_link_sent', $user, 'Password setup link sent');
    }

    public function endAllSessions(User $user): int
    {
        $ended = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        $this->auditLogger->log('sessions_revoked', $user, "Ended {$ended} active session(s)");

        return $ended;
    }

    /**
     * The user's active sessions, newest first. Raw session IDs are never exposed; each session is identified
     * by a one-way hash so that viewing the list cannot be used to hijack a session.
     *
     * @return Collection<int, array{key: string, ip_address: string|null, user_agent: string, last_active_at: CarbonImmutable, is_current: bool}>
     */
    public function activeSessions(User $user, ?string $currentSessionId = null): Collection
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'key' => $this->sessionKey($session->id),
                'ip_address' => $session->ip_address,
                'user_agent' => Str::limit((string) $session->user_agent, 120),
                'last_active_at' => CarbonImmutable::createFromTimestamp($session->last_activity, config('app.timezone')),
                'is_current' => $currentSessionId !== null && hash_equals($session->id, $currentSessionId),
            ]);
    }

    /**
     * End one of the user's sessions, identified by the key from activeSessions().
     */
    public function endSession(User $user, string $sessionKey): bool
    {
        $sessionId = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->pluck('id')
            ->first(fn (string $id): bool => hash_equals($this->sessionKey($id), $sessionKey));

        if ($sessionId === null) {
            return false;
        }

        DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->delete();

        $this->auditLogger->log('session_revoked', $user, 'Ended one active session');

        return true;
    }

    /**
     * End every session except the current one and invalidate "remember me" cookies on other devices.
     */
    public function endOtherSessions(User $user, string $currentSessionId): int
    {
        $ended = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        $this->auditLogger->log('sessions_revoked', $user, "Signed out of {$ended} other session(s)");

        return $ended;
    }

    private function sessionKey(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    /**
     * Map submitted role IDs to role names, refusing the Super Administrator role to anyone who is not one.
     *
     * @param  list<int|string>  $roleIds
     * @return list<string>
     */
    private function resolveAssignableRoles(array $roleIds, User $actor): array
    {
        $roles = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();

        if (in_array(SystemRole::SuperAdministrator->value, $roles, true) && ! $actor->isSuperAdministrator()) {
            throw new BusinessRuleException('Only a Super Administrator can assign the Super Administrator role.');
        }

        return array_values($roles);
    }

    private function ensureAnotherActiveSuperAdministratorExists(User $user): void
    {
        $others = User::role(SystemRole::SuperAdministrator->value)
            ->active()
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw new BusinessRuleException('At least one active Super Administrator must remain.');
        }
    }
}
