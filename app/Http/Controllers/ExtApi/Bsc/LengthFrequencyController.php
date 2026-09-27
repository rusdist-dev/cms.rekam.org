<?php

namespace App\Http\Controllers\ExtApi\Bsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\BscLengthFrequencyRequest;
use App\Services\Bsc\BscLengthFrequencyService;
use Illuminate\Http\JsonResponse;

/**
 * Carapace-width histogram with Lc and Lm.
 *
 * Both indicators are computed here. BSC records a gonad stage per individual,
 * so unlike the IKAN chart it does not have to be told where maturity begins —
 * only which stage counts as mature, which `tkg_matang` decides.
 */
class LengthFrequencyController extends ExternalApiController
{
    protected string $datasource = 'bsc';

    public function __invoke(BscLengthFrequencyRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:frekuensi-lebar',
            fn () => app(BscLengthFrequencyService::class)->build(
                $request->filters(),
                $request->query('dari'),
                $request->query('sampai'),
                $request->jenisKelamin(),
                $request->selangKelas(),
                $request->tkgMatang(),
            ),
        );
    }
}
