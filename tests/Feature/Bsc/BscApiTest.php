<?php

namespace Tests\Feature\Bsc;

use App\Services\Bsc\BscFilterService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Bsc\Concerns\SeedsBscDatasource;
use Tests\TenantTestCase;

/**
 * The four BSC surfaces: the filter chain, the trip chart, the catch
 * composition and the carapace-width histogram.
 *
 * Kept in one class because they share a scope rule that has to hold across
 * all of them — only `trip_nontrip = 'TRIP'` records count — and the clearest
 * way to show that is to assert it four times against one fixture.
 */
class BscApiTest extends TenantTestCase
{
    use SeedsBscDatasource;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();

        $this->bootBscDatasource();
        $this->seedBsc();
    }

    protected function tearDown(): void
    {
        $this->forgetBscDatasource();

        parent::tearDown();
    }

    private function fetch(string $path, array $query = [])
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/bsc/'.$path.($query ? '?'.http_build_query($query) : ''));
    }

    /** @return array<string, int> value => jumlah_trip */
    private function opsi(string $list, array $query = []): array
    {
        return collect($this->fetch('opsi/'.$list, $query)->assertOk()->json('data'))
            ->mapWithKeys(fn ($o) => [$o['value'] => $o['jumlah_trip']])
            ->all();
    }

    // -- the filter chain --------------------------------------------------

    public function test_the_chain_has_seven_levels_and_no_wppnri(): void
    {
        $service = app(BscFilterService::class);

        $this->assertSame([
            'provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_pendataan',
            'alat_tangkap', 'jenis_tangkapan', 'spesies',
        ], array_keys(BscFilterService::CHAIN));

        $this->assertSame([], array_keys($service->accepts('provinsi')));
        $this->assertSame(
            ['provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_pendataan', 'alat_tangkap', 'jenis_tangkapan'],
            array_keys($service->accepts('spesies')),
        );
    }

    public function test_each_list_offers_every_value_in_scope(): void
    {
        $this->assertSame(['BANTEN' => 1, 'JAWA TENGAH' => 3], $this->opsi('provinsi'));
        $this->assertSame(['DEMAK' => 2, 'JEPARA' => 1, 'SERANG' => 1], $this->opsi('kabupaten'));
        $this->assertSame(['BUBU LIPAT' => 3, 'JARING' => 1], $this->opsi('alat-tangkap'));
        $this->assertSame(['KEPITING' => 1, 'RAJUNGAN' => 3], $this->opsi('jenis-tangkapan'));
        $this->assertSame(['Portunus pelagicus' => 2, 'Scylla serrata' => 2], $this->opsi('spesies'));
    }

    public function test_each_level_is_narrowed_by_the_levels_above_it(): void
    {
        $this->assertSame(['DEMAK' => 2, 'JEPARA' => 1], $this->opsi('kabupaten', ['provinsi' => 'JAWA TENGAH']));
        $this->assertSame(['BETAHWALANG' => 2], $this->opsi('lokasi-pendaratan', ['kabupaten' => 'DEMAK']));
        $this->assertSame(['Scylla serrata' => 1], $this->opsi('spesies', ['alat_tangkap' => 'JARING']));
    }

    public function test_the_species_list_counts_trips_not_crabs(): void
    {
        // P1 alone carries fifty measured crabs; it must still count as one.
        $this->assertSame(2, $this->opsi('spesies')['Portunus pelagicus']);
    }

    // -- the trip chart ----------------------------------------------------

    public function test_the_trip_chart_carries_both_series(): void
    {
        $data = $this->fetch('grafik/trip')->assertOk()->json('data');

        $this->assertSame(['filter', 'total_trip', 'per_tanggal', 'per_lokasi_pendaratan'], array_keys($data));
        $this->assertSame(4, $data['total_trip']);

        $this->assertSame(
            ['2025-03' => 2, '2026-01' => 1, '2026-02' => 1],
            collect($data['per_tanggal'])->mapWithKeys(fn ($p) => [$p['periode'] => $p['jumlah_trip']])->all(),
        );

        // Alphabetical, matching how `opsi/lokasi-pendaratan` orders them.
        $this->assertSame(
            ['BETAHWALANG' => 2, 'GOJOYO' => 1, 'LABUHAN' => 1],
            collect($data['per_lokasi_pendaratan'])->mapWithKeys(fn ($l) => [$l['lokasi_pendaratan'] => $l['jumlah_trip']])->all(),
        );
    }

    public function test_the_trip_chart_groups_by_year_on_request(): void
    {
        $this->assertSame(
            ['2025' => 2, '2026' => 2],
            collect($this->fetch('grafik/trip', ['tipe_tanggal' => 'yearly'])->json('data.per_tanggal'))
                ->mapWithKeys(fn ($p) => [$p['periode'] => $p['jumlah_trip']])
                ->all(),
        );
    }

    public function test_a_bounded_range_fills_empty_months_with_zero(): void
    {
        $periods = collect($this->fetch('grafik/trip', ['dari' => '2025-03-01', 'sampai' => '2025-06-30'])->json('data.per_tanggal'))
            ->mapWithKeys(fn ($p) => [$p['periode'] => $p['jumlah_trip']])
            ->all();

        $this->assertSame(['2025-03' => 2, '2025-04' => 0, '2025-05' => 0, '2025-06' => 0], $periods);
    }

    // -- the catch composition ---------------------------------------------

    public function test_the_catch_composition_weighs_the_measured_crabs(): void
    {
        $data = $this->fetch('grafik/tangkapan')->assertOk()->json('data');

        $this->assertSame('gram', $data['unit']);

        // Portunus: P1's fifty at 100 plus P4's one at 150.
        // Scylla: P2's seven at 200 plus P3's single weighed 300.
        $this->assertSame(
            ['Portunus pelagicus' => 5150.0, 'Scylla serrata' => 1700.0],
            collect($data['per_spesies'])->mapWithKeys(fn ($s) => [$s['spesies'] => (float) $s['total_bobot']])->all(),
        );

        $this->assertSame(6850.0, (float) $data['total_bobot']);
    }

    public function test_an_unweighed_crab_does_not_drag_its_species_down(): void
    {
        // P3's second Scylla has no weight. It contributes nothing rather than
        // contributing a zero.
        $this->assertSame(
            1700.0,
            (float) collect($this->fetch('grafik/tangkapan')->json('data.per_spesies'))
                ->firstWhere('spesies', 'Scylla serrata')['total_bobot'],
        );
    }

    public function test_the_catch_composition_is_filterable(): void
    {
        $data = $this->fetch('grafik/tangkapan', ['alat_tangkap' => 'JARING'])->json('data');

        $this->assertSame(
            ['Scylla serrata' => 1400.0],
            collect($data['per_spesies'])->mapWithKeys(fn ($s) => [$s['spesies'] => (float) $s['total_bobot']])->all(),
        );
    }

    // -- the width histogram -----------------------------------------------

    public function test_the_histogram_bins_carapace_widths(): void
    {
        $data = $this->fetch('grafik/frekuensi-lebar', ['jenis_kelamin' => 'BETINA', 'spesies' => 'Portunus pelagicus'])
            ->assertOk()
            ->json('data');

        $this->assertSame('cm', $data['unit']);

        $this->assertSame(
            ['8' => 10, '9' => 10, '10' => 21, '11' => 10],
            collect($data['kelas'])->mapWithKeys(fn ($k) => [(string) $k['batas_bawah'] => $k['jumlah']])->all(),
        );
    }

    public function test_the_summary_describes_the_distribution(): void
    {
        // Filtered to P1 so the arithmetic is the fifty-crab plan exactly.
        $ringkasan = $this->fetch('grafik/frekuensi-lebar', [
            'jenis_kelamin' => 'BETINA',
            'lokasi_pendaratan' => 'BETAHWALANG',
        ])->json('data.ringkasan');

        $this->assertSame(50, $ringkasan['jumlah_individu']);
        $this->assertSame(8.0, (float) $ringkasan['lebar_min']);
        $this->assertSame(11.0, (float) $ringkasan['lebar_maks']);
        // (8x10 + 9x10 + 10x20 + 11x10) / 50
        $this->assertSame(9.6, (float) $ringkasan['rata_rata']);
        $this->assertSame(10.25, (float) $ringkasan['median']);
        $this->assertSame(10.5, (float) $ringkasan['modus']);
        $this->assertSame(0, $ringkasan['tanpa_tkg']);
    }

    public function test_lm_is_computed_from_the_gonad_stage(): void
    {
        $data = $this->fetch('grafik/frekuensi-lebar', [
            'jenis_kelamin' => 'BETINA',
            'lokasi_pendaratan' => 'BETAHWALANG',
        ])->json('data');

        $this->assertSame(2, $data['tkg_matang']);

        // Maturity runs 10%, 40%, 80%, 90%; half is crossed a quarter of the
        // way from the 9 class midpoint to the 10 class midpoint.
        $this->assertSame(9.75, (float) $data['indikator']['lm']);
        $this->assertSame(60.0, (float) $data['indikator']['persen_matang']);
        $this->assertStringContainsString('TKG >= 2', $data['indikator']['lm_metode']);

        // Limb runs to the modal class: 10 + 10 + 20 = 40, half is 20, which
        // lands exactly at the top of the 9 class.
        $this->assertSame(10.0, (float) $data['indikator']['lc']);
    }

    public function test_the_maturity_threshold_is_the_callers_to_choose(): void
    {
        $strict = $this->fetch('grafik/frekuensi-lebar', [
            'jenis_kelamin' => 'BETINA',
            'lokasi_pendaratan' => 'BETAHWALANG',
            'tkg_matang' => 3,
        ])->json('data');

        // Nothing reaches stage 3 in the fixture, so there is no width at
        // which half the crabs are mature — and null is the honest answer.
        $this->assertSame(3, $strict['tkg_matang']);
        $this->assertNull($strict['indikator']['lm']);
        $this->assertSame(0.0, (float) $strict['indikator']['persen_matang']);
    }

    public function test_each_class_reports_its_own_maturity(): void
    {
        $classes = $this->fetch('grafik/frekuensi-lebar', [
            'jenis_kelamin' => 'BETINA',
            'lokasi_pendaratan' => 'BETAHWALANG',
        ])->json('data.kelas');

        $this->assertSame(
            ['8' => 10.0, '9' => 40.0, '10' => 80.0, '11' => 90.0],
            collect($classes)->mapWithKeys(fn ($k) => [(string) $k['batas_bawah'] => (float) $k['persen_matang']])->all(),
        );
    }

    public function test_the_four_spellings_of_sex_are_normalised_to_two(): void
    {
        $komposisi = $this->fetch('grafik/frekuensi-lebar')->json('data.komposisi_jenis_kelamin');

        // BETINA is fifty from P1 (spelt BETINA, F and P), two from P3 and one
        // from P4; JANTAN is P2's six (JANTAN, M and L); one crab recorded "2"
        // belongs to neither.
        $this->assertSame([
            ['jenis_kelamin' => 'BETINA', 'jumlah' => 53],
            ['jenis_kelamin' => 'JANTAN', 'jumlah' => 6],
            ['jenis_kelamin' => null, 'jumlah' => 1],
        ], $komposisi);

        $this->assertSame(6, $this->fetch('grafik/frekuensi-lebar', ['jenis_kelamin' => 'JANTAN'])->json('data.ringkasan.jumlah_individu'));
    }

    public function test_an_unknown_sex_is_refused(): void
    {
        $this->fetch('grafik/frekuensi-lebar', ['jenis_kelamin' => 'M'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jenis_kelamin');
    }

    public function test_a_maturity_stage_upstream_never_records_is_refused(): void
    {
        $this->fetch('grafik/frekuensi-lebar', ['tkg_matang' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tkg_matang');
    }

    // -- the scope rule ----------------------------------------------------

    public function test_non_trip_records_are_excluded_from_every_endpoint(): void
    {
        // P5 is flagged NON TRIP and carries two 99-wide crabs at 9.999 gram.
        $this->assertArrayNotHasKey('BALI', $this->opsi('provinsi'));
        $this->assertArrayNotHasKey('KEDONGANAN', $this->opsi('lokasi-pendaratan'));

        $this->assertSame(4, $this->fetch('grafik/trip')->json('data.total_trip'));
        $this->assertSame(6850.0, (float) $this->fetch('grafik/tangkapan')->json('data.total_bobot'));
    }

    public function test_measurements_with_no_trip_at_all_are_excluded(): void
    {
        // The XX crab is 98 wide; the widest in scope is 16.
        $this->assertSame(16.0, (float) $this->fetch('grafik/frekuensi-lebar')->json('data.ringkasan.lebar_maks'));
    }

    // -- the guards --------------------------------------------------------

    public function test_every_endpoint_requires_an_api_key(): void
    {
        foreach (['opsi/provinsi', 'opsi/spesies', 'grafik/trip', 'grafik/tangkapan', 'grafik/frekuensi-lebar'] as $path) {
            $this->getJson('/api/v1/ext/bsc/'.$path)->assertUnauthorized();
        }
    }

    public function test_an_unconfigured_datasource_answers_503(): void
    {
        Config::set('datasources.sources.bsc.connection.database', null);

        $this->fetch('grafik/trip')->assertStatus(503);
        $this->fetch('opsi/provinsi')->assertStatus(503);
    }

    public function test_the_api_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_bsc')->table('data_biologi')->count();

        foreach (['opsi/spesies', 'grafik/trip', 'grafik/tangkapan', 'grafik/frekuensi-lebar'] as $path) {
            $this->fetch($path)->assertOk("Gagal pada {$path}.");
        }

        $this->assertSame($before, DB::connection('ds_bsc')->table('data_biologi')->count());
    }
}
