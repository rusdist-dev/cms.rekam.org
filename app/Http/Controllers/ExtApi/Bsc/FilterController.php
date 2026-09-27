<?php

namespace App\Http\Controllers\ExtApi\Bsc;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Services\Bsc\BscFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Option lists for BSC's chained filter dropdowns.
 *
 *   provinsi -> kabupaten -> lokasi_pendaratan -> jenis_pendataan
 *            -> alat_tangkap -> jenis_tangkapan -> spesies
 *
 * Seven levels rather than IKAN's eight: BSC records no WPPNRI, and its crab
 * table carries no family — only a species.
 */
class FilterController extends ExternalApiController
{
    protected string $datasource = 'bsc';

    public function provinsi(Request $request): JsonResponse
    {
        return $this->options($request, 'provinsi');
    }

    public function kabupaten(Request $request): JsonResponse
    {
        return $this->options($request, 'kabupaten');
    }

    public function lokasiPendaratan(Request $request): JsonResponse
    {
        return $this->options($request, 'lokasi_pendaratan');
    }

    public function jenisPendataan(Request $request): JsonResponse
    {
        return $this->options($request, 'jenis_pendataan');
    }

    public function alatTangkap(Request $request): JsonResponse
    {
        return $this->options($request, 'alat_tangkap');
    }

    public function jenisTangkapan(Request $request): JsonResponse
    {
        return $this->options($request, 'jenis_tangkapan');
    }

    public function spesies(Request $request): JsonResponse
    {
        return $this->options($request, 'spesies');
    }

    /** Unpaginated: the longest of these lists is ten landing sites. */
    private function options(Request $request, string $list): JsonResponse
    {
        $accepts = app(BscFilterService::class)->accepts($list);

        return $this->dataResponse(
            $request,
            "opsi:{$list}",
            fn () => app(BscFilterService::class)->options($list, $request->only(array_keys($accepts))),
        );
    }
}
