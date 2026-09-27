<?php

namespace Tests\Feature\Bsc\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * A throwaway BSC datasource on file-backed SQLite, shared by every test over
 * that surface.
 *
 * The fixture is built so that every figure the tests assert can be worked out
 * on paper: P1 carries fifty female Portunus pelagicus laid out in four width
 * classes with a deliberate maturity curve, and everything else exists to show
 * what gets excluded.
 */
trait SeedsBscDatasource
{
    private string $bscDatabase;

    private function bootBscDatasource(): void
    {
        $this->bscDatabase = storage_path('framework/testing/bsc-'.uniqid().'.sqlite');
        touch($this->bscDatabase);

        $connection = ['driver' => 'sqlite', 'database' => $this->bscDatabase, 'prefix' => '', 'foreign_key_constraints' => false];

        Config::set('datasources.sources.bsc', [
            'label' => 'BSC',
            'read_only' => true,
            'connection' => $connection,
        ]);
        Config::set('database.connections.ds_bsc', $connection);

        DB::purge('ds_bsc');
    }

    private function forgetBscDatasource(): void
    {
        // Purge first: on Windows the file stays locked while the connection
        // holds its PDO, and unlink() fails rather than the test failing.
        DB::purge('ds_bsc');

        if (isset($this->bscDatabase) && file_exists($this->bscDatabase)) {
            @unlink($this->bscDatabase);
        }
    }

    /** Builds the schema and rows with the read-only guard lifted for the duration. */
    private function seedBsc(): void
    {
        Config::set('datasources.sources.bsc.read_only', false);

        $db = DB::connection('ds_bsc');

        $db->statement('create table data_trip (id_trip text primary key, jenis_pendataan text, tanggal text, provinsi text, kabupaten text, lokasi_pendaratan text, alat_tangkap text, jenis_tangkapan text, total_tangkapan_utama real, trip_nontrip text)');
        $db->statement('create table data_biologi (id_trip text, spesies text, lebar_karapas real, bobot real, jenis_kelamin text, TKG integer)');
        // Present so the tests can show it is ignored, never read.
        $db->statement('create table data_nontrip (id_nontrip text, spesies text, lebar_karapas real, bobot real, jenis_kelamin text, TKG integer)');

        $trips = [
            ['P1', '2025-03-15', 'JAWA TENGAH', 'DEMAK', 'BETAHWALANG', 'BCAF', 'BUBU LIPAT', 'RAJUNGAN', 'TRIP'],
            ['P2', '2025-03-20', 'JAWA TENGAH', 'DEMAK', 'BETAHWALANG', 'BCAF', 'JARING', 'RAJUNGAN', 'TRIP'],
            ['P3', '2026-01-10', 'JAWA TENGAH', 'JEPARA', 'GOJOYO', 'PANTURA', 'BUBU LIPAT', 'KEPITING', 'TRIP'],
            ['P4', '2026-02-05', 'BANTEN', 'SERANG', 'LABUHAN', 'TELUK BANTEN', 'BUBU LIPAT', 'RAJUNGAN', 'TRIP'],
            // Excluded from every endpoint: its measurements belong to
            // data_nontrip, and 669 real rows of data_biologi hang off records
            // like it.
            ['P5', '2026-02-28', 'BALI', 'BADUNG', 'KEDONGANAN', 'UNTIRTA', 'JARING', 'RAJUNGAN', 'NON TRIP'],
        ];

        foreach ($trips as [$id, $tanggal, $prov, $kab, $lokasi, $pendataan, $alat, $tangkapan, $jenis]) {
            $db->table('data_trip')->insert([
                'id_trip' => $id,
                'tanggal' => $tanggal,
                'provinsi' => $prov,
                'kabupaten' => $kab,
                'lokasi_pendaratan' => $lokasi,
                'jenis_pendataan' => $pendataan,
                'alat_tangkap' => $alat,
                'jenis_tangkapan' => $tangkapan,
                'total_tangkapan_utama' => 100.0,
                'trip_nontrip' => $jenis,
            ]);
        }

        $this->seedP1Females($db);

        $rows = [
            // P2: six males across three spellings, plus one whose sex is
            // recorded as "2" and cannot be normalised.
            ['P2', 'Scylla serrata', 12, 200, 'JANTAN', 1],
            ['P2', 'Scylla serrata', 12, 200, 'JANTAN', 1],
            ['P2', 'Scylla serrata', 13, 200, 'M', 1],
            ['P2', 'Scylla serrata', 13, 200, 'M', 1],
            ['P2', 'Scylla serrata', 14, 200, 'L', 1],
            ['P2', 'Scylla serrata', 14, 200, 'L', 1],
            ['P2', 'Scylla serrata', 15, 200, '2', null],
            // P3: one weighed, one not — a species must not be reported at
            // zero weight because nobody put it on the scales.
            ['P3', 'Scylla serrata', 16, 300, 'BETINA', 2],
            ['P3', 'Scylla serrata', 16, null, 'BETINA', 2],
            ['P4', 'Portunus pelagicus', 10, 150, 'BETINA', 2],
            // Never in scope: P5 is a NON TRIP record, and XX has no trip.
            ['P5', 'Portunus pelagicus', 99, 9999, 'BETINA', 3],
            ['P5', 'Portunus pelagicus', 99, 9999, 'BETINA', 3],
            ['XX', 'Portunus pelagicus', 98, 8888, 'BETINA', 3],
        ];

        foreach ($rows as [$trip, $spesies, $lebar, $bobot, $sex, $tkg]) {
            $db->table('data_biologi')->insert([
                'id_trip' => $trip,
                'spesies' => $spesies,
                'lebar_karapas' => $lebar,
                'bobot' => $bobot,
                'jenis_kelamin' => $sex,
                'TKG' => $tkg,
            ]);
        }

        $db->table('data_nontrip')->insert([
            'id_nontrip' => 'P5',
            'spesies' => 'Portunus pelagicus',
            'lebar_karapas' => 97,
            'bobot' => 7777,
            'jenis_kelamin' => 'BETINA',
            'TKG' => 3,
        ]);

        Config::set('datasources.sources.bsc.read_only', true);
    }

    /**
     * Fifty female Portunus pelagicus on P1, in four width classes.
     *
     * The maturity curve is 10%, 40%, 80%, 90%, which crosses half a quarter
     * of the way from the 9 class to the 10 class — so Lm is 9.75 and can be
     * checked by hand. Sex rotates through the three spellings upstream uses
     * for female, so the normalisation has something to normalise.
     *
     * @param  \Illuminate\Database\Connection  $db
     */
    private function seedP1Females($db): void
    {
        $plan = [
            ['lebar' => 8, 'jumlah' => 10, 'matang' => 1],
            ['lebar' => 9, 'jumlah' => 10, 'matang' => 4],
            ['lebar' => 10, 'jumlah' => 20, 'matang' => 16],
            ['lebar' => 11, 'jumlah' => 10, 'matang' => 9],
        ];

        $spellings = ['BETINA', 'F', 'P'];
        $index = 0;

        foreach ($plan as $class) {
            for ($i = 0; $i < $class['jumlah']; $i++) {
                $db->table('data_biologi')->insert([
                    'id_trip' => 'P1',
                    'spesies' => 'Portunus pelagicus',
                    'lebar_karapas' => $class['lebar'],
                    'bobot' => 100,
                    'jenis_kelamin' => $spellings[$index++ % count($spellings)],
                    'TKG' => $i < $class['matang'] ? 2 : 1,
                ]);
            }
        }
    }
}
