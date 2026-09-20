<?php

namespace Tests\Feature\Ikan;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ikan\Concerns\SeedsIkanDatasource;
use Tests\TenantTestCase;

/**
 * The trip chart: counts per collection period and per landing site, over one
 * shared filter state.
 *
 * The fixture holds five trips — two in March 2025 and three across January and
 * February 2026 — which is enough for both series, for the gap-filling rule,
 * and for showing that the two series describe the same filtered set.
 */
class IkanTripChartApiTest extends TenantTestCase
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
            ->getJson('/api/v1/ext/ikan/grafik/trip'.($query ? '?'.http_build_query($query) : ''));
    }

    /** @return array<string, int> periode => jumlah_trip */
    private function periods(array $query = []): array
    {
        return collect($this->chart($query)->assertOk()->json('data.per_tanggal'))
            ->mapWithKeys(fn ($p) => [$p['periode'] => $p['jumlah_trip']])
            ->all();
    }

    /** @return array<string, int> lokasi => jumlah_trip */
    private function sites(array $query = []): array
    {
        return collect($this->chart($query)->assertOk()->json('data.per_lokasi_pendaratan'))
            ->mapWithKeys(fn ($l) => [$l['lokasi_pendaratan'] => $l['jumlah_trip']])
            ->all();
    }

    // -- the two series ----------------------------------------------------

    public function test_it_returns_both_series_and_their_shared_total(): void
    {
        $data = $this->chart()->assertOk()->json('data');

        $this->assertSame(
            ['filter', 'total_trip', 'per_tanggal', 'per_lokasi_pendaratan'],
            array_keys($data),
        );

        $this->assertSame(5, $data['total_trip']);
        $this->assertSame(5, array_sum(array_column($data['per_tanggal'], 'jumlah_trip')));
        $this->assertSame(5, array_sum(array_column($data['per_lokasi_pendaratan'], 'jumlah_trip')));
    }

    public function test_monthly_is_the_default_grouping(): void
    {
        $this->assertSame(
            ['2025-03' => 2, '2026-01' => 1, '2026-02' => 2],
            $this->periods(),
        );

        $this->assertSame($this->periods(), $this->periods(['tipe_tanggal' => 'monthly']));
    }

    public function test_yearly_groups_by_year(): void
    {
        $this->assertSame(['2025' => 2, '2026' => 3], $this->periods(['tipe_tanggal' => 'yearly']));
    }

    public function test_periods_are_ordered_oldest_first(): void
    {
        $this->assertSame(['2025-03', '2026-01', '2026-02'], array_keys($this->periods()));
    }

    public function test_landing_sites_are_ordered_alphabetically(): void
    {
        // By name, not by size: PPS KUTARAJA has the most trips but sorts third.
        $this->assertSame(
            ['KAPOPOSANG' => 1, 'LAMPULO' => 1, 'PPS KUTARAJA' => 2, 'SAILUS' => 1],
            $this->sites(),
        );
    }

    // -- the filters -------------------------------------------------------

    public function test_one_filter_state_narrows_both_series_together(): void
    {
        $query = ['provinsi' => 'ACEH'];

        $this->assertSame(3, $this->chart($query)->json('data.total_trip'));
        $this->assertSame(['2025-03' => 2, '2026-01' => 1], $this->periods($query));
        $this->assertSame(['LAMPULO' => 1, 'PPS KUTARAJA' => 2], $this->sites($query));
    }

    public function test_every_documented_filter_narrows_the_chart(): void
    {
        $this->assertSame(2, $this->chart(['wppnri' => 'WPPNRI-713'])->json('data.total_trip'));
        $this->assertSame(1, $this->chart(['kabupaten' => 'ACEH BESAR'])->json('data.total_trip'));
        $this->assertSame(2, $this->chart(['lokasi_pendaratan' => 'PPS KUTARAJA'])->json('data.total_trip'));
        $this->assertSame(2, $this->chart(['jenis_data' => 'PELAGIS-572'])->json('data.total_trip'));
    }

    public function test_the_date_range_is_inclusive_at_both_ends(): void
    {
        // T1 is 2025-03-15 and T2 is 2025-03-20: a range ending exactly on T2's
        // date must include it.
        $this->assertSame(2, $this->chart(['dari' => '2025-03-15', 'sampai' => '2025-03-20'])->json('data.total_trip'));
        $this->assertSame(1, $this->chart(['dari' => '2025-03-16', 'sampai' => '2025-03-20'])->json('data.total_trip'));
    }

    public function test_the_filter_state_is_echoed_back(): void
    {
        $filter = $this->chart([
            'provinsi' => 'ACEH',
            'tipe_tanggal' => 'yearly',
            'dari' => '2025-01-01',
            'sampai' => '2026-12-31',
        ])->json('data.filter');

        $this->assertSame([
            'tipe_tanggal' => 'yearly',
            'dari' => '2025-01-01',
            'sampai' => '2026-12-31',
            'provinsi' => 'ACEH',
        ], $filter);
    }

    // -- gap filling -------------------------------------------------------

    public function test_a_bounded_range_fills_empty_periods_with_zero(): void
    {
        // A line chart needs a continuous axis; a month with no trips is
        // genuinely zero, not unknown.
        $periods = $this->periods(['dari' => '2025-01-01', 'sampai' => '2026-02-28']);

        $this->assertCount(14, $periods);
        $this->assertSame('2025-01', array_key_first($periods));
        $this->assertSame('2026-02', array_key_last($periods));
        $this->assertSame(0, $periods['2025-01']);
        $this->assertSame(2, $periods['2025-03']);
        $this->assertSame(0, $periods['2025-12']);
        $this->assertSame(2, $periods['2026-02']);
    }

    public function test_a_bounded_yearly_range_fills_whole_years(): void
    {
        $this->assertSame(
            ['2023' => 0, '2024' => 0, '2025' => 2, '2026' => 3],
            $this->periods(['tipe_tanggal' => 'yearly', 'dari' => '2023-06-01', 'sampai' => '2026-02-28']),
        );
    }

    public function test_an_unbounded_range_returns_only_periods_that_have_data(): void
    {
        // Real data runs from a single 2004 trip to today; filling that range
        // would emit 271 months, 212 of them empty.
        $this->assertSame(['2025-03', '2026-01', '2026-02'], array_keys($this->periods()));
        $this->assertSame(['2025-03', '2026-01', '2026-02'], array_keys($this->periods(['dari' => '2025-01-01'])));
        $this->assertSame(['2025-03', '2026-01', '2026-02'], array_keys($this->periods(['sampai' => '2026-12-31'])));
    }

    // -- validation --------------------------------------------------------

    public function test_a_bad_grouping_is_refused_rather_than_silently_coerced(): void
    {
        $this->chart(['tipe_tanggal' => 'bulanan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipe_tanggal');
    }

    public function test_a_bad_date_format_is_refused(): void
    {
        $this->chart(['dari' => '01/02/2026'])->assertStatus(422)->assertJsonValidationErrors('dari');
        $this->chart(['sampai' => '2026-13-45'])->assertStatus(422)->assertJsonValidationErrors('sampai');
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
        $this->getJson('/api/v1/ext/ikan/grafik/trip')->assertUnauthorized();
    }

    public function test_an_unconfigured_datasource_answers_503_rather_than_connecting(): void
    {
        Config::set('datasources.sources.ikan.connection.database', null);

        $this->chart()->assertStatus(503);
    }

    public function test_the_chart_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_ikan')->table('data_identitas_trip')->count();

        $this->chart(['tipe_tanggal' => 'yearly', 'dari' => '2025-01-01', 'sampai' => '2026-12-31'])->assertOk();

        $this->assertSame($before, DB::connection('ds_ikan')->table('data_identitas_trip')->count());
    }
}
