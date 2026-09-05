<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:units` — a module the active tenant does not have must not merely be
 * hidden from the menu; its routes have to be gone (context.md §5.6).
 *
 * 404 rather than 403 is deliberate: for this tenant the module genuinely does
 * not exist, and a 403 would leak that another company has it.
 */
class EnsureTenantFeature
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            if (! $this->tenants->hasFeature($feature)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
