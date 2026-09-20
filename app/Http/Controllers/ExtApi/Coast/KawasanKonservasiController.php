<?php

namespace App\Http\Controllers\ExtApi\Coast;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Resources\Ext\Coast\KawasanKonservasiResource;
use App\Models\External\Coast\KawasanKonservasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * COAST's marine protected areas — a reference table, not survey data.
 *
 * No `verified` filter applies here: unlike everything else on this surface,
 * these rows do not belong to a data-collection form. Every area is listed,
 * including the five no blue-carbon record references yet, because a reference
 * list that hides entries is worse than useless to whoever is matching against
 * it.
 */
class KawasanKonservasiController extends ExternalApiController
{
    protected string $datasource = 'coast';

    /**
     * Unpaginated, like the village list: eleven rows that a caller is matching
     * its own records against wants in one piece, not in pages.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'kawasan-konservasi', fn () => KawasanKonservasiResource::collection(
            KawasanKonservasi::query()
                // lower() rather than a plain ORDER BY: one area upstream is
                // stored in full capitals, and whether that sorts among its
                // peers or ahead of them would otherwise be decided by the
                // foreign database's collation.
                ->orderByRaw('lower(nama_kawasan)')
                ->orderBy('id')
                ->get()
        )->resolve());
    }
}
