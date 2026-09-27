<?php

namespace App\Http\Controllers\ExtApi\Stsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\StscArmadaChartRequest;
use App\Services\Stsc\StscArmadaChartService;
use Illuminate\Http\JsonResponse;

/**
 * Fleet size and fleet tonnage per WPPNRI per year.
 *
 * Both series in one response: they are read against each other — a fleet that
 * shrinks while its tonnage grows is the whole point of the chart — and two
 * calls could answer from two different filters.
 */
class ArmadaChartController extends ExternalApiController
{
    protected string $datasource = 'stsc';

    public function __invoke(StscArmadaChartRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:armada',
            fn () => app(StscArmadaChartService::class)->build(
                $request->wpp(),
                $request->dariTahun(),
                $request->sampaiTahun(),
            ),
        );
    }
}
