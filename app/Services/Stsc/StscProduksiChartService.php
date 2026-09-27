<?php

namespace App\Services\Stsc;

use App\Services\DatasourceRegistry;

/**
 * Landed weight per commodity, as one line per WPPNRI per commodity.
 *
 * Nested by commodity because that is how the chart reads: one panel per
 * commodity, eleven lines in each. Narrowing to a single `wpp` leaves the same
 * shape with one line per panel, rather than a second shape the client has to
 * branch on.
 */
class StscProduksiChartService
{
    use Concerns\ScopesStscSeries;

    public const UNIT = 'ton';

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    public function build(?string $wpp, ?string $komoditas, ?int $dariTahun, ?int $sampaiTahun): array
    {
        $query = $this->applyYears($this->applyWpp($this->produksi(), $wpp), $dariTahun, $sampaiTahun);

        if (filled($komoditas)) {
            $query->where('komoditas', $komoditas);
        }

        $rows = $query
            // `sum()` over the full key is a no-op on the data as it stands —
            // there is exactly one row per year, area and commodity — and stays
            // correct if upstream ever splits a figure across two rows.
            ->selectRaw('komoditas, WPPNRI as wpp, tahun, sum(produksi_ton) as produksi')
            ->groupBy('komoditas', 'WPPNRI', 'tahun')
            ->orderBy('komoditas')
            ->orderBy('WPPNRI')
            ->orderBy('tahun')
            ->get();

        $points = [];
        $years = [];

        foreach ($rows as $row) {
            $year = (int) $row->tahun;
            $years[$year] = true;

            $points[(string) $row->komoditas][(string) $row->wpp][] = [
                'tahun' => $year,
                'nilai' => round((float) $row->produksi, 2),
            ];
        }

        ksort($years);

        return [
            'filter' => [
                'wpp' => $wpp,
                'komoditas' => $komoditas,
                'dari_tahun' => $dariTahun,
                'sampai_tahun' => $sampaiTahun,
            ],
            'unit' => self::UNIT,
            'tahun' => array_map('intval', array_keys($years)),
            // Production is a flow, so unlike the fleet series these totals are
            // worth having: they are the tonnage landed over the whole range.
            'total_produksi_ton' => $this->total($points),
            'komoditas' => $this->byCommodity($points),
        ];
    }

    /**
     * @param  array<string, array<string, array<int, array{tahun: int, nilai: float}>>>  $points
     * @return array<int, array{komoditas: string, total_produksi_ton: float, seri: array}>
     */
    private function byCommodity(array $points): array
    {
        $commodities = [];

        foreach ($points as $name => $areas) {
            $commodities[] = [
                'komoditas' => $name,
                'total_produksi_ton' => $this->total([$name => $areas]),
                'seri' => $this->toSeries($areas),
            ];
        }

        return $commodities;
    }

    /** @param  array<string, array<string, array<int, array{tahun: int, nilai: float}>>>  $points */
    private function total(array $points): float
    {
        $total = 0.0;

        foreach ($points as $areas) {
            foreach ($areas as $titik) {
                $total += array_sum(array_column($titik, 'nilai'));
            }
        }

        return round($total, 2);
    }
}
