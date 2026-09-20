<?php

namespace App\Services;

use App\Exceptions\DatasourceNotConfiguredException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "which external database is this, and may I write
 * to it" — the counterpart of TenantManager, for databases that belong to
 * neither the central CMS nor any tenant (context.md §5.2 draws the first two
 * boundaries; this is the third).
 *
 * Nothing outside this class should build an external connection name by hand.
 * `DB::connection('ds_simrs')` compiles fine while `ds_simrs` is misspelled,
 * unregistered, or write-guarded; connectionOrFail() does not.
 */
class DatasourceRegistry
{
    /**
     * Connection names a datasource may never claim. `tenant` is the dangerous
     * one — a source keyed `tenant` (prefix stripped by a future refactor, or
     * a prefix set to '') would repoint every TenantModel at a foreign
     * database while every query kept working.
     */
    public const RESERVED = ['mysql', 'tenant', 'sqlite', 'pgsql', 'sqlsrv'];

    /** @return array<int, string> Every datasource key declared in config. */
    public function keys(): array
    {
        return array_keys(config('datasources.sources', []));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, config('datasources.sources', []));
    }

    /**
     * Declared *and* usable. A key with no database name in .env is declared
     * but not configured — see the note in config/datasources.php.
     */
    public function isConfigured(string $key): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        return filled($this->definition($key)['connection']['database'] ?? null);
    }

    public function isReadOnly(string $key): bool
    {
        // Defaulting to true matters: a source added without the flag is
        // treated as somebody else's data until its owner says otherwise.
        return (bool) ($this->definition($key)['read_only'] ?? true);
    }

    public function label(string $key): string
    {
        return $this->definition($key)['label'] ?? $key;
    }

    /** The Laravel connection name, e.g. `simrs` => `ds_simrs`. */
    public function connectionName(string $key): string
    {
        return config('datasources.prefix', 'ds_').$key;
    }

    /**
     * The connection name, or a loud failure — the only form application code
     * should use. External data must never fall back to a default connection.
     *
     * @throws DatasourceNotConfiguredException
     */
    public function connectionNameOrFail(string $key): string
    {
        if (! $this->has($key)) {
            throw new DatasourceNotConfiguredException(
                $key,
                "Datasource '{$key}' tidak terdaftar di config/datasources.php."
            );
        }

        if (! $this->isConfigured($key)) {
            throw new DatasourceNotConfiguredException(
                $key,
                "Datasource '{$this->label($key)}' belum dikonfigurasi: nama database kosong di .env."
            );
        }

        return $this->connectionName($key);
    }

    /**
     * A ready connection for raw query-builder work (`->table(...)`), for the
     * cases where writing an Eloquent model over a legacy schema is not worth
     * it. The read-only guard is attached by DatasourceServiceProvider when the
     * connection is established, so raw access is protected too.
     *
     * @throws DatasourceNotConfiguredException
     */
    public function connection(string $key): Connection
    {
        return DB::connection($this->connectionNameOrFail($key));
    }

    /** @return array<string, mixed> */
    public function definition(string $key): array
    {
        return config("datasources.sources.{$key}", []);
    }

    /**
     * Every datasource with its status, for `php artisan cms:datasources` and
     * anything else that needs to report on them.
     *
     * @return array<int, array{key: string, label: string, connection: string, database: ?string, host: ?string, read_only: bool, configured: bool}>
     */
    public function all(): array
    {
        return array_map(function (string $key) {
            $connection = $this->definition($key)['connection'] ?? [];

            return [
                'key' => $key,
                'label' => $this->label($key),
                'connection' => $this->connectionName($key),
                'database' => $connection['database'] ?? null,
                'host' => $connection['host'] ?? null,
                'read_only' => $this->isReadOnly($key),
                'configured' => $this->isConfigured($key),
            ];
        }, $this->keys());
    }
}
