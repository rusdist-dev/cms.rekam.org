<?php

namespace App\Services\JogoLaut;

/**
 * The numerical toolkit behind the JOGO LAUT monitoring payload — a port of
 * perikanan.org's App\Services\JogoLaut\StatsService.
 *
 * Every function is pure and takes series as lists of `?float`: a null is a
 * gap in the record, never a zero, and none of these functions may turn one
 * into the other. Timestamps are Unix seconds, so nothing here depends on the
 * PHP default timezone.
 *
 * Two deliberate departures from the original:
 *
 *  - align() is a two-pointer merge instead of a nested scan (O(n + m) rather
 *    than O(n × m)); it returns exactly what the scan did, ties included.
 *  - correlation() returns null rather than 0 when r is undefined (fewer than
 *    two pairs, or a constant series): "no relationship" and "cannot tell"
 *    are different claims.
 */
class JogoLautStatsService
{
    /**
     * Centered moving average. Gaps inside the window are skipped; a point
     * whose whole window is empty stays null.
     *
     * @param  array<int, ?float>  $data
     * @return array<int, ?float>
     */
    public static function centeredMovingAverage(array $data, int $window): array
    {
        $data = array_values($data);
        $n = count($data);
        $half = intdiv($window, 2);
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0;
            $count = 0;

            for ($j = max(0, $i - $half), $end = min($n - 1, $i + $half); $j <= $end; $j++) {
                if ($data[$j] !== null) {
                    $sum += $data[$j];
                    $count++;
                }
            }

            $out[] = $count > 0 ? $sum / $count : null;
        }

        return $out;
    }

    /**
     * Pearson's r over the positions where both series carry a value.
     *
     * @param  array<int, ?float>  $x
     * @param  array<int, ?float>  $y
     */
    public static function correlation(array $x, array $y): ?float
    {
        [$xs, $ys] = self::pairs($x, $y);
        $n = count($xs);

        if ($n < 2) {
            return null;
        }

        $mx = array_sum($xs) / $n;
        $my = array_sum($ys) / $n;
        $num = $dx2 = $dy2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $xs[$i] - $mx;
            $dy = $ys[$i] - $my;
            $num += $dx * $dy;
            $dx2 += $dx * $dx;
            $dy2 += $dy * $dy;
        }

        return ($dx2 > 0 && $dy2 > 0) ? $num / sqrt($dx2 * $dy2) : null;
    }

    /**
     * Cross-correlation for lags 0..$maxLag: r between x[i + lag] and y[i].
     * A positive best lag therefore means x responds to y that many points
     * later.
     *
     * @return array<int, ?float> indexed by lag
     */
    public static function ccf(array $x, array $y, int $maxLag = 20): array
    {
        $x = array_values($x);
        $y = array_values($y);
        $maxLag = max(0, min($maxLag, count($x) - 1));
        $out = [];

        for ($lag = 0; $lag <= $maxLag; $lag++) {
            $out[] = self::correlation(
                array_slice($x, $lag),
                array_slice($y, 0, max(0, count($y) - $lag)),
            );
        }

        return $out;
    }

    /**
     * Tukey fences. Quartiles are taken by index exactly as the original did,
     * so the bounds match the old dashboard's.
     *
     * @return array{outliers: array<int, float>, lower: ?float, upper: ?float, q1: ?float, q3: ?float}
     */
    public static function detectOutliers(array $data, float $multiplier = 1.5): array
    {
        $sorted = array_values(array_filter($data, fn ($v) => $v !== null));
        sort($sorted);
        $n = count($sorted);

        if ($n < 4) {
            return ['outliers' => [], 'lower' => null, 'upper' => null, 'q1' => null, 'q3' => null];
        }

        $q1 = $sorted[(int) floor(0.25 * $n)];
        $q3 = $sorted[(int) floor(0.75 * $n)];
        $iqr = $q3 - $q1;
        $lower = $q1 - $multiplier * $iqr;
        $upper = $q3 + $multiplier * $iqr;

        $outliers = [];

        foreach (array_values($data) as $i => $value) {
            if ($value !== null && ($value < $lower || $value > $upper)) {
                $outliers[$i] = $value;
            }
        }

        return compact('outliers', 'lower', 'upper', 'q1', 'q3');
    }

    /**
     * Ordinary least squares of y on x.
     *
     * @return array{slope: float, intercept: float, n: int}
     */
    public static function linearRegression(array $x, array $y): array
    {
        [$xs, $ys] = self::pairs($x, $y);
        $n = count($xs);

        if ($n < 2) {
            return ['slope' => 0.0, 'intercept' => 0.0, 'n' => $n];
        }

        $mx = array_sum($xs) / $n;
        $my = array_sum($ys) / $n;
        $num = $den = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $xs[$i] - $mx;
            $num += $dx * ($ys[$i] - $my);
            $den += $dx * $dx;
        }

        $slope = $den > 0 ? $num / $den : 0.0;

        return ['slope' => $slope, 'intercept' => $my - $slope * $mx, 'n' => $n];
    }

    /**
     * Change across the last $n points, divided by $n — 0 unless all $n are
     * present. The division by $n (not $n - 1) is the original's, and the
     * ecosystem thresholds in config/jogolaut.php are tuned to it.
     */
    public static function trend(array $data, int $n = 5): float
    {
        $slice = array_slice(array_values($data), -$n);

        if (count($slice) < $n || in_array(null, $slice, true)) {
            return 0.0;
        }

        return ($slice[$n - 1] - $slice[0]) / $n;
    }

    /**
     * Mean and population standard deviation per hour of the day (0–23).
     * An hour with no readings is null in both lists.
     *
     * @param  array<int, int>  $times  Unix seconds
     * @return array{means: array<int, ?float>, stds: array<int, ?float>}
     */
    public static function hourlyStats(array $times, array $values, int $offsetSeconds): array
    {
        $buckets = [];

        foreach (array_values($times) as $i => $ts) {
            $value = $values[$i] ?? null;

            if ($value !== null) {
                $buckets[(int) gmdate('G', $ts + $offsetSeconds)][] = $value;
            }
        }

        $means = $stds = [];

        for ($h = 0; $h < 24; $h++) {
            $vals = $buckets[$h] ?? [];
            $n = count($vals);

            if ($n === 0) {
                $means[$h] = $stds[$h] = null;

                continue;
            }

            $mean = array_sum($vals) / $n;
            $means[$h] = $mean;
            $stds[$h] = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $vals)) / $n);
        }

        return compact('means', 'stds');
    }

    /**
     * For each primary timestamp, the secondary value whose timestamp is
     * nearest — or null when the nearest is more than $threshold seconds away.
     *
     * Both time lists must be ascending (every repository query orders by
     * time). On a tie, or among duplicate timestamps, the earliest secondary
     * index wins, as it did in the original nested scan.
     *
     * @param  array<int, int>  $primary  Unix seconds, ascending
     * @param  array<int, int>  $secondary  Unix seconds, ascending
     * @param  array<int, ?float>  $values  parallel to $secondary
     * @return array<int, ?float> parallel to $primary
     */
    public static function align(array $primary, array $secondary, array $values, int $threshold = 3600): array
    {
        $secondary = array_values($secondary);
        $values = array_values($values);
        $m = count($secondary);

        if ($m === 0) {
            return array_fill(0, count($primary), null);
        }

        // First index of each run of equal timestamps, so a duplicate resolves
        // to its earliest row whichever side the pointer reaches it from.
        $runStart = [];

        for ($k = 0; $k < $m; $k++) {
            $runStart[$k] = ($k > 0 && $secondary[$k] === $secondary[$k - 1]) ? $runStart[$k - 1] : $k;
        }

        $out = [];
        $j = 0; // last index with secondary[j] < ts, or 0

        foreach ($primary as $ts) {
            while ($j + 1 < $m && $secondary[$j + 1] < $ts) {
                $j++;
            }

            $best = $runStart[$j];
            $bestDiff = abs($secondary[$j] - $ts);

            // The first timestamp at or after $ts is the only other candidate.
            $next = $secondary[$j] < $ts ? $j + 1 : $j;

            if ($next < $m) {
                $diff = abs($secondary[$next] - $ts);

                if ($diff < $bestDiff) {
                    $best = $runStart[$next];
                    $bestDiff = $diff;
                }
            }

            $out[] = $bestDiff <= $threshold ? $values[$best] : null;
        }

        return $out;
    }

    /**
     * The positions where both series carry a value, as two parallel lists.
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private static function pairs(array $x, array $y): array
    {
        $x = array_values($x);
        $y = array_values($y);
        $xs = $ys = [];

        for ($i = 0, $len = min(count($x), count($y)); $i < $len; $i++) {
            if ($x[$i] !== null && $y[$i] !== null) {
                $xs[] = (float) $x[$i];
                $ys[] = (float) $y[$i];
            }
        }

        return [$xs, $ys];
    }
}
