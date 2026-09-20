<?php

namespace App\Http\Controllers\ExtApi\Ikan;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\IkanTripChartRequest;
use App\Services\Ikan\IkanTripChartService;
use Illuminate\Http\JsonResponse;

/**
 * Trip counts for the chart pair: per collection period and per landing site.
 *
 * One endpoint rather than two, because both series must describe the same
 * filtered set of trips. Split across two endpoints, a client that forgot to
 * pass one filter to one of them would get two charts that silently disagree.
 */
class TripChartController extends ExternalApiController
{
    protected string $datasource = 'ikan';

    public function __invoke(IkanTripChartRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:trip',
            fn () => app(IkanTripChartService::class)->build(
                $request->tipeTanggal(),
                $request->filters(),
                $request->query('dari'),
                $request->query('sampai'),
            ),
        );
    }
}
