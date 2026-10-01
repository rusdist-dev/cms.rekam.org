<?php

namespace Tests\Unit\JogoLaut;

use App\Services\JogoLaut\JogoLautStatsService as Stats;
use PHPUnit\Framework\TestCase;

/**
 * The numerical core of the JOGO LAUT payload. The align() cases matter most:
 * it replaced an O(n × m) scan, and must return exactly what that scan did.
 */
class JogoLautStatsServiceTest extends TestCase
{
    /** The original nested scan, verbatim in behaviour, as the reference. */
    private static function scan(array $primary, array $secondary, array $values, int $threshold): array
    {
        $out = [];

        foreach ($primary as $ts) {
            $closest = null;
            $closestDiff = INF;

            foreach ($secondary as $i => $s) {
                $diff = abs($s - $ts);

                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $closest = $values[$i];
                }
            }

            $out[] = $closestDiff <= $threshold ? $closest : null;
        }

        return $out;
    }

    public function test_align_matches_the_nested_scan_on_ragged_data(): void
    {
        mt_srand(20261001);

        for ($round = 0; $round < 200; $round++) {
            $primary = $this->ascending(mt_rand(0, 40), 900);
            // Coarse steps on the secondary side force duplicates and exact ties.
            $secondary = $this->ascending(mt_rand(0, 40), 600, 300);
            $values = array_map(fn () => mt_rand(0, 4) === 0 ? null : (float) mt_rand(0, 999), $secondary);

            $this->assertSame(
                self::scan($primary, $secondary, $values, 1200),
                Stats::align($primary, $secondary, $values, 1200),
                "round {$round}",
            );
        }
    }

    public function test_align_breaks_a_tie_towards_the_earlier_reading(): void
    {
        $this->assertSame([1.0], Stats::align([150], [100, 200], [1.0, 2.0]));
        // Among duplicate timestamps, the first row wins from either side.
        $this->assertSame([1.0, 3.0], Stats::align([100, 210], [100, 100, 200, 200], [1.0, 2.0, 3.0, 4.0]));
    }

    public function test_align_leaves_a_gap_beyond_the_threshold(): void
    {
        $this->assertSame([5.0, null], Stats::align([0, 10000], [60], [5.0], 3600));
        $this->assertSame([null, null], Stats::align([0, 1], [], []));
    }

    public function test_moving_average_skips_gaps_but_never_fills_an_empty_window(): void
    {
        $this->assertSame([2.0, 4.0, 6.0, 6.0, null, 9.0, 9.0], Stats::centeredMovingAverage([2.0, null, 6.0, null, null, null, 9.0], 3));
    }

    public function test_correlation_is_null_when_undefined(): void
    {
        $this->assertNull(Stats::correlation([1.0, 2.0, 3.0], [5.0, 5.0, 5.0]));
        $this->assertNull(Stats::correlation([1.0, null], [2.0, 3.0]));
        $this->assertEqualsWithDelta(-1.0, Stats::correlation([1.0, 2.0, null, 4.0], [8.0, 6.0, 1.0, 2.0]), 1e-9);
    }

    public function test_ccf_finds_the_lag_at_which_x_follows_y(): void
    {
        $y = [0.0, 0.0, 5.0, 1.0, 9.0, 2.0, 7.0, 3.0, 8.0, 4.0, 6.0, 1.0];
        // x is y delayed by two points.
        $x = array_merge([0.0, 0.0], array_slice($y, 0, -2));

        $ccf = Stats::ccf($x, $y, 4);

        $this->assertEqualsWithDelta(1.0, $ccf[2], 1e-9);
        $this->assertSame(2, array_search(max($ccf), $ccf, true));
    }

    public function test_trend_needs_every_one_of_its_points(): void
    {
        // (10 - 1) / 5: the last five points, divided by five.
        $this->assertSame(1.8, Stats::trend([0.0, 1.0, 3.0, 5.0, 7.0, 10.0], 5));
        $this->assertSame(0.0, Stats::trend([1.0, 3.0, null, 7.0, 10.0], 5));
        $this->assertSame(0.0, Stats::trend([1.0, 2.0], 5));
    }

    public function test_outlier_fences_follow_the_original_quartiles(): void
    {
        $result = Stats::detectOutliers([10.0, 11.0, null, 12.0, 13.0, 14.0, 100.0]);

        // n = 6: q1 = sorted[1] = 11, q3 = sorted[4] = 14, IQR = 3.
        $this->assertSame([6 => 100.0], $result['outliers']);
        $this->assertSame(6.5, $result['lower']);
        $this->assertSame(18.5, $result['upper']);
    }

    public function test_hourly_stats_bucket_by_station_hour(): void
    {
        // 23:30 UTC is 06:30 at +07:00.
        $ts = [gmmktime(23, 30, 0, 9, 30, 2026), gmmktime(23, 45, 0, 9, 30, 2026)];
        $stats = Stats::hourlyStats($ts, [10.0, 20.0], 7 * 3600);

        $this->assertSame(15.0, $stats['means'][6]);
        $this->assertSame(5.0, $stats['stds'][6]);
        $this->assertNull($stats['means'][23]);
    }

    /** @return array<int, int> */
    private function ascending(int $n, int $maxStep, int $grain = 1): array
    {
        $out = [];
        $t = 0;

        for ($i = 0; $i < $n; $i++) {
            $t += intdiv(mt_rand(0, $maxStep), $grain) * $grain;
            $out[] = $t;
        }

        return $out;
    }
}
