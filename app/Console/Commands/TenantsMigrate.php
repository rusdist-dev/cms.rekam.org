<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantMigrations;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs `database/migrations/tenant/` against every tenant database.
 *
 * Each tenant keeps its own `migrations` table, so the two companies' schema
 * histories are genuinely independent and may legitimately differ
 * (plan.md §2.2a). Never point `php artisan migrate` at a tenant database.
 */
class TenantsMigrate extends Command
{
    use ResolvesTenantMigrations;

    protected $signature = 'tenants:migrate
        {--tenant= : Batasi ke satu tenant (slug)}
        {--fresh : Drop seluruh tabel tenant lalu migrasi ulang}
        {--seed : Jalankan tenants:seed setelah migrasi}
        {--pretend : Tampilkan SQL tanpa menjalankannya}';

    protected $description = 'Menjalankan migrasi ke seluruh database tenant';

    public function handle(TenantManager $tenants): int
    {
        $targets = $this->targetTenants();

        if ($targets->isEmpty()) {
            return self::FAILURE;
        }

        if ($this->option('fresh') && app()->isProduction() && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $failed = [];

        foreach ($targets as $tenant) {
            $this->newLine();
            $this->components->info("Tenant: {$tenant->name} ({$tenant->db_name})");

            try {
                $tenants->setCurrent($tenant);
                $this->assertDatabaseExists($tenant);
                $this->migrate($tenant);
            } catch (Throwable $e) {
                // One broken tenant must not stop the others: a half-migrated
                // fleet is worse than knowing exactly which one failed.
                $failed[$tenant->slug] = $e->getMessage();
                $this->components->error($e->getMessage());
            }
        }

        $this->newLine();

        if ($failed) {
            $this->components->error('Gagal untuk tenant: '.implode(', ', array_keys($failed)));

            return self::FAILURE;
        }

        $this->components->info('Seluruh database tenant termigrasi.');

        if ($this->option('seed')) {
            $this->call('tenants:seed', array_filter(['--tenant' => $this->option('tenant')]));
        }

        return self::SUCCESS;
    }

    private function migrate(Tenant $tenant): void
    {
        $paths = $this->migrationPathsFor($tenant);

        if (! $paths) {
            $this->components->warn('Tidak ada folder migrasi tenant. Dilewati.');

            return;
        }

        if ($this->option('fresh')) {
            // `migrate:fresh` would target the default connection, so the drop
            // is scoped explicitly to this tenant's database.
            $this->call('db:wipe', ['--database' => 'tenant', '--force' => true]);
        }

        foreach ($paths as $path) {
            $this->components->twoColumnDetail('Folder', $path);

            $this->call('migrate', array_filter([
                '--database' => 'tenant',
                '--path' => $path,
                '--realpath' => false,
                '--force' => true,
                '--pretend' => $this->option('pretend') ?: null,
            ]));
        }
    }

    /**
     * A missing tenant database produces a connection error deep inside the
     * migrator; catching it here says what to actually do about it.
     */
    private function assertDatabaseExists(Tenant $tenant): void
    {
        try {
            DB::connection('tenant')->getPdo();
        } catch (Throwable $e) {
            throw new \RuntimeException(
                "Database '{$tenant->db_name}' tidak dapat diakses. ".
                "Buat dulu: CREATE DATABASE {$tenant->db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
            );
        }
    }

    private function confirmToProceed(): bool
    {
        return $this->confirm('Ini akan MENGHAPUS seluruh konten tenant. Lanjutkan?', false);
    }
}
