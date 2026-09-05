<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the central database only. Tenant content is seeded separately by
     * `php artisan tenants:seed`, because it lives on a different connection
     * (context.md §5.1).
     *
     * Order matters: roles must exist before the admin can be given one, and
     * tenants before the admin can be assigned to them.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            TenantSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
