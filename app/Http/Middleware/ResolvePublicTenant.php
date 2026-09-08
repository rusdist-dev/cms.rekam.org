<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the public API (routes/api.php) by `X-Api-Key` instead of a
 * session — there is no logged-in user here, so this binds the `tenant`
 * connection directly via TenantManager::setCurrent() rather than
 * ResolveTenant's resolveForCurrentUser(), which is session/user-based
 * (context.md §2.4, plan.md Fase 6).
 */
class ResolvePublicTenant
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Api-Key');

        $tenant = $key ? Tenant::findByApiKey($key) : null;

        if ($tenant === null) {
            return response()->json([
                'message' => 'API key tidak valid atau tidak ditemukan.',
            ], 401);
        }

        $this->tenants->setCurrent($tenant);

        return $next($request);
    }
}
