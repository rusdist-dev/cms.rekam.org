<?php

namespace App\Services\JogoLaut;

/**
 * Domain rules of the JOGO LAUT station — a port of perikanan.org's
 * App\Services\JogoLaut\EcosystemService, plus the gauge thresholds that used
 * to live inline in its MonitoringController.
 *
 * Everything returns codes, never presentation: a status is `photosynthesis`,
 * a level is `caution`, a colour is a token name (`amber`) the frontend maps
 * to its own palette. Translated labels are added by the caller.
 */
class JogoLautEcosystemService
{
    public const DIRECTIONS = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];

    /** Upper bounds (m/s, exclusive); the last bin is open-ended. */
    public const SPEED_BINS = ['0-2' => 2, '2-4' => 4, '4-6' => 6, '6+' => null];

    /**
     * Converts a rate of change in ppm/s inside the chamber into a surface
     * flux: chamber height (V / A) × CO₂ density (1.96 kg/m³) × 1000.
     */
    public static function fluxK(): float
    {
        $area = M_PI * (config('jogolaut.chamber_diameter') / 2) ** 2;

        return (config('jogolaut.chamber_volume') / $area) * 1.96 * 1000;
    }

    /**
     * Soil respiration from consecutive soil CO₂ readings, and its carbon
     * fraction (12/44). The first point, and any point next to a gap or a
     * non-increasing timestamp, is null.
     *
     * @param  array<int, int>  $times  Unix seconds
     * @param  array<int, ?float>  $co2
     * @return array{respirasi: array<int, ?float>, carbon_flux: array<int, ?float>}
     */
    public static function computeFlux(array $times, array $co2): array
    {
        $n = count($co2);
        $k = self::fluxK();
        $respirasi = $carbonFlux = array_fill(0, $n, null);

        for ($i = 1; $i < $n; $i++) {
            if ($co2[$i] === null || $co2[$i - 1] === null) {
                continue;
            }

            $dt = $times[$i] - $times[$i - 1];

            if ($dt <= 0) {
                continue;
            }

            $r = $k * (($co2[$i] - $co2[$i - 1]) / $dt);
            $respirasi[$i] = $r;
            $carbonFlux[$i] = $r * (12 / 44);
        }

        return ['respirasi' => $respirasi, 'carbon_flux' => $carbonFlux];
    }

    /**
     * Flux averaged per clock hour (station time), then smoothed with a
     * centered moving average. An hour with readings but no computable flux
     * is null — the original reported it as 0, which drew a false "neutral".
     *
     * @return array{times: array<int, int>, respirasi: array<int, ?float>, carbon_flux: array<int, ?float>}
     */
    public static function hourlyFlux(array $times, array $respirasi, array $carbonFlux, int $offsetSeconds, int $maWindow = 3): array
    {
        $buckets = [];

        foreach ($times as $i => $ts) {
            // Hour start in station time, kept as a UTC instant.
            $hour = intdiv($ts + $offsetSeconds, 3600) * 3600 - $offsetSeconds;
            $buckets[$hour] ??= ['resp' => [], 'cflux' => []];

            if ($respirasi[$i] !== null) {
                $buckets[$hour]['resp'][] = $respirasi[$i];
            }

            if ($carbonFlux[$i] !== null) {
                $buckets[$hour]['cflux'][] = $carbonFlux[$i];
            }
        }

        $mean = fn (array $v) => $v ? array_sum($v) / count($v) : null;

        return [
            'times' => array_keys($buckets),
            'respirasi' => JogoLautStatsService::centeredMovingAverage(array_map(fn ($b) => $mean($b['resp']), array_values($buckets)), $maWindow),
            'carbon_flux' => JogoLautStatsService::centeredMovingAverage(array_map(fn ($b) => $mean($b['cflux']), array_values($buckets)), $maWindow),
        ];
    }

    /**
     * Which process dominates, from the short-term trends of soil CO₂, DO and
     * tide. CO₂ falling while DO rises reads as photosynthesis; the reverse as
     * respiration; otherwise a moving tide wins; otherwise nothing does.
     *
     * @return array{code: string, color: string}
     */
    public static function status(float $co2Trend, float $doTrend, float $pasutTrend): array
    {
        $thCo2 = (float) config('jogolaut.co2_trend_threshold');
        $thDo = (float) config('jogolaut.do_trend_threshold');

        return match (true) {
            $co2Trend < -$thCo2 && $doTrend > $thDo => ['code' => 'photosynthesis', 'color' => 'green'],
            $co2Trend > $thCo2 && $doTrend < -$thDo => ['code' => 'respiration', 'color' => 'coral'],
            abs($pasutTrend) > 1 => ['code' => 'tidal', 'color' => 'blue'],
            default => ['code' => 'mixed', 'color' => 'slate'],
        };
    }

    /** 0–100, how strongly the CO₂ and DO trends commit to a status. */
    public static function confidence(float $co2Trend, float $doTrend): int
    {
        return min(100, max(0, (int) round((abs($co2Trend) + abs($doTrend) * 10) * 5)));
    }

    /** Short-term direction of soil CO₂ from the difference of two 3-point means. */
    public static function co2Direction(float $delta): string
    {
        return match (true) {
            $delta > 5 => 'up',
            $delta < -5 => 'down',
            default => 'stable',
        };
    }

    /**
     * Direction × speed counts. A reading counts only when it has both a
     * direction and a speed, so every direction total equals the sum of its
     * speed bins.
     *
     * @param  array<int, array{deg: float, speed: float}>  $readings
     * @return array{directions: array<string, array<string, int>>, total: int, dominant: ?string, dominant_speed_bin: ?string, avg_speed: ?float, max_speed: ?float}
     */
    public static function windRose(array $readings): array
    {
        $empty = array_fill_keys(array_keys(self::SPEED_BINS), 0);
        $directions = array_fill_keys(self::DIRECTIONS, $empty);
        $speeds = [];

        foreach ($readings as ['deg' => $deg, 'speed' => $speed]) {
            $index = ((int) round($deg / 22.5) % 16 + 16) % 16;
            $directions[self::DIRECTIONS[$index]][self::speedBin($speed)]++;
            $speeds[] = $speed;
        }

        $total = count($speeds);
        $dirTotals = array_map('array_sum', $directions);
        $binTotals = $empty;

        foreach ($directions as $bins) {
            foreach ($bins as $bin => $count) {
                $binTotals[$bin] += $count;
            }
        }

        return [
            'directions' => $directions,
            'total' => $total,
            'dominant' => $total ? array_search(max($dirTotals), $dirTotals, true) : null,
            'dominant_speed_bin' => $total ? array_search(max($binTotals), $binTotals, true) : null,
            'avg_speed' => $total ? array_sum($speeds) / $total : null,
            'max_speed' => $total ? max($speeds) : null,
        ];
    }

    public static function speedBin(float $speed): string
    {
        foreach (self::SPEED_BINS as $bin => $upper) {
            if ($upper === null || $speed < $upper) {
                return $bin;
            }
        }

        return '6+';
    }

    /** The station's own six-step reading of the Beaufort scale (m/s). */
    public static function beaufort(float $speed): string
    {
        return match (true) {
            $speed < 1 => 'calm',
            $speed < 3 => 'light_breeze',
            $speed < 5 => 'gentle_breeze',
            $speed < 8 => 'moderate_breeze',
            $speed < 11 => 'fresh_breeze',
            default => 'strong_breeze',
        };
    }

    /**
     * Rothfusz heat index (°C). Below 27 °C or 40 % RH the regression does not
     * apply and the air temperature is returned as-is.
     */
    public static function heatIndex(float $t, float $rh): float
    {
        if ($t < 27 || $rh < 40) {
            return $t;
        }

        return -8.78469475556 + 1.61139411 * $t + 2.33854883889 * $rh
            - 0.14611605 * $t * $rh - 0.012308094 * $t * $t
            - 0.0164248277778 * $rh * $rh + 0.002211732 * $t * $t * $rh
            + 0.00072546 * $t * $rh * $rh - 0.000003582 * $t * $t * $rh * $rh;
    }

    /** @return array{level: string, color: string} */
    public static function heatIndexLevel(float $hi): array
    {
        return match (true) {
            $hi < 27 => ['level' => 'safe', 'color' => 'green'],
            $hi < 32 => ['level' => 'caution', 'color' => 'amber'],
            $hi < 41 => ['level' => 'warning', 'color' => 'coral'],
            $hi < 54 => ['level' => 'danger', 'color' => 'coral'],
            default => ['level' => 'extreme', 'color' => 'coral'],
        };
    }

    /** Dissolved oxygen, mg/L. @return array{level: string, color: string} */
    public static function doLevel(float $do): array
    {
        return match (true) {
            $do < 2 => ['level' => 'hypoxic', 'color' => 'coral'],
            $do < 5 => ['level' => 'low', 'color' => 'amber'],
            $do < 8 => ['level' => 'normal', 'color' => 'teal'],
            default => ['level' => 'optimal', 'color' => 'green'],
        };
    }

    /** Conductivity, µS/cm. @return array{level: string, color: string} */
    public static function conductivityLevel(float $cond): array
    {
        return match (true) {
            $cond < 200 => ['level' => 'low', 'color' => 'blue'],
            $cond < 800 => ['level' => 'normal', 'color' => 'teal'],
            $cond < 2000 => ['level' => 'high', 'color' => 'amber'],
            default => ['level' => 'saline', 'color' => 'violet'],
        };
    }

    /** Water temperature, °C. @return array{level: string, color: string} */
    public static function waterTempLevel(float $t): array
    {
        return match (true) {
            $t < 25 => ['level' => 'cool', 'color' => 'blue'],
            $t < 30 => ['level' => 'normal', 'color' => 'teal'],
            $t < 35 => ['level' => 'warm', 'color' => 'amber'],
            default => ['level' => 'hot', 'color' => 'coral'],
        };
    }

    /**
     * Water pH. Zero or below is a sensor with no reading rather than an
     * impossibly acidic sea, so it has no level.
     *
     * @return array{level: ?string, color: ?string}
     */
    public static function phLevel(float $ph): array
    {
        return match (true) {
            $ph <= 0 => ['level' => null, 'color' => null],
            $ph < 6.5 => ['level' => 'acidic', 'color' => 'coral'],
            $ph <= 9 => ['level' => 'optimal', 'color' => 'green'],
            default => ['level' => 'basic', 'color' => 'amber'],
        };
    }
}
