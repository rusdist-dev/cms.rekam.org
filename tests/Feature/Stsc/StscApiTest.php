<?php

namespace Tests\Feature\Stsc;

use App\Services\Stsc\StscProduksiChartService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * The four STSC endpoints: two option lists and two charts.
 *
 * The fixture is deliberately ragged where the real tables are regular — one
 * area missing a year, one area absent from `data_armada` altogether, one
 * commodity split across two rows — because those are the cases where a chart
 * quietly says something false, and every figure below can still be worked out
 * on paper.
 */
class StscApiTest extends TenantTestCase
{
    private string $key;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();

        $this->bootStscDatasource();
        $this->seedStsc();
    }

    protected function tearDown(): void
    {
        // Purge first: on Windows the file stays locked while the connection
        // holds its PDO, and unlink() fails rather than the test failing.
        DB::purge('ds_stsc');

        if (isset($this->database) && file_exists($this->database)) {
            @unlink($this->database);
        }

        parent::tearDown();
    }

    private function fetch(string $path, array $query = [])
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/stsc/'.$path.($query ? '?'.http_build_query($query) : ''));
    }

    private function armada(array $query = []): array
    {
        return $this->fetch('grafik/armada', $query)->assertOk()->json('data');
    }

    private function produksi(array $query = []): array
    {
        return $this->fetch('grafik/produksi', $query)->assertOk()->json('data');
    }

    // -- the option lists --------------------------------------------------

    public function test_the_area_list_gives_each_areas_year_range_and_its_sources(): void
    {
        // 573 lands fish but owns no fleet record: a caller who picks it would
        // otherwise draw an empty armada chart with nothing to explain it.
        $this->assertSame([
            ['value' => '571', 'tahun_awal' => 2019, 'tahun_akhir' => 2021, 'sumber' => ['armada', 'produksi']],
            ['value' => '573', 'tahun_awal' => 2021, 'tahun_akhir' => 2021, 'sumber' => ['produksi']],
            ['value' => '712', 'tahun_awal' => 2020, 'tahun_akhir' => 2021, 'sumber' => ['armada', 'produksi']],
        ], $this->fetch('opsi/wpp')->assertOk()->json('data'));
    }

    public function test_the_commodity_list_counts_the_areas_that_land_it(): void
    {
        $this->assertSame([
            ['value' => 'Cumi-Cumi', 'jumlah_wpp' => 1, 'tahun_awal' => 2020, 'tahun_akhir' => 2020],
            ['value' => 'Lobster', 'jumlah_wpp' => 1, 'tahun_awal' => 2020, 'tahun_akhir' => 2020],
            ['value' => 'Rajungan', 'jumlah_wpp' => 3, 'tahun_awal' => 2019, 'tahun_akhir' => 2021],
        ], $this->fetch('opsi/komoditas')->assertOk()->json('data'));
    }

    public function test_the_commodity_list_narrows_to_one_area(): void
    {
        $this->assertSame(
            ['Cumi-Cumi', 'Rajungan'],
            array_column($this->fetch('opsi/komoditas', ['wpp' => '712'])->assertOk()->json('data'), 'value'),
        );
    }

    // -- the fleet chart ---------------------------------------------------

    public function test_the_fleet_chart_returns_both_series_from_the_same_rows(): void
    {
        $data = $this->armada();

        $this->assertSame(['armada' => 'unit', 'gt' => 'GT'], $data['unit']);
        $this->assertSame([2019, 2020, 2021], $data['tahun']);

        $this->assertSame([
            ['wpp' => '571', 'titik' => [
                ['tahun' => 2019, 'nilai' => 100],
                ['tahun' => 2020, 'nilai' => 120],
                ['tahun' => 2021, 'nilai' => 130],
            ]],
            ['wpp' => '712', 'titik' => [
                ['tahun' => 2020, 'nilai' => 10],
                ['tahun' => 2021, 'nilai' => 20],
            ]],
        ], $data['armada']);

        $this->assertSame(
            [[500, 600, 700], [50, 80]],
            array_map(fn ($seri) => array_column($seri['titik'], 'nilai'), $data['gt']),
        );
    }

    public function test_a_year_nobody_reported_is_left_out_rather_than_drawn_as_zero(): void
    {
        // 712 has no 2019 row. The axis still carries 2019 because 571 does —
        // but inventing a zero there would draw a fleet that vanished and came
        // back, which is a different claim from "not reported".
        $series = collect($this->armada()['armada'])->firstWhere('wpp', '712');

        $this->assertSame([2020, 2021], array_column($series['titik'], 'tahun'));
    }

    public function test_the_fleet_chart_reports_no_grand_total(): void
    {
        // Both series are stocks counted afresh each year; a sum over years
        // would report a fleet larger than any that ever existed.
        $this->assertSame(['filter', 'unit', 'tahun', 'armada', 'gt'], array_keys($this->armada()));
    }

    public function test_the_fleet_chart_narrows_to_one_area(): void
    {
        $data = $this->armada(['wpp' => '571']);

        $this->assertSame('571', $data['filter']['wpp']);
        $this->assertCount(1, $data['armada']);
        $this->assertCount(1, $data['gt']);
    }

    public function test_the_fleet_chart_narrows_to_a_year_range_inclusively(): void
    {
        $data = $this->armada(['dari_tahun' => 2020, 'sampai_tahun' => 2021]);

        $this->assertSame([2020, 2021], $data['tahun']);
        $this->assertSame(
            [[120, 130], [10, 20]],
            array_map(fn ($seri) => array_column($seri['titik'], 'nilai'), $data['armada']),
        );
    }

    public function test_an_area_with_no_fleet_record_yields_an_empty_fleet_chart(): void
    {
        $data = $this->armada(['wpp' => '573']);

        $this->assertSame([], $data['armada']);
        $this->assertSame([], $data['gt']);
        $this->assertSame([], $data['tahun']);
    }

    // -- the production chart ----------------------------------------------

    public function test_production_is_nested_by_commodity_then_by_area(): void
    {
        $data = $this->produksi();

        $this->assertSame('ton', $data['unit']);
        $this->assertSame([2019, 2020, 2021], $data['tahun']);
        $this->assertSame(73.25, $data['total_produksi_ton']);

        $this->assertSame(['Cumi-Cumi', 'Lobster', 'Rajungan'], array_column($data['komoditas'], 'komoditas'));

        $rajungan = collect($data['komoditas'])->firstWhere('komoditas', 'Rajungan');

        $this->assertSame(64.25, $rajungan['total_produksi_ton']);
        $this->assertSame(['571', '573', '712'], array_column($rajungan['seri'], 'wpp'));
        $this->assertSame([2019, 2020, 2021], array_column($rajungan['seri'][0]['titik'], 'tahun'));
        // Cast: a whole number of tons crosses JSON as 30, not 30.0.
        $this->assertSame(
            [10.5, 20.25, 30.0],
            array_map('floatval', array_column($rajungan['seri'][0]['titik'], 'nilai')),
        );
    }

    public function test_two_rows_for_the_same_year_area_and_commodity_are_one_point(): void
    {
        // Upstream currently writes one row per key, but the endpoint adds
        // them rather than drawing two points at the same x.
        $cumi = collect($this->produksi()['komoditas'])->firstWhere('komoditas', 'Cumi-Cumi');

        $this->assertCount(1, $cumi['seri'][0]['titik']);
        $this->assertSame(2020, $cumi['seri'][0]['titik'][0]['tahun']);
        $this->assertSame(4.0, (float) $cumi['seri'][0]['titik'][0]['nilai']);
        $this->assertSame(4.0, (float) $cumi['total_produksi_ton']);
    }

    public function test_narrowing_to_one_area_keeps_the_same_shape(): void
    {
        // Not a second response shape to branch on: one line per panel instead
        // of eleven.
        $data = $this->produksi(['wpp' => '571']);

        $this->assertSame(['Lobster', 'Rajungan'], array_column($data['komoditas'], 'komoditas'));

        foreach ($data['komoditas'] as $commodity) {
            $this->assertSame(['571'], array_column($commodity['seri'], 'wpp'));
        }

        $this->assertSame(65.75, $data['total_produksi_ton']);
    }

    public function test_the_production_chart_narrows_by_commodity_and_year(): void
    {
        $data = $this->produksi(['komoditas' => 'Rajungan', 'dari_tahun' => 2020]);

        $this->assertCount(1, $data['komoditas']);
        $this->assertSame([2020, 2021], $data['tahun']);
        // 20.25 + 30 + 1.5 + 2
        $this->assertSame(53.75, $data['total_produksi_ton']);
    }

    public function test_a_commodity_matching_nothing_yields_an_empty_chart(): void
    {
        $data = $this->produksi(['komoditas' => 'TIDAK ADA']);

        $this->assertSame([], $data['komoditas']);
        $this->assertSame([], $data['tahun']);
        $this->assertSame(0.0, (float) $data['total_produksi_ton']);
    }

    public function test_the_production_unit_is_stated_in_the_response(): void
    {
        $this->assertSame(StscProduksiChartService::UNIT, $this->produksi()['unit']);
    }

    // -- validation --------------------------------------------------------

    public function test_an_area_upstream_does_not_record_is_refused(): void
    {
        // 999 is not a WPPNRI; answering with an empty chart would read as
        // "no fleet there".
        foreach (['grafik/armada', 'grafik/produksi'] as $path) {
            $this->fetch($path, ['wpp' => '999'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('wpp');
        }
    }

    public function test_a_reversed_year_range_is_refused(): void
    {
        $this->fetch('grafik/armada', ['dari_tahun' => 2021, 'sampai_tahun' => 2019])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sampai_tahun');
    }

    public function test_a_year_that_is_not_a_year_is_refused(): void
    {
        $this->fetch('grafik/produksi', ['dari_tahun' => 'dua ribu'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dari_tahun');
    }

    public function test_one_bound_on_its_own_is_allowed(): void
    {
        $this->assertSame([2021], $this->armada(['dari_tahun' => 2021])['tahun']);
        $this->assertSame([2019, 2020], $this->armada(['sampai_tahun' => 2020])['tahun']);
    }

    // -- the guards --------------------------------------------------------

    public function test_the_endpoints_require_an_api_key(): void
    {
        foreach (['opsi/wpp', 'opsi/komoditas', 'grafik/armada', 'grafik/produksi'] as $path) {
            $this->getJson('/api/v1/ext/stsc/'.$path)->assertUnauthorized();
        }
    }

    public function test_an_unconfigured_datasource_answers_503(): void
    {
        Config::set('datasources.sources.stsc.connection.database', null);

        $this->fetch('opsi/wpp')->assertStatus(503);
        $this->fetch('grafik/armada')->assertStatus(503);
        $this->fetch('grafik/produksi')->assertStatus(503);
    }

    public function test_the_api_never_writes_to_the_datasource(): void
    {
        $before = [
            DB::connection('ds_stsc')->table('data_armada')->count(),
            DB::connection('ds_stsc')->table('data_produksi')->count(),
        ];

        $this->fetch('opsi/wpp')->assertOk();
        $this->fetch('opsi/komoditas')->assertOk();
        $this->fetch('grafik/armada', ['wpp' => '571'])->assertOk();
        $this->fetch('grafik/produksi', ['komoditas' => 'Rajungan'])->assertOk();

        $this->assertSame($before, [
            DB::connection('ds_stsc')->table('data_armada')->count(),
            DB::connection('ds_stsc')->table('data_produksi')->count(),
        ]);
    }

    // -- fixture -----------------------------------------------------------

    private function bootStscDatasource(): void
    {
        $this->database = storage_path('framework/testing/stsc-'.uniqid().'.sqlite');
        touch($this->database);

        $connection = ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'foreign_key_constraints' => false];

        Config::set('datasources.sources.stsc', [
            'label' => 'STSC',
            'read_only' => true,
            'connection' => $connection,
        ]);
        Config::set('database.connections.ds_stsc', $connection);

        DB::purge('ds_stsc');
    }

    /** Builds the schema and rows with the read-only guard lifted for the duration. */
    private function seedStsc(): void
    {
        Config::set('datasources.sources.stsc.read_only', false);

        $db = DB::connection('ds_stsc');

        // WPPNRI is an int in one table and text in the other, exactly as
        // upstream declares them — the filter has to survive that.
        $db->statement('create table data_armada (tahun integer, WPPNRI integer, jumlah_armada_unit integer, total_GT integer)');
        $db->statement('create table data_produksi (tahun integer, WPPNRI text, komoditas text, produksi_ton real)');

        $armada = [
            [2019, 571, 100, 500],
            [2020, 571, 120, 600],
            [2021, 571, 130, 700],
            // No 2019 row: the gap the fleet chart must not fill with a zero.
            [2020, 712, 10, 50],
            [2021, 712, 20, 80],
        ];

        foreach ($armada as [$tahun, $wpp, $unit, $gt]) {
            $db->table('data_armada')->insert([
                'tahun' => $tahun,
                'WPPNRI' => $wpp,
                'jumlah_armada_unit' => $unit,
                'total_GT' => $gt,
            ]);
        }

        $produksi = [
            [2019, '571', 'Rajungan', 10.5],
            [2020, '571', 'Rajungan', 20.25],
            [2021, '571', 'Rajungan', 30.0],
            [2020, '571', 'Lobster', 5.0],
            [2020, '712', 'Rajungan', 1.5],
            // 573 lands fish but owns no fleet record at all.
            [2021, '573', 'Rajungan', 2.0],
            // One commodity split across two rows for the same year and area.
            [2020, '712', 'Cumi-Cumi', 1.25],
            [2020, '712', 'Cumi-Cumi', 2.75],
        ];

        foreach ($produksi as [$tahun, $wpp, $komoditas, $ton]) {
            $db->table('data_produksi')->insert([
                'tahun' => $tahun,
                'WPPNRI' => $wpp,
                'komoditas' => $komoditas,
                'produksi_ton' => $ton,
            ]);
        }

        Config::set('datasources.sources.stsc.read_only', true);
    }
}
