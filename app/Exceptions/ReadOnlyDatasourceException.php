<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when anything tries to write to a datasource declared `read_only`.
 *
 * The database user for such a source should only hold SELECT anyway, but that
 * is a server-side setting nobody in this repository can see or test. This is
 * the half we control: the write is refused here, in a stack trace that points
 * at our own code, instead of surfacing as an opaque permission error from a
 * database owned by another team — or, on a misconfigured grant, succeeding.
 */
class ReadOnlyDatasourceException extends RuntimeException
{
    public function __construct(public readonly string $key, string $statement = '')
    {
        $detail = $statement !== '' ? " Perintah ditolak: {$statement}." : '';

        parent::__construct("Datasource '{$key}' bersifat read-only.{$detail}");
    }

    public function render(): JsonResponse
    {
        // 500, not 4xx: the caller did nothing wrong, our code did.
        return response()->json([
            'message' => 'Operasi tulis tidak diizinkan pada sumber data ini.',
        ], 500);
    }
}
