<?php

namespace App\Http\Controllers\ExtApi\Stsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\StscProduksiChartRequest;
use App\Services\Stsc\StscProduksiChartService;
use Illuminate\Http\JsonResponse;

/**
 * Landed weight per commodity, one line per WPPNRI.
 *
 * The response keeps the same shape whether or not `wpp` narrows it, so a
 * client draws one chart per commodity either way rather than branching on
 * which filter it happened to send.
 */
class ProduksiChartController extends ExternalApiController
{
    protected string $datasource = 'stsc';

    public function __invoke(StscProduksiChartRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:produksi',
            fn () => app(StscProduksiChartService::class)->build(
                $request->wpp(),
                $request->komoditas(),
                $request->dariTahun(),
                $request->sampaiTahun(),
            ),
        );
    }
}
