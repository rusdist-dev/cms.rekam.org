<?php

namespace App\Services\JogoLaut;

use App\Services\DatasourceRegistry;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;

/**
 * Every read the JOGO LAUT payload makes — a port of perikanan.org's
 * DataRepository, with two changes that matter:
 *
 *  - Time bounds are computed in PHP and bound as parameters. The original
 *    spliced `NOW() - INTERVAL {days} DAY` into the SQL from config; here no
 *    request value ever reaches the query text.
 *  - Timezone conversion happens in PHP rather than with CONVERT_TZ(), so the
 *    same queries run on MySQL in production and SQLite under test.
 *
 * Timestamps leave this class as Unix seconds. Formatting them for display is
 * the caller's job, after alignment, so the arithmetic never touches a string.
 */
class JogoLautRepository
{
    /**
     * Summary columns per table: KPI key => column. Each table's latest row,
     * min, max, and yesterday's mean are taken over the same window.
     */
    public const SUMMARY = [
        'scd41_data' => ['time' => 'created_at', 'columns' => ['suhu_udara' => 'temperature', 'kelembaban_udara' => 'humidity']],
        'menara' => ['time' => 'waktu', 'columns' => ['curah_hujan' => 'curah_hujan']],
        'pasut' => ['time' => 'waktu', 'columns' => ['jarak_air' => 'jarak_air']],
        'data_co2' => ['time' => 'waktu', 'columns' => [
            'co2_lapangan' => 'co2', 'suhu_co2' => 'temp_air', 'kelembaban_co2' => 'humidity',
            'kelembaban_tanah' => 'soil_moisture', 'suhu_tanah' => 'soil_temp', 'ph_tanah' => 'soil_ph',
        ]],
        'ctd' => ['time' => 'waktu', 'columns' => ['conductivity' => 'conductivity', 'suhu_ctd' => 'suhu_air', 'level_air' => 'level_air']],
        'dissolve_oxygen' => ['time' => 'waktu', 'columns' => ['do_air' => 'do_air', 'suhu_do' => 'suhu_air']],
        'ph_air' => ['time' => 'waktu', 'columns' => ['ph_air' => 'ph', 'suhu_ph' => 'suhu_air']],
    ];

    private readonly DateTimeZone $sourceTz;

    public function __construct(private readonly DatasourceRegistry $datasources)
    {
        $this->sourceTz = new DateTimeZone(config('jogolaut.source_timezone', '+00:00'));
    }

    /**
     * One table's readings since $from, oldest first, as parallel lists.
     *
     * @param  array<string, string>  $columns  output key => column
     * @return array{ts: array<int, int>, values: array<string, array<int, ?float>>}
     */
    public function series(string $table, string $timeColumn, array $columns, int $from): array
    {
        $rows = $this->db()->table($table)
            ->select(array_merge([$timeColumn], array_values($columns)))
            ->where($timeColumn, '>=', $this->stamp($from))
            ->orderBy($timeColumn)
            ->get();

        $ts = [];
        $values = array_fill_keys(array_keys($columns), []);

        foreach ($rows as $row) {
            $ts[] = $this->parse($row->{$timeColumn});

            foreach ($columns as $key => $column) {
                $values[$key][] = self::number($row->{$column});
            }
        }

        return ['ts' => $ts, 'values' => $values];
    }

    /**
     * One page of soil-sensor rows since $from, newest first.
     *
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function co2Page(int $from, int $limit, int $offset): array
    {
        $query = $this->db()->table('data_co2')->where('waktu', '>=', $this->stamp($from));

        $rows = (clone $query)
            ->select(['waktu', 'co2', 'temp_air', 'humidity', 'soil_moisture', 'soil_temp', 'soil_ph'])
            ->orderByDesc('waktu')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($row) => [
                'ts' => $this->parse($row->waktu),
                'co2_tanah' => self::number($row->co2),
                'temp_air' => self::number($row->temp_air),
                'humidity' => self::number($row->humidity),
                'soil_moisture' => self::number($row->soil_moisture),
                'soil_temp' => self::number($row->soil_temp),
                'soil_ph' => self::number($row->soil_ph),
            ])
            ->all();

        return ['total' => $query->count(), 'rows' => $rows];
    }

    /**
     * Latest value, min and max since $from, and the mean over
     * [$yesterdayFrom, $yesterdayTo), for every column in SUMMARY.
     *
     * Two queries per table: one aggregate, one for the latest row. The
     * original used one correlated subquery per column — 22 of them.
     *
     * @return array<string, array{latest: ?float, latest_at: ?int, min: ?float, max: ?float, yesterday_avg: ?float}>
     */
    public function summary(int $from, int $yesterdayFrom, int $yesterdayTo): array
    {
        $out = [];

        foreach (self::SUMMARY as $table => ['time' => $time, 'columns' => $columns]) {
            $selects = [];
            $bindings = [];

            foreach ($columns as $key => $column) {
                $selects[] = "min({$column}) as {$key}_min";
                $selects[] = "max({$column}) as {$key}_max";
                $selects[] = "avg(case when {$time} >= ? and {$time} < ? then {$column} end) as {$key}_yavg";
                array_push($bindings, $this->stamp($yesterdayFrom), $this->stamp($yesterdayTo));
            }

            $aggregate = $this->db()->table($table)
                ->selectRaw(implode(', ', $selects), $bindings)
                ->where($time, '>=', $this->stamp($from))
                ->first();

            $latest = $this->db()->table($table)
                ->select(array_merge([$time], array_values($columns)))
                ->where($time, '>=', $this->stamp($from))
                ->orderByDesc($time)
                ->first();

            foreach ($columns as $key => $column) {
                $out[$key] = [
                    'latest' => $latest ? self::number($latest->{$column}) : null,
                    'latest_at' => $latest ? $this->parse($latest->{$time}) : null,
                    'min' => self::number($aggregate->{"{$key}_min"} ?? null),
                    'max' => self::number($aggregate->{"{$key}_max"} ?? null),
                    'yesterday_avg' => self::number($aggregate->{"{$key}_yavg"} ?? null),
                ];
            }
        }

        return $out;
    }

    private function db(): Connection
    {
        return $this->datasources->connection('jogolaut');
    }

    /** Unix seconds as the upstream column stores them. */
    private function stamp(int $ts): string
    {
        return (new DateTimeImmutable('@'.$ts))->setTimezone($this->sourceTz)->format('Y-m-d H:i:s');
    }

    private function parse(string $value): int
    {
        return (new DateTimeImmutable($value, $this->sourceTz))->getTimestamp();
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
