<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when content is queried without an active tenant.
 *
 * This is deliberately fatal rather than a fallback to the default connection:
 * a silent fallback is exactly how one company's content ends up written into
 * the other company's database (context.md §5.3).
 */
class TenantNotResolvedException extends RuntimeException
{
    public function __construct(string $message = 'Tenant belum ditentukan untuk permintaan ini.')
    {
        parent::__construct($message);
    }

    public function render(): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $message = 'Company aktif belum ditentukan. Silakan pilih company terlebih dahulu.';

        if (request()->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        return response(view('errors.tenant-not-resolved', ['message' => $message]), 409);
    }
}
