<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline hardening headers (plan.md Fase 8) applied globally — dashboard,
 * dash-api, and the public compro API alike.
 *
 * `script-src` needs `'unsafe-eval'`: the app bundles the plain `alpinejs`
 * package (not the `@alpinejs/csp` build), and Alpine evaluates every
 * `x-data`/`x-show`/`x-model` expression via `new Function()` internally.
 * Removing it would break every page's interactivity, not just tighten a
 * policy — do not "clean this up" without switching Alpine builds first.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}
