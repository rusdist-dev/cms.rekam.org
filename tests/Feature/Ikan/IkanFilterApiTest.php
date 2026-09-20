<?php

namespace Tests\Feature\Ikan;

use App\Services\Ikan\IkanFilterService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ikan\Concerns\SeedsIkanDatasource;
use Tests\TenantTestCase;

/**
 * The chained filter dropdowns over the IKAN datasource.
 *
 * The point of these endpoints is that a dropdown never offers a choice that
 * leads nowhere, so most of what is asserted here is narrowing: what each level
 * still offers once the levels above it have been picked.
 */
class IkanFilterApiTest extends TenantTestCase
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

    private function fetch(string $path, array $filters = []): array
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/ikan/opsi/'.$path.($filters ? '?'.http_build_query($filters) : ''))
            ->assertOk()
            ->json('data');
    }

    /** @return array<string, int> value => jumlah_trip, in the order returned */
    private function values(string $path, array $filters = []): array
    {
        return collect($this->fetch($path, $filters))
            ->mapWithKeys(fn ($o) => [$o['value'] => $o['jumlah_trip']])
            ->all();
    }

    // -- the unfiltered lists ---------------------------------------------

    public function test_each_list_offers_every_value_present_in_the_data(): void
    {
        $this->assertSame(['WPPNRI-572' => 3, 'WPPNRI-713' => 2], $this->values('wppnri'));
        $this->assertSame(['ACEH' => 3, 'SULAWESI SELATAN' => 2], $this->values('provinsi'));
        $this->assertSame(['ACEH BESAR' => 1, 'KOTA SABANG' => 2, 'PANGKEP' => 2], $this->values('kabupaten'));
        $this->assertSame(['PANAH' => 2, 'PANCING ULUR' => 2, 'PAYANG' => 1], $this->values('alat-tangkap'));
    }

    public function test_options_are_ordered_alphabetically(): void
    {
        $this->assertSame(
            ['KAPOPOSANG', 'LAMPULO', 'PPS KUTARAJA', 'SAILUS'],
            array_keys($this->values('lokasi-pendaratan')),
        );
    }

    public function test_every_option_carries_how_many_trips_it_still_covers(): void
    {
        $option = $this->fetch('wppnri')[0];

        $this->assertSame(['value', 'jumlah_trip'], array_keys($option));
        $this->assertSame('WPPNRI-572', $option['value']);
        $this->assertSame(3, $option['jumlah_trip']);
    }

    // -- the chain ---------------------------------------------------------

    public function test_each_level_is_narrowed_by_the_levels_above_it(): void
    {
        $this->assertSame(['SULAWESI SELATAN' => 2], $this->values('provinsi', ['wppnri' => 'WPPNRI-713']));

        $this->assertSame(
            ['ACEH BESAR' => 1, 'KOTA SABANG' => 2],
            $this->values('kabupaten', ['wppnri' => 'WPPNRI-572', 'provinsi' => 'ACEH']),
        );

        $this->assertSame(
            ['PPS KUTARAJA' => 2],
            $this->values('lokasi-pendaratan', ['provinsi' => 'ACEH', 'kabupaten' => 'KOTA SABANG']),
        );

        $this->assertSame(
            ['BCAF' => 1],
            $this->values('jenis-data', ['lokasi_pendaratan' => 'LAMPULO']),
        );

        $this->assertSame(
            ['PANCING ULUR' => 1, 'PAYANG' => 1],
            $this->values('alat-tangkap', ['jenis_data' => 'PELAGIS-572']),
        );
    }

    public function test_kabupaten_can_be_narrowed_by_provinsi_alone(): void
    {
        // The whole point of the chain: after picking ACEH, the kabupaten
        // dropdown must not still offer PANGKEP.
        $this->assertSame(
            ['ACEH BESAR' => 1, 'KOTA SABANG' => 2],
            $this->values('kabupaten', ['provinsi' => 'ACEH']),
        );
    }

    public function test_a_filter_may_be_skipped_without_breaking_the_ones_below_it(): void
    {
        // Jumping straight from provinsi to alat_tangkap, with no kabupaten,
        // lokasi or jenis_data in between.
        $this->assertSame(
            ['PANCING ULUR' => 2, 'PAYANG' => 1],
            $this->values('alat-tangkap', ['provinsi' => 'ACEH']),
        );
    }

    public function test_a_filter_from_below_its_own_level_is_ignored(): void
    {
        // A client that keeps its whole filter state and sends it to every
        // level must not accidentally narrow a list by something beneath it.
        $this->assertSame(
            ['WPPNRI-572' => 3, 'WPPNRI-713' => 2],
            $this->values('wppnri', ['provinsi' => 'ACEH', 'alat_tangkap' => 'PAYANG']),
        );
    }

    public function test_a_value_that_matches_nothing_yields_an_empty_list(): void
    {
        $this->assertSame([], $this->fetch('kabupaten', ['provinsi' => 'TIDAK ADA']));
    }

    public function test_an_empty_filter_value_is_treated_as_no_filter(): void
    {
        // A dropdown reset to its placeholder sends `provinsi=`, which must
        // mean "all", not "the province whose name is the empty string".
        $this->assertSame($this->values('kabupaten'), $this->values('kabupaten', ['provinsi' => '']));
    }

    // -- family and species ------------------------------------------------

    public function test_family_and_species_list_what_was_actually_measured(): void
    {
        $this->assertSame(
            ['Clupeidae' => 1, 'Epinephelidae' => 1, 'Scombridae' => 2],
            $this->values('family'),
        );

        $this->assertSame(
            [
                'Cephalopholis argus' => 1,
                'Euthynnus affinis' => 2,
                'Katsuwonus pelamis' => 1,
                'Sardinella lemuru' => 1,
            ],
            $this->values('spesies'),
        );
    }

    public function test_jumlah_trip_counts_trips_not_fish_measured(): void
    {
        // T1 recorded Euthynnus affinis three times and T3 once: two trips,
        // four measurements. A plain count(*) would say four.
        $this->assertSame(2, $this->values('spesies')['Euthynnus affinis']);
        $this->assertSame(2, $this->values('family')['Scombridae']);
    }

    public function test_family_and_species_continue_the_chain(): void
    {
        $this->assertSame(
            ['Scombridae' => 2],
            $this->values('family', ['alat_tangkap' => 'PANCING ULUR']),
        );

        $this->assertSame(
            ['Euthynnus affinis' => 2, 'Katsuwonus pelamis' => 1],
            $this->values('spesies', ['family' => 'Scombridae']),
        );

        $this->assertSame(
            ['Euthynnus affinis' => 1],
            $this->values('spesies', ['provinsi' => 'ACEH', 'jenis_data' => 'BCAF', 'family' => 'Scombridae']),
        );
    }

    public function test_a_trip_with_no_measurements_still_counts_above_the_family_level(): void
    {
        // T5 has no biological record. Joining that table for every list would
        // silently drop it — Sulawesi Selatan would report one trip, not two.
        $this->assertSame(2, $this->values('provinsi')['SULAWESI SELATAN']);
        $this->assertSame(2, $this->values('kabupaten')['PANGKEP']);
        $this->assertSame(2, $this->values('alat-tangkap')['PANAH']);

        // At the family level, excluding it is the right answer: it measured
        // nothing, so there is no family it could be filed under.
        $this->assertSame(
            ['Epinephelidae' => 1],
            $this->values('family', ['provinsi' => 'SULAWESI SELATAN']),
        );
    }

    // -- the chain definition ---------------------------------------------

    public function test_the_chain_defines_both_the_levels_and_what_each_accepts(): void
    {
        $service = app(IkanFilterService::class);

        $this->assertSame([
            'wppnri', 'provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_data',
            'alat_tangkap', 'family', 'spesies',
        ], array_keys(IkanFilterService::CHAIN));

        $this->assertSame([], array_keys($service->accepts('wppnri')));
        $this->assertSame(['wppnri', 'provinsi'], array_keys($service->accepts('kabupaten')));
        $this->assertSame(
            ['wppnri', 'provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_data'],
            array_keys($service->accepts('alat_tangkap')),
        );
        $this->assertSame(
            ['wppnri', 'provinsi', 'kabupaten', 'lokasi_pendaratan', 'jenis_data', 'alat_tangkap', 'family'],
            array_keys($service->accepts('spesies')),
        );
    }

    // -- the guards --------------------------------------------------------

    public function test_the_endpoints_require_an_api_key(): void
    {
        $this->getJson('/api/v1/ext/ikan/opsi/wppnri')->assertUnauthorized();
        $this->getJson('/api/v1/ext/ikan/opsi/alat-tangkap')->assertUnauthorized();
    }

    public function test_an_unconfigured_datasource_answers_503_rather_than_connecting(): void
    {
        Config::set('datasources.sources.ikan.connection.database', null);

        $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/ikan/opsi/provinsi')
            ->assertStatus(503);
    }

    public function test_the_api_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_ikan')->table('data_identitas_trip')->count();

        foreach (['wppnri', 'provinsi', 'kabupaten', 'lokasi-pendaratan', 'jenis-data', 'alat-tangkap', 'family', 'spesies'] as $list) {
            $this->fetch($list);
        }

        $this->assertSame($before, DB::connection('ds_ikan')->table('data_identitas_trip')->count());
    }
}
