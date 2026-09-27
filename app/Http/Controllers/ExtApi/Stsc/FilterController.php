<?php

namespace App\Http\Controllers\ExtApi\Stsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Services\Stsc\StscFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Option lists for STSC's two filters.
 *
 * Not a chain, unlike IKAN and BSC: WPPNRI and commodity are two axes of the
 * same grid rather than a hierarchy. `opsi/komoditas` still accepts `wpp`,
 * because a caller who has picked an area wants the commodities landed there.
 */
class FilterController extends ExternalApiController
{
    protected string $datasource = 'stsc';

    /** Unpaginated: there are eleven areas and eleven commodities. */
    public function wpp(Request $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'opsi:wpp',
            fn () => app(StscFilterService::class)->wpp(),
        );
    }

    public function komoditas(Request $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'opsi:komoditas',
            fn () => app(StscFilterService::class)->komoditas($request->query('wpp')),
        );
    }
}
