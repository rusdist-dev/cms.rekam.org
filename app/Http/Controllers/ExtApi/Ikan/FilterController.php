<?php

namespace App\Http\Controllers\ExtApi\Ikan;

use App\Http\Controllers\ExtApi\ExternalApiController;
use App\Services\Ikan\IkanFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Option lists for IKAN's chained filter dropdowns.
 *
 * Each level may be narrowed by every level above it:
 *
 *   wppnri → provinsi → kabupaten → lokasi_pendaratan → jenis_data
 *          → alat_tangkap → family → spesies
 *
 * A client picking `provinsi=ACEH` asks the next endpoint for kabupaten with
 * that filter attached and gets only the kabupaten that still have trips —
 * never a dropdown whose every choice leads to an empty result.
 *
 * Query parameters use underscores (`lokasi_pendaratan`) even though the paths
 * use hyphens (`lokasi-pendaratan`), matching the rest of this API surface.
 */
class FilterController extends ExternalApiController
{
    protected string $datasource = 'ikan';

    public function wppnri(Request $request): JsonResponse
    {
        return $this->options($request, 'wppnri');
    }

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

    public function jenisData(Request $request): JsonResponse
    {
        return $this->options($request, 'jenis_data');
    }

    public function alatTangkap(Request $request): JsonResponse
    {
        return $this->options($request, 'alat_tangkap');
    }

    public function family(Request $request): JsonResponse
    {
        return $this->options($request, 'family');
    }

    public function spesies(Request $request): JsonResponse
    {
        return $this->options($request, 'spesies');
    }

    /**
     * Unpaginated, like every other list on this surface: the longest of these
     * is 374 species, and a dropdown wants all of its options at once.
     */
    private function options(Request $request, string $list): JsonResponse
    {
        $filters = app(IkanFilterService::class)->accepts($list);

        return $this->dataResponse(
            $request,
            "opsi:{$list}",
            fn () => app(IkanFilterService::class)->options(
                $list,
                $request->only(array_keys($filters)),
            ),
        );
    }
}
