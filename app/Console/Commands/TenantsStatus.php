<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantMigrations;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Answers "is any tenant behind?" — the failure mode plan.md §7 flags as most
 * likely to reach production: a migration that ran on one database and not the
 * other.
 */
class TenantsStatus extends Command
{
    use ResolvesTenantMigrations;

    protected $signature = 'tenants:status {--tenant= : Batasi ke satu tenant (slug)}';

    protected $description = 'Menampilkan migrasi yang tertinggal di tiap database tenant';

    public function handle(TenantManager $tenants): int
    {
        $targets = $this->targetTenants();

        if ($targets->isEmpty()) {
            return self::FAILURE;
        }

        $behind = [];

        foreach ($targets as $tenant) {
            $this->newLine();
            $this->components->info("{$tenant->name} ({$tenant->db_name})");

            try {
                $tenants->setCurrent($tenant);
                $pending = $this->pendingFor($tenant);
            } catch (Throwable $e) {
                $this->components->error($e->getMessage());
                $behind[$tenant->slug] = ['(tidak dapat diakses)'];

                continue;
            }

            if (! $pending) {
                $this->components->twoColumnDetail('Status', '<fg=green>Mutakhir</>');

                continue;
            }

            $behind[$tenant->slug] = $pending;

            $this->components->twoColumnDetail('Status', '<fg=yellow>'.count($pending).' migrasi tertinggal</>');

            foreach ($pending as $name) {
                $this->components->twoColumnDetail('  '.$name, '<fg=yellow>Pending</>');
            }
        }

        $this->newLine();

        if ($behind) {
            $this->components->warn('Jalankan `php artisan tenants:migrate` untuk: '.implode(', ', array_keys($behind)));

            return self::FAILURE;
        }

        $this->components->info('Semua tenant mutakhir.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function pendingFor(Tenant $tenant): array
    {
        DB::connection('tenant')->getPdo();

        $ran = Schema::connection('tenant')->hasTable('migrations')
            ? DB::connection('tenant')->table('migrations')->pluck('migration')->all()
            : [];

        $available = [];

        foreach ($this->migrationPathsFor($tenant) as $path) {
            foreach (glob(base_path($path).'/*.php') ?: [] as $file) {
                $available[] = basename($file, '.php');
            }
        }

        sort($available);

        return array_values(array_diff($available, $ran));
    }
}
