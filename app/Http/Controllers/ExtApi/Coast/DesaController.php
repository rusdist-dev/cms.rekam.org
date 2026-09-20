<?php

namespace App\Http\Controllers\ExtApi\Coast;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Services\Coast\CoastRegionService;
use App\Services\Coast\CoastSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two endpoints the COAST map is built from: the villages to drop markers
 * on, and the panel that opens when one is clicked.
 *
 * Both read only `verified` forms. A draft or a rejected submission is an
 * enumerator's working material, and the same rule `published` enforces for
 * CMS content applies here (context.md §4.10).
 */
class DesaController extends ExternalApiController
{
    protected string $datasource = 'coast';

    /**
     * Unpaginated by design — a map needs every marker at once, and there are
     * nineteen of them. See CoastRegionService.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'desa', fn () => app(CoastRegionService::class)->list());
    }

    /** {kode} is the wilayah code (`desa_kode`), not the form id. */
    public function show(Request $request, string $kode): JsonResponse
    {
        return $this->dataResponse(
            $request,
            "desa:{$kode}",
            fn () => app(CoastSummaryService::class)->forDesa($kode),
        );
    }
}
