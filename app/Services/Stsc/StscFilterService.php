<?php

namespace App\Services\Stsc;

use App\Services\DatasourceRegistry;

/**
 * The option lists behind STSC's two filters, and the WPPNRI vocabulary every
 * other STSC service validates against.
 *
 * Only two levels, and they are not a chain in the IKAN/BSC sense: WPPNRI and
 * commodity are independent dimensions of the same grid, not a hierarchy. The
 * commodity list still accepts `wpp`, because a caller who has already picked
 * an area wants to know what is landed there.
 */
class StscFilterService
{
    use Concerns\ScopesStscSeries;

    /**
     * The eleven fisheries management areas (WPPNRI) defined by Permen KP
     * 18/2014, and the eleven this database carries.
     *
     * A fixed list rather than a `select distinct`: requests are validated
     * against it, so asking for area 999 comes back as an error instead of as
     * an empty chart that looks like "no fleet there". If upstream ever adds an
     * area, this constant is the one place that has to learn about it.
     */
    public const WPPNRI = ['571', '572', '573', '711', '712', '713', '714', '715', '716', '717', '718'];

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * Areas that carry at least one row, with the year range behind each and
     * which of the two tables it came from.
     *
     * `sumber` matters because the two tables are filled independently: an area
     * present in `produksi` but not in `armada` would draw an empty fleet chart
     * and a full production one, and this list says so before the chart does.
     *
     * @return array<int, array{value: string, tahun_awal: int, tahun_akhir: int, sumber: array<int, string>}>
     */
    public function wpp(): array
    {
        $areas = [];

        foreach (['armada' => $this->armada(), 'produksi' => $this->produksi()] as $source => $query) {
            $rows = $query
                ->selectRaw('WPPNRI as value, min(tahun) as tahun_awal, max(tahun) as tahun_akhir')
                ->groupBy('WPPNRI')
                ->get();

            foreach ($rows as $row) {
                $key = (string) $row->value;

                $areas[$key]['tahun_awal'] = min($areas[$key]['tahun_awal'] ?? PHP_INT_MAX, (int) $row->tahun_awal);
                $areas[$key]['tahun_akhir'] = max($areas[$key]['tahun_akhir'] ?? PHP_INT_MIN, (int) $row->tahun_akhir);
                $areas[$key]['sumber'][] = $source;
            }
        }

        // Natural sort: the codes are numeric strings, and 571 belongs before
        // 711 however the driver happened to type the column.
        uksort($areas, 'strnatcmp');

        $options = [];

        foreach ($areas as $value => $area) {
            $options[] = [
                'value' => (string) $value,
                'tahun_awal' => $area['tahun_awal'],
                'tahun_akhir' => $area['tahun_akhir'],
                'sumber' => $area['sumber'],
            ];
        }

        return $options;
    }

    /**
     * Commodities recorded in `data_produksi`, alphabetically — the same order
     * the production chart returns them in, so the axis and the dropdown that
     * filters it read alike.
     *
     * `jumlah_wpp` counts the areas that landed the commodity at all, which is
     * how many lines the chart will draw for it.
     *
     * @return array<int, array{value: string, jumlah_wpp: int, tahun_awal: int, tahun_akhir: int}>
     */
    public function komoditas(?string $wpp = null): array
    {
        return $this->applyWpp($this->produksi(), $wpp)
            ->selectRaw('komoditas as value, count(distinct WPPNRI) as jumlah_wpp')
            ->selectRaw('min(tahun) as tahun_awal, max(tahun) as tahun_akhir')
            ->groupBy('komoditas')
            ->orderBy('komoditas')
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'jumlah_wpp' => (int) $row->jumlah_wpp,
                'tahun_awal' => (int) $row->tahun_awal,
                'tahun_akhir' => (int) $row->tahun_akhir,
            ])
            ->all();
    }
}
