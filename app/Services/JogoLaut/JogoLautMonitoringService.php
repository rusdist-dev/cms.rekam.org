<?php

namespace App\Services\JogoLaut;

use App\Services\JogoLaut\JogoLautEcosystemService as Eco;
use App\Services\JogoLaut\JogoLautStatsService as Stats;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * The whole JOGO LAUT monitoring page as one chart-neutral payload — the API
 * replacement for perikanan.org's server-rendered MonitoringController.
 *
 * Output is data plus metadata, never chart configuration: a time-series
 * section is a shared `x` axis and a list of self-describing series, and the
 * frontend decides how to draw it. Keys are stable snake_case; labels follow
 * `locale` and may change.
 *
 * Only the requested sections are built, and each upstream table is read at
 * most once per build however many sections use it. A section that throws is
 * reported as `{type, empty: true, error: true}` instead of failing the
 * response — and the controller does not cache a payload that carries one.
 */
class JogoLautMonitoringService
{
    /** Section key => its `type`, in response order. */
    public const SECTIONS = [
        'co2' => 'timeseries',
        'flux' => 'timeseries',
        'do' => 'timeseries',
        'ph' => 'timeseries',
        'ctd' => 'timeseries',
        'atm' => 'timeseries',
        'diurnal' => 'category',
        'windrose' => 'polar',
        'correlation' => 'matrix',
        'analysis' => 'analysis',
        'ecosystem' => 'status',
        'kpi' => 'stats',
        'gauges' => 'stats',
        'table' => 'table',
    ];

    /** Series key => [unit, decimals]. Labels come from lang/{locale}/jogolaut.php. */
    private const SERIES = [
        'co2_tanah' => ['ppm', 1],
        'co2_udara' => ['ppm', 0],
        'pasut_ma' => ['cm', 1],
        'respirasi' => ['mg/m²/s', 3],
        'carbon_flux' => ['mg C/m²/s', 3],
        'do' => ['mg/L', 2],
        'suhu_air' => ['°C', 1],
        'ph' => ['pH', 2],
        'conductivity' => ['µS/cm', 1],
        'level_air' => ['cm', 1],
        'kec_angin' => ['m/s', 2],
        'arah_angin' => ['°', 0],
        'suhu_udara' => ['°C', 1],
        'kelembaban' => ['%', 1],
        'curah_hujan' => ['mm', 1],
        'mean' => ['ppm', 1],
        'std' => ['ppm', 1],
        'ph_tanah' => ['pH', 2],
        'ph_air' => ['pH', 2],
        'temp_air' => ['°C', 1],
        'humidity' => ['%', 1],
        'soil_moisture' => ['%', 1],
        'soil_temp' => ['°C', 1],
        'soil_ph' => ['pH', 2],
    ];

    /** KPI key => [unit, decimals]. Order is the order of the old status strip. */
    private const KPI = [
        'suhu_udara' => ['°C', 1],
        'kelembaban_udara' => ['%', 1],
        'curah_hujan' => ['mm', 1],
        'jarak_air' => ['cm', 1],
        'pasut' => ['cm', 1],
        'co2_lapangan' => ['ppm', 0],
        'suhu_co2' => ['°C', 1],
        'kelembaban_co2' => ['%', 1],
        'kelembaban_tanah' => ['%', 1],
        'suhu_tanah' => ['°C', 1],
        'ph_tanah' => ['pH', 2],
        'conductivity' => ['µS/cm', 1],
        'suhu_ctd' => ['°C', 1],
        'level_air' => ['cm', 1],
        'do_air' => ['mg/L', 2],
        'suhu_do' => ['°C', 1],
        'ph_air' => ['pH', 2],
        'suhu_ph' => ['°C', 1],
    ];

    private const PREDICTION_STEPS = 12;

    private const CCF_MAX_LAG = 20;

    /** @var array<string, mixed> per-build memo of upstream reads */
    private array $memo = [];

    private array $params;

    private int $now;

    private int $from;

    private int $offset;

    public function __construct(private readonly JogoLautRepository $repository) {}

    /**
     * @param  array{include: array<int, string>, days: int, window: int, page: int, limit: int, locale: string}  $params
     */
    public function build(array $params): array
    {
        $this->memo = [];
        $this->params = $params;
        $this->now = now()->getTimestamp();
        $this->from = $this->now - $params['days'] * 86400;
        $this->offset = (new DateTimeZone(config('jogolaut.timezone')))->getOffset(new DateTimeImmutable('@'.$this->now));

        $sections = [];

        foreach (array_keys(self::SECTIONS) as $key) {
            if (in_array($key, $params['include'], true)) {
                $sections[$key] = $this->section($key);
            }
        }

        return [
            'meta' => [
                'generated_at' => $this->format($this->now),
                'timezone' => config('jogolaut.timezone'),
                'from' => $this->format($this->from),
                'to' => $this->format($this->now),
                'params' => $params,
            ],
            'sections' => $sections,
        ];
    }

    /** True when any section of a built payload failed rather than came back empty. */
    public static function hasErrors(array $payload): bool
    {
        foreach ($payload['sections'] ?? [] as $section) {
            if (! empty($section['error'])) {
                return true;
            }
        }

        return false;
    }

    private function section(string $key): array
    {
        $type = self::SECTIONS[$key];

        try {
            return $this->{'build'.ucfirst($key)}() ?? ['type' => $type, 'empty' => true];
        } catch (Throwable $e) {
            report($e);

            return ['type' => $type, 'empty' => true, 'error' => true];
        }
    }

    // -- time-series sections ---------------------------------------------

    private function buildCo2(): ?array
    {
        $co2 = $this->co2();

        return $this->timeseries($co2['ts'], [
            ['co2_tanah', 'data_co2', $co2['values']['co2_tanah']],
            ['co2_udara', 'scd41_data', $this->alignTo($co2['ts'], $this->scd41(), 'co2_udara')],
            ['pasut_ma', 'pasut', $this->pasutMa()],
        ]);
    }

    private function buildFlux(): ?array
    {
        $co2 = $this->co2();

        if ($co2['ts'] === []) {
            return null;
        }

        $flux = Eco::computeFlux($co2['ts'], $co2['values']['co2_tanah']);
        $maWindow = (int) config('jogolaut.ma_window');
        $hourly = Eco::hourlyFlux($co2['ts'], $flux['respirasi'], $flux['carbon_flux'], $this->offset, $maWindow);

        return $this->timeseries($hourly['times'], [
            ['respirasi', 'data_co2', $hourly['respirasi']],
            ['carbon_flux', 'data_co2', $hourly['carbon_flux']],
        ]) + [
            'resolution' => 'hour',
            'ma_window' => $maWindow,
            // Positive = emission (respiration), negative = uptake.
            'latest' => [
                'respirasi' => $this->round($this->lastValue($flux['respirasi']), self::SERIES['respirasi'][1]),
                'carbon_flux' => $this->round($this->lastValue($flux['carbon_flux']), self::SERIES['carbon_flux'][1]),
            ],
        ];
    }

    private function buildDo(): ?array
    {
        $do = $this->dissolved();

        return $this->timeseries($do['ts'], [
            ['do', 'dissolve_oxygen', $do['values']['do']],
            ['suhu_air', 'dissolve_oxygen', $do['values']['suhu_air']],
            ['pasut_ma', 'pasut', $this->pasutMaOn($do['ts'])],
            ['curah_hujan', 'menara', $this->alignTo($do['ts'], $this->menara(), 'curah_hujan')],
        ]);
    }

    private function buildPh(): ?array
    {
        $ph = $this->ph();

        return $this->timeseries($ph['ts'], [
            ['ph', 'ph_air', $ph['values']['ph']],
            ['suhu_air', 'ph_air', $ph['values']['suhu_air']],
            ['pasut_ma', 'pasut', $this->pasutMaOn($ph['ts'])],
        ]);
    }

    private function buildCtd(): ?array
    {
        $ctd = $this->ctd();

        return $this->timeseries($ctd['ts'], [
            ['conductivity', 'ctd', $ctd['values']['conductivity']],
            ['suhu_air', 'ctd', $ctd['values']['suhu_air']],
            ['level_air', 'ctd', $ctd['values']['level_air']],
            ['pasut_ma', 'pasut', $this->pasutMaOn($ctd['ts'])],
        ]);
    }

    private function buildAtm(): ?array
    {
        $menara = $this->menara();

        return $this->timeseries($menara['ts'], [
            ['kec_angin', 'menara', $menara['values']['kec_angin']],
            ['arah_angin', 'menara', $menara['values']['arah_angin']],
            ['suhu_udara', 'scd41_data', $this->alignTo($menara['ts'], $this->scd41(), 'suhu_udara')],
            ['kelembaban', 'scd41_data', $this->alignTo($menara['ts'], $this->scd41(), 'kelembaban')],
            ['curah_hujan', 'menara', $menara['values']['curah_hujan']],
        ]);
    }

    // -- derived sections -------------------------------------------------

    private function buildDiurnal(): ?array
    {
        $co2 = $this->co2();

        if ($co2['ts'] === []) {
            return null;
        }

        $stats = Stats::hourlyStats($co2['ts'], $co2['values']['co2_tanah'], $this->offset);
        $present = array_filter($stats['means'], fn ($v) => $v !== null);

        return [
            'type' => 'category',
            'x' => range(0, 23),
            'x_unit' => 'hour',
            'series' => [
                $this->series('mean', 'data_co2', $stats['means']),
                $this->series('std', 'data_co2', $stats['stds']),
            ],
            'peak_hour' => $present ? array_search(max($present), $present, true) : null,
        ];
    }

    private function buildWindrose(): ?array
    {
        $menara = $this->menara();
        $readings = [];

        foreach ($menara['values']['arah_angin'] as $i => $deg) {
            $speed = $menara['values']['kec_angin'][$i];

            if ($deg !== null && $speed !== null) {
                $readings[] = ['deg' => $deg, 'speed' => $speed];
            }
        }

        $rose = Eco::windRose($readings);

        if ($rose['total'] === 0) {
            return null;
        }

        $directions = [];
        $binTotals = array_fill_keys(array_keys(Eco::SPEED_BINS), 0);

        foreach (Eco::DIRECTIONS as $i => $dir) {
            $directions[] = [
                'dir' => $dir,
                'deg' => $i * 22.5,
                'label' => $this->trans("direction.{$dir}"),
                'count' => array_sum($rose['directions'][$dir]),
                'by_speed' => $rose['directions'][$dir],
            ];

            foreach ($rose['directions'][$dir] as $bin => $count) {
                $binTotals[$bin] += $count;
            }
        }

        $speedBins = [];
        $lower = 0;

        foreach (Eco::SPEED_BINS as $bin => $upper) {
            $speedBins[] = ['key' => $bin, 'min' => $lower, 'max' => $upper, 'count' => $binTotals[$bin]];
            $lower = $upper;
        }

        $beaufort = fn (float $v) => ['code' => $code = Eco::beaufort($v), 'label' => $this->trans("beaufort.{$code}")];

        return [
            'type' => 'polar',
            'unit' => 'm/s',
            'directions' => $directions,
            'speed_bins' => $speedBins,
            'summary' => [
                'total' => $rose['total'],
                'dominant' => ['dir' => $rose['dominant'], 'label' => $this->trans("direction.{$rose['dominant']}")],
                'dominant_speed_bin' => $rose['dominant_speed_bin'],
                'avg_speed' => round($rose['avg_speed'], 1),
                'max_speed' => round($rose['max_speed'], 1),
                'beaufort_avg' => $beaufort($rose['avg_speed']),
                'beaufort_max' => $beaufort($rose['max_speed']),
            ],
        ];
    }

    private function buildCorrelation(): ?array
    {
        $co2 = $this->co2();

        if ($co2['ts'] === []) {
            return null;
        }

        $ts = $co2['ts'];
        $variables = [
            'co2_tanah' => $co2['values']['co2_tanah'],
            'pasut_ma' => $this->pasutMa(),
            'suhu_udara' => $this->alignTo($ts, $this->scd41(), 'suhu_udara'),
            'kelembaban' => $this->alignTo($ts, $this->scd41(), 'kelembaban'),
            'ph_tanah' => $co2['values']['soil_ph'],
            'co2_udara' => $this->alignTo($ts, $this->scd41(), 'co2_udara'),
            'ph_air' => $this->alignTo($ts, $this->ph(), 'ph'),
        ];

        $keys = array_keys($variables);
        $values = [];
        $maxPair = null;

        foreach ($keys as $a => $keyA) {
            foreach ($keys as $b => $keyB) {
                // Symmetric: compute the upper triangle once and mirror it.
                $r = $b < $a ? $values[$b][$a] : $this->round(Stats::correlation($variables[$keyA], $variables[$keyB]), 3);
                $values[$a][$b] = $r;

                if ($b > $a && $r !== null && ($maxPair === null || abs($r) > abs($maxPair['r']))) {
                    $maxPair = ['a' => $keyA, 'b' => $keyB, 'r' => $r];
                }
            }
        }

        return [
            'type' => 'matrix',
            'method' => 'pearson',
            'vars' => array_map(fn ($key) => ['key' => $key, 'label' => $this->trans("series.{$key}")], $keys),
            'values' => $values,
            'max_pair' => $maxPair,
        ];
    }

    private function buildAnalysis(): ?array
    {
        $co2 = $this->co2();

        if ($co2['ts'] === []) {
            return null;
        }

        $soil = $co2['values']['co2_tanah'];
        $pasut = $this->pasutMa();
        $dec = self::SERIES['co2_tanah'][1];

        $out = Stats::detectOutliers($soil);
        $outliers = [];

        foreach ($out['outliers'] as $i => $value) {
            $outliers[] = ['index' => $i, 'x' => $this->format($co2['ts'][$i]), 'value' => $this->round($value, $dec)];
        }

        $ccf = Stats::ccf($soil, $pasut, self::CCF_MAX_LAG);
        $scored = array_filter($ccf, fn ($r) => $r !== null);
        $bestLag = $scored ? array_search(max($scored), $scored, true) : null;

        // Regress soil CO₂ on the tide it responds to: co2[i + lag] against
        // pasut[i], the same pairing ccf() scored. (The old page paired them
        // the other way round, so its line did not match its own lag.)
        $lag = $bestLag ?? 0;
        $n = count($soil);
        $regression = Stats::linearRegression(array_slice($pasut, 0, max(0, $n - $lag)), array_slice($soil, $lag));

        [$estimate, $predictions] = $this->predict($pasut, $regression, $dec);

        return [
            'type' => 'analysis',
            'unit' => self::SERIES['co2_tanah'][0],
            'outliers' => [
                'method' => 'iqr',
                'items' => $outliers,
                'bounds' => [
                    'lower' => $this->round($out['lower'], $dec),
                    'upper' => $this->round($out['upper'], $dec),
                    'q1' => $this->round($out['q1'], $dec),
                    'q3' => $this->round($out['q3'], $dec),
                ],
            ],
            'ccf' => array_map(fn ($lag, $r) => ['lag' => $lag, 'r' => $this->round($r, 3)], array_keys($ccf), $ccf),
            'best_lag' => $bestLag,
            'lag_minutes' => $bestLag === null ? null : $bestLag * (int) config('jogolaut.sampling_minutes'),
            'regression' => [
                'x' => 'pasut_ma',
                'y' => 'co2_tanah',
                'slope' => round($regression['slope'], 4),
                'intercept' => round($regression['intercept'], 4),
                'n' => $regression['n'],
            ],
            'estimate_now' => $estimate,
            'predictions' => $predictions,
        ];
    }

    /**
     * The old page's forecast: carry the tide forward along its last 5-point
     * trend and read soil CO₂ off the regression line, one sampling interval
     * per step.
     *
     * @return array{0: ?float, 1: array<int, array{step: int, minutes_ahead: int, value: ?float}>}
     */
    private function predict(array $pasut, array $regression, int $dec): array
    {
        $last = $this->lastValue($pasut);

        if ($last === null || $regression['n'] < 2) {
            return [null, []];
        }

        $recent = array_values(array_filter(array_slice($pasut, -5), fn ($v) => $v !== null));
        $trend = count($recent) >= 5 ? ($recent[4] - $recent[0]) / 4 : 0.0;
        $minutes = (int) config('jogolaut.sampling_minutes');
        $line = fn (float $x) => $this->round($regression['slope'] * $x + $regression['intercept'], $dec);

        $predictions = [];

        for ($step = 1; $step <= self::PREDICTION_STEPS; $step++) {
            $predictions[] = ['step' => $step, 'minutes_ahead' => $step * $minutes, 'value' => $line($last + $trend * $step)];
        }

        return [$line($last), $predictions];
    }

    private function buildEcosystem(): ?array
    {
        $soil = $this->co2()['values']['co2_tanah'];
        $do = $this->dissolved()['values']['do'];

        if ($soil === [] && $do === []) {
            return null;
        }

        $co2Trend = Stats::trend($soil, 5);
        $doTrend = Stats::trend($do, 5);
        $pasutTrend = Stats::trend($this->pasutMa(), 5);
        $status = Eco::status($co2Trend, $doTrend, $pasutTrend);

        // Short-term soil CO₂ change: mean of the last three readings against
        // the three before them.
        $recent = array_values(array_filter(array_slice($soil, -6), fn ($v) => $v !== null));
        $delta = count($recent) === 6
            ? round(array_sum(array_slice($recent, 3)) / 3 - array_sum(array_slice($recent, 0, 3)) / 3, 1)
            : null;

        return [
            'type' => 'status',
            'code' => $status['code'],
            'color' => $status['color'],
            'label' => $this->trans("status.{$status['code']}.label"),
            'description' => $this->trans("status.{$status['code']}.description"),
            'confidence' => Eco::confidence($co2Trend, $doTrend),
            'trends' => [
                'co2_tanah' => round($co2Trend, 3),
                'do' => round($doTrend, 3),
                'pasut_ma' => round($pasutTrend, 3),
            ],
            'co2_change' => [
                'delta' => $delta,
                'direction' => $delta === null ? null : Eco::co2Direction($delta),
            ],
        ];
    }

    private function buildKpi(): ?array
    {
        $summary = $this->summary();

        // The tide gauge reports distance to the water; the time-series report
        // tide level. Both are listed, under distinct keys and labels, so the
        // two can never be confused for one another.
        $ref = (float) config('jogolaut.ref_pasut');
        $raw = $summary['jarak_air'];
        $flip = fn (?float $v) => $v === null ? null : $ref - $v;
        $summary['pasut'] = [
            'latest' => $flip($raw['latest']),
            'latest_at' => $raw['latest_at'],
            'min' => $flip($raw['max']),
            'max' => $flip($raw['min']),
            'yesterday_avg' => $flip($raw['yesterday_avg']),
        ];

        $sources = ['pasut' => 'pasut'];

        foreach (JogoLautRepository::SUMMARY as $table => $definition) {
            foreach (array_keys($definition['columns']) as $key) {
                $sources[$key] = $table;
            }
        }

        $items = [];

        foreach (self::KPI as $key => [$unit, $dec]) {
            $s = $summary[$key];
            $items[] = [
                'key' => $key,
                'label' => $this->trans("kpi.{$key}"),
                'unit' => $unit,
                'dec' => $dec,
                'source' => $sources[$key],
                'latest' => $this->round($s['latest'], $dec),
                'latest_at' => $s['latest_at'] === null ? null : $this->format($s['latest_at']),
                'min' => $this->round($s['min'], $dec),
                'max' => $this->round($s['max'], $dec),
                // Latest reading against yesterday's mean (station calendar day).
                'delta' => $s['latest'] === null || $s['yesterday_avg'] === null
                    ? null
                    : $this->round($s['latest'] - $s['yesterday_avg'], $dec),
            ];
        }

        if (array_filter($items, fn ($item) => $item['latest'] !== null) === []) {
            return null;
        }

        return ['type' => 'stats', 'items' => $items];
    }

    private function buildGauges(): ?array
    {
        $summary = $this->summary();
        $t = $summary['suhu_udara']['latest'];
        $rh = $summary['kelembaban_udara']['latest'];

        $heatIndex = $t === null || $rh === null ? null : Eco::heatIndex($t, $rh);
        $gauges = [
            ['heat_index', $heatIndex, '°C', 1, fn ($v) => Eco::heatIndexLevel($v)],
            ['do', $summary['do_air']['latest'], 'mg/L', 2, fn ($v) => Eco::doLevel($v)],
            ['conductivity', $summary['conductivity']['latest'], 'µS/cm', 1, fn ($v) => Eco::conductivityLevel($v)],
            ['water_temp', $summary['suhu_ctd']['latest'], '°C', 1, fn ($v) => Eco::waterTempLevel($v)],
            ['ph_air', $summary['ph_air']['latest'], 'pH', 2, fn ($v) => Eco::phLevel($v)],
        ];

        $items = [];

        foreach ($gauges as [$key, $value, $unit, $dec, $classify]) {
            $level = $value === null ? ['level' => null, 'color' => null] : $classify($value);

            $item = [
                'key' => $key,
                'label' => $this->trans("gauge.{$key}"),
                'value' => $this->round($value, $dec),
                'unit' => $unit,
                'dec' => $dec,
                'level' => $level['level'],
                'level_label' => $level['level'] === null ? null : $this->trans("level.{$key}.{$level['level']}"),
                'color' => $level['color'],
            ];

            if ($key === 'heat_index') {
                $item['description'] = $level['level'] === null ? null : $this->trans("heat_index_desc.{$level['level']}");
                $item['inputs'] = ['suhu_udara' => $this->round($t, 1), 'kelembaban_udara' => $this->round($rh, 1)];
            }

            $items[] = $item;
        }

        if (array_filter($items, fn ($item) => $item['value'] !== null) === []) {
            return null;
        }

        return ['type' => 'stats', 'items' => $items];
    }

    private function buildTable(): ?array
    {
        $limit = $this->params['limit'];
        $page = $this->params['page'];
        $result = $this->repository->co2Page($this->from, $limit, ($page - 1) * $limit);

        if ($result['total'] === 0) {
            return null;
        }

        $columnKeys = ['co2_tanah', 'temp_air', 'humidity', 'soil_moisture', 'soil_temp', 'soil_ph'];
        $columns = [['key' => 'waktu', 'label' => $this->trans('series.waktu'), 'unit' => null, 'dec' => null]];

        foreach ($columnKeys as $key) {
            [$unit, $dec] = self::SERIES[$key];
            $columns[] = ['key' => $key, 'label' => $this->trans("series.{$key}"), 'unit' => $unit, 'dec' => $dec];
        }

        $rows = array_map(function (array $row) use ($columnKeys) {
            $out = ['waktu' => $this->format($row['ts'])];

            foreach ($columnKeys as $key) {
                $out[$key] = $this->round($row[$key], self::SERIES[$key][1]);
            }

            return $out;
        }, $result['rows']);

        return [
            'type' => 'table',
            'order' => 'waktu_desc',
            'columns' => $columns,
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'pages' => max(1, (int) ceil($result['total'] / $limit)),
            ],
        ];
    }

    // -- upstream reads, each memoised for the build -----------------------

    private function co2(): array
    {
        return $this->memo['co2'] ??= $this->repository->series('data_co2', 'waktu', [
            'co2_tanah' => 'co2',
            'soil_moisture' => 'soil_moisture',
            'soil_ph' => 'soil_ph',
            'soil_temp' => 'soil_temp',
            'temp_air' => 'temp_air',
            'humidity' => 'humidity',
        ], $this->from);
    }

    private function scd41(): array
    {
        return $this->memo['scd41'] ??= $this->repository->series('scd41_data', 'created_at', [
            'co2_udara' => 'co2',
            'suhu_udara' => 'temperature',
            'kelembaban' => 'humidity',
        ], $this->from);
    }

    /** Tide level: the reference height minus the gauge's distance to water. */
    private function pasut(): array
    {
        if (! isset($this->memo['pasut'])) {
            $rows = $this->repository->series('pasut', 'waktu', ['jarak_air' => 'jarak_air'], $this->from);
            $ref = (float) config('jogolaut.ref_pasut');

            $this->memo['pasut'] = [
                'ts' => $rows['ts'],
                'values' => ['pasut' => array_map(fn ($v) => $v === null ? null : $ref - $v, $rows['values']['jarak_air'])],
            ];
        }

        return $this->memo['pasut'];
    }

    /**
     * Tide aligned to the soil CO₂ timeline, then smoothed with a centered
     * moving average of `window` points — the order the old page used, so
     * the lag and correlation figures match it.
     */
    private function pasutMa(): array
    {
        return $this->memo['pasut_ma'] ??= Stats::centeredMovingAverage(
            $this->alignTo($this->co2()['ts'], $this->pasut(), 'pasut'),
            $this->params['window'],
        );
    }

    /** The smoothed tide carried onto another sensor's timeline. */
    private function pasutMaOn(array $ts): array
    {
        return Stats::align($ts, $this->co2()['ts'], $this->pasutMa(), $this->threshold());
    }

    private function dissolved(): array
    {
        return $this->memo['do'] ??= $this->repository->series('dissolve_oxygen', 'waktu', [
            'do' => 'do_air',
            'suhu_air' => 'suhu_air',
        ], $this->from);
    }

    private function ph(): array
    {
        return $this->memo['ph'] ??= $this->repository->series('ph_air', 'waktu', [
            'ph' => 'ph',
            'suhu_air' => 'suhu_air',
        ], $this->from);
    }

    private function ctd(): array
    {
        return $this->memo['ctd'] ??= $this->repository->series('ctd', 'waktu', [
            'conductivity' => 'conductivity',
            'suhu_air' => 'suhu_air',
            'level_air' => 'level_air',
        ], $this->from);
    }

    /** Weather mast. Wind speed is stored in cm/s. */
    private function menara(): array
    {
        if (! isset($this->memo['menara'])) {
            $rows = $this->repository->series('menara', 'waktu', [
                'kec_angin' => 'kec_angin',
                'arah_angin' => 'arah_angin',
                'curah_hujan' => 'curah_hujan',
            ], $this->from);

            $rows['values']['kec_angin'] = array_map(fn ($v) => $v === null ? null : $v / 100, $rows['values']['kec_angin']);
            $this->memo['menara'] = $rows;
        }

        return $this->memo['menara'];
    }

    private function summary(): array
    {
        if (! isset($this->memo['summary'])) {
            // "Yesterday" is the station's calendar day, not the UTC one.
            $tz = new DateTimeZone(config('jogolaut.timezone'));
            $today = (new DateTimeImmutable('@'.$this->now))->setTimezone($tz)->setTime(0, 0)->getTimestamp();

            $this->memo['summary'] = $this->repository->summary($this->from, $today - 86400, $today);
        }

        return $this->memo['summary'];
    }

    // -- shaping ----------------------------------------------------------

    private function alignTo(array $ts, array $source, string $key): array
    {
        return Stats::align($ts, $source['ts'], $source['values'][$key], $this->threshold());
    }

    /**
     * @param  array<int, int>  $ts
     * @param  array<int, array{0: string, 1: string, 2: array<int, ?float>}>  $series  [key, source table, data]
     */
    private function timeseries(array $ts, array $series): ?array
    {
        if ($ts === []) {
            return null;
        }

        return [
            'type' => 'timeseries',
            'x' => array_map($this->format(...), $ts),
            'series' => array_map(fn ($s) => $this->series(...$s), $series),
        ];
    }

    private function series(string $key, string $source, array $data): array
    {
        [$unit, $dec] = self::SERIES[$key];

        return [
            'key' => $key,
            'label' => $this->trans("series.{$key}"),
            'unit' => $unit,
            'dec' => $dec,
            'source' => $source,
            'data' => array_map(fn ($v) => $this->round($v, $dec), array_values($data)),
        ];
    }

    private function round(?float $value, int $dec): ?float
    {
        return $value === null || ! is_finite($value) ? null : round($value, $dec);
    }

    private function lastValue(array $data): ?float
    {
        for ($i = count($data) - 1; $i >= 0; $i--) {
            if ($data[$i] !== null) {
                return $data[$i];
            }
        }

        return null;
    }

    private function format(int $ts): string
    {
        return gmdate('Y-m-d H:i:s', $ts + $this->offset);
    }

    private function threshold(): int
    {
        return (int) config('jogolaut.align_threshold_seconds', 3600);
    }

    private function trans(string $key): string
    {
        return __("jogolaut.{$key}", [], $this->params['locale']);
    }
}
