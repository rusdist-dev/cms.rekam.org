<?php

namespace App\Services\Ikan;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Two series over the same filtered set of trips: how many were collected in
 * each period, and how many at each landing site.
 *
 * Both come from one filter state and one join, so a chart pair can never show
 * two different populations — the thing that happens when each series is its
 * own endpoint and the client forgets to pass a filter to one of them.
 */
class IkanTripChartService
{
    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    /**
     * Accepted filters, reusing the dropdown chain's column definitions so the
     * chart and the dropdown that feeds it cannot disagree about what
     * `provinsi` means. Gear, family and species are not among them: this chart
     * counts trips, and those three either multiply a trip across rows or
     * exclude trips that recorded no catch.
     */
    public const FILTERS = ['wppnri', 'provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_data'];

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function build(string $tipeTanggal, array $filters, ?string $dari, ?string $sampai): array
    {
        $periods = $this->byPeriod($tipeTanggal, $filters, $dari, $sampai);

        return [
            'filter' => [
                'tipe_tanggal' => $tipeTanggal,
                'dari' => $dari,
                'sampai' => $sampai,
            ] + array_map(fn ($value) => (string) $value, $filters),
            'total_trip' => array_sum(array_column($periods, 'jumlah_trip')),
            'per_tanggal' => $periods,
            'per_lokasi_pendaratan' => $this->byLandingSite($filters, $dari, $sampai),
        ];
    }

    /**
     * Trips per month or per year.
     *
     * Gaps are filled with zeroes only when the caller bounded the range with
     * both `dari` and `sampai`. Filling an unbounded range would stretch from
     * the one 2004 trip to today — 271 months, 212 of them empty — and a chart
     * of that is unreadable. Bounded, a continuous axis is exactly what a line
     * chart needs and a missing month is genuinely zero, not unknown.
     *
     * @return array<int, array{periode: string, jumlah_trip: int}>
     */
    private function byPeriod(string $tipeTanggal, array $filters, ?string $dari, ?string $sampai): array
    {
        $length = $tipeTanggal === self::YEARLY ? 4 : 7;

        $counts = $this->trips($filters, $dari, $sampai)
            // substr() on a DATE, not date_format()/strftime(): both MySQL and
            // SQLite render a date as YYYY-MM-DD before slicing it, so the same
            // SQL serves production and the test suite.
            ->selectRaw("substr(i.tanggal_pendataan, 1, {$length}) as periode, count(*) as jumlah_trip")
            ->groupBy('periode')
            ->orderBy('periode')
            ->pluck('jumlah_trip', 'periode')
            ->map(fn ($n) => (int) $n)
            ->all();

        if ($dari !== null && $sampai !== null) {
            $counts = $this->fillGaps($counts, $tipeTanggal, $dari, $sampai);
        }

        $series = [];

        foreach ($counts as $periode => $jumlah) {
            $series[] = ['periode' => (string) $periode, 'jumlah_trip' => $jumlah];
        }

        return $series;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function fillGaps(array $counts, string $tipeTanggal, string $dari, string $sampai): array
    {
        $yearly = $tipeTanggal === self::YEARLY;
        $format = $yearly ? 'Y' : 'Y-m';

        $cursor = Carbon::parse($dari)->startOfMonth();
        $end = Carbon::parse($sampai)->startOfMonth();

        if ($yearly) {
            $cursor = $cursor->startOfYear();
            $end = $end->startOfYear();
        }

        $filled = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format($format);
            $filled[$key] = $counts[$key] ?? 0;

            $yearly ? $cursor->addYear() : $cursor->addMonth();
        }

        return $filled;
    }

    /**
     * Trips per landing site, alphabetically.
     *
     * Plain ordering rather than the `lower()` the protected-area list needs:
     * every one of the 41 site names is upper-case, and this matches how
     * `opsi/lokasi-pendaratan` orders the same values, so the chart's axis and
     * the dropdown that filters it read in the same order.
     *
     * @return array<int, array{lokasi_pendaratan: string, jumlah_trip: int}>
     */
    private function byLandingSite(array $filters, ?string $dari, ?string $sampai): array
    {
        return $this->trips($filters, $dari, $sampai)
            ->selectRaw('i.lokasi_pendaratan as lokasi_pendaratan, count(*) as jumlah_trip')
            ->groupBy('i.lokasi_pendaratan')
            ->orderBy('i.lokasi_pendaratan')
            ->get()
            ->map(fn ($row) => [
                'lokasi_pendaratan' => (string) $row->lokasi_pendaratan,
                'jumlah_trip' => (int) $row->jumlah_trip,
            ])
            ->all();
    }

    /**
     * The filtered trip set both series are built from.
     *
     * `count(*)` is safe here and means trips: the operational join is 1:1, and
     * no table that multiplies a trip across rows is ever joined.
     */
    private function trips(array $filters, ?string $dari, ?string $sampai): Builder
    {
        $query = $this->datasources->connection('ikan')
            ->table('data_identitas_trip as i')
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
