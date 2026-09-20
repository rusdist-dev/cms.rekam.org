<?php

namespace App\Http\Controllers\ExtApi\Ikan;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\IkanLengthFrequencyRequest;
use App\Services\Ikan\IkanLengthFrequencyService;
use Illuminate\Http\JsonResponse;

/**
 * Length-frequency histogram with the indicators it is read against.
 *
 * Lc comes back computed from the distribution; Lm has to be supplied, because
 * length at maturity is a property of the species rather than of this sample,
 * and nothing in the IKAN database records it.
 */
class LengthFrequencyController extends ExternalApiController
{
    protected string $datasource = 'ikan';

    public function __invoke(IkanLengthFrequencyRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:frekuensi-panjang',
            fn () => app(IkanLengthFrequencyService::class)->build(
                $request->filters(),
                $request->query('dari'),
                $request->query('sampai'),
                $request->tipePanjang(),
                $request->selangKelas(),
                $request->lm(),
            ),
        );
    }
}
