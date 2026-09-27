<?php

namespace App\Services\Stsc\Concerns;

use Illuminate\Database\Query\Builder;

/**
 * The one definition of "which STSC rows are in scope", shared by every service
 * over that datasource so they cannot drift apart.
 *
 * STSC is unlike the other fisheries datasources here: it holds no trips and no
 * individuals, only two already-aggregated national tables — one row per year
 * per WPPNRI. There is nothing to exclude, so scoping is just the two filters
 * every endpoint accepts.
 *
 * `WPPNRI` is an int in `data_armada` and a varchar in `data_produksi`. Both
 * MySQL and SQLite coerce across that boundary on comparison, so the filter is
 * passed as the string it arrived as and every value is cast to string on the
 * way out — a chart legend keyed '571' in one series and 571 in the other is
 * two legends.
 */
trait ScopesStscSeries
{
    /** One row per year per WPPNRI: fleet size and total tonnage. */
    protected function armada(): Builder
    {
        return $this->datasources->connection('stsc')->table('data_armada');
    }

    /** One row per year per WPPNRI per commodity: landed weight. */
    protected function produksi(): Builder
    {
        return $this->datasources->connection('stsc')->table('data_produksi');
    }

    protected function applyWpp(Builder $query, ?string $wpp): Builder
    {
        if (filled($wpp)) {
            $query->where('WPPNRI', $wpp);
        }

        return $query;
    }

    /** Inclusive at both ends: `sampai_tahun` is the last year a user expects to see. */
    protected function applyYears(Builder $query, ?int $dari, ?int $sampai): Builder
    {
        if ($dari !== null) {
            $query->where('tahun', '>=', $dari);
        }

        if ($sampai !== null) {
            $query->where('tahun', '<=', $sampai);
        }

        return $query;
    }

    /**
     * One line per WPPNRI, area codes in natural order.
     *
     * Points are emitted only for the years that carry a row: a gap here is a
     * year nobody reported, not a year with no fleet, and filling it with a
     * zero would draw a collapse that never happened. The `tahun` axis beside
     * the series says which years the selection covers in total.
     *
     * @param  array<string, array<int, array{tahun: int, nilai: int|float}>>  $points
     * @return array<int, array{wpp: string, titik: array<int, array{tahun: int, nilai: int|float}>}>
     */
    protected function toSeries(array $points): array
    {
        uksort($points, 'strnatcmp');

        $series = [];

        foreach ($points as $wpp => $titik) {
            $series[] = ['wpp' => (string) $wpp, 'titik' => $titik];
        }

        return $series;
    }
}
