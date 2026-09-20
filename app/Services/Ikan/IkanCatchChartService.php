<?php

namespace App\Services\Ikan;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;

/**
 * Total catch per species, over a filtered set of trips.
 *
 * Reads `data_tangkapan_catch`, which records a weight per species per trip —
 * not `data_tangkapan_biologi`, which measures individual fish. The two carry
 * different taxonomies (61 families / 401 species here, 49 / 374 there), so
 * they are not interchangeable.
 */
class IkanCatchChartService
{
    /**
     * Accepted filters, reusing the dropdown chain's column definitions so the
     * chart and the dropdown that feeds it cannot disagree about what
     * `provinsi` means.
     *
     * `family` and `spesies` are not among them: this endpoint *is* the species
     * breakdown, and filtering it by species would leave one bar.
     */
    public const FILTERS = ['wppnri', 'provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_data', 'alat_tangkap'];

    /** Upstream stores no unit; kilograms, confirmed with the data owner. */
    public const UNIT = 'kg';

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
            'total_catch' => round(array_sum(array_column($species, 'total_catch')), 2),
            'per_spesies' => $species,
        ];
    }

    /**
     * Heaviest first, name as tie-break — the question this endpoint answers is
     * which species the catch is made of, and that is read from the top.
     *
     * Unpaginated: 401 species, and a chart wants them in one piece.
     *
     * Species with no recorded weight are left out entirely rather than
     * reported as zero; see the HAVING clause below.
     *
     * @return array<int, array{spesies: string, total_catch: float}>
     */
    private function bySpecies(array $filters, ?string $dari, ?string $sampai): array
    {
        return $this->catches($filters, $dari, $sampai)
            ->selectRaw('t.spesies as spesies, sum(t.total_catch) as total_catch')
            ->groupBy('t.spesies')
            // A species whose every row has no recorded weight sums to NULL,
            // which would surface as 0 kg — a claim that it was weighed and
            // found empty. It was not weighed at all, so it has no place in a
            // chart of weights.
            ->havingRaw('sum(t.total_catch) is not null')
            ->orderByDesc('total_catch')
            ->orderBy('t.spesies')
            ->get()
            ->map(fn ($row) => [
                'spesies' => (string) $row->spesies,
                'total_catch' => round((float) $row->total_catch, 2),
            ])
            ->all();
    }

    /**
     * Catch rows joined to the trip that landed them.
     *
     * Both joins are 1:1 from the catch row's side — every one of the 17.900
     * rows has exactly one trip and one operational record, checked against the
     * live database — so summing `total_catch` over this join counts each
     * weight once.
     *
     * The gear filter deliberately reads `o.alat_tangkap_utama`, the trip's
     * main gear, and not the catch table's own column of the same name. The two
     * disagree on 1.110 of 17.900 rows, and they do not even share a
     * vocabulary: the catch table knows two gears the operational table does
     * not, and is missing five that it has. Since `opsi/alat-tangkap` lists the
     * operational values, filtering the other column would make five dropdown
     * options return nothing. `alat_tangkap=X` therefore means "landed by trips
     * whose main gear was X", not "caught with X".
     */
    private function catches(array $filters, ?string $dari, ?string $sampai): Builder
    {
        $query = $this->datasources->connection('ikan')
            ->table('data_tangkapan_catch as t')
            ->join('data_identitas_trip as i', 'i.id_trip', '=', 't.id_trip')
            ->join('data_operasional_trip as o', 'o.id_trip', '=', 'i.id_trip');

        foreach ($filters as $key => $value) {
            if (filled($value) && isset(IkanFilterService::CHAIN[$key])) {
                $query->where(IkanFilterService::CHAIN[$key], $value);
            }
        }

        // Inclusive at both ends: `sampai` is the last day a user expects to
        // see, not the first day they expect excluded.
        if ($dari !== null) {
            $query->where('i.tanggal_pendataan', '>=', $dari);
        }

        if ($sampai !== null) {
            $query->where('i.tanggal_pendataan', '<=', $sampai);
        }

        return $query;
    }
}
