<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantMigrations;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Seeds each tenant database through its own connection. Tenant seeders live in
 * `database/seeders/Tenant/` and must never touch the central connection.
 */
class TenantsSeed extends Command
{
    use ResolvesTenantMigrations;

    protected $signature = 'tenants:seed
        {--tenant= : Batasi ke satu tenant (slug)}
        {--class=Database\\Seeders\\Tenant\\TenantDatabaseSeeder : Seeder yang dijalankan}';

    protected $description = 'Menjalankan seeder ke seluruh database tenant';

    public function handle(TenantManager $tenants): int
    {
        $targets = $this->targetTenants();

        if ($targets->isEmpty()) {
            return self::FAILURE;
        }

        $class = $this->option('class');

        if (! class_exists($class)) {
            $this->components->error("Seeder {$class} tidak ditemukan.");

            return self::FAILURE;
        }

        $failed = [];

        foreach ($targets as $tenant) {
            $this->newLine();
            $this->components->info("Tenant: {$tenant->name} ({$tenant->db_name})");

            try {
                $tenants->setCurrent($tenant);

                $this->call('db:seed', [
                    '--class' => $class,
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                $this->seedTenantSpecific($tenant->slug);
            } catch (Throwable $e) {
                $failed[$tenant->slug] = $e->getMessage();
                $this->components->error($e->getMessage());
            }
        }

        $this->newLine();

        if ($failed) {
            $this->components->error('Gagal untuk tenant: '.implode(', ', array_keys($failed)));

            return self::FAILURE;
        }

        $this->components->info('Seluruh database tenant ter-seed.');

        return self::SUCCESS;
    }

    /**
     * Runs `Database\Seeders\Tenant\{Slug}\{Slug}Seeder` when it exists.
     *
     * This mirrors how migrations map a slug to a folder, and is the same
     * sanctioned exception: identity decides *which file runs*, never what the
     * application does at runtime (context.md §5.8).
     */
    private function seedTenantSpecific(string $slug): void
    {
        $studly = str($slug)->studly()->value();
        $class = "Database\\Seeders\\Tenant\\{$studly}\\{$studly}Seeder";

        if (! class_exists($class)) {
            return;
        }

        $this->call('db:seed', [
            '--class' => $class,
            '--database' => 'tenant',
            '--force' => true,
        ]);
    }
}
