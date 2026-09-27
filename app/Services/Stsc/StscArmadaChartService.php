<?php

namespace App\Services\Stsc;

use App\Services\DatasourceRegistry;

/**
 * Two series over the same rows of `data_armada`: how many vessels each WPPNRI
 * carried in each year, and the tonnage those vessels add up to.
 *
 * One endpoint rather than two, for the same reason `bsc/grafik/trip` returns
 * both of its series at once — fleet size and fleet tonnage are read against
 * each other, and two calls could answer from two different filters.
 */
class StscArmadaChartService
{
    use Concerns\ScopesStscSeries;

    /**
     * Upstream states no unit for either column. `jumlah_armada_unit` counts
     * vessels; `total_GT` is gross tonnage, the standard unit for this figure
     * and consistent with the magnitudes recorded (a mean of about 5 GT per
     * vessel across the whole table).
     */
    public const UNIT = ['armada' => 'unit', 'gt' => 'GT'];

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    public function build(?string $wpp, ?int $dariTahun, ?int $sampaiTahun): array
    {
        $rows = $this->applyYears($this->applyWpp($this->armada(), $wpp), $dariTahun, $sampaiTahun)
            // Aliased rather than selected by name: `total_GT` reaches PHP with
            // whatever casing the driver reports, and a property read is
            // case-sensitive even where the column name is not.
            ->selectRaw('tahun, WPPNRI as wpp, jumlah_armada_unit as armada, total_GT as gt')
            ->orderBy('WPPNRI')
            ->orderBy('tahun')
            ->get();

        $armada = [];
        $gt = [];
        $years = [];

        foreach ($rows as $row) {
            $area = (string) $row->wpp;
            $year = (int) $row->tahun;

            $years[$year] = true;
            $armada[$area][] = ['tahun' => $year, 'nilai' => (int) $row->armada];
            $gt[$area][] = ['tahun' => $year, 'nilai' => (int) $row->gt];
        }

        ksort($years);

        return [
            'filter' => [
                'wpp' => $wpp,
                'dari_tahun' => $dariTahun,
                'sampai_tahun' => $sampaiTahun,
            ],
            'unit' => self::UNIT,
            // The shared x-axis, so a caller drawing eleven lines does not have
            // to work out their union itself.
            'tahun' => array_map('intval', array_keys($years)),
            // No grand total for either series, deliberately: both are stocks
            // counted afresh every year, and adding 1990 to 1991 would report
            // a fleet twice the size of any that ever existed.
            'armada' => $this->toSeries($armada),
            'gt' => $this->toSeries($gt),
        ];
    }
}
