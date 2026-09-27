<?php

namespace App\Services\Bsc\Concerns;

use App\Services\Bsc\BscFilterService;
use Illuminate\Database\Query\Builder;

/**
 * The one definition of "which BSC rows are in scope", shared by every service
 * over that datasource so they cannot drift apart.
 *
 * Two rules live here and nowhere else:
 *
 *  - only `trip_nontrip = 'TRIP'` records count. `data_trip` also holds 40
 *    non-trip records whose measurements live in `data_nontrip`, a table these
 *    endpoints deliberately ignore; 669 rows of `data_biologi` hang off those
 *    records and are excluded with them.
 *
 *  - the crab table is joined only when a `b.` column is actually in play.
 *    It holds many rows per trip, so joining it for a list that has nothing to
 *    do with individuals would turn every trip count into a crab count, and
 *    would drop the trips on which nothing was measured.
 */
trait ScopesBscTrips
{
    /**
     * Trips in scope, joined to the crab table when the columns in play need it.
     *
     * @param  array<int, string>  $columns  table-qualified columns in play
     */
    protected function scopedTrips(array $columns = []): Builder
    {
        $query = $this->datasources->connection('bsc')
            ->table('data_trip as t')
            ->where('t.trip_nontrip', BscFilterService::TRIP);

        foreach ($columns as $column) {
            if (str_starts_with($column, 'b.')) {
                return $query->join('data_biologi as b', 'b.id_trip', '=', 't.id_trip');
            }
        }

        return $query;
    }

    /**
     * Applies the chain filters and the collection-date range.
     *
     * @param  array<string, string>  $filters
     */
    protected function applyBscFilters(Builder $query, array $filters, ?string $dari, ?string $sampai): Builder
    {
        foreach ($filters as $key => $value) {
            if (filled($value) && isset(BscFilterService::CHAIN[$key])) {
                $query->where(BscFilterService::CHAIN[$key], $value);
            }
        }

        // Inclusive at both ends: `sampai` is the last day a user expects to
        // see, not the first day they expect excluded.
        if ($dari !== null) {
            $query->where('t.tanggal', '>=', $dari);
        }

        if ($sampai !== null) {
            $query->where('t.tanggal', '<=', $sampai);
        }

        return $query;
    }
}
