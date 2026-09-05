<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\TenantNotResolvedException;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * The guarantee the whole architecture exists for: one company's content is
 * never visible while the other is active (context.md §5.11).
 */
class TenantIsolationTest extends TenantTestCase
{
    public function test_content_written_for_one_tenant_is_invisible_to_the_other(): void
    {
        $this->tenants->setCurrent($this->rekam);
        SiteSetting::put('site', 'identity', ['name' => 'Rekam Indonesia']);

        $this->tenants->setCurrent($this->perikanan);
        SiteSetting::put('site', 'identity', ['name' => 'Perikanan Indonesia']);

        $this->tenants->setCurrent($this->rekam);
        $this->assertSame('Rekam Indonesia', SiteSetting::get('site', 'identity')['name']);

        $this->tenants->setCurrent($this->perikanan);
        $this->assertSame('Perikanan Indonesia', SiteSetting::get('site', 'identity')['name']);

        // Exactly one identity row per database — the write went to one company,
        // not to both.
        foreach ([$this->rekam, $this->perikanan] as $tenant) {
            $this->tenants->setCurrent($tenant);
            $this->assertSame(1, SiteSetting::where('group', 'site')->where('key', 'identity')->count());
        }
    }

    public function test_switching_tenant_actually_repoints_the_connection(): void
    {
        // Without the purge/reconnect in setCurrent, Laravel keeps handing back
        // the PDO opened for the previous tenant and the switch does nothing.
        $this->tenants->setCurrent($this->rekam);
        $first = DB::connection('tenant')->getDatabaseName();

        $this->tenants->setCurrent($this->perikanan);
        $second = DB::connection('tenant')->getDatabaseName();

        $this->assertNotSame($first, $second);
        $this->assertSame($this->rekam->db_name, $first);
        $this->assertSame($this->perikanan->db_name, $second);
    }

    public function test_a_content_query_without_an_active_tenant_throws(): void
    {
        // A silent fallback to the default connection is how one company's rows
        // end up in the other's database (context.md §5.3).
        $this->tenants->forget();

        $this->expectException(TenantNotResolvedException::class);

        SiteSetting::count();
    }

    public function test_tenant_specific_tables_exist_only_in_their_own_database(): void
    {
        $this->tenants->setCurrent($this->rekam);
        $this->assertTrue(Schema::connection('tenant')->hasTable('units'));
        $this->assertFalse(Schema::connection('tenant')->hasTable('milestones'));

        $this->tenants->setCurrent($this->perikanan);
        $this->assertTrue(Schema::connection('tenant')->hasTable('milestones'));
        $this->assertFalse(Schema::connection('tenant')->hasTable('units'));
    }

    public function test_shared_tables_exist_in_every_tenant(): void
    {
        foreach ([$this->rekam, $this->perikanan] as $tenant) {
            $this->tenants->setCurrent($tenant);

            $this->assertTrue(Schema::connection('tenant')->hasTable('site_settings'));
            $this->assertTrue(Schema::connection('tenant')->hasTable('media'));
        }
    }

    public function test_each_tenant_keeps_its_own_migration_history(): void
    {
        // Separate histories are what allow the two schemas to legitimately
        // differ (plan.md §2.2a).
        $this->tenants->setCurrent($this->rekam);
        $rekamRan = DB::connection('tenant')->table('migrations')->pluck('migration')->all();

        $this->tenants->setCurrent($this->perikanan);
        $perikananRan = DB::connection('tenant')->table('migrations')->pluck('migration')->all();

        $this->assertContains('2026_09_04_120000_create_units_table', $rekamRan);
        $this->assertNotContains('2026_09_04_120000_create_units_table', $perikananRan);

        $this->assertContains('2026_09_04_120000_create_milestones_table', $perikananRan);
        $this->assertNotContains('2026_09_04_120000_create_milestones_table', $rekamRan);
    }

    public function test_content_never_lands_in_the_central_database(): void
    {
        $this->tenants->setCurrent($this->rekam);
        SiteSetting::put('site', 'identity', ['name' => 'Rekam']);

        $this->assertFalse(
            Schema::connection(config('database.default'))->hasTable('site_settings'),
            'Tabel konten tidak boleh ada di database pusat.'
        );
    }

    public function test_for_each_tenant_restores_the_previous_tenant(): void
    {
        $this->tenants->setCurrent($this->rekam);

        $names = $this->tenants->forEachTenant(fn ($tenant) => $tenant->slug);

        $this->assertSame(['rekam', 'perikanan'], array_keys($names));
        // Aggregating across companies must not leave the request pointed at
        // the last one it visited (context.md §5.4).
        $this->assertSame($this->rekam->id, $this->tenants->currentId());
    }

    public function test_taxonomy_options_differ_per_tenant(): void
    {
        $taxonomy = app(\App\Services\TaxonomyService::class);

        $this->tenants->setCurrent($this->rekam);
        SiteSetting::put('news', 'programs', [
            ['slug' => 'forest', 'label' => ['id' => 'Forest', 'en' => 'Forest']],
        ]);
        $taxonomy->flush();

        $this->tenants->setCurrent($this->perikanan);
        SiteSetting::put('news', 'programs', [
            ['slug' => 'blue-carbon', 'label' => ['id' => 'Blue Carbon', 'en' => 'Blue Carbon']],
            ['slug' => 'ikan', 'label' => ['id' => 'IKAN', 'en' => 'IKAN']],
        ]);
        $taxonomy->flush();

        $this->tenants->setCurrent($this->rekam);
        $this->assertSame(['forest'], array_column($taxonomy->options('news_programs'), 'value'));

        $this->tenants->setCurrent($this->perikanan);
        $this->assertSame(['blue-carbon', 'ikan'], array_column($taxonomy->options('news_programs'), 'value'));
    }

    public function test_taxonomy_falls_back_to_indonesian_when_english_is_empty(): void
    {
        $taxonomy = app(\App\Services\TaxonomyService::class);

        $this->tenants->setCurrent($this->rekam);
        SiteSetting::put('team', 'levels', [
            ['slug' => 'direktur', 'label' => ['id' => 'Direktur', 'en' => '']],
        ]);
        $taxonomy->flush();

        // EN is optional everywhere; an empty translation must not render blank
        // (context.md §6.4).
        $this->assertSame('Direktur', $taxonomy->options('team_levels', 'en')[0]['label']);
    }
}
