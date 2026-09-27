<?php

namespace App\Http\Controllers\ExtApi\Bsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\BscCatchChartRequest;
use App\Services\Bsc\BscCatchChartService;
use Illuminate\Http\JsonResponse;

/**
 * What the measured BSC catch is made of, by weight per species.
 *
 * A sample rather than a landing — see BscCatchChartService.
 */
class CatchChartController extends ExternalApiController
{
    protected string $datasource = 'bsc';

    public function __invoke(BscCatchChartRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:tangkapan',
            fn () => app(BscCatchChartService::class)->build(
                $request->filters(),
                $request->query('dari'),
                $request->query('sampai'),
            ),
        );
    }
}
