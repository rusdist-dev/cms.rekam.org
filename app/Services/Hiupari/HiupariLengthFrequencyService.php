<?php

namespace App\Services\Hiupari;

use App\Services\DatasourceRegistry;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Length-frequency distribution for sharks and rays, with Lm and Linf, and the
 * species list that feeds its filter.
 *
 * The scope here is deliberately narrow, but one thing it cannot be narrow
 * about is *which* length. HIUPARI records five different measurements per
 * animal, and how completely each is filled in varies enormously by species:
 * every one of the 7.558 Rhynchobatus australiae has a total length, while
 * only 21 of the 247 Prionace glauca do. Defaulting to one column and saying
 * nothing would draw a blue-shark histogram from 8% of the blue sharks.
 *
 * So the measurement is a parameter, and the response always says how many
 * individuals in scope lacked the one that was chosen.
 */
class HiupariLengthFrequencyService
{
    /**
     * The measurements upstream records, as `parameter => column`.
     *
     * `panjang_total` is the default because total length is the conventional
     * reporting measure for sharks and rays — not because it is the most
     * complete, which it is not for several species.
     */
    public const MEASUREMENTS = [
        'panjang_total' => 'b.panjang_total',
        'precaudal_length' => 'b.precaudal_length',
        'fork_length' => 'b.fork_length',
        'predorsal_length' => 'b.predorsal_length',
        'panjang_headless' => 'b.panjang_headless',
    ];

    public const DEFAULT_MEASUREMENT = 'panjang_total';

    public const DEFAULT_CLASS_WIDTH = 1.0;

    /** Upstream writes sex as a single letter and nothing else. */
    public const SEXES = ['M', 'F'];

    public const MALE = 'M';

    /**
     * Clasper stage at or above which a male counts as mature.
     *
     * Upstream records 0, 1, 2 and 3 — and 158 rows carrying values from 5 to
     * 37, which are clasper *lengths* entered in the maturity column. Those are
     * treated as no stage at all rather than as extremely mature animals.
     */
    public const DEFAULT_MATURE_STAGE = 3;

    public const MAX_STAGE = 3;

    /**
     * Upstream records no unit. Measurements run 5–392, which is centimetres
     * for these animals; worth confirming with the data owner.
     */
    public const UNIT = 'cm';

    /**
     * Froese & Binohlan (2000): asymptotic length is about Lmax / 0.95.
     *
     * Worth knowing that the relationship was established for *total* length.
     * Applied to a precaudal or predorsal measurement it still returns a
     * number, but that number is an asymptote of that measurement rather than
     * of the animal, which is why `jenis_ukuran` comes back beside it.
     */
    public const LINF_RATIO = 0.95;

    public function __construct(private readonly DatasourceRegistry $datasources) {}

    /**
     * Species that have at least one individual, alphabetically.
     *
     * Unpaginated — there are twenty. `jumlah_individu` rather than a trip
     * count, because this list exists to feed a histogram of individuals.
     *
     * @return array<int, array{value: string, jumlah_individu: int}>
     */
    public function species(): array
    {
        return $this->base(null, null)
            ->selectRaw('b.spesies as value, count(*) as jumlah_individu')
            ->whereNotNull('b.spesies')
            ->where('b.spesies', '!=', '')
            ->groupBy('b.spesies')
            ->orderBy('b.spesies')
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'jumlah_individu' => (int) $row->jumlah_individu,
            ])
            ->all();
    }

    public function build(?string $spesies, ?string $jenisKelamin, string $jenisUkuran, float $selangKelas, int $kematanganMatang): array
    {
        $column = self::MEASUREMENTS[$jenisUkuran]
            ?? throw new InvalidArgumentException("Jenis ukuran '{$jenisUkuran}' tidak dikenal.");

        $male = $jenisKelamin === self::MALE;

        $summary = $this->summary($spesies, $jenisKelamin, $column, $kematanganMatang, $male);
        $total = (int) $summary->jumlah_individu;
        $classes = $this->classes($spesies, $jenisKelamin, $column, $selangKelas, $total, $kematanganMatang, $male);

        return [
            'filter' => array_filter([
                'spesies' => $spesies,
                'jenis_kelamin' => $jenisKelamin,
            ], fn ($v) => $v !== null),
            'jenis_ukuran' => $jenisUkuran,
            'unit' => self::UNIT,
            'selang_kelas' => $selangKelas,
            'kematangan_matang' => $kematanganMatang,
            'ringkasan' => $this->describe($summary, $total, $selangKelas, $spesies, $jenisKelamin, $classes),
            // Which measurements are available for this selection at all, so a
            // caller looking at 21 of 247 blue sharks can see that precaudal
            // length would have given them 30 and predorsal length 88 — rather
            // than having to guess that a better column exists.
            'ketersediaan_ukuran' => $this->availability($spesies, $jenisKelamin),
            'indikator' => $this->indicators($summary, $total, $classes, $selangKelas, $kematanganMatang, $male),
            'kelas' => $classes,
        ];
    }

    /**
     * Lm and Linf.
     *
     * **Linf** is the empirical Froese & Binohlan estimate, Lmax / 0.95. It is
     * a rule of thumb rather than a growth-curve fit, and it rests on a single
     * extreme value — one mis-entered giant would move it — so `linf_metode`
     * says so in the payload.
     *
     * **Lm** is the length at which half the males have reached the clasper
     * stage given by `kematangan_matang`, interpolated between the two class
     * midpoints that bracket the crossing, with classes under ten individuals
     * skipped so a handful of animals at the tails cannot place it.
     *
     * Lm is **male-only, and null otherwise**. Clasper maturity is a male
     * character: 12.091 of the 12.108 females upstream carry a 0, which means
     * "no claspers", not "immature". Computing a maturity ogive over a mixed
     * sample would read as though almost nothing in it had ever bred.
     */
    private function indicators(object $summary, int $total, array $classes, float $width, int $matureStage, bool $male): array
    {
        $lmax = $total > 0 ? (float) $summary->panjang_maks : null;

        return [
            'linf' => $lmax !== null ? round($lmax / self::LINF_RATIO, 2) : null,
            'linf_metode' => 'empiris Lmax / '.self::LINF_RATIO.' (Froese & Binohlan 2000)',
            'lm' => $male ? $this->maturityAtHalf($classes) : null,
            'lm_metode' => $male
                ? "interpolasi 50% individu dengan kematangan klasper >= {$matureStage}, kelas dengan <10 individu dilewati"
                : 'hanya tersedia untuk jantan: kematangan klasper adalah ciri jantan, dan betina tercatat 0 karena tidak berklasper',
            'persen_matang' => $male && $total > 0
                ? round((int) $summary->jumlah_matang / $total * 100, 2)
                : null,
        ];
    }

    /**
     * The length where the mature proportion first crosses half.
     *
     * Small classes are ignored: at the tails a handful of animals swings the
     * proportion from 0 to 100 and would put Lm wherever the sparsest data
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
                    // Already mature in the first usable class: nothing below
                    // to interpolate towards.
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

    /**
     * Contiguous classes from the smallest to the largest observed, empty ones
     * included — a gap in the axis reads as a bimodal distribution rather than
     * as one missing bar.
     *
     * The two maturity fields are null unless the selection is male-only; see
     * indicators().
     *
     * @return array<int, array>
     */
    private function classes(?string $spesies, ?string $jenisKelamin, string $column, float $width, int $total, int $matureStage, bool $male): array
    {
        if ($total === 0) {
            return [];
        }

        // Grouped by length, then binned here rather than `floor(x / w) * w` in
        // SQL: SQLite ships without floor() unless built with its math
        // extension, and MySQL's CAST rounds rather than truncates. The widest
        // of these columns has 566 distinct values, so the grouped result is
        // small however many animals are in scope.
        $query = $this->measured($spesies, $jenisKelamin, $column)
            ->selectRaw("{$column} as panjang, count(*) as jumlah")
            ->groupBy($column);

        if ($male) {
            $query->selectRaw($this->matureSql($matureStage).' as matang');
        }

        $counts = [];

        foreach ($query->get() as $row) {
            $key = (string) round(floor((float) $row->panjang / $width) * $width, 4);

            $counts[$key]['jumlah'] = ($counts[$key]['jumlah'] ?? 0) + (int) $row->jumlah;
            $counts[$key]['matang'] = ($counts[$key]['matang'] ?? 0) + (int) ($row->matang ?? 0);
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
                'jumlah_matang' => $male ? $bucket['matang'] : null,
                'persen' => round($bucket['jumlah'] / $total * 100, 4),
                'kumulatif_persen' => round($cumulative / $total * 100, 4),
                'persen_matang' => $male && $bucket['jumlah'] > 0
                    ? round($bucket['matang'] / $bucket['jumlah'] * 100, 4)
                    : null,
            ];
        }

        return $classes;
    }

    private function summary(?string $spesies, ?string $jenisKelamin, string $column, int $matureStage, bool $male): object
    {
        $query = $this->measured($spesies, $jenisKelamin, $column)
            ->selectRaw('count(*) as jumlah_individu')
            ->selectRaw("min({$column}) as panjang_min")
            ->selectRaw("max({$column}) as panjang_maks")
            ->selectRaw("avg({$column}) as rata_rata");

        if ($male) {
            $query->selectRaw($this->matureSql($matureStage).' as jumlah_matang');
        }

        return $query->first();
    }

    /**
     * Counts animals at or above the given clasper stage, ignoring the 158
     * rows whose stage is outside the 0–3 scale upstream actually uses.
     */
    private function matureSql(int $matureStage): string
    {
        return 'sum(case when b.kematangan_klasper >= '.$matureStage
            .' and b.kematangan_klasper <= '.self::MAX_STAGE.' then 1 else 0 end)';
    }

    private function describe(object $summary, int $total, float $width, ?string $spesies, ?string $jenisKelamin, array $classes): array
    {
        return [
            'jumlah_individu' => $total,
            // How many animals in scope carry no value for the chosen
            // measurement. Without this a histogram drawn from 21 of 247 blue
            // sharks looks exactly like one drawn from all of them.
            'jumlah_tanpa_ukuran' => $this->inScope($spesies, $jenisKelamin) - $total,
            'panjang_min' => $total ? round((float) $summary->panjang_min, 2) : null,
            'panjang_maks' => $total ? round((float) $summary->panjang_maks, 2) : null,
            'rata_rata' => $total ? round((float) $summary->rata_rata, 2) : null,
            'median' => $this->interpolate($classes, $total / 2, $width),
            'modus' => $this->modalClass($classes),
        ];
    }

    /** @return array<int, array{jenis_ukuran: string, jumlah_individu: int}> */
    private function availability(?string $spesies, ?string $jenisKelamin): array
    {
        $query = $this->base($spesies, $jenisKelamin);

        foreach (self::MEASUREMENTS as $name => $column) {
            $query->selectRaw("sum(case when {$column} > 0 then 1 else 0 end) as `{$name}`");
        }

        $row = $query->first();
        $available = [];

        foreach (array_keys(self::MEASUREMENTS) as $name) {
            $available[] = [
                'jenis_ukuran' => $name,
                'jumlah_individu' => (int) ($row->{$name} ?? 0),
            ];
        }

        usort($available, fn ($a, $b) => $b['jumlah_individu'] <=> $a['jumlah_individu']);

        return $available;
    }

    private function inScope(?string $spesies, ?string $jenisKelamin): int
    {
        return $this->base($spesies, $jenisKelamin)->count();
    }

    private function modalClass(array $classes): ?float
    {
        $best = null;

        foreach ($classes as $index => $class) {
            // First on a tie: the smaller length is the conservative read.
            if ($best === null || $class['jumlah'] > $classes[$best]['jumlah']) {
                $best = $index;
            }
        }

        return $best === null || $classes[$best]['jumlah'] === 0
            ? null
            : $classes[$best]['nilai_tengah'];
    }

    /** `L + ((target - F) / f) * c`, the grouped-data formula. */
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

    /** Animals that carry the chosen measurement. */
    private function measured(?string $spesies, ?string $jenisKelamin, string $column): Builder
    {
        return $this->base($spesies, $jenisKelamin)
            ->whereNotNull($column)
            ->where($column, '>', 0);
    }

    /**
     * Every measured animal, joined to the trip that landed it.
     *
     * One row is one animal, and the join is 1:1 from that row's side — no trip
     * in this database lacks its identity record.
     */
    private function base(?string $spesies, ?string $jenisKelamin): Builder
    {
        $query = $this->datasources->connection('hiupari')
            ->table('data_tangkapan_biologi as b')
            ->join('data_identitas_trip as i', 'i.id_trip', '=', 'b.id_trip');

        if (filled($spesies)) {
            $query->where('b.spesies', $spesies);
        }

        if (filled($jenisKelamin)) {
            $query->where('b.jenis_kelamin', $jenisKelamin);
        }

        return $query;
    }
}
