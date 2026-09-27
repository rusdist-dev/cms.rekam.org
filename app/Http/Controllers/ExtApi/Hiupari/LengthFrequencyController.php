<?php

namespace App\Http\Controllers\ExtApi\Hiupari;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Http\Requests\ExtApi\HiupariLengthFrequencyRequest;
use App\Services\Hiupari\HiupariLengthFrequencyService;
use Illuminate\Http\JsonResponse;

/**
 * Length-frequency histogram for sharks and rays, filtered by species.
 *
 * Two things in the response are worth reading twice. `jumlah_tanpa_ukuran`:
 * these animals are measured five different ways and no column is filled in
 * for all of them, so a histogram can legitimately be drawn from a small
 * fraction of the individuals in scope. And `indikator.lm`, which is null
 * unless the request narrowed to males — clasper maturity is a male character
 * and says nothing about the females in the sample.
 */
class LengthFrequencyController extends ExternalApiController
{
    protected string $datasource = 'hiupari';

    public function __invoke(HiupariLengthFrequencyRequest $request): JsonResponse
    {
        return $this->dataResponse(
            $request,
            'grafik:frekuensi-panjang',
            fn () => app(HiupariLengthFrequencyService::class)->build(
                $request->spesies(),
                $request->jenisKelamin(),
                $request->jenisUkuran(),
                $request->selangKelas(),
                $request->kematanganMatang(),
            ),
        );
    }
}
