<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline browser security headers to every web response.
 */
class SecurityHeaders
{
    /**
     * Directives that hold without restricting the page's own scripts and styles: no framing by other sites,
     * no plugins, no <base> rewriting and forms that only submit to this application.
     */
    public const CONTENT_SECURITY_POLICY = "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'";

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
