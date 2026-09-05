<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the `tenant` database connection for the rest of the request
 * (context.md §5.3). Every dashboard and internal API route passes through
 * here; without it, content models have nowhere to read from.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenants->resolveForCurrentUser();

        if ($tenant === null) {
            // An account with no company assigned can sign in but has nothing to
            // edit. Signing them out would hide the reason, so explain it.
            return $this->noTenantResponse($request);
        }

        return $next($request);
    }

    private function noTenantResponse(Request $request): Response
    {
        $message = 'Akun Anda belum ditugaskan ke company mana pun. Hubungi administrator.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        return response(view('errors.tenant-not-resolved', ['message' => $message]), 409);
    }
}
