<?php

namespace App\Models\Concerns;

use App\Exceptions\ReadOnlyDatasourceException;
use App\Services\DatasourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base class for every model that reads an external database (App\Models\External\*).
 *
 * The third of the three model families in this codebase:
 *  - central models (User, Tenant) — default `mysql` connection;
 *  - TenantModel — the per-company content connection;
 *  - ExternalModel — a database owned by another system entirely.
 *
 * A subclass names its datasource, not its connection:
 *
 *     class Pasien extends ExternalModel
 *     {
 *         protected string $datasource = 'simrs';
 *         protected $table = 'm_pasien';
 *     }
 *
 * Two guarantees, mirroring TenantModel's:
 *  - the model always resolves its connection through DatasourceRegistry, so a
 *    key that is unregistered or missing from .env throws by name instead of
 *    querying whatever the driver would have defaulted to;
 *  - on a `read_only` datasource, a write fails here — naming the model — rather
 *    than at the connection guard or, worse, not at all.
 */
abstract class ExternalModel extends Model
{
    /** Key in config('datasources.sources'). Every subclass must set it. */
    protected string $datasource = '';

    /**
     * Foreign schemas are not ours to shape and rarely carry Laravel's
     * created_at/updated_at pair. Opting in is one line in the subclass;
     * having every SELECT fail on a missing column is not worth the default.
     */
    public $timestamps = false;

    public function getConnectionName(): string
    {
        if ($this->datasource === '') {
            throw new LogicException(static::class.' belum menetapkan $datasource.');
        }

        return app(DatasourceRegistry::class)->connectionNameOrFail($this->datasource);
    }

    public function datasourceKey(): string
    {
        return $this->datasource;
    }

    public function isReadOnly(): bool
    {
        return $this->datasource !== ''
            && app(DatasourceRegistry::class)->isReadOnly($this->datasource);
    }

    protected function performInsert(Builder $query): bool
    {
        $this->assertWritable('menyimpan');

        return parent::performInsert($query);
    }

    protected function performUpdate(Builder $query): bool
    {
        $this->assertWritable('memperbarui');

        return parent::performUpdate($query);
    }

    /**
     * No return type, deliberately: Eloquent's own declaration has none, and
     * narrowing it here makes SoftDeletes — which overrides this method —
     * fatally incompatible with any subclass that uses the trait. Such a
     * subclass loses this check (a trait method wins over an inherited one),
     * which is why the connection-level guard in DatasourceServiceProvider is
     * the backstop rather than the belt-and-braces.
     */
    protected function performDeleteOnModel()
    {
        $this->assertWritable('menghapus');

        return parent::performDeleteOnModel();
    }

    private function assertWritable(string $action): void
    {
        if ($this->isReadOnly()) {
            throw new ReadOnlyDatasourceException(
                $this->datasource,
                $action.' '.static::class
            );
        }
    }
}
