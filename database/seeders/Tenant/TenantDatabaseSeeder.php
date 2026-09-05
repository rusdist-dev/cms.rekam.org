<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan tenants:seed`. Runs inside whichever tenant
 * connection the command has bound, so every seeder called from here must use
 * the `tenant` connection and never the central one (context.md §5.1).
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SiteSettingSeeder::class,
            NewsCategorySeeder::class,
        ]);
    }
}
