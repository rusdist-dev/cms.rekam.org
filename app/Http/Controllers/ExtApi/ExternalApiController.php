<?php

namespace App\Http\Controllers\ExtApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ExtApi\Concerns\PaginatesExternalJson;
use App\Services\DatasourceRegistry;
use LogicException;

/**
 * Base controller for /api/v1/ext/*. A subclass names its datasource once:
 *
 *     class PasienController extends ExternalApiController
 *     {
 *         protected string $datasource = 'simrs';
 *     }
 *
 * and gets the cache key, the connection helper, and the {data, meta} envelope
 * scoped to it — so no endpoint has to repeat a connection name, which is how a
 * copy-pasted controller ends up reading the wrong database.
 */
abstract class ExternalApiController extends Controller
{
    use PaginatesExternalJson;

    /** Key in config('datasources.sources'). */
    protected string $datasource = '';

    public function __construct()
    {
        if ($this->datasource === '') {
            throw new LogicException(static::class.' belum menetapkan $datasource.');
        }
    }

    /** Query builder on this controller's datasource, for schemas without a model. */
    protected function table(string $table): \Illuminate\Database\Query\Builder
    {
        return app(DatasourceRegistry::class)->connection($this->datasource)->table($table);
    }
}
