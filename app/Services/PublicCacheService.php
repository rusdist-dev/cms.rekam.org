<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * Public API response caching (plan.md Fase 6).
 *
 * Same tenant-id-prefixed-key approach TaxonomyService already uses — the
 * `file` cache driver has no tag support, so per-tenant isolation comes from
 * the key itself, not Cache::tags().
 *
 * forget() only clears the *canonical* (no query params) entry per locale —
 * a filtered/paginated variant relies on the TTL instead. There is no way to
 * enumerate or pattern-match every possible query-string combination without
 * tag support, so this is TTL-first, explicit-forget second: the common,
 * unfiltered request is invalidated immediately, everything else within
 * config('cms.public_api.cache_ttl').
 */
class PublicCacheService
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function remember(string $resource, array $query, Closure $callback): mixed
    {
        return Cache::remember(
            $this->key($this->tenants->currentOrFail()->id, App::getLocale(), $resource, $query),
            config('cms.public_api.cache_ttl'),
            $callback,
        );
    }

    public function forget(string $resource): void
    {
        $tenantId = $this->tenants->currentId();

        if ($tenantId === null) {
            return;
        }

        foreach (config('cms.locales') as $locale) {
            Cache::forget($this->key($tenantId, $locale, $resource, []));
        }
    }

    private function key(int $tenantId, string $locale, string $resource, array $query): string
    {
        // Sparse fieldsets are applied after the cache lookup, not baked into
        // it — a ?fields= request must not fragment the cache.
        unset($query['fields']);
        ksort($query);

        return "public:{$tenantId}:{$locale}:{$resource}:".http_build_query($query);
    }
}
