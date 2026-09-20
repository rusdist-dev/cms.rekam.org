<?php

namespace App\Http\Controllers\ExtApi\Coast;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Services\Coast\CoastSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * COAST totals across every village — the headline panel, with no `{desa_kode}`
 * to scope it.
 *
 * Same rules as a village summary: verified forms only, and the current year
 * against the one before it.
 */
class StatistikController extends ExternalApiController
{
    protected string $datasource = 'coast';

    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'statistik', fn () => app(CoastSummaryService::class)->overall());
    }
}
