<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\System\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * Records sign-in activity in the audit log and keeps the user's last-login details current.
 *
 * Methods are deliberately not prefixed with "handle" so automatic listener discovery does not register them twice.
 */
class AuthenticationAuditSubscriber
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->auditLogger->log('login', $event->user, $event->remember ? 'Signed in (remember me)' : 'Signed in', userId: $event->user->id);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->log('logout', $event->user, 'Signed out', userId: $event->user->id);
        }
    }

    public function onFailed(Failed $event): void
    {
        $email = Str::limit((string) ($event->credentials['email'] ?? ''), 120);

        $this->auditLogger->log(
            'login_failed',
            $event->user instanceof User ? $event->user : null,
            'Failed sign-in attempt for '.$email,
            userId: null,
        );
    }

    public function onLockout(Lockout $event): void
    {
        $email = Str::limit((string) $event->request->input('email', ''), 120);

        $this->auditLogger->log('login_lockout', null, 'Too many sign-in attempts for '.$email);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->log('password_reset', $event->user, 'Password set via email link', userId: $event->user->id);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }
}
