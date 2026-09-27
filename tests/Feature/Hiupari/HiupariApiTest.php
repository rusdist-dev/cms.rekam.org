<?php

namespace Tests\Feature\Hiupari;

use App\Services\Hiupari\HiupariLengthFrequencyService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * The two HIUPARI endpoints: the species list, and the length histogram it
 * filters.
 *
 * The fixture is built around the problem this surface exists to handle —
 * sharks and rays are measured five different ways and no one column is filled
 * in for every animal, so the histogram is often drawn from a fraction of the
 * individuals in scope and has to say so.
 */
class HiupariApiTest extends TenantTestCase
{
    private string $key;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();

        $this->bootHiupariDatasource();
        $this->seedHiupari();
    }

    protected function tearDown(): void
    {
        // Purge first: on Windows the file stays locked while the connection
        // holds its PDO, and unlink() fails rather than the test failing.
        DB::purge('ds_hiupari');

        if (isset($this->database) && file_exists($this->database)) {
            @unlink($this->database);
        }

        parent::tearDown();
    }

    private function fetch(string $path, array $query = [])
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/ext/hiupari/'.$path.($query ? '?'.http_build_query($query) : ''));
    }

    private function histogram(array $query = []): array
    {
        return $this->fetch('grafik/frekuensi-panjang', $query)->assertOk()->json('data');
    }

    // -- the species list --------------------------------------------------

    public function test_the_species_list_counts_individuals_alphabetically(): void
    {
        $data = $this->fetch('opsi/spesies')->assertOk()->json('data');

        $this->assertSame([
            ['value' => 'Carcharhinus falciformis', 'jumlah_individu' => 56],
            ['value' => 'Prionace glauca', 'jumlah_individu' => 7],
            ['value' => 'Rhynchobatus australiae', 'jumlah_individu' => 12],
        ], $data);
    }

    public function test_the_species_list_counts_every_individual_not_only_the_measured_ones(): void
    {
        // Two of the twelve wedgefish have no total length; the list is there
        // to say which species exist, not which happen to carry one column.
        $this->assertSame(
            12,
            collect($this->fetch('opsi/spesies')->json('data'))->firstWhere('value', 'Rhynchobatus australiae')['jumlah_individu'],
        );
    }

    // -- the histogram -----------------------------------------------------

    public function test_it_bins_lengths_into_contiguous_classes(): void
    {
        $data = $this->histogram(['spesies' => 'Rhynchobatus australiae', 'selang_kelas' => 10]);

        $this->assertSame('cm', $data['unit']);
        $this->assertSame('panjang_total', $data['jenis_ukuran']);

        $this->assertSame(
            ['100' => 2, '110' => 3, '120' => 4, '130' => 1],
            collect($data['kelas'])->mapWithKeys(fn ($k) => [(string) $k['batas_bawah'] => $k['jumlah']])->all(),
        );
    }

    public function test_the_summary_describes_the_distribution(): void
    {
        $ringkasan = $this->histogram(['spesies' => 'Rhynchobatus australiae', 'selang_kelas' => 10])['ringkasan'];

        $this->assertSame(10, $ringkasan['jumlah_individu']);
        $this->assertSame(100.0, (float) $ringkasan['panjang_min']);
        $this->assertSame(130.0, (float) $ringkasan['panjang_maks']);
        // (100x2 + 110x3 + 120x4 + 130) / 10
        $this->assertSame(114.0, (float) $ringkasan['rata_rata']);
        $this->assertSame(120.0, (float) $ringkasan['median']);
        $this->assertSame(125.0, (float) $ringkasan['modus']);
    }

    public function test_the_class_width_is_the_callers_to_choose(): void
    {
        // At 25 wide, everything but the 130 falls in the first class.
        $this->assertSame(
            ['100' => 9, '125' => 1],
            collect($this->histogram(['spesies' => 'Rhynchobatus australiae', 'selang_kelas' => 25])['kelas'])
                ->mapWithKeys(fn ($k) => [(string) $k['batas_bawah'] => $k['jumlah']])
                ->all(),
        );

        $this->assertSame(1.0, (float) $this->histogram()['selang_kelas']);
    }

    // -- the measurement ---------------------------------------------------

    public function test_it_says_how_many_animals_the_chosen_measurement_left_out(): void
    {
        // Two of the twelve wedgefish were never measured this way.
        $this->assertSame(
            2,
            $this->histogram(['spesies' => 'Rhynchobatus australiae'])['ringkasan']['jumlah_tanpa_ukuran'],
        );

        // And five of the seven blue sharks: a histogram of two animals that
        // would otherwise look exactly like a histogram of all of them.
        $glauca = $this->histogram(['spesies' => 'Prionace glauca'])['ringkasan'];

        $this->assertSame(2, $glauca['jumlah_individu']);
        $this->assertSame(5, $glauca['jumlah_tanpa_ukuran']);
    }

    public function test_the_measurement_is_the_callers_to_choose(): void
    {
        $data = $this->histogram(['spesies' => 'Prionace glauca', 'jenis_ukuran' => 'predorsal_length']);

        $this->assertSame('predorsal_length', $data['jenis_ukuran']);
        $this->assertSame(5, $data['ringkasan']['jumlah_individu']);
        $this->assertSame(2, $data['ringkasan']['jumlah_tanpa_ukuran']);
    }

    public function test_it_reports_which_measurements_are_available_for_this_species(): void
    {
        // Ranked, so a caller looking at two blue sharks can see that
        // predorsal length would have given them five.
        $this->assertSame([
            ['jenis_ukuran' => 'predorsal_length', 'jumlah_individu' => 5],
            ['jenis_ukuran' => 'panjang_total', 'jumlah_individu' => 2],
            ['jenis_ukuran' => 'precaudal_length', 'jumlah_individu' => 0],
            ['jenis_ukuran' => 'fork_length', 'jumlah_individu' => 0],
            ['jenis_ukuran' => 'panjang_headless', 'jumlah_individu' => 0],
        ], $this->histogram(['spesies' => 'Prionace glauca'])['ketersediaan_ukuran']);
    }

    public function test_the_default_measurement_is_total_length(): void
    {
        $this->assertSame('panjang_total', HiupariLengthFrequencyService::DEFAULT_MEASUREMENT);
        $this->assertSame('panjang_total', $this->histogram()['jenis_ukuran']);
    }

    // -- the indicators ----------------------------------------------------

    public function test_linf_is_the_empirical_estimate_from_the_largest_animal(): void
    {
        $data = $this->histogram(['spesies' => 'Carcharhinus falciformis', 'jenis_kelamin' => 'M']);

        // Largest male is 130; 130 / 0.95.
        $this->assertSame(136.84, (float) $data['indikator']['linf']);
        $this->assertStringContainsString('Froese & Binohlan', $data['indikator']['linf_metode']);
    }

    public function test_linf_follows_the_chosen_selection_rather_than_the_species(): void
    {
        // The females reach 140, so including them moves the asymptote.
        $this->assertSame(
            147.37,
            (float) $this->histogram(['spesies' => 'Carcharhinus falciformis'])['indikator']['linf'],
        );
    }

    public function test_lm_is_computed_from_clasper_maturity_for_males(): void
    {
        $data = $this->histogram([
            'spesies' => 'Carcharhinus falciformis',
            'jenis_kelamin' => 'M',
            'selang_kelas' => 10,
        ]);

        // Maturity runs 9.09%, 40%, 80%, 90%; half is crossed a quarter of the
        // way from the 110 class midpoint to the 120 one.
        $this->assertSame(117.5, (float) $data['indikator']['lm']);
        $this->assertStringContainsString('kematangan klasper >= 3', $data['indikator']['lm_metode']);
        $this->assertSame(3, $data['kematangan_matang']);
    }

    public function test_lm_is_null_for_females_and_for_a_mixed_sample(): void
    {
        // Clasper maturity is a male character: a female recorded as stage 0
        // has no claspers, she is not immature, and averaging the two together
        // would read as though almost nothing in the sample had ever bred.
        foreach ([['jenis_kelamin' => 'F'], []] as $query) {
            $indikator = $this->histogram($query + ['spesies' => 'Carcharhinus falciformis'])['indikator'];

            $this->assertNull($indikator['lm']);
            $this->assertNull($indikator['persen_matang']);
            $this->assertStringContainsString('hanya tersedia untuk jantan', $indikator['lm_metode']);
        }
    }

    public function test_a_clasper_stage_outside_the_scale_is_not_maturity(): void
    {
        // One male carries stage 12 — a clasper length written into the
        // maturity column, as 158 real rows do. Its class holds eleven males
        // of which one is mature, not two.
        $classes = $this->histogram([
            'spesies' => 'Carcharhinus falciformis',
            'jenis_kelamin' => 'M',
            'selang_kelas' => 10,
        ])['kelas'];

        $first = collect($classes)->firstWhere('batas_bawah', 100);

        $this->assertSame(11, $first['jumlah']);
        $this->assertSame(1, $first['jumlah_matang']);
    }

    public function test_the_maturity_threshold_is_the_callers_to_choose(): void
    {
        $data = $this->histogram([
            'spesies' => 'Carcharhinus falciformis',
            'jenis_kelamin' => 'M',
            'selang_kelas' => 10,
            'kematangan_matang' => 1,
        ]);

        // Every male is at stage 1 or 3, so all fifty count — but not the
        // fifty-first, whose stage of 12 is off the scale entirely. 50 of 51.
        $this->assertSame(1, $data['kematangan_matang']);
        $this->assertSame(98.04, (float) $data['indikator']['persen_matang']);
    }

    public function test_maturity_is_absent_from_the_classes_unless_the_sample_is_male(): void
    {
        $mixed = collect($this->histogram(['spesies' => 'Carcharhinus falciformis'])['kelas'])
            ->firstWhere('batas_bawah', 100);

        $this->assertNull($mixed['jumlah_matang']);
        $this->assertNull($mixed['persen_matang']);
    }

    public function test_an_unknown_sex_is_refused(): void
    {
        $this->fetch('grafik/frekuensi-panjang', ['jenis_kelamin' => 'JANTAN'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jenis_kelamin');
    }

    public function test_a_clasper_stage_upstream_never_records_is_refused(): void
    {
        $this->fetch('grafik/frekuensi-panjang', ['kematangan_matang' => 12])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kematangan_matang');
    }

    // -- scope and edges ---------------------------------------------------

    public function test_an_animal_whose_trip_does_not_exist_is_excluded(): void
    {
        // The orphan is 999 long; the longest in scope is 220.
        $this->assertSame(220.0, (float) $this->histogram()['ringkasan']['panjang_maks']);
    }

    public function test_without_a_species_every_animal_is_included(): void
    {
        // 68 of the 75 animals in scope carry a total length.
        $this->assertSame(68, $this->histogram()['ringkasan']['jumlah_individu']);
        $this->assertSame(7, $this->histogram()['ringkasan']['jumlah_tanpa_ukuran']);
        $this->assertSame([], $this->histogram()['filter']);
    }

    public function test_a_species_matching_nothing_yields_an_empty_histogram(): void
    {
        $data = $this->histogram(['spesies' => 'TIDAK ADA']);

        $this->assertSame(0, $data['ringkasan']['jumlah_individu']);
        $this->assertSame([], $data['kelas']);
        $this->assertNull($data['ringkasan']['rata_rata']);
        $this->assertNull($data['ringkasan']['median']);
    }

    // -- validation --------------------------------------------------------

    public function test_an_unknown_measurement_is_refused(): void
    {
        $this->fetch('grafik/frekuensi-panjang', ['jenis_ukuran' => 'standard_length'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jenis_ukuran');
    }

    public function test_an_unusable_class_width_is_refused(): void
    {
        $this->fetch('grafik/frekuensi-panjang', ['selang_kelas' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('selang_kelas');
    }

    // -- the guards --------------------------------------------------------

    public function test_the_endpoints_require_an_api_key(): void
    {
        $this->getJson('/api/v1/ext/hiupari/opsi/spesies')->assertUnauthorized();
        $this->getJson('/api/v1/ext/hiupari/grafik/frekuensi-panjang')->assertUnauthorized();
    }

    public function test_an_unconfigured_datasource_answers_503(): void
    {
        Config::set('datasources.sources.hiupari.connection.database', null);

        $this->fetch('opsi/spesies')->assertStatus(503);
        $this->fetch('grafik/frekuensi-panjang')->assertStatus(503);
    }

    public function test_the_api_never_writes_to_the_datasource(): void
    {
        $before = DB::connection('ds_hiupari')->table('data_tangkapan_biologi')->count();

        $this->fetch('opsi/spesies')->assertOk();
        $this->fetch('grafik/frekuensi-panjang', ['spesies' => 'Prionace glauca'])->assertOk();

        $this->assertSame($before, DB::connection('ds_hiupari')->table('data_tangkapan_biologi')->count());
    }

    // -- fixture -----------------------------------------------------------

    private function bootHiupariDatasource(): void
    {
        $this->database = storage_path('framework/testing/hiupari-'.uniqid().'.sqlite');
        touch($this->database);

        $connection = ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'foreign_key_constraints' => false];

        Config::set('datasources.sources.hiupari', [
            'label' => 'HIUPARI',
            'read_only' => true,
            'connection' => $connection,
        ]);
        Config::set('database.connections.ds_hiupari', $connection);

        DB::purge('ds_hiupari');
    }

    /** Builds the schema and rows with the read-only guard lifted for the duration. */
    private function seedHiupari(): void
    {
        Config::set('datasources.sources.hiupari.read_only', false);

        $db = DB::connection('ds_hiupari');

        $db->statement('create table data_identitas_trip (id_trip text primary key, tanggal_pendataan text, provinsi text)');
        $db->statement('create table data_tangkapan_biologi (id integer primary key, id_trip text, family text, spesies text, jenis_kelamin text, kematangan_klasper integer, panjang_total real, fork_length real, precaudal_length real, predorsal_length real, panjang_headless real)');

        foreach (['T1', 'T2'] as $trip) {
            $db->table('data_identitas_trip')->insert([
                'id_trip' => $trip,
                'tanggal_pendataan' => '2026-01-10',
                'provinsi' => 'ACEH',
            ]);
        }

        // Ten wedgefish with a total length, laid out 2/3/4/1 across four
        // 10-wide classes, and two measured only by precaudal length.
        $lengths = [100, 100, 110, 110, 110, 120, 120, 120, 120, 130];

        foreach ($lengths as $length) {
            $this->measurement($db, 'T1', 'Rhynchobatus australiae', ['panjang_total' => $length]);
        }

        $this->measurement($db, 'T1', 'Rhynchobatus australiae', ['precaudal_length' => 90]);
        $this->measurement($db, 'T1', 'Rhynchobatus australiae', ['precaudal_length' => 95]);

        // Blue sharks: two with a total length, five with only a predorsal
        // one — the real table is this lopsided for several shark species.
        $this->measurement($db, 'T2', 'Prionace glauca', ['panjang_total' => 200]);
        $this->measurement($db, 'T2', 'Prionace glauca', ['panjang_total' => 220]);

        foreach ([50, 55, 60, 65, 70] as $predorsal) {
            $this->measurement($db, 'T2', 'Prionace glauca', ['predorsal_length' => $predorsal]);
        }

        $this->seedSilkySharks($db);

        // No such trip: must never reach a chart.
        $this->measurement($db, 'XX', 'Rhynchobatus australiae', ['panjang_total' => 999]);

        Config::set('datasources.sources.hiupari.read_only', true);
    }

    /**
     * Fifty male silky sharks in four 10-wide classes, with a clasper-maturity
     * curve of 10%, 40%, 80%, 90% — so Lm falls a quarter of the way from the
     * 110 class midpoint to the 120 one, at 117.5, and can be checked by hand.
     *
     * Plus five females, so that filtering to males matters, and one male whose
     * clasper stage is 12: upstream has 158 rows like that, clasper *lengths*
     * written into the maturity column, and they must not read as mature.
     *
     * @param  \Illuminate\Database\Connection  $db
     */
    private function seedSilkySharks($db): void
    {
        $plan = [
            ['panjang' => 100, 'jumlah' => 10, 'matang' => 1],
            ['panjang' => 110, 'jumlah' => 10, 'matang' => 4],
            ['panjang' => 120, 'jumlah' => 20, 'matang' => 16],
            ['panjang' => 130, 'jumlah' => 10, 'matang' => 9],
        ];

        foreach ($plan as $class) {
            for ($i = 0; $i < $class['jumlah']; $i++) {
                $this->measurement($db, 'T1', 'Carcharhinus falciformis', [
                    'panjang_total' => $class['panjang'],
                    'jenis_kelamin' => 'M',
                    'kematangan_klasper' => $i < $class['matang'] ? 3 : 1,
                ]);
            }
        }

        for ($i = 0; $i < 5; $i++) {
            $this->measurement($db, 'T1', 'Carcharhinus falciformis', [
                'panjang_total' => 140,
                'jenis_kelamin' => 'F',
                'kematangan_klasper' => 0,
            ]);
        }

        $this->measurement($db, 'T1', 'Carcharhinus falciformis', [
            'panjang_total' => 100,
            'jenis_kelamin' => 'M',
            'kematangan_klasper' => 12,
        ]);
    }

    /** @param  \Illuminate\Database\Connection  $db */
    private function measurement($db, string $trip, string $spesies, array $values): void
    {
        $db->table('data_tangkapan_biologi')->insert($values + [
            'id_trip' => $trip,
            'spesies' => $spesies,
            'family' => 'Rhinidae',
        ]);
    }
}
