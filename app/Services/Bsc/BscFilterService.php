<?php

namespace App\Services\Bsc;

use App\Services\DatasourceRegistry;
use InvalidArgumentException;

/**
 * The option lists behind BSC's chained filter dropdowns, and the definitions
 * every other BSC service reads its columns from.
 *
 * Same shape as IkanFilterService, over a database that is not the same shape
 * at all: BSC has no WPPNRI, no per-species catch table, and no family — a crab
 * is measured by carapace width, sex and gonad stage rather than by length and
 * length type.
 */
class BscFilterService
{
    use Concerns\ScopesBscTrips;

    /**
     * The only value of `trip_nontrip` these endpoints read; see
     * Concerns\ScopesBscTrips for why the other one is excluded.
     */
    public const TRIP = 'TRIP';

    /**
     * Normalises the four vocabularies upstream uses for two sexes.
     *
     * JANTAN/M/L and BETINA/F/P are the same two values recorded by different
     * enumerators; 83 rows carry something else again (blank, or "2") and
     * become null rather than being forced into one of them.
     *
     * Lives here rather than on the trait because PHP 8.1 has no trait
     * constants.
     */
    public const SEX_SQL = "case when upper(trim(b.jenis_kelamin)) in ('JANTAN', 'M', 'L') then 'JANTAN'"
        ." when upper(trim(b.jenis_kelamin)) in ('BETINA', 'F', 'P') then 'BETINA' else null end";

    public const SEXES = ['JANTAN', 'BETINA'];

    /**
     * Ordered: each key's options can be filtered by every key before it.
     *
     * The alias says which table a level lives on — `t` the trip, `b` the
     * individual crabs measured on it — and that is what decides which joins a
     * given query needs.
     */
    public const CHAIN = [
        'provinsi' => 't.provinsi',
        'kabupaten' => 't.kabupaten',
        'lokasi_pendaratan' => 't.lokasi_pendaratan',
        'jenis_pendataan' => 't.jenis_pendataan',
        'alat_tangkap' => 't.alat_tangkap',
        'jenis_tangkapan' => 't.jenis_tangkapan',
        'spesies' => 'b.spesies',
    ];

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * Distinct values for one list, narrowed by whichever applicable filters
     * were supplied.
     *
     * A filter that does not apply to this list is ignored rather than
     * rejected, so a client may keep its whole filter state and send it at
     * every level.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{value: string, jumlah_trip: int}>
     */
    public function options(string $list, array $filters = []): array
    {
        $column = self::CHAIN[$list]
            ?? throw new InvalidArgumentException("Daftar filter '{$list}' tidak dikenal.");

        $applied = [];

        foreach ($this->accepts($list) as $key => $filterColumn) {
            if (filled($filters[$key] ?? null)) {
                $applied[$filterColumn] = $filters[$key];
            }
        }

        $query = $this->scopedTrips([$column, ...array_keys($applied)]);

        foreach ($applied as $filterColumn => $value) {
            $query->where($filterColumn, $value);
        }

        return $query
            ->groupBy($column)
            ->orderBy($column)
            // count(distinct) rather than count(*): a trip has many crabs
            // measured on it, so once that table is joined a plain count would
            // report individuals, not trips.
            ->selectRaw("{$column} as value, count(distinct t.id_trip) as jumlah_trip")
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'jumlah_trip' => (int) $row->jumlah_trip,
            ])
            ->all();
    }

    /**
     * The filter keys a list accepts — every level above it in the chain.
     *
     * @return array<string, string> filter key => column
     */
    public function accepts(string $list): array
    {
        $position = array_search($list, array_keys(self::CHAIN), true);

        return $position === false ? [] : array_slice(self::CHAIN, 0, $position, true);
    }
}
