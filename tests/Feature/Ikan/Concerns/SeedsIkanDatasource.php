<?php

namespace Tests\Feature\Ikan\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * A throwaway IKAN datasource on file-backed SQLite, shared by every test over
 * that surface.
 *
 * Possible only because the queries are written driver-agnostically — `substr()`
 * on a date rather than MySQL's `date_format()` — which is what lets this run
 * without a database server.
 */
trait SeedsIkanDatasource
{
    private string $ikanDatabase;

    private function bootIkanDatasource(): void
    {
        $this->ikanDatabase = storage_path('framework/testing/ikan-'.uniqid().'.sqlite');
        touch($this->ikanDatabase);

        $connection = ['driver' => 'sqlite', 'database' => $this->ikanDatabase, 'prefix' => '', 'foreign_key_constraints' => false];

        Config::set('datasources.sources.ikan', [
            'label' => 'IKAN',
            'read_only' => true,
            'connection' => $connection,
        ]);
        Config::set('database.connections.ds_ikan', $connection);

        DB::purge('ds_ikan');
    }

    private function forgetIkanDatasource(): void
    {
        // Purge first: on Windows the file stays locked while the connection
        // holds its PDO, and unlink() fails rather than the test failing.
        DB::purge('ds_ikan');

        if (isset($this->ikanDatabase) && file_exists($this->ikanDatabase)) {
            @unlink($this->ikanDatabase);
        }
    }

    /** Builds the schema and rows with the read-only guard lifted for the duration. */
    private function seedIkan(): void
    {
        Config::set('datasources.sources.ikan.read_only', false);

        $db = DB::connection('ds_ikan');

        $db->statement('create table data_identitas_trip (id_trip text primary key, jenis_data text, provinsi text, kabupaten text, lokasi_pendaratan text, tanggal_pendataan text)');
        $db->statement('create table data_operasional_trip (id_trip text primary key, "WPPNRI" text, alat_tangkap_utama text)');
        $db->statement('create table data_tangkapan_biologi (id integer primary key, id_trip text, family text, spesies text, tangkapan_per_jenis integer, panjang_total real, tipe_panjang text)');
        $db->statement('create table data_tangkapan_catch (id_trip text, jenis_data text, alat_tangkap_utama text, jenis_ikan text, family text, spesies text, total_catch real)');

        // Three Aceh trips in WPPNRI-572 and two Sulawesi ones in 713, shaped
        // so that every level of the filter chain has something left to narrow,
        // and spread over two years and three months for the charts.
        $trips = [
            ['T1', '2025-03-15', 'PELAGIS-572', 'ACEH', 'KOTA SABANG', 'PPS KUTARAJA', 'WPPNRI-572', 'PANCING ULUR'],
            ['T2', '2025-03-20', 'PELAGIS-572', 'ACEH', 'KOTA SABANG', 'PPS KUTARAJA', 'WPPNRI-572', 'PAYANG'],
            ['T3', '2026-01-10', 'BCAF', 'ACEH', 'ACEH BESAR', 'LAMPULO', 'WPPNRI-572', 'PANCING ULUR'],
            ['T4', '2026-02-05', 'SAILUS', 'SULAWESI SELATAN', 'PANGKEP', 'SAILUS', 'WPPNRI-713', 'PANAH'],
            ['T5', '2026-02-28', 'SAILUS', 'SULAWESI SELATAN', 'PANGKEP', 'KAPOPOSANG', 'WPPNRI-713', 'PANAH'],
        ];

        foreach ($trips as [$id, $tanggal, $jenis, $provinsi, $kabupaten, $lokasi, $wppnri, $alat]) {
            $db->table('data_identitas_trip')->insert([
                'id_trip' => $id,
                'jenis_data' => $jenis,
                'provinsi' => $provinsi,
                'kabupaten' => $kabupaten,
                'lokasi_pendaratan' => $lokasi,
                'tanggal_pendataan' => $tanggal,
            ]);

            $db->table('data_operasional_trip')->insert([
                'id_trip' => $id,
                'WPPNRI' => $wppnri,
                'alat_tangkap_utama' => $alat,
            ]);
        }

        // One row per fish measured. T1 carries the same species several times,
        // so a trip counted once is distinguishable from a trip counted per
        // fish; T5 carries none at all — 1.372 real trips are in that position,
        // and they must still be counted by every level above family.
        //
        // The twelve usable lengths are 12, 14, 16, 18, 20, 22, 22, 22, 22, 24,
        // 26 and 30, which at a 2-unit class width give a clean mode at 22 and
        // a left limb long enough for Lc to be worth computing. One row records
        // no length type (311 real rows do not) and one has a length of zero,
        // which is not a measurement and must not reach the histogram.
        $measurements = [
            ['T1', 'Scombridae', 'Euthynnus affinis', 20, 'FL'],
            ['T1', 'Scombridae', 'Euthynnus affinis', 22, 'FL'],
            ['T1', 'Scombridae', 'Euthynnus affinis', 22, 'FL'],
            ['T1', 'Scombridae', 'Euthynnus affinis', 22, 'FL'],
            ['T1', 'Scombridae', 'Euthynnus affinis', 24, 'FL'],
            ['T1', 'Scombridae', 'Katsuwonus pelamis', 30, 'FL'],
            ['T2', 'Clupeidae', 'Sardinella lemuru', 12, 'TL'],
            ['T2', 'Clupeidae', 'Sardinella lemuru', 14, 'TL'],
            ['T2', 'Clupeidae', 'Sardinella lemuru', 16, ''],
            ['T3', 'Scombridae', 'Euthynnus affinis', 22, 'FL'],
            ['T3', 'Scombridae', 'Euthynnus affinis', 26, 'FL'],
            ['T4', 'Epinephelidae', 'Cephalopholis argus', 18, 'TL'],
            ['T4', 'Epinephelidae', 'Cephalopholis argus', 0, 'TL'],
        ];

        foreach ($measurements as [$trip, $family, $spesies, $panjang, $tipe]) {
            $db->table('data_tangkapan_biologi')->insert([
                'id_trip' => $trip,
                'family' => $family,
                'spesies' => $spesies,
                'tangkapan_per_jenis' => 1,
                'panjang_total' => $panjang,
                'tipe_panjang' => $tipe,
            ]);
        }

        // Landed weight per species. T1's second row carries a gear of its own
        // that disagrees with its trip's — 1.110 of 17.900 real rows do — so a
        // gear filter can be shown to read the trip's gear, not this column.
        // T5 lands nothing, and one row has no weight at all.
        $catches = [
            ['T1', 'Katsuwonus pelamis', 'PANCING ULUR', 100.5],
            ['T1', 'Decapterus macarellus', 'JARING LINGKAR', 50.0],
            ['T2', 'Katsuwonus pelamis', 'PAYANG', 20.0],
            ['T3', 'Thunnus albacares', 'PANCING ULUR', 200.0],
            ['T4', 'Epinephelus merra', 'PANAH', 10.25],
            ['T4', 'Lutjanus gibbus', 'PANAH', null],
        ];

        foreach ($catches as [$trip, $spesies, $alat, $berat]) {
            $db->table('data_tangkapan_catch')->insert([
                'id_trip' => $trip,
                'spesies' => $spesies,
                'alat_tangkap_utama' => $alat,
                'total_catch' => $berat,
            ]);
        }

        Config::set('datasources.sources.ikan.read_only', true);
    }
}
