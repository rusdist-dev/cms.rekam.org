<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when code asks for an external datasource that is unknown to
 * config/datasources.php, or that is known but has no database name in .env.
 *
 * Same reasoning as TenantNotResolvedException: connecting anyway would mean
 * running another system's queries against whatever database the driver
 * happened to default to. A 503 naming the datasource is the useful answer —
 * this is an operator/deployment problem, not a caller problem.
 */
class DatasourceNotConfiguredException extends RuntimeException
{
    public function __construct(public readonly string $key, string $message = '')
    {
        parent::__construct($message ?: "Datasource '{$key}' belum dikonfigurasi.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 503);
    }
}
