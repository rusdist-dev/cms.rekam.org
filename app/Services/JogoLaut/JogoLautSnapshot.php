<?php

namespace App\Services\JogoLaut;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * The only path from the monitoring payload to the upstream database: every
 * read goes through remember(), which makes it cached, single-flight, and
 * failure-aware.
 *
 * Entries are keyed by a time *bucket* (floor(now / ttl)) rather than merely
 * given a TTL. Every read in the same bucket therefore sees the same
 * snapshot — the soil CO₂ chart and the outliers flagged on it can never come
 * from two different five-minute windows — and a new bucket starts clean
 * without anyone having to invalidate anything. The scheduled warm-up
 * (cms:jogolaut-warm) fills each bucket as it opens.
 *
 * What it protects the upstream from:
 *
 *  - cache-busting: request parameters reach the key only as far as they
 *    change the query (`days`, the table page), so varying `window`,
 *    `locale` or `include` costs CPU here, never a query there;
 *  - stampedes: one process fetches a missing entry while the others wait on
 *    a lock and then read what it stored;
 *  - hammering while down: a failed read is remembered for `failure_ttl`
 *    seconds, during which it fails fast instead of being retried.
 */
class JogoLautSnapshot
{
    private const PREFIX = 'ext:jogolaut:';

    public function __construct(private readonly Repository $cache) {}

    /** Start of the bucket $now falls in, in Unix seconds. */
    public function bucket(int $now): int
    {
        $ttl = $this->ttl();

        return intdiv($now, $ttl) * $ttl;
    }

    public function ttl(): int
    {
        return max(60, (int) config('jogolaut.cache.ttl', 300));
    }

    /**
     * @param  string  $source  what is being read — the unit that is marked down on failure
     * @param  array<string, scalar>  $params  everything else that changes the result
     *
     * @throws JogoLautSourceUnavailable
     */
    public function remember(int $bucket, string $source, array $params, Closure $fetch): mixed
    {
        $key = $this->key('raw', $bucket, $source, $params);
        $miss = new \stdClass;

        if (($value = $this->cache->get($key, $miss)) !== $miss) {
            return $value;
        }

        if ($this->cache->has(self::PREFIX."down:{$source}")) {
            throw new JogoLautSourceUnavailable($source);
        }

        $lock = $this->cache->getStore()->lock("{$key}:lock", (int) config('jogolaut.cache.lock_ttl', 60));

        try {
            return $lock->block((int) config('jogolaut.cache.lock_wait', 10), function () use ($key, $miss, $source, $fetch) {
                // Whoever held the lock before us has probably stored it.
                if (($value = $this->cache->get($key, $miss)) !== $miss) {
                    return $value;
                }

                return $this->fetch($key, $source, $fetch);
            });
        } catch (LockTimeoutException) {
            // The holder is stuck (a slow upstream, most likely). Waiting longer
            // would only stack requests up behind it; read it ourselves.
            return $this->fetch($key, $source, $fetch);
        }
    }

    /** A cached derived value (a built section), or null. */
    public function getSection(int $bucket, string $section, array $params): ?array
    {
        return $this->cache->get($this->key('section', $bucket, $section, $params));
    }

    public function putSection(int $bucket, string $section, array $params, array $value): void
    {
        $this->cache->put($this->key('section', $bucket, $section, $params), $value, $this->ttl() * 2);
    }

    private function fetch(string $key, string $source, Closure $fetch): mixed
    {
        try {
            $value = $fetch();
        } catch (Throwable $e) {
            report($e);
            $this->cache->put(self::PREFIX."down:{$source}", true, (int) config('jogolaut.cache.failure_ttl', 30));

            throw new JogoLautSourceUnavailable($source, $e);
        }

        // Twice the bucket: long enough to outlive the bucket it belongs to,
        // short enough that old buckets clean themselves up.
        $this->cache->put($key, $value, $this->ttl() * 2);

        return $value;
    }

    private function key(string $kind, int $bucket, string $name, array $params): string
    {
        ksort($params);

        return self::PREFIX."{$kind}:{$bucket}:{$name}:".http_build_query($params);
    }
}
