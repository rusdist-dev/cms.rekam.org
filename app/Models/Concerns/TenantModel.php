<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantNotResolvedException;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every content model (context.md §5.1).
 *
 * Two guarantees:
 *  - the model always uses the `tenant` connection, never the central one;
 *  - a query issued before a tenant is resolved throws instead of quietly
 *    hitting whatever database the connection happens to point at.
 *
 * The second is the important one. Without it a forgotten middleware turns into
 * one company's content being written into another's database, and nothing in
 * the response would say so.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';

    public function getConnectionName(): string
    {
        // A resolved tenant always sets this connection's database; an unset one
        // means we are about to query nothing in particular.
        if (! app(TenantManager::class)->hasTenant()) {
            throw new TenantNotResolvedException(
                'Query ke '.static::class.' dijalankan tanpa tenant aktif.'
            );
        }

        return 'tenant';
    }
}
