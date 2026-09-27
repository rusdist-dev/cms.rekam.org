<?php

namespace App\Services\Bsc;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Two series over the same filtered set of BSC trips: how many were collected
 * in each period, and how many at each landing site.
 *
 * The IKAN counterpart of this chart, over a table whose collection date is
 * `tanggal` rather than `tanggal_pendataan` and which carries no WPPNRI.
 */
class BscTripChartService
{
    use Concerns\ScopesBscTrips;

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    /**
     * Trip-level filters only. `spesies` lives on the crab table, which holds
     * many rows per trip: joining it here would turn a trip count into a crab
     * count and would drop the trips where nothing was measured.
     */
    public const FILTERS = [
        'provinsi', 'kabupaten', 'lokasi_pendaratan',
        'jenis_pendataan', 'alat_tangkap', 'jenis_tangkapan',
    ];

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
     * both `dari` and `sampai` — a bounded range is what a line chart needs,
     * and a missing month is then genuinely zero rather than unknown.
     *
     * @return array<int, array{periode: string, jumlah_trip: int}>
     */
    private function byPeriod(string $tipeTanggal, array $filters, ?string $dari, ?string $sampai): array
    {
        $length = $tipeTanggal === self::YEARLY ? 4 : 7;

        $counts = $this->tripQuery($filters, $dari, $sampai)
            // substr() on a DATE, not date_format()/strftime(): both MySQL and
            // SQLite render a date as YYYY-MM-DD before slicing it, so the same
            // SQL serves production and the test suite.
            ->selectRaw("substr(t.tanggal, 1, {$length}) as periode, count(*) as jumlah_trip")
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
     * Trips per landing site, alphabetically — the same order
     * `opsi/lokasi-pendaratan` returns, so the chart axis and the dropdown that
     * filters it read alike.
     *
     * @return array<int, array{lokasi_pendaratan: string, jumlah_trip: int}>
     */
    private function byLandingSite(array $filters, ?string $dari, ?string $sampai): array
    {
        return $this->tripQuery($filters, $dari, $sampai)
            ->selectRaw('t.lokasi_pendaratan as lokasi_pendaratan, count(*) as jumlah_trip')
            ->groupBy('t.lokasi_pendaratan')
            ->orderBy('t.lokasi_pendaratan')
            ->get()
            ->map(fn ($row) => [
                'lokasi_pendaratan' => (string) $row->lokasi_pendaratan,
                'jumlah_trip' => (int) $row->jumlah_trip,
            ])
            ->all();
    }

    /**
     * `count(*)` means trips here: no table that multiplies a trip across rows
     * is ever joined, because FILTERS holds only trip-level keys.
     */
    private function tripQuery(array $filters, ?string $dari, ?string $sampai): Builder
    {
        return $this->applyBscFilters($this->scopedTrips(), $filters, $dari, $sampai);
    }
}
