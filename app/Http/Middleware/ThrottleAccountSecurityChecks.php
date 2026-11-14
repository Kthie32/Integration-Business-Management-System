<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits the Fortify actions that check a secret but are not covered by the login limiters:
 * confirming the password, changing it, confirming a new two-factor code and requesting reset links.
 *
 * Without a limit, someone holding a signed-in session could guess the account password (and then switch
 * two-factor off), and the reset form could be used to flood mailboxes.
 */
class ThrottleAccountSecurityChecks
{
    /**
     * Route names checked, all sharing the "account-security" limiter.
     */
    public const ROUTES = ['password.confirm.store', 'user-password.update', 'two-factor.confirm', 'password.email'];

    public function __construct(private ThrottleRequests $throttle) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->routeIs(...self::ROUTES)) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'account-security');
    }
}
