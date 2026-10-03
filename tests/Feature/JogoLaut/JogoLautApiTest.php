<?php

namespace Tests\Feature\JogoLaut;

use App\Services\DatasourceRegistry;
use App\Services\JogoLaut\JogoLautRepository;
use App\Services\JogoLaut\JogoLautSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Tests\TenantTestCase;

/**
 * GET /api/v1/ext/jogolaut/monitoring, end to end.
 *
 * The clock is frozen at 2026-10-01 05:00 UTC — noon at the station — and
 * the fixture is one hour of readings before it, small enough that every
 * aligned value below can be checked against the timestamps by hand. Upstream
 * stores UTC; every timestamp in the response is +07:00.
 */
class JogoLautApiTest extends TenantTestCase
{
    private string $key;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-01 05:00:00', 'UTC'));

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();

        $this->database = storage_path('framework/testing/jogolaut-'.uniqid().'.sqlite');
        touch($this->database);

        $connection = ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'foreign_key_constraints' => false];
        Config::set('datasources.sources.jogolaut', ['label' => 'JOGOLAUT', 'read_only' => true, 'connection' => $connection]);
        Config::set('database.connections.ds_jogolaut', $connection);
        DB::purge('ds_jogolaut');

        $this->seedJogoLaut();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        // Purge first: on Windows the file stays locked while the connection
        // holds its PDO, and unlink() fails rather than the test failing.
        DB::purge('ds_jogolaut');

        if (isset($this->database) && file_exists($this->database)) {
            @unlink($this->database);
        }

        parent::tearDown();
    }

    private function fetch(array $query = [])
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/jogolaut/monitoring'.($query ? '?'.http_build_query($query) : ''));
    }

    private function sections(array $query = []): array
    {
        return $this->fetch($query)->assertOk()->json('data.sections');
    }

    /** One series of a time-series section, by key. */
    private function series(array $section, string $key): array
    {
        foreach ($section['series'] as $series) {
            if ($series['key'] === $key) {
                return $series;
            }
        }

        $this->fail("Series {$key} tidak ada.");
    }

    // -- the envelope ------------------------------------------------------

    public function test_every_section_is_returned_in_a_fixed_order_by_default(): void
    {
        $data = $this->fetch()->assertOk()->json('data');

        $this->assertSame(
            ['co2', 'flux', 'do', 'ph', 'ctd', 'atm', 'diurnal', 'windrose', 'correlation', 'analysis', 'ecosystem', 'kpi', 'gauges', 'table'],
            array_keys($data['sections']),
        );

        $this->assertSame('+07:00', $data['meta']['timezone']);
        $this->assertSame('2026-10-01 12:00:00', $data['meta']['to']);
        $this->assertSame('2026-09-24 12:00:00', $data['meta']['from']);
        $this->assertSame(['days' => 7, 'window' => 11, 'locale' => 'id'], array_intersect_key($data['meta']['params'], array_flip(['days', 'window', 'locale'])));
    }

    public function test_every_series_is_exactly_as_long_as_its_axis(): void
    {
        foreach ($this->sections() as $key => $section) {
            if (! isset($section['series'])) {
                continue;
            }

            foreach ($section['series'] as $series) {
                $this->assertCount(count($section['x']), $series['data'], "{$key}.{$series['key']}");
            }
        }
    }

    public function test_include_selects_sections_and_ignores_their_order(): void
    {
        $this->assertSame(['co2', 'windrose'], array_keys($this->sections(['include' => 'windrose, CO2'])));
    }

    public function test_bad_parameters_are_rejected_rather_than_corrected(): void
    {
        $this->fetch(['include' => 'co2,chart'])->assertStatus(422)->assertJsonValidationErrors('include');
        $this->fetch(['days' => 31])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->fetch(['days' => '7 DAY) OR (1'])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->fetch(['window' => 4])->assertStatus(422)->assertJsonValidationErrors('window');
        $this->fetch(['locale' => 'fr'])->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    public function test_an_unconfigured_datasource_answers_503(): void
    {
        Config::set('datasources.sources.jogolaut.connection.database', null);

        $this->fetch()->assertStatus(503);
    }

    // -- time series -------------------------------------------------------

    public function test_the_co2_section_runs_on_the_soil_timeline_in_station_time(): void
    {
        $co2 = $this->sections(['include' => 'co2'])['co2'];

        // Twelve readings in the window; the 20 September row is outside it.
        $this->assertCount(12, $co2['x']);
        $this->assertSame('2026-10-01 11:00:00', $co2['x'][0]);
        $this->assertSame('2026-10-01 11:55:00', $co2['x'][11]);

        $soil = $this->series($co2, 'co2_tanah');
        $this->assertSame(['ppm', 1, 'data_co2', 'CO₂ Tanah'], [$soil['unit'], $soil['dec'], $soil['source'], $soil['label']]);
        // A missing reading stays a gap: never 0.
        $this->assertNull($soil['data'][3]);
        $this->assertEquals(400, $soil['data'][0]);

        // Air CO₂ is read at :02, :30 and :58 and carried to the nearest soil
        // reading — 11:15 is 13 minutes from :02 and 15 from :30.
        $this->assertEquals(
            [450, 450, 450, 450, 460, 460, 460, 460, 460, 470, 470, 470],
            $this->series($co2, 'co2_udara')['data'],
        );

        // jarak_air is a constant 320 cm: tide 420 - 320.
        $this->assertEquals(array_fill(0, 12, 100), $this->series($co2, 'pasut_ma')['data']);
    }

    public function test_the_do_section_carries_tide_and_rain_onto_its_own_timeline(): void
    {
        $do = $this->sections(['include' => 'do'])['do'];

        $this->assertSame(['2026-10-01 11:00:00', '2026-10-01 11:10:00', '2026-10-01 11:20:00'], $do['x']);
        $this->assertEquals([6.5, 6.6, 6.7], $this->series($do, 'do')['data']);
        $this->assertEquals([100, 100, 100], $this->series($do, 'pasut_ma')['data']);
        // Mast readings at :00, :15, :30, :45 — 11:10 is nearest 11:15.
        $this->assertEquals([0, 1.2, 1.2], $this->series($do, 'curah_hujan')['data']);
    }

    public function test_wind_speed_is_converted_from_centimetres_per_second(): void
    {
        $atm = $this->sections(['include' => 'atm'])['atm'];

        $this->assertEquals([1.5, 3.5, 5.5, 7.5], $this->series($atm, 'kec_angin')['data']);
        $this->assertSame('m/s', $this->series($atm, 'kec_angin')['unit']);
        $this->assertEquals([0, 1.2, 0, null], $this->series($atm, 'curah_hujan')['data']);
    }

    public function test_flux_is_averaged_per_station_hour(): void
    {
        $flux = $this->sections(['include' => 'flux'])['flux'];

        $this->assertSame(['2026-10-01 11:00:00'], $flux['x']);
        $this->assertSame(3, $flux['ma_window']);
        $this->assertNotNull($this->series($flux, 'respirasi')['data'][0]);
    }

    // -- derived sections --------------------------------------------------

    public function test_the_diurnal_cycle_has_all_24_hours_with_gaps_as_null(): void
    {
        $diurnal = $this->sections(['include' => 'diurnal'])['diurnal'];

        $this->assertSame(range(0, 23), $diurnal['x']);
        $this->assertSame(11, $diurnal['peak_hour']);
        $this->assertNull($this->series($diurnal, 'mean')['data'][3]);
        $this->assertNotNull($this->series($diurnal, 'mean')['data'][11]);
    }

    public function test_the_wind_rose_counts_direction_against_speed(): void
    {
        $rose = $this->sections(['include' => 'windrose', 'locale' => 'en'])['windrose'];

        $north = $rose['directions'][0];
        $east = $rose['directions'][4];

        $this->assertSame(['N', 0, 2], [$north['dir'], $north['deg'], $north['count']]);
        $this->assertSame(['0-2' => 1, '2-4' => 1, '4-6' => 0, '6+' => 0], $north['by_speed']);
        $this->assertSame(['E', 1], [$east['dir'], $east['count']]);

        // The fourth mast reading has no direction and is left out entirely.
        $this->assertSame(3, $rose['summary']['total']);
        $this->assertSame(['dir' => 'N', 'label' => 'North'], $rose['summary']['dominant']);
        $this->assertEquals(3.5, $rose['summary']['avg_speed']);
        $this->assertEquals(5.5, $rose['summary']['max_speed']);
        $this->assertSame('gentle_breeze', $rose['summary']['beaufort_avg']['code']);
    }

    public function test_a_constant_series_correlates_with_nothing(): void
    {
        $matrix = $this->sections(['include' => 'correlation'])['correlation'];

        $keys = array_column($matrix['vars'], 'key');
        $this->assertSame(['co2_tanah', 'pasut_ma', 'suhu_udara', 'kelembaban', 'ph_tanah', 'co2_udara', 'ph_air'], $keys);
        $this->assertCount(7, $matrix['values']);

        // The tide is flat in this fixture: r is undefined, not zero.
        $this->assertNull($matrix['values'][0][1]);
        $this->assertEquals(1, $matrix['values'][0][0]);
        $this->assertSame($matrix['values'][2][5], $matrix['values'][5][2]);
        $this->assertNotNull($matrix['max_pair']);
    }

    public function test_the_analysis_flags_the_spike_and_forecasts_twelve_steps(): void
    {
        $analysis = $this->sections(['include' => 'analysis'])['analysis'];

        $this->assertSame([['index' => 10, 'x' => '2026-10-01 11:50:00', 'value' => 900]], $analysis['outliers']['items']);
        $this->assertCount(12, $analysis['predictions']);
        $this->assertSame(60, $analysis['predictions'][11]['minutes_ahead']);
        // Flat tide: no lag can be scored.
        $this->assertNull($analysis['best_lag']);
        $this->assertNull($analysis['lag_minutes']);
    }

    public function test_the_ecosystem_status_is_a_code_and_a_colour(): void
    {
        $eco = $this->sections(['include' => 'ecosystem', 'locale' => 'en'])['ecosystem'];

        $this->assertSame('status', $eco['type']);
        $this->assertContains($eco['code'], ['photosynthesis', 'respiration', 'tidal', 'mixed']);
        $this->assertArrayNotHasKey('icon', $eco);
        $this->assertIsInt($eco['confidence']);
        $this->assertNotEmpty($eco['description']);
    }

    // -- stats -------------------------------------------------------------

    public function test_kpis_compare_the_latest_reading_with_yesterdays_mean(): void
    {
        $items = collect($this->sections(['include' => 'kpi'])['kpi']['items'])->keyBy('key');

        // 650 now against 500 yesterday (station day 30 September).
        $this->assertEquals(650, $items['conductivity']['latest']);
        $this->assertEquals(150, $items['conductivity']['delta']);
        $this->assertSame('2026-10-01 11:30:00', $items['conductivity']['latest_at']);
        $this->assertEquals(500, $items['conductivity']['min']);

        // The gauge's raw distance and the tide derived from it, side by side.
        $this->assertEquals(320, $items['jarak_air']['latest']);
        $this->assertEquals(100, $items['pasut']['latest']);
        $this->assertNull($items['co2_lapangan']['delta']);
    }

    public function test_gauges_classify_the_latest_readings(): void
    {
        $items = collect($this->sections(['include' => 'gauges', 'locale' => 'en'])['gauges']['items'])->keyBy('key');

        $this->assertSame(['normal', 'teal', 'Normal'], [$items['do']['level'], $items['do']['color'], $items['do']['level_label']]);
        $this->assertSame(['optimal', 'green'], [$items['ph_air']['level'], $items['ph_air']['color']]);
        $this->assertSame('normal', $items['conductivity']['level']);
        $this->assertSame('normal', $items['water_temp']['level']);

        // 32 °C at 74 % RH.
        $this->assertSame(['suhu_udara' => 32, 'kelembaban_udara' => 74], array_map('intval', $items['heat_index']['inputs']));
        $this->assertSame('danger', $items['heat_index']['level']);
        $this->assertNotEmpty($items['heat_index']['description']);
    }

    public function test_the_table_pages_newest_first(): void
    {
        $table = $this->sections(['include' => 'table', 'limit' => 5])['table'];

        $this->assertSame(['page' => 1, 'limit' => 5, 'total' => 12, 'pages' => 3], $table['pagination']);
        $this->assertSame('2026-10-01 11:55:00', $table['rows'][0]['waktu']);
        $this->assertSame('waktu', $table['columns'][0]['key']);

        $last = $this->sections(['include' => 'table', 'limit' => 5, 'page' => 3])['table'];
        $this->assertCount(2, $last['rows']);
    }

    // -- failure and caching -----------------------------------------------

    public function test_an_empty_table_empties_only_its_section(): void
    {
        $this->writable(fn ($db) => $db->table('dissolve_oxygen')->delete());

        $sections = $this->sections(['include' => 'do,ph']);

        $this->assertSame(['type' => 'timeseries', 'empty' => true], $sections['do']);
        $this->assertArrayHasKey('x', $sections['ph']);
    }

    public function test_a_failing_section_is_isolated_and_retried_once_the_failure_expires(): void
    {
        $this->writable(fn ($db) => $db->statement('drop table ph_air'));

        $sections = $this->sections(['include' => 'ph,ctd']);

        $this->assertSame(['type' => 'timeseries', 'empty' => true, 'error' => true], $sections['ph']);
        $this->assertArrayHasKey('x', $sections['ctd']);

        $this->writable(function ($db) {
            $db->statement('create table ph_air (waktu text, ph real, suhu_air real)');
            $db->table('ph_air')->insert(['waktu' => '2026-10-01 04:00:00', 'ph' => 7.8, 'suhu_air' => 29]);
        });

        // Within failure_ttl the table is still treated as down, not retried…
        $this->assertTrue($this->sections(['include' => 'ph'])['ph']['error'] ?? false);

        // …and once it lapses, the failure was never cached as a result.
        $this->travel(31)->seconds();
        $this->assertArrayHasKey('x', $this->sections(['include' => 'ph'])['ph']);
    }

    public function test_a_dead_table_is_read_once_however_many_sections_need_it(): void
    {
        $this->writable(fn ($db) => $db->statement('drop table data_co2'));

        // A failed query fires no QueryExecuted event, so count the attempts
        // at the repository instead.
        $attempts = $this->countSeriesCalls();

        $this->sections();
        $this->assertSame(1, $attempts->counts['data_co2'] ?? 0);

        // Every section built on soil CO₂ fails; the others are untouched.
        $sections = $this->sections();
        foreach (['co2', 'flux', 'diurnal', 'correlation', 'analysis'] as $key) {
            $this->assertTrue($sections[$key]['error'] ?? false, $key);
        }
        $this->assertArrayHasKey('x', $sections['atm']);

        // While it is marked down, further requests do not touch it at all.
        $this->assertSame(1, $attempts->counts['data_co2']);
    }

    public function test_varying_parameters_never_reaches_upstream_twice(): void
    {
        $this->sections();

        $reads = $this->countReads(null, function () {
            $this->sections(['include' => 'do,co2']);
            $this->sections(['include' => 'correlation,analysis', 'window' => 5]);
            $this->sections(['window' => 21, 'locale' => 'en']);
        });

        $this->assertSame(0, $reads);
    }

    public function test_every_section_reads_the_snapshot_of_its_bucket(): void
    {
        $first = $this->sections(['include' => 'co2']);

        $this->writable(fn ($db) => $db->table('data_co2')->delete());

        // Same five-minute bucket: the same snapshot, whatever happened upstream.
        $this->travel(4)->minutes();
        $this->assertSame($first, $this->sections(['include' => 'co2']));

        // Next bucket: read afresh.
        $this->travel(1)->minutes();
        $this->assertSame(['type' => 'timeseries', 'empty' => true], $this->sections(['include' => 'co2'])['co2']);
        $this->assertSame('2026-10-01 12:05:00', $this->fetch()->json('data.meta.snapshot_at'));
    }

    public function test_a_stuck_lock_holder_does_not_block_the_request(): void
    {
        Config::set('jogolaut.cache.lock_wait', 1);
        // Lock::block() measures its wait on the (frozen) Carbon clock; let
        // each poll's sleep move that clock instead of really sleeping.
        Sleep::fake(syncWithCarbon: true);

        // Another process "holds" every read of this bucket and never finishes.
        $snapshot = app(JogoLautSnapshot::class);
        $bucket = $snapshot->bucket(now()->getTimestamp());
        Cache::lock("ext:jogolaut:raw:{$bucket}:menara:days=7:lock", 60)->get();

        $this->assertArrayHasKey('x', $this->sections(['include' => 'atm'])['atm']);
    }

    public function test_the_warm_up_fills_the_cache_for_the_default_payload(): void
    {
        $this->artisan('cms:jogolaut-warm')->assertSuccessful();

        $this->assertSame(0, $this->countReads(null, function () {
            $this->sections();
            $this->sections(['locale' => 'en', 'include' => 'kpi,gauges']);
        }));
    }

    public function test_the_warm_up_skips_an_unconfigured_datasource(): void
    {
        Config::set('datasources.sources.jogolaut.connection.database', null);

        $this->artisan('cms:jogolaut-warm')->assertSuccessful();
    }

    public function test_page_is_bounded(): void
    {
        $this->fetch(['include' => 'table', 'page' => 10001])->assertStatus(422)->assertJsonValidationErrors('page');
    }

    public function test_responses_are_cached_per_normalised_parameters(): void
    {
        $first = $this->sections(['include' => 'co2,do']);

        $this->writable(fn ($db) => $db->table('data_co2')->delete());

        $this->assertSame($first, $this->sections(['include' => 'do,co2']));
    }

    public function test_labels_follow_the_locale_but_keys_do_not(): void
    {
        $id = $this->sections(['include' => 'co2'])['co2'];
        $en = $this->sections(['include' => 'co2', 'locale' => 'en'])['co2'];

        $this->assertSame('Soil CO₂', $this->series($en, 'co2_tanah')['label']);
        $this->assertSame(array_column($id['series'], 'key'), array_column($en['series'], 'key'));
    }

    // -- fixture -----------------------------------------------------------

    /** Swaps in a repository that counts series() calls per table, failed or not. */
    private function countSeriesCalls(): JogoLautRepository
    {
        $repository = new class(app(DatasourceRegistry::class)) extends JogoLautRepository
        {
            public array $counts = [];

            public function series(string $table, string $timeColumn, array $columns, int $from): array
            {
                $this->counts[$table] = ($this->counts[$table] ?? 0) + 1;

                return parent::series($table, $timeColumn, $columns, $from);
            }
        };

        $this->app->instance(JogoLautRepository::class, $repository);

        return $repository;
    }

    /** SELECTs sent upstream while $callback runs — against one table, or any. */
    private function countReads(?string $table, \Closure $callback): int
    {
        $count = 0;

        DB::listen(function ($query) use ($table, &$count) {
            $sql = strtolower($query->sql);

            if ($query->connectionName === 'ds_jogolaut'
                && str_starts_with(ltrim($sql), 'select')
                && ($table === null || str_contains($sql, "\"{$table}\""))) {
                $count++;
            }
        });

        $callback();

        // The listener outlives this call (listeners cannot be removed), but
        // what it counts afterwards no longer reaches anyone.
        return $count;
    }

    private function writable(\Closure $callback): void
    {
        Config::set('datasources.sources.jogolaut.read_only', false);
        $callback(DB::connection('ds_jogolaut'));
        Config::set('datasources.sources.jogolaut.read_only', true);
    }

    /**
     * One hour, 04:00–04:55 UTC (11:00–11:55 station time). Soil CO₂ climbs
     * by 2 ppm per reading, with a gap at 04:15 and a spike at 04:50.
     */
    private function seedJogoLaut(): void
    {
        $this->writable(function ($db) {
            $db->statement('create table data_co2 (waktu text, co2 real, soil_moisture real, soil_ph real, soil_temp real, temp_air real, humidity real)');
            $db->statement('create table scd41_data (created_at text, co2 real, temperature real, humidity real)');
            $db->statement('create table pasut (waktu text, jarak_air real)');
            $db->statement('create table dissolve_oxygen (waktu text, do_air real, suhu_air real)');
            $db->statement('create table ph_air (waktu text, ph real, suhu_air real)');
            $db->statement('create table ctd (waktu text, conductivity real, suhu_air real, level_air real)');
            $db->statement('create table menara (waktu text, kec_angin real, arah_angin real, curah_hujan real)');

            $soil = [400, 402, 404, null, 408, 410, 412, 414, 416, 418, 900, 422];

            foreach ($soil as $i => $co2) {
                $at = sprintf('2026-10-01 04:%02d:00', $i * 5);

                $db->table('data_co2')->insert([
                    'waktu' => $at, 'co2' => $co2, 'soil_moisture' => 40 + $i,
                    'soil_ph' => 6.5 + $i / 10, 'soil_temp' => 27, 'temp_air' => 30, 'humidity' => 70,
                ]);
                $db->table('pasut')->insert(['waktu' => $at, 'jarak_air' => 320]);
            }

            // Outside the 7-day window: must never appear.
            $db->table('data_co2')->insert(['waktu' => '2026-09-20 04:00:00', 'co2' => 9999]);

            foreach ([['04:02', 450, 30, 70], ['04:30', 460, 31, 72], ['04:58', 470, 32, 74]] as [$at, $co2, $t, $rh]) {
                $db->table('scd41_data')->insert(['created_at' => "2026-10-01 {$at}:00", 'co2' => $co2, 'temperature' => $t, 'humidity' => $rh]);
            }

            foreach ([['04:00', 6.5], ['04:10', 6.6], ['04:20', 6.7]] as [$at, $do]) {
                $db->table('dissolve_oxygen')->insert(['waktu' => "2026-10-01 {$at}:00", 'do_air' => $do, 'suhu_air' => 29]);
            }

            foreach (['04:00', '04:30'] as $at) {
                $db->table('ph_air')->insert(['waktu' => "2026-10-01 {$at}:00", 'ph' => 7.8, 'suhu_air' => 29]);
            }

            // 12:00 station time on 30 September — yesterday's only reading.
            $db->table('ctd')->insert(['waktu' => '2026-09-30 05:00:00', 'conductivity' => 500, 'suhu_air' => 28, 'level_air' => 50]);
            $db->table('ctd')->insert(['waktu' => '2026-10-01 04:00:00', 'conductivity' => 640, 'suhu_air' => 28, 'level_air' => 50]);
            $db->table('ctd')->insert(['waktu' => '2026-10-01 04:30:00', 'conductivity' => 650, 'suhu_air' => 28, 'level_air' => 52]);

            // kec_angin in cm/s; the last reading has no direction.
            foreach ([['04:00', 150, 0, 0], ['04:15', 350, 10, 1.2], ['04:30', 550, 90, 0], ['04:45', 750, null, null]] as [$at, $speed, $deg, $rain]) {
                $db->table('menara')->insert(['waktu' => "2026-10-01 {$at}:00", 'kec_angin' => $speed, 'arah_angin' => $deg, 'curah_hujan' => $rain]);
            }
        });
    }
}
