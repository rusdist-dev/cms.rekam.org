<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Some hosts lock every MySQL database to its own dedicated user, with no way
 * to grant one shared user access to more than one tenant database — the
 * shared DB_TENANT_USERNAME/PASSWORD in .env cannot cover that (docs/deploy.md).
 *
 * Console-only, deliberately: a tenant is already provisioned from the console
 * (TenantPolicy), and a database password should not cross HTTP at all when
 * the console is already where this happens.
 */
class SetTenantDbCredentials extends Command
{
    protected $signature = 'tenant:db-credentials
        {slug : Slug tenant, sesuai kolom `slug` di tabel tenants}
        {--username= : Username database; diminta interaktif bila kosong}
        {--password= : Password database; diminta interaktif (tersembunyi) bila kosong}
        {--clear : Hapus kredensial khusus, kembali memakai kredensial .env bersama}';

    protected $description = 'Mengatur kredensial database khusus satu tenant (untuk host yang mengunci satu user per database)';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->components->error("Tenant dengan slug \"{$this->argument('slug')}\" tidak ditemukan.");

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $tenant->setDatabaseCredentials(null, null);

            $this->components->info("Kredensial khusus {$tenant->name} dihapus — kembali memakai kredensial .env bersama.");

            return self::SUCCESS;
        }

        $username = $this->option('username') ?: $this->ask('Username database');
        $password = $this->option('password') ?: $this->secret('Password database');

        $validator = Validator::make(
            ['username' => $username, 'password' => $password],
            [
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $tenant->setDatabaseCredentials($username, $password);

        $this->newLine();
        $this->components->info("Kredensial database {$tenant->name} disimpan.");
        $this->components->twoColumnDetail('Tenant', "{$tenant->name} ({$tenant->slug})");
        $this->components->twoColumnDetail('Username', $username);

        $this->newLine();
        $this->components->warn(
            'Password disimpan terenkripsi dan tidak dapat ditampilkan lagi. Kesalahan ketik baru '
            .'terlihat saat tenant ini berikutnya diaktifkan — pastikan sudah benar sekarang.'
        );

        return self::SUCCESS;
    }
}
