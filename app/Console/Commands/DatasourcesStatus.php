<?php

namespace App\Console\Commands;

use App\Services\DatasourceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Answers the first question of every deployment and every "the API returns
 * 503" report: which external databases does this environment actually reach?
 *
 * Deliberately a real connection attempt, not a config dump. Credentials that
 * look right in .env and a database the app can genuinely open are different
 * claims, and only the second one matters (docs/api-external.md).
 */
class DatasourcesStatus extends Command
{
    protected $signature = 'cms:datasources
        {--ping : Coba buka koneksi ke setiap datasource, bukan hanya membaca konfigurasi}';

    protected $description = 'Menampilkan status database eksternal (config/datasources.php)';

    public function handle(DatasourceRegistry $datasources): int
    {
        $sources = $datasources->all();

        if ($sources === []) {
            $this->components->warn('Belum ada datasource eksternal di config/datasources.php.');
            $this->line('  Lihat docs/api-external.md untuk cara menambahkannya.');

            return self::SUCCESS;
        }

        $ping = (bool) $this->option('ping');
        $failed = false;

        $rows = [];

        foreach ($sources as $source) {
            $status = $source['configured'] ? '<fg=green>terdaftar</>' : '<fg=yellow>belum diisi</>';

            if ($ping && $source['configured']) {
                $error = $this->ping($source['connection']);
                $status = $error === null
                    ? '<fg=green>terhubung</>'
                    : '<fg=red>gagal</>';

                if ($error !== null) {
                    $failed = true;
                    $this->components->error("{$source['label']}: {$error}");
                }
            }

            $rows[] = [
                $source['key'],
                $source['label'],
                $source['connection'],
                $source['host'] ?? '-',
                $source['database'] ?: '-',
                $source['read_only'] ? 'read-only' : 'read-write',
                $status,
            ];
        }

        $this->table(
            ['Key', 'Label', 'Koneksi', 'Host', 'Database', 'Mode', 'Status'],
            $rows,
        );

        // A missing datasource is a normal state on a laptop, so only a *failed*
        // connection is worth a non-zero exit — that is the one CI should catch.
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function ping(string $connection): ?string
    {
        try {
            DB::connection($connection)->getPdo();

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
