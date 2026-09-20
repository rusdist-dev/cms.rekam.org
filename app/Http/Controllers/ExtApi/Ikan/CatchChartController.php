<?php

namespace App\Http\Controllers\ExtApi\Ikan;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\IkanCatchChartRequest;
use App\Services\Ikan\IkanCatchChartService;
use Illuminate\Http\JsonResponse;

/**
 * Total catch per species — what the landed weight is made of.
 *
 * Unlike the trip chart, this one accepts `alat_tangkap`: summing weights is
 * unaffected by a trip appearing in several catch rows, whereas counting trips
 * would be.
 */
class CatchChartController extends ExternalApiController
{
    protected string $datasource = 'ikan';

    public function __invoke(IkanCatchChartRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:tangkapan',
            fn () => app(IkanCatchChartService::class)->build(
                $request->filters(),
                $request->query('dari'),
                $request->query('sampai'),
            ),
        );
    }
}
