<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Base for tests that need two genuinely separate tenant databases.
 *
 * The tenant connection is pointed at file-backed SQLite here rather than
 * MySQL: the thing under test is the switching mechanism — purge, reconnect,
 * and one database per company — which is driver-agnostic, and this keeps the
 * suite runnable without a database server. Production behaviour on MariaDB is
 * verified by running `tenants:migrate` against the real databases.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $rekam;

    protected Tenant $perikanan;

    protected TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.tenant.driver', 'sqlite');
        Config::set('database.connections.tenant.foreign_key_constraints', true);

        $this->tenants = app(TenantManager::class);

        $this->seed(RolePermissionSeeder::class);

        $this->rekam = $this->makeTenant('rekam', 'Rekam', [
            'news' => true, 'news_programs' => true, 'events' => true,
            'event_rundown' => true, 'team' => true, 'partners' => true,
            'contacts' => true, 'units' => true,
            // Confirmed 2026-09-08: rekam.org now runs Publikasi too.
            'publications' => true, 'milestones' => false,
        ]);

        // Confirmed flags (plan.md §5.2.g, §5.3.f): perikanan runs milestones
        // but no events; rekam runs neither milestones nor events' mirror
        // (units). Both now run publications.
        $this->perikanan = $this->makeTenant('perikanan', 'Perikanan', [
            'news' => true, 'news_programs' => true, 'events' => false,
            'event_rundown' => false, 'team' => true, 'partners' => true,
            'contacts' => true, 'publications' => true, 'milestones' => true,
            'units' => false,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');

        foreach ([$this->rekam ?? null, $this->perikanan ?? null] as $tenant) {
            if ($tenant && File::exists($tenant->db_name)) {
                File::delete($tenant->db_name);
            }
        }

        parent::tearDown();
    }

    private function makeTenant(string $slug, string $name, array $features): Tenant
    {
        $path = storage_path("framework/testing/tenant_{$slug}_".getmypid().'.sqlite');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'db_name' => $path,
            'domain' => "{$slug}.test",
            'features' => $features,
            'is_active' => true,
        ]);

        $this->migrateTenant($tenant);
        $this->seedTenant($tenant);

        return $tenant;
    }

    /**
     * Seeds the shared defaults plus this company's own taxonomy, exactly as
     * `tenants:seed` does — including the slug-to-class mapping, which is the
     * only sanctioned place tenant identity is inspected (context.md §5.8).
     */
    private function seedTenant(Tenant $tenant): void
    {
        $this->tenants->setCurrent($tenant);

        $this->seed(\Database\Seeders\Tenant\TenantDatabaseSeeder::class);

        $studly = str($tenant->slug)->studly()->value();

        // Concatenated, not interpolated: in a double-quoted string `\{` escapes
        // the interpolation brace, so "...\{$studly}..." yields a literal
        // "{Rekam}" and the seeder is silently never found.
        $specific = 'Database\\Seeders\\Tenant\\'.$studly.'\\'.$studly.'Seeder';

        if (class_exists($specific)) {
            $this->seed($specific);
        }
    }

    /** Runs shared migrations then this tenant's own, exactly as the command does. */
    private function migrateTenant(Tenant $tenant): void
    {
        $this->tenants->setCurrent($tenant);

        $paths = ['database/migrations/tenant/shared'];

        if (is_dir(base_path("database/migrations/tenant/{$tenant->slug}"))) {
            $paths[] = "database/migrations/tenant/{$tenant->slug}";
        }

        foreach ($paths as $path) {
            $this->artisan('migrate', [
                '--database' => 'tenant',
                '--path' => $path,
                '--realpath' => false,
                '--force' => true,
            ]);
        }
    }

    protected function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(User::SUPER_ADMIN);
        $user->tenants()->sync([$this->rekam->id, $this->perikanan->id]);

        $this->actingAs($user);

        return $user;
    }

    protected function actingAsUserWith(string $role, array $tenants = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->tenants()->sync($tenants ?: [$this->rekam->id]);

        $this->actingAs($user);

        return $user;
    }

    /** Makes a tenant the active one, as the switcher would. */
    protected function useTenant(Tenant $tenant): void
    {
        session([TenantManager::SESSION_KEY => $tenant->id]);
        $this->tenants->switchTo($tenant->id);
    }
}
