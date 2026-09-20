<?php

namespace App\Services\Coast;

use App\Models\External\Coast\CoastForm;
use App\Models\External\Coast\WilayahBoundary;
use Illuminate\Support\Collection;

/**
 * The villages that have verified COAST data, with the point the map drops a
 * marker on.
 *
 * Deliberately unpaginated: there are nineteen of them, a map needs all the
 * markers at once, and paginating would mean a client drawing a map in pages.
 * If that ever stops being true it is a filter that is wanted here, not a page.
 */
class CoastRegionService
{
    public function __construct(private readonly CoastWilayahResolver $wilayah) {}

    public function list(): array
    {
        $forms = $this->verifiedForms();
        $kodes = $forms->pluck('desa_kode')->unique();

        $points = $this->points($kodes);
        $regions = $this->wilayah->forDesaCodes($kodes);

        return $forms
            ->groupBy('desa_kode')
            ->map(fn (Collection $group, string $kode) => $this->village($group, $kode, $points, $regions))
            // By village name alone — the map's list is read as an index of
            // villages, not as an administrative tree.
            ->sortBy(fn (array $village) => mb_strtolower((string) $village['desa']))
            ->values()
            ->all();
    }

    /**
     * Every verified form's identity columns, newest first.
     *
     * One query and the grouping done here, rather than GROUP BY in SQL, for a
     * reason the data forces: the same `desa_kode` carries different spellings
     * across forms ("Teluk Awur" and "Telukawur"; one form filed under the
     * wrong kecamatan). Grouping by the name columns splits a village into two
     * rows — two markers on one spot, each with part of the count. The code is
     * the identity; the names come from `wilayah` (CoastWilayahResolver), and
     * the form's own name columns survive here only as a last-resort fallback.
     */
    private function verifiedForms(): Collection
    {
        return CoastForm::query()
            ->verified()
            ->whereNotNull('desa_kode')
            ->where('desa_kode', '!=', '')
            ->orderByDesc('tanggal_pendataan')
            ->orderByDesc('id')
            ->get([
                'id', 'desa_kode', 'desa', 'kecamatan', 'kecamatan_kode',
                'kabupaten_kota', 'kabupaten_kode', 'provinsi', 'provinsi_kode',
                'tanggal_pendataan',
            ]);
    }

    private function village(Collection $forms, string $kode, Collection $points, Collection $regions): array
    {
        $latest = $forms->first();

        $region = $this->wilayah->withFallback(
            $regions->get($kode) ?? [],
            [
                'desa' => $latest->desa,
                'kecamatan' => $latest->kecamatan,
                'kabupaten_kota' => $latest->kabupaten_kota,
                'provinsi' => $latest->provinsi,
            ],
        );

        return $region + [
            // Null rather than 0,0 when a village has no boundary row: a marker
            // at null island is worse than no marker, because it looks like data.
            'koordinat' => $points->get($kode),
            'jumlah_form' => $forms->count(),
            'pendataan_terakhir' => $latest->tanggal_pendataan?->toDateString(),
        ];
    }

    /** @return Collection<string, array{lat: float, lng: float}|null> */
    private function points(Collection $kodes): Collection
    {
        return WilayahBoundary::query()
            ->whereIn('kode', $kodes)
            ->get(['kode', 'lat', 'lng'])
            ->mapWithKeys(fn (WilayahBoundary $b) => [
                $b->kode => $b->lat === null || $b->lng === null
                    ? null
                    : ['lat' => $b->lat, 'lng' => $b->lng],
            ]);
    }
}
