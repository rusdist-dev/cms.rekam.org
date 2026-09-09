<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Some hosts lock every MySQL database to its own dedicated user, so a tenant
 * can override the shared .env tenant-connection credentials (docs/deploy.md).
 *
 * Not a TenantTestCase: that base class forces the `tenant` connection to
 * sqlite in setUp(), which would hide real username/password behaviour.
 * Assertions read config('database.connections.tenant.*') directly rather
 * than opening a real MySQL socket — TenantManager::setCurrent() never
 * queries through the connection itself, only repoints it.
 */
class TenantDbCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_with_credentials_overrides_the_shared_connection(): void
    {
        $tenant = Tenant::factory()->create([
            'db_username' => 'tenant_specific_user',
            'db_password' => 'tenant-specific-secret',
        ]);

        app(TenantManager::class)->setCurrent($tenant);

        $this->assertSame('tenant_specific_user', config('database.connections.tenant.username'));
        $this->assertSame('tenant-specific-secret', config('database.connections.tenant.password'));
    }

    public function test_a_tenant_without_credentials_falls_back_to_the_shared_defaults(): void
    {
        $defaultUsername = config('database.connections.tenant.username');
        $defaultPassword = config('database.connections.tenant.password');

        $tenant = Tenant::factory()->create();

        app(TenantManager::class)->setCurrent($tenant);

        $this->assertSame($defaultUsername, config('database.connections.tenant.username'));
        $this->assertSame($defaultPassword, config('database.connections.tenant.password'));
    }

    public function test_switching_away_from_an_overridden_tenant_does_not_leak_its_credentials(): void
    {
        $defaultUsername = config('database.connections.tenant.username');
        $defaultPassword = config('database.connections.tenant.password');

        $overridden = Tenant::factory()->create([
            'db_username' => 'overridden_user',
            'db_password' => 'overridden-secret',
        ]);
        $plain = Tenant::factory()->create();

        $tenants = app(TenantManager::class);
        $tenants->setCurrent($overridden);
        $tenants->setCurrent($plain);

        // Must resolve to the true .env default, not the previous tenant's
        // override — the exact bug the constructor-captured defaults avoid.
        $this->assertSame($defaultUsername, config('database.connections.tenant.username'));
        $this->assertSame($defaultPassword, config('database.connections.tenant.password'));
    }

    public function test_the_password_is_stored_encrypted(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->setDatabaseCredentials('a_user', 'a-plaintext-secret');

        $raw = DB::table('tenants')->where('id', $tenant->id)->value('db_password');

        $this->assertNotSame('a-plaintext-secret', $raw);
        $this->assertSame('a-plaintext-secret', $tenant->fresh()->db_password);
    }

    public function test_setting_null_credentials_clears_an_existing_override(): void
    {
        $tenant = Tenant::factory()->create([
            'db_username' => 'was_set',
            'db_password' => 'was-set',
        ]);

        $tenant->setDatabaseCredentials(null, null);

        $this->assertNull($tenant->fresh()->db_username);
        $this->assertNull($tenant->fresh()->db_password);
    }

    public function test_console_command_sets_credentials_non_interactively(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'rekam']);

        $this->artisan('tenant:db-credentials', [
            'slug' => 'rekam',
            '--username' => 'cli_user',
            '--password' => 'cli-secret',
        ])->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame('cli_user', $fresh->db_username);
        $this->assertSame('cli-secret', $fresh->db_password);
    }

    public function test_console_command_clear_reverts_to_shared_credentials(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'rekam',
            'db_username' => 'cli_user',
            'db_password' => 'cli-secret',
        ]);

        $this->artisan('tenant:db-credentials', ['slug' => 'rekam', '--clear' => true])
            ->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertNull($fresh->db_username);
        $this->assertNull($fresh->db_password);
    }

    public function test_console_command_fails_cleanly_for_an_unknown_slug(): void
    {
        $this->artisan('tenant:db-credentials', [
            'slug' => 'tidak-ada',
            '--username' => 'x',
            '--password' => 'y',
        ])->assertFailed();
    }
}
