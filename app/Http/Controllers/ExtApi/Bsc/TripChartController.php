<?php

namespace App\Http\Controllers\ExtApi\Bsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\BscTripChartRequest;
use App\Services\Bsc\BscTripChartService;
use Illuminate\Http\JsonResponse;

/**
 * BSC trip counts per collection period and per landing site.
 *
 * One endpoint rather than two, so both series always describe the same
 * filtered set of trips.
 */
class TripChartController extends ExternalApiController
{
    protected string $datasource = 'bsc';

    public function __invoke(BscTripChartRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:trip',
            fn () => app(BscTripChartService::class)->build(
                $request->tipeTanggal(),
                $request->filters(),
                $request->query('dari'),
                $request->query('sampai'),
            ),
        );
    }
}
