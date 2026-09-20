<?php

namespace App\Providers;

use App\Exceptions\ReadOnlyDatasourceException;
use App\Services\DatasourceRegistry;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LogicException;

/**
 * Turns config/datasources.php into real Laravel connections, and makes the
 * `read_only` flag on each one mean something.
 *
 * Kept out of AppServiceProvider on purpose: the connection registration has to
 * happen in register(), before anything resolves the database manager, and the
 * read-only wiring is the kind of thing a reader should be able to find by
 * filename rather than by scrolling.
 */
class DatasourceServiceProvider extends ServiceProvider
{
    /**
     * Connections already carrying the guard, by spl_object_id.
     *
     * ConnectionEstablished fires again on DB::reconnect() for the same
     * Connection instance; without this the guard would stack up one extra
     * copy per reconnect.
     *
     * @var array<int, true>
     */
    private array $guarded = [];

    public function register(): void
    {
        $this->app->singleton(DatasourceRegistry::class);

        $prefix = config('datasources.prefix', 'ds_');
        $defaults = config('datasources.defaults', []);

        foreach (config('datasources.sources', []) as $key => $source) {
            $name = $prefix.$key;

            // A datasource silently overwriting `mysql` or `tenant` would be
            // catastrophic and invisible, so this is fatal at boot rather than
            // a log line nobody reads.
            if (in_array($name, DatasourceRegistry::RESERVED, true)) {
                throw new LogicException(
                    "Datasource '{$key}' menghasilkan nama koneksi '{$name}' yang sudah dipakai sistem."
                );
            }

            Config::set(
                "database.connections.{$name}",
                array_merge($defaults, $source['connection'] ?? [])
            );
        }
    }

    public function boot(): void
    {
        $registry = $this->app->make(DatasourceRegistry::class);

        // Attaching lazily, when a connection is actually established, keeps
        // boot from resolving connections nobody in this request will use.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) use ($registry) {
            $key = $this->keyForConnection($event->connection->getName(), $registry);

            if ($key !== null) {
                $this->guardReadOnly($event->connection, $key);
            }
        });
    }

    private function keyForConnection(?string $name, DatasourceRegistry $registry): ?string
    {
        foreach ($registry->keys() as $key) {
            if ($registry->connectionName($key) === $name) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Refuses anything that is not a read, at the connection level — so it
     * covers Eloquent, the query builder, and hand-written DB::statement()
     * alike, which a base-model override would not.
     *
     * The guard goes on every datasource connection, read-only or not, and asks
     * the registry per statement rather than deciding once at attach time. A
     * connection object outlives a config change; deciding up front would mean
     * a source flipped to read_only kept the connection it opened while it was
     * still writable — exactly the case the flag exists to stop.
     */
    private function guardReadOnly(Connection $connection, string $key): void
    {
        $id = spl_object_id($connection);

        if (isset($this->guarded[$id])) {
            return;
        }

        $this->guarded[$id] = true;

        $registry = $this->app->make(DatasourceRegistry::class);

        $connection->beforeExecuting(function (string $query) use ($key, $registry) {
            if ($registry->isReadOnly($key) && ! $this->isReadStatement($query)) {
                throw new ReadOnlyDatasourceException($key, $this->firstWord($query));
            }
        });
    }

    /**
     * An allow-list, not a block-list: a block-list is only ever as complete as
     * the SQL dialect you remembered, and the cost of being wrong here is a
     * write into someone else's production database.
     */
    private function isReadStatement(string $query): bool
    {
        return in_array($this->firstWord($query), [
            'select', 'show', 'describe', 'desc', 'explain', 'with', 'set',
        ], true);
    }

    private function firstWord(string $query): string
    {
        // Strip leading parentheses (a UNION written as `(select ...)`) and
        // any leading comment/whitespace the caller may have included.
        $normalised = ltrim(preg_replace('/^\s*(\/\*.*?\*\/|--[^\n]*\n)*\s*/s', '', $query) ?? $query, "( \t\n\r");

        return strtolower(strtok($normalised, " \t\n\r(") ?: '');
    }
}
