<?php

namespace Tests\Feature\Ikan;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ikan\Concerns\SeedsIkanDatasource;
use Tests\TenantTestCase;

/**
 * The length-frequency histogram and the indicators read off it.
 *
 * The fixture's twelve usable lengths are 12, 14, 16, 18, 20, 22, 22, 22, 22,
 * 24, 26 and 30, so every figure below is arithmetic that can be checked by
 * hand rather than a number copied out of a previous run.
 */
class IkanLengthFrequencyApiTest extends TenantTestCase
{
    use SeedsIkanDatasource;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();

        $this->bootIkanDatasource();
        $this->seedIkan();
    }

    protected function tearDown(): void
    {
        $this->forgetIkanDatasource();

        parent::tearDown();
    }

    private function chart(array $query = [])
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/ikan/grafik/frekuensi-panjang'.($query ? '?'.http_build_query($query) : ''));
    }

    /** @return array<float, int> batas_bawah => jumlah */
    private function classes(array $query = []): array
    {
        return collect($this->chart($query)->assertOk()->json('data.kelas'))
            ->mapWithKeys(fn ($k) => [(string) $k['batas_bawah'] => $k['jumlah']])
            ->all();
    }

    // -- the histogram -----------------------------------------------------

    public function test_it_bins_measurements_into_contiguous_classes(): void
    {
        // Class 16 is empty, and must still appear: a gap in the axis reads as
        // a bimodal distribution rather than as one missing bar.
        $this->assertSame([
            '12' => 1, '14' => 1, '16' => 1, '18' => 1, '20' => 1,
            '22' => 4, '24' => 1, '26' => 1, '28' => 0, '30' => 1,
        ], $this->classes(['selang_kelas' => 2]));
    }

    public function test_each_class_carries_its_share_and_running_share(): void
    {
        $classes = $this->chart(['selang_kelas' => 2])->json('data.kelas');
        $modal = collect($classes)->firstWhere('batas_bawah', 22);

        $this->assertSame(22.0, (float) $modal['batas_bawah']);
        $this->assertSame(24.0, (float) $modal['batas_atas']);
        $this->assertSame(23.0, (float) $modal['nilai_tengah']);

        // 4 of 12 fish, and 9 of 12 at or below this class.
        $this->assertSame(33.3333, (float) $modal['persen']);
        $this->assertSame(75.0, (float) $modal['kumulatif_persen']);

        $this->assertSame(100.0, (float) end($classes)['kumulatif_persen']);
    }

    public function test_the_class_width_is_the_callers_to_choose(): void
    {
        $this->assertSame(
            ['10' => 2, '15' => 2, '20' => 6, '25' => 1, '30' => 1],
            $this->classes(['selang_kelas' => 5]),
        );

        // Default width is 1.
        $this->assertSame(1.0, (float) $this->chart()->json('data.selang_kelas'));
    }

    public function test_a_length_of_zero_is_not_a_measurement(): void
    {
        // One fixture row records 0 — it was never measured, and counting it
        // would drag the mean down and invent a class at the bottom.
        $this->assertSame(12, $this->chart()->json('data.ringkasan.jumlah_ikan'));
        $this->assertSame(12.0, (float) $this->chart()->json('data.ringkasan.panjang_min'));
    }

    // -- the summary -------------------------------------------------------

    public function test_the_summary_describes_the_distribution(): void
    {
        $ringkasan = $this->chart(['selang_kelas' => 2])->assertOk()->json('data.ringkasan');

        $this->assertSame(12, $ringkasan['jumlah_ikan']);
        $this->assertSame(12.0, (float) $ringkasan['panjang_min']);
        $this->assertSame(30.0, (float) $ringkasan['panjang_maks']);
        // 248 / 12
        $this->assertSame(20.67, (float) $ringkasan['rata_rata']);
        // Sixth of twelve falls in class 22, one fish into its four: 22 + 2/4.
        $this->assertSame(22.5, (float) $ringkasan['median']);
        $this->assertSame(23.0, (float) $ringkasan['modus']);
    }

    public function test_min_max_and_mean_come_from_the_rows_not_from_the_bins(): void
    {
        // A 5-wide class starting at 10 would put the minimum at 10 and the
        // mean on class midpoints; both must stay what the fish actually were.
        $wide = $this->chart(['selang_kelas' => 5])->json('data.ringkasan');

        $this->assertSame(12.0, (float) $wide['panjang_min']);
        $this->assertSame(30.0, (float) $wide['panjang_maks']);
        $this->assertSame(20.67, (float) $wide['rata_rata']);
    }

    // -- the indicators ----------------------------------------------------

    public function test_lc_is_the_midpoint_of_the_ascending_limb_and_says_so(): void
    {
        $indikator = $this->chart(['selang_kelas' => 2])->json('data.indikator');

        // Limb runs to the modal class: 1+1+1+1+1+4 = 9 fish, half is 4.5,
        // which lands one fish into class 20.
        $this->assertSame(21.0, (float) $indikator['lc']);
        $this->assertStringContainsString('limb naik', $indikator['lc_metode']);
    }

    public function test_lm_is_an_input_because_the_database_does_not_hold_it(): void
    {
        $without = $this->chart(['selang_kelas' => 2])->json('data.indikator');

        $this->assertNull($without['lm']);
        $this->assertNull($without['persen_di_bawah_lm']);

        // Four of twelve fish are shorter than 20.
        $with = $this->chart(['selang_kelas' => 2, 'lm' => 20])->json('data.indikator');

        $this->assertSame(20.0, (float) $with['lm']);
        $this->assertSame(33.33, (float) $with['persen_di_bawah_lm']);
    }

    // -- length types ------------------------------------------------------

    public function test_it_reports_which_length_types_the_histogram_is_made_of(): void
    {
        // A fork length of 20 and a total length of 20 are not the same fish,
        // so a caller has to be able to see when a histogram mixes them.
        $this->assertSame(
            [
                ['tipe_panjang' => 'FL', 'jumlah' => 8],
                ['tipe_panjang' => 'TL', 'jumlah' => 3],
                ['tipe_panjang' => null, 'jumlah' => 1],
            ],
            $this->chart()->json('data.komposisi_tipe_panjang'),
        );
    }

    public function test_an_empty_length_type_means_both_and_a_given_one_narrows(): void
    {
        $this->assertSame(12, $this->chart()->json('data.ringkasan.jumlah_ikan'));

        $tl = $this->chart(['tipe_panjang' => 'TL'])->json('data.ringkasan');
        $this->assertSame(3, $tl['jumlah_ikan']);
        $this->assertSame(14.67, (float) $tl['rata_rata']);

        $fl = $this->chart(['tipe_panjang' => 'FL'])->json('data.ringkasan');
        $this->assertSame(8, $fl['jumlah_ikan']);
        $this->assertSame(23.5, (float) $fl['rata_rata']);
    }

    // -- the filters -------------------------------------------------------

    public function test_every_documented_filter_narrows_the_histogram(): void
    {
        // Aceh is T1's six fish, T2's three and T3's two.
        $this->assertSame(11, $this->chart(['provinsi' => 'ACEH'])->json('data.ringkasan.jumlah_ikan'));
        // WPPNRI-713 is T4 and T5: one measured fish between them.
        $this->assertSame(1, $this->chart(['wppnri' => 'WPPNRI-713'])->json('data.ringkasan.jumlah_ikan'));
        $this->assertSame(2, $this->chart(['lokasi_pendaratan' => 'LAMPULO'])->json('data.ringkasan.jumlah_ikan'));
        $this->assertSame(8, $this->chart(['family' => 'Scombridae'])->json('data.ringkasan.jumlah_ikan'));
        $this->assertSame(7, $this->chart(['spesies' => 'Euthynnus affinis'])->json('data.ringkasan.jumlah_ikan'));
    }

    public function test_the_date_range_is_inclusive_at_both_ends(): void
    {
        // Only T1 and T2 were collected in March 2025: six plus three fish.
        $this->assertSame(
            9,
            $this->chart(['dari' => '2025-03-15', 'sampai' => '2025-03-20'])->json('data.ringkasan.jumlah_ikan'),
        );
    }

    public function test_a_filter_matching_nothing_yields_an_empty_histogram(): void
    {
        $data = $this->chart(['spesies' => 'TIDAK ADA', 'lm' => 20])->assertOk()->json('data');

        $this->assertSame(0, $data['ringkasan']['jumlah_ikan']);
        $this->assertSame([], $data['kelas']);
        $this->assertNull($data['ringkasan']['rata_rata']);
        $this->assertNull($data['indikator']['lc']);
        $this->assertNull($data['indikator']['persen_di_bawah_lm']);
    }

    // -- validation --------------------------------------------------------

    public function test_an_unknown_length_type_is_refused(): void
    {
        $this->chart(['tipe_panjang' => 'SL'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipe_panjang');
    }

    public function test_an_unusable_class_width_is_refused(): void
    {
        $this->chart(['selang_kelas' => 0])->assertStatus(422)->assertJsonValidationErrors('selang_kelas');
        $this->chart(['selang_kelas' => 500])->assertStatus(422)->assertJsonValidationErrors('selang_kelas');
        $this->chart(['selang_kelas' => 'dua'])->assertStatus(422)->assertJsonValidationErrors('selang_kelas');
    }

    public function test_a_bad_date_range_is_refused(): void
    {
        $this->chart(['dari' => '2026-01-01', 'sampai' => '2025-01-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sampai');
    }

    // -- the guards --------------------------------------------------------

    public function test_the_endpoint_requires_an_api_key(): void
    {
        $this->getJson('/api/v1/ext/ikan/grafik/frekuensi-panjang')->assertUnauthorized();
    }

    public function test_an_unconfigured_datasource_answers_503_rather_than_connecting(): void
    {
        Config::set('datasources.sources.ikan.connection.database', null);

        $this->chart()->assertStatus(503);
    }

    public function test_the_chart_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_ikan')->table('data_tangkapan_biologi')->count();

        $this->chart(['selang_kelas' => 2, 'lm' => 20, 'tipe_panjang' => 'FL'])->assertOk();

        $this->assertSame($before, DB::connection('ds_ikan')->table('data_tangkapan_biologi')->count());
    }
}
