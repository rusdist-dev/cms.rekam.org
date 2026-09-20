<?php

namespace Tests\Feature\Coast;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * The COAST map endpoints, end to end: routing, the X-Api-Key guard, the
 * datasource gate, and the arithmetic behind all twenty-four statistics.
 *
 * The `coast` datasource is pointed at file-backed SQLite here. That is only
 * possible because the summary queries are written driver-agnostically — the
 * year window is a date range rather than MySQL's YEAR(), which is both what
 * lets an index serve it and what lets this run without a database server.
 */
class CoastApiTest extends TenantTestCase
{
    private const DESA = '33.21.12.2011';

    private const DESA_LAIN = '33.20.11.2008';

    private string $key;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen: every statistic is "this year vs last year", so a suite that
        // passes in December and fails in January is not a test.
        Carbon::setTestNow('2026-09-17 10:00:00');

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();

        $this->bootCoastDatasource();
        $this->seedCoast();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        // Purge first: on Windows the file stays locked while the connection
        // holds its PDO, and unlink() fails rather than the test failing.
        DB::purge('ds_coast');

        if (isset($this->database) && file_exists($this->database)) {
            @unlink($this->database);
        }

        parent::tearDown();
    }

    private function getCoast(string $uri)
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])->getJson($uri);
    }

    // -- the list ---------------------------------------------------------

    public function test_the_list_returns_one_entry_per_village_with_its_marker(): void
    {
        $response = $this->getCoast('/api/v1/ext/coast/desa')->assertOk();

        $response->assertJsonCount(2, 'data');

        $desa = collect($response->json('data'))->firstWhere('desa_kode', self::DESA);

        $this->assertSame(-6.82, $desa['koordinat']['lat']);
        $this->assertSame(110.56, $desa['koordinat']['lng']);
        $this->assertSame('2026-03-01', $desa['pendataan_terakhir']);
    }

    public function test_region_names_come_from_the_wilayah_table_not_from_the_form(): void
    {
        // The two verified forms spell the village differently and one files it
        // under the wrong kecamatan. Neither spelling should reach the API.
        $desa = collect($this->getCoast('/api/v1/ext/coast/desa')->json('data'))
            ->firstWhere('desa_kode', self::DESA);

        $this->assertSame('Purworejo', $desa['desa']);
        $this->assertSame('Bonang', $desa['kecamatan']);
        $this->assertSame('Kabupaten Demak', $desa['kabupaten_kota']);
        $this->assertSame('Jawa Tengah', $desa['provinsi']);
    }

    public function test_inconsistent_spellings_do_not_split_a_village_into_two_markers(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa')->json('data');

        $this->assertCount(1, collect($data)->where('desa_kode', self::DESA));

        // All three verified forms for the village, counted once — 2026, 2025
        // and 2024. The count is all-time; only the statistics are windowed.
        $this->assertSame(3, collect($data)->firstWhere('desa_kode', self::DESA)['jumlah_form']);
    }

    public function test_the_list_is_sorted_by_village_name(): void
    {
        $names = collect($this->getCoast('/api/v1/ext/coast/desa')->json('data'))->pluck('desa');

        // Telukawur sits in Jepara and Purworejo in Demak, so a province-first
        // ordering would put Purworejo first — the name alone decides.
        $this->assertSame(['Purworejo', 'Telukawur'], $names->all());
    }

    public function test_a_village_with_only_unverified_forms_is_not_listed(): void
    {
        $kodes = collect($this->getCoast('/api/v1/ext/coast/desa')->json('data'))->pluck('desa_kode');

        $this->assertNotContains('33.99.99.9999', $kodes, 'Desa yang formnya masih draft tidak boleh muncul.');
    }

    // -- the summary ------------------------------------------------------

    public function test_the_summary_reports_both_years_of_every_metric(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->assertOk()->json('data');

        $this->assertSame(2026, $data['statistik']['tahun_baru']);
        $this->assertSame(2025, $data['statistik']['tahun_lama']);
        // Nine top-level metrics; the other fifteen figures are children.
        $this->assertSame([
            'luas_ekosistem_total',
            'nilai_ekonomi_total',
            'luas_area_konservasi',
            'luas_area_direhabilitasi',
            'dampak_ekonomi_produksi',
            'dampak_ekonomi_unit_terjual',
            'orang_dilatih_total',
            'orang_terlibat_total',
            'nilai_stok_karbon',
        ], array_column($data['statistik']['metrik'], 'key'));

        $this->assertMetric($data, 'luas_ekosistem_mangrove', baru: 10.0, lama: 4.0);
        $this->assertMetric($data, 'luas_ekosistem_lamun', baru: 2.5, lama: 0.0);
        $this->assertMetric($data, 'luas_area_konservasi', baru: 7.0, lama: 0.0);
        $this->assertMetric($data, 'nilai_stok_karbon', baru: 120.0, lama: 80.0);

        // Megagrams of carbon, not rupiah — the upstream column is called
        // "nilai" and declares no unit, so this label is the only thing
        // standing between the figure and being read as money.
        $this->assertSame('Mg C', $this->metric($data, 'nilai_stok_karbon')['unit']);
        $this->assertMetric($data, 'orang_dilatih_total', baru: 30.0, lama: 12.0);
        $this->assertMetric($data, 'orang_dilatih_disabilitas', baru: 2.0, lama: 0.0);
        $this->assertMetric($data, 'orang_terlibat_total', baru: 50.0, lama: 20.0);
    }

    public function test_a_combined_ecosystem_row_counts_towards_the_total_but_not_towards_a_type(): void
    {
        // The 2026 forms hold Mangrove 10 ha, Lamun 2.5 ha, and one row naming
        // all three at once for 6 ha whose split is unknowable.
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $this->assertMetric($data, 'luas_ekosistem_total', baru: 18.5, lama: 4.0);
        $this->assertMetric($data, 'luas_ekosistem_mangrove', baru: 10.0, lama: 4.0);
        $this->assertMetric($data, 'luas_ekosistem_terumbu_karang', baru: 0.0, lama: 0.0);
    }

    public function test_output_is_split_by_activity_because_produksi_is_not_always_kilograms(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        // kg: Perikanan Tangkap 100 + Silvofishery 50. The processing row's 30
        // is packs and the seedling row has no kg at all.
        $this->assertMetric($data, 'dampak_ekonomi_produksi', baru: 150.0, lama: 0.0);

        // units: 2.000 seedlings + 40 visits + 30 packs.
        $this->assertMetric($data, 'dampak_ekonomi_unit_terjual', baru: 2070.0, lama: 0.0);
    }

    public function test_the_output_metrics_break_down_by_activity(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $kg = $this->metric($data, 'dampak_ekonomi_produksi');
        $unit = $this->metric($data, 'dampak_ekonomi_unit_terjual');

        // Ordered by this year's figure, biggest first, and each child carries
        // its parent's unit so a renderer needs no lookup.
        $this->assertSame(
            [['Perikanan Tangkap', 100.0], ['Silvofishery', 50.0]],
            array_map(fn ($c) => [$c['label'], (float) $c['baru']], $kg['children']),
        );
        $this->assertSame('kg', $kg['children'][0]['unit']);
        $this->assertSame('perikanan-tangkap', $kg['children'][0]['key']);

        $this->assertSame(
            [['Penjualan Bibit Mangrove', 2000.0], ['Wisata', 40.0], ['Pengolahan Hasil Perikanan', 30.0]],
            array_map(fn ($c) => [$c['label'], (float) $c['baru']], $unit['children']),
        );
        $this->assertSame('unit', $unit['children'][0]['unit']);
    }

    public function test_every_node_claiming_an_exhaustive_breakdown_actually_has_one(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $checked = $this->assertSumsRecursively($data['statistik']['metrik']);

        $this->assertGreaterThan(0, $checked, 'Tidak ada node yang diperiksa.');
    }

    /** @return int how many nodes claimed `children_sum_to_total` and held up */
    private function assertSumsRecursively(array $rows): int
    {
        $checked = 0;

        foreach ($rows as $row) {
            if (empty($row['children'])) {
                continue;
            }

            if ($row['children_sum_to_total']) {
                $checked++;

                foreach (['baru', 'lama'] as $slot) {
                    $this->assertSame(
                        (float) $row[$slot],
                        (float) array_sum(array_column($row['children'], $slot)),
                        "Rincian '{$row['key']}' tidak menjumlah ke induknya pada tahun {$slot}.",
                    );
                }
            }

            $checked += $this->assertSumsRecursively($row['children']);
        }

        return $checked;
    }

    public function test_which_metrics_decompose_and_whether_their_children_are_exhaustive(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $decomposing = collect($data['statistik']['metrik'])
            ->filter(fn ($m) => array_key_exists('children', $m))
            ->mapWithKeys(fn ($m) => [$m['key'] => $m['children_sum_to_total']])
            ->all();

        $this->assertSame([
            // False where the children cannot account for the parent: a
            // combined ecosystem row belongs to no single type, and the SDM
            // categories overlap and may be left blank.
            'luas_ekosistem_total' => false,
            'nilai_ekonomi_total' => true,
            'dampak_ekonomi_produksi' => true,
            'dampak_ekonomi_unit_terjual' => true,
            'orang_dilatih_total' => false,
            'orang_terlibat_total' => false,
        ], $decomposing);

        // Leaf metrics say nothing about children at all.
        $this->assertArrayNotHasKey('children', $this->metric($data, 'nilai_stok_karbon'));
        $this->assertArrayNotHasKey('children', $this->metric($data, 'luas_area_konservasi'));
    }

    public function test_the_ecosystem_children_do_not_hide_the_unattributable_combined_row(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $total = $this->metric($data, 'luas_ekosistem_total');

        // 10 + 2.5 attributed, 6 from the combined row that belongs to none of
        // the three — the parent keeps it, the children cannot claim it.
        $this->assertSame(18.5, (float) $total['baru']);
        $this->assertSame(12.5, (float) array_sum(array_column($total['children'], 'baru')));
        $this->assertFalse($total['children_sum_to_total']);
    }

    public function test_the_output_breakdown_nests_commodities_under_their_activity(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $tangkap = $this->metric($data, 'perikanan-tangkap');

        $this->assertSame(100.0, (float) $tangkap['baru']);
        $this->assertSame(
            [['Rajungan', 60.0], ['Kepiting Bakau', 40.0]],
            array_map(fn ($c) => [$c['label'], (float) $c['baru']], $tangkap['children']),
        );
        $this->assertSame('kg', $tangkap['children'][0]['unit']);
        $this->assertSame('rajungan', $tangkap['children'][0]['key']);

        // A commodity is a leaf: nothing hangs below it.
        $this->assertArrayNotHasKey('children', $tangkap['children'][0]);
    }

    public function test_a_blank_commodity_becomes_lainnya_rather_than_an_empty_label(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $wisata = $this->metric($data, 'wisata');

        $this->assertSame('Lainnya', $wisata['children'][0]['label']);
        $this->assertSame('lainnya', $wisata['children'][0]['key']);
        $this->assertSame(40.0, (float) $wisata['children'][0]['baru']);
    }

    public function test_total_economic_value_is_valuation_plus_income(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $this->assertMetric($data, 'nilai_valuasi', baru: 900.0, lama: 300.0);
        $this->assertMetric($data, 'nilai_pendapatan', baru: 75.0, lama: 0.0);
        $this->assertMetric($data, 'nilai_ekonomi_total', baru: 975.0, lama: 300.0);
    }

    public function test_unverified_and_out_of_window_forms_are_excluded_from_the_statistics(): void
    {
        // A draft form in 2026 carries 999 ha and a verified 2024 form carries
        // 500 ha; neither may reach a two-year, verified-only statistic.
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $this->assertMetric($data, 'luas_ekosistem_total', baru: 18.5, lama: 4.0);

        // Five forms exist for this village; the draft and the soft-deleted one
        // are not among the three counted.
        $this->assertSame(3, $data['pendataan']['jumlah_form']);
    }

    public function test_the_summary_carries_the_polygon_photos_and_both_histories(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->json('data');

        $this->assertSame([[[-6.8, 110.5], [-6.9, 110.6]]], $data['peta']['path']);

        $this->assertCount(1, $data['gambar']);
        $this->assertSame('Tambak dan mangrove', $data['gambar'][0]['keterangan']);

        // Newest first, and the full history rather than the two-year window.
        $this->assertSame(['2026-04-02', '2024-05-05'], array_column($data['rehabilitasi'], 'tanggal'));
        $this->assertSame(1.5, $data['rehabilitasi'][0]['luas_area_direhabilitasi']);
        $this->assertArrayNotHasKey('geometry', $data['rehabilitasi'][0]);

        $this->assertCount(1, $data['pelatihan']);
        $this->assertSame('Pelatihan Silvofishery', $data['pelatihan'][0]['nama']);
        $this->assertSame(30, $data['pelatihan'][0]['peserta']);
    }

    public function test_a_village_without_verified_data_is_a_404(): void
    {
        $this->getCoast('/api/v1/ext/coast/desa/33.99.99.9999')->assertNotFound();
    }

    // -- the national totals ----------------------------------------------

    public function test_the_national_totals_cover_every_village_flat_and_in_a_fixed_order(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/statistik')->assertOk()->json('data');

        $this->assertSame(2026, $data['statistik']['tahun_baru']);
        $this->assertSame(2025, $data['statistik']['tahun_lama']);

        $this->assertSame([
            'luas_ekosistem_mangrove',
            'luas_ekosistem_lamun',
            'luas_ekosistem_terumbu_karang',
            'nilai_valuasi',
            'nilai_pendapatan',
            'luas_area_konservasi',
            'luas_area_direhabilitasi',
            'dampak_ekonomi_produksi',
            'dampak_ekonomi_unit_terjual',
            'orang_dilatih_total',
            'orang_terlibat_total',
            'nilai_stok_karbon',
        ], array_column($data['statistik']['metrik'], 'key'));

        // Flat: no accordion at this scale, so no node carries children.
        foreach ($data['statistik']['metrik'] as $metric) {
            $this->assertArrayNotHasKey('children', $metric, "Metrik '{$metric['key']}' tidak boleh punya children.");
        }
    }

    public function test_the_national_totals_add_both_villages_together(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/statistik')->json('data');

        // Purworejo 10 ha + Telukawur 5 ha this year; only Purworejo's 4 ha last.
        $this->assertMetric($data, 'luas_ekosistem_mangrove', baru: 15.0, lama: 4.0);
        $this->assertMetric($data, 'nilai_valuasi', baru: 1100.0, lama: 300.0);
        $this->assertMetric($data, 'nilai_pendapatan', baru: 83.0, lama: 0.0);
        $this->assertMetric($data, 'luas_area_konservasi', baru: 10.0, lama: 0.0);
        $this->assertMetric($data, 'luas_area_direhabilitasi', baru: 2.0, lama: 0.0);
        $this->assertMetric($data, 'dampak_ekonomi_produksi', baru: 175.0, lama: 0.0);
        $this->assertMetric($data, 'dampak_ekonomi_unit_terjual', baru: 2070.0, lama: 0.0);
        $this->assertMetric($data, 'orang_dilatih_total', baru: 36.0, lama: 12.0);
        $this->assertMetric($data, 'orang_terlibat_total', baru: 60.0, lama: 20.0);
        $this->assertMetric($data, 'nilai_stok_karbon', baru: 160.0, lama: 80.0);
        $this->assertSame('Mg C', $this->metric($data, 'nilai_stok_karbon')['unit']);
    }

    public function test_the_national_totals_obey_the_same_verified_and_two_year_rules(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/statistik')->json('data');

        // The draft's 999 ha, the soft-deleted form's 777, and the verified
        // 2024 form's 500 are all absent from the 15 ha above.
        $this->assertMetric($data, 'luas_ekosistem_mangrove', baru: 15.0, lama: 4.0);
    }

    public function test_the_national_totals_report_how_much_data_stands_behind_them(): void
    {
        $data = $this->getCoast('/api/v1/ext/coast/statistik')->json('data');

        // All-time, like a village summary's `pendataan`: four verified forms
        // across two villages, the draft and the soft-deleted one excluded.
        $this->assertSame(
            ['jumlah_form' => 4, 'jumlah_desa' => 2, 'terakhir' => '2026-05-01'],
            $data['pendataan'],
        );
    }

    // -- the protected areas ----------------------------------------------

    public function test_the_protected_areas_are_listed_whole_without_paging(): void
    {
        $response = $this->getCoast('/api/v1/ext/coast/kawasan-konservasi')->assertOk();

        $response->assertJsonCount(3, 'data');

        // No `meta`: there are no pages to describe, and an envelope that
        // implies otherwise invites a client to go looking for page two.
        $this->assertSame(['data'], array_keys($response->json()));

        $this->assertSame([
            'id', 'nama_kawasan', 'id_mpa', 'luas_area_dikonservasi', 'pelaksana_konservasi',
        ], array_keys($response->json('data.0')));

        // Upstream row timestamps say when COAST last touched its own record,
        // not anything about the area, so they stay out of the payload.
        $this->assertArrayNotHasKey('created_at', $response->json('data.0'));
    }

    public function test_the_protected_areas_sort_by_name_regardless_of_capitalisation(): void
    {
        $names = collect($this->getCoast('/api/v1/ext/coast/kawasan-konservasi')->json('data'))->pluck('nama_kawasan');

        $this->assertSame([
            'Kawasan Konservasi Anakan',
            'KAWASAN KONSERVASI BETAHWALANG',
            'Kawasan Konservasi Cilacap',
        ], $names->all());
    }

    public function test_every_protected_area_is_listed_even_one_no_survey_references(): void
    {
        // This is a reference table, not survey data: there is no `verified`
        // filter to apply, and an area nothing points at yet must still appear.
        $data = $this->getCoast('/api/v1/ext/coast/kawasan-konservasi')->json('data');

        $this->assertCount(3, $data);
        $this->assertNull(collect($data)->firstWhere('id', 2)['id_mpa']);
    }

    // -- the guards -------------------------------------------------------

    public function test_the_endpoints_require_an_api_key(): void
    {
        $this->getJson('/api/v1/ext/coast/desa')->assertUnauthorized();
        $this->getJson('/api/v1/ext/coast/desa/'.self::DESA)->assertUnauthorized();
        $this->getJson('/api/v1/ext/coast/kawasan-konservasi')->assertUnauthorized();
        $this->getJson('/api/v1/ext/coast/statistik')->assertUnauthorized();
    }

    public function test_an_unconfigured_datasource_answers_503_rather_than_connecting(): void
    {
        Config::set('datasources.sources.coast.connection.database', null);

        $this->getCoast('/api/v1/ext/coast/desa')->assertStatus(503);
        $this->getCoast('/api/v1/ext/coast/kawasan-konservasi')->assertStatus(503);
        $this->getCoast('/api/v1/ext/coast/statistik')->assertStatus(503);
    }

    public function test_the_api_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_coast')->table('identitas_coast')->count();

        $this->getCoast('/api/v1/ext/coast/desa')->assertOk();
        $this->getCoast('/api/v1/ext/coast/desa/'.self::DESA)->assertOk();
        $this->getCoast('/api/v1/ext/coast/kawasan-konservasi')->assertOk();
        $this->getCoast('/api/v1/ext/coast/statistik')->assertOk();

        $this->assertSame($before, DB::connection('ds_coast')->table('identitas_coast')->count());
    }

    // -- fixture ----------------------------------------------------------

    /** Finds a metric anywhere in the tree — top level, child, or grandchild. */
    private function metric(array $data, string $key): array
    {
        $found = $this->findMetric($data['statistik']['metrik'], $key);

        $this->assertNotNull($found, "Metrik '{$key}' tidak ada di respons.");

        return $found;
    }

    private function findMetric(array $rows, string $key): ?array
    {
        foreach ($rows as $row) {
            if ($row['key'] === $key) {
                return $row;
            }

            $nested = $this->findMetric($row['children'] ?? [], $key);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function assertMetric(array $data, string $key, float $baru, float $lama): void
    {
        $metric = $this->metric($data, $key);
        $this->assertSame($baru, (float) $metric['baru'], "Metrik '{$key}' tahun baru.");
        $this->assertSame($lama, (float) $metric['lama'], "Metrik '{$key}' tahun lama.");
    }

    private function bootCoastDatasource(): void
    {
        $this->database = storage_path('framework/testing/coast-'.uniqid().'.sqlite');
        touch($this->database);

        $connection = ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'foreign_key_constraints' => false];

        Config::set('datasources.sources.coast', [
            'label' => 'COAST',
            'read_only' => true,
            'connection' => $connection,
        ]);
        Config::set('database.connections.ds_coast', $connection);

        DB::purge('ds_coast');
    }

    /** Builds the schema and rows with the read-only guard lifted for the duration. */
    private function seedCoast(): void
    {
        Config::set('datasources.sources.coast.read_only', false);

        $db = DB::connection('ds_coast');

        $db->statement('create table identitas_coast (id integer primary key, form_id text, tanggal_pendataan text, provinsi text, provinsi_kode text, kabupaten_kota text, kabupaten_kode text, kecamatan text, kecamatan_kode text, desa text, desa_kode text, status text, deleted_at text)');
        $db->statement('create table coast_ecosystem (id integer primary key, form_id text, ekosistem text, luasan_ekosistem real, nilai_ekonomi real)');
        $db->statement('create table coast_blue_carbons (id integer primary key, form_id text, luas_area_dikonservasi real, luas_area_direhabilitasi real, nilai_stok_karbon real)');
        $db->statement('create table coast_ekonomi (id integer primary key, form_id text, jenis_kegiatan text, komoditas text, produksi real, jumlah_bibit_terjual integer, jumlah_kunjungan integer, economic_value_nett real)');
        $db->statement('create table coast_kelompok (id integer primary key, form_id text, jumlah_orang_dilatih integer, pria_orang_dilatih integer, wanita_orang_dilatih integer, remaja_orang_dilatih integer, lansia_orang_dilatih integer, disabilitas_orang_dilatih integer, jumlah_orang_terlibat integer, pria_orang_terlibat integer, wanita_orang_terlibat integer, remaja_orang_terlibat integer, lansia_orang_terlibat integer, disabilitas_orang_terlibat integer)');
        $db->statement('create table coast_blue_carbon_rehabilitasi (id integer primary key, form_id text, tanggal_rehabilitasi text, ekosistem text, status_lahan text, luas_area_direhabilitasi real, pelaksana_rehabilitasi text, kolaborator text, jumlah_bibit integer, survival_rate real, geometry text)');
        $db->statement('create table coast_kegiatan (id integer primary key, form_id text, tanggal_kegiatan text, nama_kegiatan text, jumlah_peserta_kegiatan integer, peserta_kegiatan_pria integer, peserta_kegiatan_wanita integer, peserta_kegiatan_remaja integer, peserta_kegiatan_lansia integer, peserta_kegiatan_disabilitas integer)');
        $db->statement('create table desa_images (id integer primary key, kode text, url text, keterangan text)');
        $db->statement('create table kawasan_konservasi (id integer primary key, nama_kawasan text, id_mpa text, luas_area_dikonservasi real, pelaksana_konservasi text, created_at text, updated_at text)');
        $db->statement('create table wilayah (kode text primary key, nama text, level integer, parent_kode text)');
        $db->statement('create table wilayah_boundaries (kode text primary key, nama text, lat real, lng real, luas real, penduduk integer, path text, fetched_at text)');

        $db->table('wilayah')->insert([
            ['kode' => '33', 'nama' => 'Jawa Tengah', 'level' => 1, 'parent_kode' => null],
            ['kode' => '33.21', 'nama' => 'Kabupaten Demak', 'level' => 2, 'parent_kode' => '33'],
            ['kode' => '33.21.12', 'nama' => 'Bonang', 'level' => 3, 'parent_kode' => '33.21'],
            ['kode' => self::DESA, 'nama' => 'Purworejo', 'level' => 4, 'parent_kode' => '33.21.12'],
            ['kode' => '33.20', 'nama' => 'Kabupaten Jepara', 'level' => 2, 'parent_kode' => '33'],
            ['kode' => '33.20.11', 'nama' => 'Tahunan', 'level' => 3, 'parent_kode' => '33.20'],
            ['kode' => self::DESA_LAIN, 'nama' => 'Telukawur', 'level' => 4, 'parent_kode' => '33.20.11'],
        ]);

        $db->table('wilayah_boundaries')->insert([
            ['kode' => self::DESA, 'nama' => 'Purworejo', 'lat' => -6.82, 'lng' => 110.56, 'luas' => 12.0, 'penduduk' => 4000, 'path' => '[[[-6.8,110.5],[-6.9,110.6]]]'],
            ['kode' => self::DESA_LAIN, 'nama' => 'Telukawur', 'lat' => -6.61, 'lng' => 110.66, 'luas' => 8.0, 'penduduk' => 2000, 'path' => '[[[-6.6,110.6]]]'],
        ]);

        // Two verified forms for the same village, spelt differently and one
        // filed under the wrong kecamatan — the case the wilayah lookup fixes.
        $this->form($db, 'F-2026', '2026-03-01', self::DESA, 'verified', desa: 'Purworejo', kecamatan: 'Purworejo');
        $this->form($db, 'F-2025', '2025-06-01', self::DESA, 'verified', desa: 'Purwareja', kecamatan: 'Bonang');
        // Excluded: a draft this year, a verified form outside the window, a
        // soft-deleted one, and a village whose only form is a draft.
        $this->form($db, 'F-DRAFT', '2026-02-01', self::DESA, 'draft');
        $this->form($db, 'F-2024', '2024-06-01', self::DESA, 'verified');
        $this->form($db, 'F-DEL', '2026-01-15', self::DESA, 'verified', deletedAt: '2026-02-01 00:00:00');
        $this->form($db, 'F-LAIN', '2026-05-01', self::DESA_LAIN, 'verified', desa: 'Telukawur', kecamatan: 'Tahunan');
        $this->form($db, 'F-NOPE', '2026-05-01', '33.99.99.9999', 'draft');

        $db->table('coast_ecosystem')->insert([
            ['form_id' => 'F-2026', 'ekosistem' => 'Mangrove', 'luasan_ekosistem' => 10.0, 'nilai_ekonomi' => 700.0],
            ['form_id' => 'F-2026', 'ekosistem' => ' Lamun ', 'luasan_ekosistem' => 2.5, 'nilai_ekonomi' => 100.0],
            ['form_id' => 'F-2026', 'ekosistem' => 'Mangrove,Lamun dan Terumbu Karang', 'luasan_ekosistem' => 6.0, 'nilai_ekonomi' => 100.0],
            ['form_id' => 'F-2025', 'ekosistem' => 'Mangrove', 'luasan_ekosistem' => 4.0, 'nilai_ekonomi' => 300.0],
            // The second village, so a national total is more than one village's.
            ['form_id' => 'F-LAIN', 'ekosistem' => 'Mangrove', 'luasan_ekosistem' => 5.0, 'nilai_ekonomi' => 200.0],
            ['form_id' => 'F-DRAFT', 'ekosistem' => 'Mangrove', 'luasan_ekosistem' => 999.0, 'nilai_ekonomi' => 999.0],
            ['form_id' => 'F-2024', 'ekosistem' => 'Mangrove', 'luasan_ekosistem' => 500.0, 'nilai_ekonomi' => 500.0],
            ['form_id' => 'F-DEL', 'ekosistem' => 'Mangrove', 'luasan_ekosistem' => 777.0, 'nilai_ekonomi' => 777.0],
        ]);

        $db->table('coast_blue_carbons')->insert([
            ['form_id' => 'F-2026', 'luas_area_dikonservasi' => 7.0, 'luas_area_direhabilitasi' => 1.5, 'nilai_stok_karbon' => 120.0],
            ['form_id' => 'F-2025', 'luas_area_dikonservasi' => 0.0, 'luas_area_direhabilitasi' => 0.0, 'nilai_stok_karbon' => 80.0],
            ['form_id' => 'F-LAIN', 'luas_area_dikonservasi' => 3.0, 'luas_area_direhabilitasi' => 0.5, 'nilai_stok_karbon' => 40.0],
        ]);

        // Perikanan Tangkap is split across two commodities so the second
        // level has something to nest; Wisata's is blank, to exercise the
        // "Lainnya" fallback for a commodity upstream never filled in.
        $db->table('coast_ekonomi')->insert([
            ['form_id' => 'F-2026', 'jenis_kegiatan' => 'Perikanan Tangkap', 'komoditas' => 'Rajungan', 'produksi' => 60.0, 'jumlah_bibit_terjual' => 0, 'jumlah_kunjungan' => 0, 'economic_value_nett' => 18.0],
            ['form_id' => 'F-2026', 'jenis_kegiatan' => 'Perikanan Tangkap', 'komoditas' => 'Kepiting Bakau', 'produksi' => 40.0, 'jumlah_bibit_terjual' => 0, 'jumlah_kunjungan' => 0, 'economic_value_nett' => 12.0],
            ['form_id' => 'F-2026', 'jenis_kegiatan' => 'Silvofishery', 'komoditas' => 'Bandeng', 'produksi' => 50.0, 'jumlah_bibit_terjual' => 0, 'jumlah_kunjungan' => 0, 'economic_value_nett' => 20.0],
            ['form_id' => 'F-2026', 'jenis_kegiatan' => 'Penjualan Bibit Mangrove', 'komoditas' => 'Rhizophora sp', 'produksi' => 0.0, 'jumlah_bibit_terjual' => 2000, 'jumlah_kunjungan' => 0, 'economic_value_nett' => 15.0],
            ['form_id' => 'F-2026', 'jenis_kegiatan' => 'Wisata', 'komoditas' => '', 'produksi' => 0.0, 'jumlah_bibit_terjual' => 0, 'jumlah_kunjungan' => 40, 'economic_value_nett' => 5.0],
            ['form_id' => 'F-2026', 'jenis_kegiatan' => 'Pengolahan Hasil Perikanan', 'komoditas' => 'Rengginang kepiting', 'produksi' => 30.0, 'jumlah_bibit_terjual' => 0, 'jumlah_kunjungan' => 0, 'economic_value_nett' => 5.0],
            ['form_id' => 'F-LAIN', 'jenis_kegiatan' => 'Perikanan Tangkap', 'komoditas' => 'Rajungan', 'produksi' => 25.0, 'jumlah_bibit_terjual' => 0, 'jumlah_kunjungan' => 0, 'economic_value_nett' => 8.0],
        ]);

        $db->table('coast_kelompok')->insert([
            $this->kelompok('F-2026', dilatih: [30, 18, 12, 5, 3, 2], terlibat: [50, 30, 20, 8, 4, 1]),
            $this->kelompok('F-2025', dilatih: [12, 7, 5, 2, 1, 0], terlibat: [20, 12, 8, 3, 2, 0]),
            $this->kelompok('F-LAIN', dilatih: [6, 4, 2, 1, 0, 0], terlibat: [10, 6, 4, 2, 1, 0]),
        ]);

        $db->table('coast_blue_carbon_rehabilitasi')->insert([
            ['form_id' => 'F-2026', 'tanggal_rehabilitasi' => '2026-04-02', 'ekosistem' => 'Mangrove', 'status_lahan' => 'Pribadi', 'luas_area_direhabilitasi' => 1.5, 'pelaksana_rehabilitasi' => 'Kelompok', 'kolaborator' => 'REKAM', 'jumlah_bibit' => 6000, 'survival_rate' => 88.5, 'geometry' => '[[0,0]]'],
            ['form_id' => 'F-2024', 'tanggal_rehabilitasi' => '2024-05-05', 'ekosistem' => 'Mangrove', 'status_lahan' => 'Negara', 'luas_area_direhabilitasi' => 2.0, 'pelaksana_rehabilitasi' => 'Dinas', 'kolaborator' => null, 'jumlah_bibit' => 1000, 'survival_rate' => 70.0, 'geometry' => '[[0,0]]'],
        ]);

        $db->table('coast_kegiatan')->insert([
            ['form_id' => 'F-2026', 'tanggal_kegiatan' => '2026-03-10', 'nama_kegiatan' => 'Pelatihan Silvofishery', 'jumlah_peserta_kegiatan' => 30, 'peserta_kegiatan_pria' => 18, 'peserta_kegiatan_wanita' => 12, 'peserta_kegiatan_remaja' => 5, 'peserta_kegiatan_lansia' => 3, 'peserta_kegiatan_disabilitas' => 2],
        ]);

        $db->table('desa_images')->insert([
            ['kode' => self::DESA, 'url' => 'https://example.test/purworejo.jpg', 'keterangan' => 'Tambak dan mangrove'],
        ]);

        // "BETAHWALANG" in capitals, as one area genuinely is upstream — it
        // must still sort between Anakan and Cilacap, not ahead of both.
        $db->table('kawasan_konservasi')->insert([
            ['id' => 3, 'nama_kawasan' => 'Kawasan Konservasi Cilacap', 'id_mpa' => 'T946', 'luas_area_dikonservasi' => 288.98, 'pelaksana_konservasi' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 1, 'nama_kawasan' => 'KAWASAN KONSERVASI BETAHWALANG', 'id_mpa' => 'T397', 'luas_area_dikonservasi' => 244.88, 'pelaksana_konservasi' => 'DKP Provinsi Jawa Tengah', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'nama_kawasan' => 'Kawasan Konservasi Anakan', 'id_mpa' => null, 'luas_area_dikonservasi' => 1730.58, 'pelaksana_konservasi' => 'DKP2SKSA', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);

        Config::set('datasources.sources.coast.read_only', true);
    }

    private function form($db, string $formId, string $tanggal, string $desaKode, string $status, ?string $desa = null, ?string $kecamatan = null, ?string $deletedAt = null): void
    {
        $parts = explode('.', $desaKode);

        $db->table('identitas_coast')->insert([
            'form_id' => $formId,
            'tanggal_pendataan' => $tanggal.' 00:00:00',
            'provinsi' => 'Jawa Tengah',
            'provinsi_kode' => $parts[0],
            'kabupaten_kota' => 'Kabupaten X',
            'kabupaten_kode' => $parts[0].'.'.$parts[1],
            'kecamatan' => $kecamatan ?? 'Kecamatan X',
            'kecamatan_kode' => implode('.', array_slice($parts, 0, 3)),
            'desa' => $desa ?? 'Desa X',
            'desa_kode' => $desaKode,
            'status' => $status,
            'deleted_at' => $deletedAt,
        ]);
    }

    /** @param  array{0:int,1:int,2:int,3:int,4:int,5:int}  $dilatih  total, pria, wanita, remaja, lansia, disabilitas */
    private function kelompok(string $formId, array $dilatih, array $terlibat): array
    {
        return [
            'form_id' => $formId,
            'jumlah_orang_dilatih' => $dilatih[0],
            'pria_orang_dilatih' => $dilatih[1],
            'wanita_orang_dilatih' => $dilatih[2],
            'remaja_orang_dilatih' => $dilatih[3],
            'lansia_orang_dilatih' => $dilatih[4],
            'disabilitas_orang_dilatih' => $dilatih[5],
            'jumlah_orang_terlibat' => $terlibat[0],
            'pria_orang_terlibat' => $terlibat[1],
            'wanita_orang_terlibat' => $terlibat[2],
            'remaja_orang_terlibat' => $terlibat[3],
            'lansia_orang_terlibat' => $terlibat[4],
            'disabilitas_orang_terlibat' => $terlibat[5],
        ];
    }
}
