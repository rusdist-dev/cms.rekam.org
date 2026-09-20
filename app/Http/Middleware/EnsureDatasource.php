<?php

namespace App\Http\Middleware;

use App\Services\DatasourceRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `datasource:simrs` — refuses the route when that external database has no
 * credentials in this environment.
 *
 * Datasources come and go per environment: staging may have SIMRS but not the
 * payroll database, a developer laptop usually has neither. Without this the
 * first missing .env line surfaces as a PDO connection timeout several seconds
 * into the request; with it, the route answers 503 immediately and says which
 * datasource is missing.
 */
class EnsureDatasource
{
    public function __construct(private readonly DatasourceRegistry $datasources) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! $this->datasources->isConfigured($key)) {
            return response()->json([
                'message' => "Sumber data '{$this->datasources->label($key)}' tidak tersedia di lingkungan ini.",
            ], 503);
        }

        return $next($request);
    }
}
