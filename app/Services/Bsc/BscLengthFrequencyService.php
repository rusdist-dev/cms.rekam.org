<?php

namespace App\Services\Bsc;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;

/**
 * Carapace-width frequency for measured crabs, with Lc and Lm.
 *
 * The BSC counterpart of the IKAN length-frequency chart, and it differs in the
 * one way that matters: **Lm is computed here rather than supplied.** BSC
 * records a gonad maturity stage per individual, so the width at which half the
 * crabs are mature is in the data — where IKAN had nothing of the kind and had
 * to take Lm from the literature.
 */
class BscLengthFrequencyService
{
    use Concerns\ScopesBscTrips;

    /** Every chain level is a valid filter; this endpoint measures individuals. */
    public const FILTERS = [
        'provinsi', 'kabupaten', 'lokasi_pendaratan',
        'jenis_pendataan', 'alat_tangkap', 'jenis_tangkapan', 'spesies',
    ];

    public const DEFAULT_CLASS_WIDTH = 1.0;

    /**
     * Gonad stage at or above which an individual counts as mature.
     *
     * Where maturity begins on this scale is a biological judgement the data
     * does not state, which is why it is a parameter. The default is 2 because
     * the alternatives do not describe anything:
     *
     *  - at stage 1, 96,5% of crabs are "mature" and Lm collapses onto the
     *    smallest class;
     *  - at stage 3, only 11,1% are, no width class ever reaches half, and Lm
     *    comes back null for every query — a default that turns the indicator
     *    off entirely;
     *  - at stage 2, 62,7% are, and Lm lands at 9,5 for female and 14,3 for
     *    male Portunus pelagicus, which is the neighbourhood the literature
     *    puts that species in.
     *
     * Worth confirming with the data owner all the same.
     */
    public const DEFAULT_MATURE_STAGE = 2;

    /**
     * Upstream records no unit. Widths run 5–87 with 37 distinct values, which
     * is centimetres for a crab; worth confirming with the data owner.
     */
    public const UNIT = 'cm';

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function build(array $filters, ?string $dari, ?string $sampai, ?string $jenisKelamin, float $selangKelas, int $tkgMatang): array
    {
        $summary = $this->summary($filters, $dari, $sampai, $jenisKelamin, $tkgMatang);
        $total = (int) $summary->jumlah_individu;
        $classes = $this->classes($filters, $dari, $sampai, $jenisKelamin, $selangKelas, $total, $tkgMatang);

        return [
            'filter' => array_filter([
                'dari' => $dari,
                'sampai' => $sampai,
                'jenis_kelamin' => $jenisKelamin,
            ], fn ($v) => $v !== null) + array_map(fn ($value) => (string) $value, $filters),
            'unit' => self::UNIT,
            'selang_kelas' => $selangKelas,
            'tkg_matang' => $tkgMatang,
            'ringkasan' => $this->describe($summary, $classes, $selangKelas),
            // Always present. Sex is recorded in four vocabularies upstream and
            // 83 rows in none of them; showing the split makes both the
            // normalisation and its leftovers visible.
            'komposisi_jenis_kelamin' => $this->sexes($filters, $dari, $sampai, $jenisKelamin),
            'indikator' => $this->indicators($summary, $classes, $selangKelas, $tkgMatang),
            'kelas' => $classes,
        ];
    }

    /**
     * Contiguous classes, empty ones included, each carrying how many of its
     * crabs were mature — which is what makes the maturity ogive plottable and
     * the Lm below checkable.
     *
     * @return array<int, array>
     */
    private function classes(array $filters, ?string $dari, ?string $sampai, ?string $jenisKelamin, float $width, int $total, int $tkgMatang): array
    {
        if ($total === 0) {
            return [];
        }

        // Grouped by width, then binned here rather than `floor(x / w) * w` in
        // SQL: SQLite ships without floor() unless built with its math
        // extension, and MySQL's CAST rounds rather than truncates. There are
        // only 37 distinct widths in the whole table, so this is a tiny result
        // set however many crabs are in scope.
        $rows = $this->measurements($filters, $dari, $sampai, $jenisKelamin)
            ->selectRaw('b.lebar_karapas as lebar, count(*) as jumlah')
            ->selectRaw('sum(case when b.TKG >= ? then 1 else 0 end) as matang', [$tkgMatang])
            ->groupBy('b.lebar_karapas')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = (string) round(floor((float) $row->lebar / $width) * $width, 4);

            $counts[$key]['jumlah'] = ($counts[$key]['jumlah'] ?? 0) + (int) $row->jumlah;
            $counts[$key]['matang'] = ($counts[$key]['matang'] ?? 0) + (int) $row->matang;
        }

        if ($counts === []) {
            return [];
        }

        $bounds = array_map('floatval', array_keys($counts));
        $classes = [];
        $cumulative = 0;

        for ($lower = min($bounds); $lower <= max($bounds) + 1e-9; $lower += $width) {
            $bucket = ['jumlah' => 0, 'matang' => 0];

            foreach ($counts as $key => $value) {
                if (abs((float) $key - $lower) < 1e-9) {
                    $bucket = $value;
                    break;
                }
            }

            $cumulative += $bucket['jumlah'];

            $classes[] = [
                'batas_bawah' => round($lower, 4),
                'batas_atas' => round($lower + $width, 4),
                'nilai_tengah' => round($lower + $width / 2, 4),
                'jumlah' => $bucket['jumlah'],
                'jumlah_matang' => $bucket['matang'],
                'persen' => round($bucket['jumlah'] / $total * 100, 4),
                'kumulatif_persen' => round($cumulative / $total * 100, 4),
                'persen_matang' => $bucket['jumlah'] > 0
                    ? round($bucket['matang'] / $bucket['jumlah'] * 100, 4)
                    : null,
            ];
        }

        return $classes;
    }

    /** Exact counts and extremes, straight from the rows rather than from the bins. */
    private function summary(array $filters, ?string $dari, ?string $sampai, ?string $jenisKelamin, int $tkgMatang): object
    {
        return $this->measurements($filters, $dari, $sampai, $jenisKelamin)
            ->selectRaw('count(*) as jumlah_individu')
            ->selectRaw('min(b.lebar_karapas) as lebar_min')
            ->selectRaw('max(b.lebar_karapas) as lebar_maks')
            ->selectRaw('avg(b.lebar_karapas) as rata_rata')
            ->selectRaw('sum(case when b.TKG >= ? then 1 else 0 end) as jumlah_matang', [$tkgMatang])
            ->selectRaw('sum(case when b.TKG is null then 1 else 0 end) as tanpa_tkg')
            ->first();
    }

    private function describe(object $summary, array $classes, float $width): array
    {
        $total = (int) $summary->jumlah_individu;

        return [
            'jumlah_individu' => $total,
            'lebar_min' => $total ? round((float) $summary->lebar_min, 2) : null,
            'lebar_maks' => $total ? round((float) $summary->lebar_maks, 2) : null,
            'rata_rata' => $total ? round((float) $summary->rata_rata, 2) : null,
            'median' => $this->interpolate($classes, $total / 2, $width),
            'modus' => $this->modalClass($classes)['nilai_tengah'] ?? null,
            // Stated because it bounds how much the maturity figures can be
            // trusted: a crab with no stage recorded counts as not mature.
            'tanpa_tkg' => (int) $summary->tanpa_tkg,
        ];
    }

    /** @return array<int, array{jenis_kelamin: ?string, jumlah: int}> */
    private function sexes(array $filters, ?string $dari, ?string $sampai, ?string $jenisKelamin): array
    {
        $sql = BscFilterService::SEX_SQL;

        return $this->measurements($filters, $dari, $sampai, $jenisKelamin)
            // Aliased `jk`, not `jenis_kelamin`: an alias that collides with a
            // real column makes MySQL group by the column instead, which
            // returns one row per raw spelling — three rows all labelled
            // BETINA. Grouping by the expression itself avoids the question.
            ->selectRaw("{$sql} as jk, count(*) as jumlah")
            ->groupByRaw($sql)
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($row) => [
                'jenis_kelamin' => $row->jk !== null ? (string) $row->jk : null,
                'jumlah' => (int) $row->jumlah,
            ])
            ->all();
    }

    /**
     * Lc and Lm, both derived — and by different methods, which the payload
     * states rather than leaving to be guessed.
     *
     * **Lc** is the width at 50% of the cumulative frequency of the ascending
     * limb (the classes up to and including the modal one), interpolated within
     * its class: the grouped-data method in Sparre & Venema, a descriptive
     * estimate rather than a fitted selectivity ogive.
     *
     * **Lm** is the width at which the proportion of mature individuals first
     * reaches half, interpolated between the two class midpoints that bracket
     * it. Not a logistic fit either — the proportion mature is noisy at the
     * tails, where a class of three crabs can read 100% — so classes below a
     * minimum sample size are skipped when looking for the crossing.
     *
     * Lc below Lm means the fishery is taking crabs before they have bred.
     */
    private function indicators(object $summary, array $classes, float $width, int $tkgMatang): array
    {
        $total = (int) $summary->jumlah_individu;
        $limb = $this->ascendingLimb($classes);
        $limbTotal = array_sum(array_column($limb, 'jumlah'));

        return [
            'lc' => $limbTotal > 0 ? $this->interpolate($limb, $limbTotal / 2, $width) : null,
            'lc_metode' => 'interpolasi 50% frekuensi kumulatif pada limb naik hingga kelas modus',
            'lm' => $this->maturityAtHalf($classes),
            'lm_metode' => "interpolasi lebar saat 50% individu mencapai TKG >= {$tkgMatang}, kelas dengan <10 individu dilewati",
            'persen_matang' => $total > 0 ? round((int) $summary->jumlah_matang / $total * 100, 2) : null,
        ];
    }

    /**
     * The width where the mature proportion first crosses half.
     *
     * Small classes are ignored: at the tails a handful of individuals swings
     * the proportion from 0 to 100 and would put Lm wherever the sparsest data
     * happens to be.
     */
    private function maturityAtHalf(array $classes, int $minimumSample = 10): ?float
    {
        $previous = null;

        foreach ($classes as $class) {
            if ($class['jumlah'] < $minimumSample || $class['persen_matang'] === null) {
                continue;
            }

            if ($class['persen_matang'] >= 50.0) {
                if ($previous === null) {
                    // Already mature in the first usable class: there is
                    // nothing below to interpolate towards.
                    return $class['nilai_tengah'];
                }

                $span = $class['persen_matang'] - $previous['persen_matang'];

                if ($span <= 0) {
                    return $class['nilai_tengah'];
                }

                $fraction = (50.0 - $previous['persen_matang']) / $span;

                return round(
                    $previous['nilai_tengah'] + $fraction * ($class['nilai_tengah'] - $previous['nilai_tengah']),
                    2,
                );
            }

            $previous = $class;
        }

        return null;
    }

    /** Classes up to and including the modal one. */
    private function ascendingLimb(array $classes): array
    {
        $modal = $this->modalClass($classes);

        return $modal === null ? [] : array_slice($classes, 0, $modal['index'] + 1);
    }

    /** @return array{index: int, nilai_tengah: float}|null */
    private function modalClass(array $classes): ?array
    {
        $best = null;

        foreach ($classes as $index => $class) {
            // First on a tie: the smaller width is the conservative read.
            if ($best === null || $class['jumlah'] > $classes[$best]['jumlah']) {
                $best = $index;
            }
        }

        return $best === null || $classes[$best]['jumlah'] === 0
            ? null
            : ['index' => $best, 'nilai_tengah' => $classes[$best]['nilai_tengah']];
    }

    /**
     * The width at which a running count reaches $target, interpolated inside
     * the class that crosses it — `L + ((target - F) / f) * c`.
     */
    private function interpolate(array $classes, float $target, float $width): ?float
    {
        $cumulative = 0;

        foreach ($classes as $class) {
            if ($class['jumlah'] === 0) {
                continue;
            }

            if ($cumulative + $class['jumlah'] >= $target) {
                return round(
                    $class['batas_bawah'] + (($target - $cumulative) / $class['jumlah']) * $width,
                    2,
                );
            }

            $cumulative += $class['jumlah'];
        }

        return null;
    }

    /** Measured crabs on in-scope trips. One row is one crab. */
    private function measurements(array $filters, ?string $dari, ?string $sampai, ?string $jenisKelamin): Builder
    {
        $query = $this->applyBscFilters(
            $this->scopedTrips(['b.lebar_karapas']),
            $filters,
            $dari,
            $sampai,
        )
            ->whereNotNull('b.lebar_karapas')
            ->where('b.lebar_karapas', '>', 0);

        if ($jenisKelamin !== null) {
            $query->whereRaw(BscFilterService::SEX_SQL.' = ?', [$jenisKelamin]);
        }

        return $query;
    }
}
