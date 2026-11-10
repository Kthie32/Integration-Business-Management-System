<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users whose role requires two-factor authentication to their Security page until they confirm it.
 */
class EnsureTwoFactorIsEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->requiresTwoFactor() && $user->two_factor_confirmed_at === null) {
            return redirect()->route('account.security')
                ->with('warning', __('Your role requires two-factor authentication. Set it up to continue.'));
        }

        return $next($request);
    }
}
