<?php

namespace App\Services\Ikan;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;

/**
 * Length-frequency distribution of measured fish, with the descriptive
 * indicators a stock-status chart is read against.
 *
 * Reads `data_tangkapan_biologi.panjang_total` — one row per fish measured,
 * 76.007 of them. A request costs three grouped queries whose results are all
 * small regardless of how many fish are in scope, so nothing here scales with
 * the number of measurements.
 */
class IkanLengthFrequencyService
{
    /**
     * Every level of the dropdown chain is a valid filter here, including
     * `family` and `spesies`: this endpoint measures individual fish, so
     * narrowing to one species is the normal case rather than a degenerate one.
     */
    public const FILTERS = [
        'wppnri', 'provinsi', 'kabupaten', 'lokasi_pendaratan',
        'jenis_data', 'alat_tangkap', 'family', 'spesies',
    ];

    public const LENGTH_TYPES = ['TL', 'FL'];

    public const DEFAULT_CLASS_WIDTH = 1.0;

    /**
     * Upstream records no unit. Measurements run 3–150 with a mean of 23.7,
     * which is centimetres for fish; stated here so a reader cannot mistake it,
     * and worth confirming with the data owner.
     */
    public const UNIT = 'cm';

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function build(array $filters, ?string $dari, ?string $sampai, ?string $tipePanjang, float $selangKelas, ?float $lm): array
    {
        $summary = $this->summary($filters, $dari, $sampai, $tipePanjang, $lm);
        $classes = $this->classes($filters, $dari, $sampai, $tipePanjang, $selangKelas, (int) $summary->jumlah_ikan);

        return [
            'filter' => array_filter([
                'dari' => $dari,
                'sampai' => $sampai,
                'tipe_panjang' => $tipePanjang,
            ], fn ($v) => $v !== null) + array_map(fn ($value) => (string) $value, $filters),
            'unit' => self::UNIT,
            'selang_kelas' => $selangKelas,
            'ringkasan' => $this->describe($summary, $classes, $selangKelas),
            // Always present, because a histogram that silently mixes fork
            // length with total length is measuring two different things: an
            // FL of 20 and a TL of 20 are not the same fish. Several species
            // are recorded both ways, so the composition belongs in the
            // response rather than in a footnote.
            'komposisi_tipe_panjang' => $this->lengthTypes($filters, $dari, $sampai, $tipePanjang),
            'indikator' => $this->indicators($summary, $classes, $selangKelas, $lm),
            'kelas' => $classes,
        ];
    }

    /**
     * Contiguous classes from the smallest to the largest observed, empty ones
     * included — a histogram with holes in its axis misreads as a bimodal
     * distribution.
     *
     * @return array<int, array{batas_bawah: float, batas_atas: float, nilai_tengah: float, jumlah: int, persen: float, kumulatif_persen: float}>
     */
    private function classes(array $filters, ?string $dari, ?string $sampai, ?string $tipePanjang, float $width, int $total): array
    {
        if ($total === 0) {
            return [];
        }

        // Grouped by length, then binned here — not `floor(x / w) * w` in SQL.
        // SQLite ships without floor() unless compiled with its math
        // extension, and MySQL's CAST rounds rather than truncates, so there is
        // no one expression both accept. The distinct lengths number 196 across
        // all 76.007 measurements, so bringing them back to bin in PHP costs a
        // couple of hundred rows however many fish are in scope.
        $byLength = $this->measurements($filters, $dari, $sampai, $tipePanjang)
            ->selectRaw('b.panjang_total as panjang, count(*) as jumlah')
            ->groupBy('b.panjang_total')
            ->get();

        $counts = [];

        foreach ($byLength as $row) {
            $lower = floor((float) $row->panjang / $width) * $width;
            $key = (string) round($lower, 4);

            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->jumlah;
        }

        if ($counts === []) {
            return [];
        }

        $bounds = array_map('floatval', array_keys($counts));
        $classes = [];
        $cumulative = 0;

        for ($lower = min($bounds); $lower <= max($bounds) + 1e-9; $lower += $width) {
            $jumlah = 0;

            // Float keys: match on the rounded bound rather than on identity.
            foreach ($counts as $key => $n) {
                if (abs((float) $key - $lower) < 1e-9) {
                    $jumlah = $n;
                    break;
                }
            }

            $cumulative += $jumlah;

            $classes[] = [
                'batas_bawah' => round($lower, 4),
                'batas_atas' => round($lower + $width, 4),
                'nilai_tengah' => round($lower + $width / 2, 4),
                'jumlah' => $jumlah,
                'persen' => round($jumlah / $total * 100, 4),
                'kumulatif_persen' => round($cumulative / $total * 100, 4),
            ];
        }

        return $classes;
    }

    /** Exact counts and extremes, straight from the database rather than from the bins. */
    private function summary(array $filters, ?string $dari, ?string $sampai, ?string $tipePanjang, ?float $lm): object
    {
        $query = $this->measurements($filters, $dari, $sampai, $tipePanjang)
            ->selectRaw('count(*) as jumlah_ikan')
            ->selectRaw('min(b.panjang_total) as panjang_min')
            ->selectRaw('max(b.panjang_total) as panjang_maks')
            ->selectRaw('avg(b.panjang_total) as rata_rata');

        if ($lm !== null) {
            // Exact, not interpolated from the classes: the share of the catch
            // taken before maturity is the number this chart exists to show.
            $query->selectRaw('sum(case when b.panjang_total < ? then 1 else 0 end) as di_bawah_lm', [$lm]);
        }

        return $query->first();
    }

    private function describe(object $summary, array $classes, float $width): array
    {
        $total = (int) $summary->jumlah_ikan;

        return [
            'jumlah_ikan' => $total,
            'panjang_min' => $total ? round((float) $summary->panjang_min, 2) : null,
            'panjang_maks' => $total ? round((float) $summary->panjang_maks, 2) : null,
            'rata_rata' => $total ? round((float) $summary->rata_rata, 2) : null,
            'median' => $this->interpolate($classes, $total / 2, $width),
            'modus' => $this->modalClass($classes)['nilai_tengah'] ?? null,
        ];
    }

    /**
     * @return array<int, array{tipe_panjang: string, jumlah: int}>
     */
    private function lengthTypes(array $filters, ?string $dari, ?string $sampai, ?string $tipePanjang): array
    {
        return $this->measurements($filters, $dari, $sampai, $tipePanjang)
            ->selectRaw('b.tipe_panjang as tipe_panjang, count(*) as jumlah')
            ->groupBy('b.tipe_panjang')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($row) => [
                // 311 rows upstream record no type at all.
                'tipe_panjang' => filled($row->tipe_panjang) ? (string) $row->tipe_panjang : null,
                'jumlah' => (int) $row->jumlah,
            ])
            ->all();
    }

    /**
     * Lc and Lm — the pair a length-frequency chart is usually read against.
     *
     * **Lc is a descriptive estimate, not a fitted selectivity ogive.** It is
     * the length at 50% of the cumulative frequency of the *ascending limb*
     * (the classes up to and including the modal class), interpolated within
     * its class — the grouped-data method in Sparre & Venema. `lc_metode`
     * states this in the payload so the figure cannot be mistaken for the
     * output of a logistic fit, which would need the gear's own selection data.
     *
     * **Lm cannot be computed from this database at all.** Length at maturity
     * is a biological parameter of a species, not something a length-frequency
     * sample reveals, and there is no reference table here that holds it. It is
     * therefore an input: supply it from the literature for the species you
     * filtered to, and the share of fish caught below it comes back with it.
     */
    private function indicators(object $summary, array $classes, float $width, ?float $lm): array
    {
        $total = (int) $summary->jumlah_ikan;
        $limb = $this->ascendingLimb($classes);
        $limbTotal = array_sum(array_column($limb, 'jumlah'));

        return [
            'lc' => $limbTotal > 0 ? $this->interpolate($limb, $limbTotal / 2, $width) : null,
            'lc_metode' => 'interpolasi 50% frekuensi kumulatif pada limb naik hingga kelas modus',
            'lm' => $lm,
            'persen_di_bawah_lm' => $lm !== null && $total > 0
                ? round((int) $summary->di_bawah_lm / $total * 100, 2)
                : null,
        ];
    }

    /** Classes up to and including the modal one. */
    private function ascendingLimb(array $classes): array
    {
        $modal = $this->modalClass($classes);

        if ($modal === null) {
            return [];
        }

        return array_slice($classes, 0, $modal['index'] + 1);
    }

    /** @return array{index: int, nilai_tengah: float}|null */
    private function modalClass(array $classes): ?array
    {
        $best = null;

        foreach ($classes as $index => $class) {
            // First on a tie: the smaller length is the conservative read when
            // two classes are equally common.
            if ($best === null || $class['jumlah'] > $classes[$best]['jumlah']) {
                $best = $index;
            }
        }

        return $best === null || $classes[$best]['jumlah'] === 0
            ? null
            : ['index' => $best, 'nilai_tengah' => $classes[$best]['nilai_tengah']];
    }

    /**
     * The length at which a running count reaches $target, interpolated inside
     * the class that crosses it — the standard grouped-data formula
     * `L + ((target - F) / f) * c`.
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

    /**
     * Measured fish, joined to the trip that landed them.
     *
     * `count(*)` here means fish, not trips: one row is one fish, and the two
     * joins are 1:1 from that row's side.
     */
    private function measurements(array $filters, ?string $dari, ?string $sampai, ?string $tipePanjang): Builder
    {
        $query = $this->datasources->connection('ikan')
            ->table('data_tangkapan_biologi as b')
            ->join('data_identitas_trip as i', 'i.id_trip', '=', 'b.id_trip')
            ->join('data_operasional_trip as o', 'o.id_trip', '=', 'i.id_trip')
            ->whereNotNull('b.panjang_total')
            ->where('b.panjang_total', '>', 0);

        foreach ($filters as $key => $value) {
            if (filled($value) && isset(IkanFilterService::CHAIN[$key])) {
                $query->where(IkanFilterService::CHAIN[$key], $value);
            }
        }

        if ($tipePanjang !== null) {
            $query->where('b.tipe_panjang', $tipePanjang);
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
