<?php

namespace App\Services;

/**
 * Response caching for /api/v1/ext/*.
 *
 * Deliberately *not* PublicCacheService. That one keys every entry by the
 * active tenant and locale, which is right for tenant content and wrong here:
 * external data is the same rows whoever asks for it, so tenant-keying it would
 * multiply identical payloads by the number of tenants and multiply the load on
 * a system that is usually the slow, fragile dependency in the chain.
 *
 * Keys are `ext:{datasource}:{resource}:{query}`, so clearing one datasource
 * never touches another's.
 */
class ExternalCacheService
{
    public function __construct(private readonly \Illuminate\Contracts\Cache\Repository $cache) {}

    public function remember(string $datasource, string $resource, array $query, \Closure $callback): mixed
    {
        return $this->cache->remember(
            $this->key($datasource, $resource, $query),
            config('datasources.cache_ttl'),
            $callback,
        );
    }

    /**
     * remember(), except that a value $discard accepts is returned without
     * being stored — for a payload assembled from parts that can fail
     * independently, where caching a partial failure would serve it for the
     * whole TTL after the upstream has recovered.
     */
    public function rememberUnless(string $datasource, string $resource, array $query, \Closure $callback, \Closure $discard): mixed
    {
        $key = $this->key($datasource, $resource, $query);
        $miss = new \stdClass;
        $value = $this->cache->get($key, $miss);

        if ($value !== $miss) {
            return $value;
        }

        $value = $callback();

        if (! $discard($value)) {
            $this->cache->put($key, $value, config('datasources.cache_ttl'));
        }

        return $value;
    }

    public function forget(string $datasource, string $resource): void
    {
        $this->cache->forget($this->key($datasource, $resource, []));
    }

    private function key(string $datasource, string $resource, array $query): string
    {
        // Sparse fieldsets are applied after the lookup, exactly as in
        // PaginatesPublicJson — a ?fields= request must not fragment the cache.
        unset($query['fields']);
        ksort($query);

        return "ext:{$datasource}:{$resource}:".http_build_query($query);
    }
}
