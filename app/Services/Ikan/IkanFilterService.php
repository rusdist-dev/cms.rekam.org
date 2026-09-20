<?php

namespace App\Services\Ikan;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * The option lists behind IKAN's chained filter dropdowns.
 *
 * One constant defines the whole chain. A list may be narrowed by every level
 * above it and by none below, so CHAIN says both what each endpoint lists and
 * which filters it accepts — there is no second place for the two to disagree,
 * and adding a level later is one line here rather than an edit in four files.
 */
class IkanFilterService
{
    /**
     * Ordered: each key's options can be filtered by every key before it.
     *
     * The alias says which table a level lives on — `i` the trip, `o` its
     * operational record, `b` its biological catch measurements — and that is
     * what decides which joins a given query needs.
     */
    public const CHAIN = [
        'wppnri' => 'o.WPPNRI',
        'provinsi' => 'i.provinsi',
        'kabupaten' => 'i.kabupaten',
        'lokasi_pendaratan' => 'i.lokasi_pendaratan',
        'jenis_data' => 'i.jenis_data',
        'alat_tangkap' => 'o.alat_tangkap_utama',
        'family' => 'b.family',
        'spesies' => 'b.spesies',
    ];

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * Distinct values for one list, narrowed by whichever applicable filters
     * were supplied.
     *
     * A filter that does not apply to this list is ignored rather than
     * rejected: a chained dropdown resets the levels below whatever changed,
     * and the client is free to keep sending the whole filter state at every
     * level instead of trimming it per request.
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

        $query = $this->trips([$column, ...array_keys($applied)]);

        foreach ($applied as $filterColumn => $value) {
            $query->where($filterColumn, $value);
        }

        return $query
            ->groupBy($column)
            ->orderBy($column)
            // count(distinct) rather than count(*): a trip has many biological
            // measurements, so once that table is joined a plain count would
            // report rows of fish, not trips. The distinct count means the
            // same thing at every level, joined or not.
            ->selectRaw("{$column} as value, count(distinct i.id_trip) as jumlah_trip")
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                // What the option is worth selecting: how many trips it still
                // covers under the filters already chosen.
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

    /**
     * Trips, joined to whichever tables the columns in play actually need.
     *
     * The operational join is always there and is safe: both tables key on
     * `id_trip`, hold the same number of rows, and neither has an orphan.
     *
     * The biological join is not. That table covers 9.247 of the 10.619 trips,
     * so joining it unconditionally would quietly drop 1.372 trips from lists
     * that have nothing to do with species — a province would report fewer
     * trips than it has. It is added only when a family or species column is
     * being listed or filtered on, where excluding a trip that recorded no
     * measurements is the correct answer rather than a loss.
     *
     * @param  array<int, string>  $columns  table-qualified columns in play
     */
    private function trips(array $columns): Builder
    {
        $query = $this->datasources->connection('ikan')
            ->table('data_identitas_trip as i')
            ->join('data_operasional_trip as o', 'o.id_trip', '=', 'i.id_trip');

        foreach ($columns as $column) {
            if (str_starts_with($column, 'b.')) {
                return $query->join('data_tangkapan_biologi as b', 'b.id_trip', '=', 'i.id_trip');
            }
        }

        return $query;
    }
}
