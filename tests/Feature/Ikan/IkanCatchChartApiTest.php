<?php

namespace Tests\Feature\Ikan;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ikan\Concerns\SeedsIkanDatasource;
use Tests\TenantTestCase;

/**
 * Total landed weight per species.
 *
 * The fixture lands four species across four of the five trips, with one row
 * whose own gear disagrees with its trip's and one with no weight recorded —
 * both of which occur in the real table and both of which change the answer if
 * handled carelessly.
 */
class IkanCatchChartApiTest extends TenantTestCase
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
            ->getJson('/api/v1/ext/ikan/grafik/tangkapan'.($query ? '?'.http_build_query($query) : ''));
    }

    /** @return array<string, float> spesies => total_catch */
    private function species(array $query = []): array
    {
        return collect($this->chart($query)->assertOk()->json('data.per_spesies'))
            ->mapWithKeys(fn ($s) => [$s['spesies'] => (float) $s['total_catch']])
            ->all();
    }

    // -- the series --------------------------------------------------------

    public function test_it_totals_the_landed_weight_of_each_species(): void
    {
        $data = $this->chart()->assertOk()->json('data');

        $this->assertSame(['filter', 'unit', 'total_catch', 'per_spesies'], array_keys($data));
        $this->assertSame('kg', $data['unit']);

        // Katsuwonus is landed by two trips, 100.5 + 20.
        $this->assertSame([
            'Thunnus albacares' => 200.0,
            'Katsuwonus pelamis' => 120.5,
            'Decapterus macarellus' => 50.0,
            'Epinephelus merra' => 10.25,
        ], $this->species());

        $this->assertSame(380.75, (float) $data['total_catch']);
    }

    public function test_species_are_ordered_heaviest_first(): void
    {
        $this->assertSame(
            ['Thunnus albacares', 'Katsuwonus pelamis', 'Decapterus macarellus', 'Epinephelus merra'],
            array_keys($this->species()),
        );
    }

    public function test_the_grand_total_is_the_sum_of_the_species(): void
    {
        $data = $this->chart(['provinsi' => 'ACEH'])->json('data');

        $this->assertSame(
            (float) $data['total_catch'],
            round(array_sum(array_column($data['per_spesies'], 'total_catch')), 2),
        );
    }

    public function test_a_row_with_no_recorded_weight_does_not_become_a_zero_species(): void
    {
        // T4 lands Lutjanus gibbus with a null weight. Summing must skip it
        // rather than publish a species that appears to have been weighed at 0.
        $this->assertArrayNotHasKey('Lutjanus gibbus', $this->species());
    }

    // -- the filters -------------------------------------------------------

    public function test_every_documented_filter_narrows_the_chart(): void
    {
        $this->assertSame(370.5, (float) $this->chart(['provinsi' => 'ACEH'])->json('data.total_catch'));
        $this->assertSame(10.25, (float) $this->chart(['wppnri' => 'WPPNRI-713'])->json('data.total_catch'));
        $this->assertSame(200.0, (float) $this->chart(['kabupaten' => 'ACEH BESAR'])->json('data.total_catch'));
        $this->assertSame(170.5, (float) $this->chart(['lokasi_pendaratan' => 'PPS KUTARAJA'])->json('data.total_catch'));
        $this->assertSame(170.5, (float) $this->chart(['jenis_data' => 'PELAGIS-572'])->json('data.total_catch'));
    }

    public function test_the_gear_filter_reads_the_trips_gear_not_the_catch_rows_own(): void
    {
        // T1's Decapterus row is tagged JARING LINGKAR, but T1 is a PANCING
        // ULUR trip — so it belongs to this result. Reading the catch table's
        // own column would drop those 50 kg and would also leave five
        // `opsi/alat-tangkap` options matching nothing at all.
        $this->assertSame([
            'Thunnus albacares' => 200.0,
            'Katsuwonus pelamis' => 100.5,
            'Decapterus macarellus' => 50.0,
        ], $this->species(['alat_tangkap' => 'PANCING ULUR']));

        // And the catch table's own vocabulary is not a filter at all.
        $this->assertSame([], $this->species(['alat_tangkap' => 'JARING LINGKAR']));
    }

    public function test_the_date_range_is_inclusive_at_both_ends(): void
    {
        $this->assertSame(
            ['Katsuwonus pelamis' => 120.5, 'Decapterus macarellus' => 50.0],
            $this->species(['dari' => '2025-03-15', 'sampai' => '2025-03-20']),
        );

        // Excluding T1's day drops its 100.5 and its 50.
        $this->assertSame(
            ['Katsuwonus pelamis' => 20.0],
            $this->species(['dari' => '2025-03-16', 'sampai' => '2025-03-20']),
        );
    }

    public function test_the_filter_state_is_echoed_back(): void
    {
        $filter = $this->chart([
            'provinsi' => 'ACEH',
            'alat_tangkap' => 'PANCING ULUR',
            'dari' => '2025-01-01',
            'sampai' => '2026-12-31',
        ])->json('data.filter');

        $this->assertSame([
            'dari' => '2025-01-01',
            'sampai' => '2026-12-31',
            'provinsi' => 'ACEH',
            'alat_tangkap' => 'PANCING ULUR',
        ], $filter);
    }

    public function test_a_filter_matching_nothing_yields_an_empty_series(): void
    {
        $data = $this->chart(['provinsi' => 'TIDAK ADA'])->assertOk()->json('data');

        $this->assertSame([], $data['per_spesies']);
        $this->assertSame(0.0, (float) $data['total_catch']);
    }

    // -- validation --------------------------------------------------------

    public function test_a_bad_date_format_is_refused(): void
    {
        $this->chart(['dari' => '01/02/2026'])->assertStatus(422)->assertJsonValidationErrors('dari');
    }

    public function test_a_range_that_ends_before_it_starts_is_refused(): void
    {
        $this->chart(['dari' => '2026-01-01', 'sampai' => '2025-01-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sampai');
    }

    // -- the guards --------------------------------------------------------

    public function test_the_endpoint_requires_an_api_key(): void
    {
        $this->getJson('/api/v1/ext/ikan/grafik/tangkapan')->assertUnauthorized();
    }

    public function test_an_unconfigured_datasource_answers_503_rather_than_connecting(): void
    {
        Config::set('datasources.sources.ikan.connection.database', null);

        $this->chart()->assertStatus(503);
    }

    public function test_the_chart_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_ikan')->table('data_tangkapan_catch')->count();

        $this->chart(['provinsi' => 'ACEH', 'dari' => '2025-01-01', 'sampai' => '2026-12-31'])->assertOk();

        $this->assertSame($before, DB::connection('ds_ikan')->table('data_tangkapan_catch')->count());
    }
}
