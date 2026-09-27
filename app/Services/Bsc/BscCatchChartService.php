<?php

namespace App\Services\Bsc;

use App\Services\DatasourceRegistry;

/**
 * Catch composition per species, by the weight of the crabs measured.
 *
 * Unlike IKAN, BSC has no per-species catch table. `data_trip` carries
 * whole-trip totals with no species breakdown, so the only figure that can be
 * attributed to a species is `data_biologi.bobot` — the weight of the
 * individuals actually measured.
 *
 * **That is a sample, not the landing.** Whole-trip landings across the same
 * scope total about 22.115 in `total_tangkapan_utama`, while the measured crabs
 * total about 6,7 million in `bobot`, which are different units as well as
 * different populations. Read this endpoint as "what the measured catch was
 * made of", never as "how much was landed".
 */
class BscCatchChartService
{
    use Concerns\ScopesBscTrips;

    /**
     * `spesies` is not among them: this endpoint *is* the species breakdown,
     * and filtering it by species would leave one bar.
     */
    public const FILTERS = [
        'provinsi', 'kabupaten', 'lokasi_pendaratan',
        'jenis_pendataan', 'alat_tangkap', 'jenis_tangkapan',
    ];

    /**
     * Upstream records no unit. A mean of about 145 over 46.093 crabs with a
     * maximum of 1.800 is grams; stated here so a reader cannot mistake it,
     * and worth confirming with the data owner.
     */
    public const UNIT = 'gram';

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function build(array $filters, ?string $dari, ?string $sampai): array
    {
        $species = $this->bySpecies($filters, $dari, $sampai);

        return [
            'filter' => [
                'dari' => $dari,
                'sampai' => $sampai,
            ] + array_map(fn ($value) => (string) $value, $filters),
            'unit' => self::UNIT,
            'total_bobot' => round(array_sum(array_column($species, 'total_bobot')), 2),
            'per_spesies' => $species,
        ];
    }

    /**
     * Heaviest first, name as tie-break. Seven species, so unpaginated.
     *
     * @return array<int, array{spesies: string, total_bobot: float}>
     */
    private function bySpecies(array $filters, ?string $dari, ?string $sampai): array
    {
        $query = $this->applyBscFilters(
            $this->scopedTrips(['b.spesies']),
            $filters,
            $dari,
            $sampai,
        );

        return $query
            ->selectRaw('b.spesies as spesies, sum(b.bobot) as total_bobot')
            ->groupBy('b.spesies')
            // 728 crabs were measured but not weighed. A species whose every
            // individual is in that position sums to NULL, which would surface
            // as 0 gram — a claim that it was weighed and found empty.
            ->havingRaw('sum(b.bobot) is not null')
            ->orderByDesc('total_bobot')
            ->orderBy('b.spesies')
            ->get()
            ->map(fn ($row) => [
                'spesies' => (string) $row->spesies,
                'total_bobot' => round((float) $row->total_bobot, 2),
            ])
            ->all();
    }
}
