<?php

namespace Tests\Feature\Tenancy;

use Tests\TenantTestCase;

/**
 * A module a company does not have must be absent, not merely hidden: no menu
 * entry, no route, no API endpoint (context.md §5.6).
 */
class FeatureFlagTest extends TenantTestCase
{
    public function test_a_disabled_module_has_no_dashboard_route(): void
    {
        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        // 404, not 403: for this company the module genuinely does not exist,
        // and a 403 would leak that another company has it.
        $this->get(route('milestones.index'))->assertNotFound();
        $this->get(route('units.index'))->assertOk();
        // Confirmed 2026-09-08: rekam.org now runs Publikasi too — no longer
        // an example of a module rekam lacks (see PublicationCrudTest for the
        // still-covered "absent when a tenant's flag is off" case).
        $this->get(route('publications.index'))->assertOk();
    }

    public function test_a_disabled_module_has_no_api_endpoint(): void
    {
        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        $this->getJson('/dash-api/v1/milestones')->assertNotFound();
        $this->getJson('/dash-api/v1/units')->assertOk();
    }

    public function test_the_mirror_case_holds_for_the_other_company(): void
    {
        $this->actingAsSuperAdmin();
        $this->useTenant($this->perikanan);

        $this->get(route('units.index'))->assertNotFound();
        $this->getJson('/dash-api/v1/units')->assertNotFound();

        $this->get(route('milestones.index'))->assertOk();
        $this->getJson('/dash-api/v1/milestones')->assertOk();
    }

    public function test_the_sidebar_only_links_modules_the_tenant_has(): void
    {
        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        $response = $this->get(route('dashboard'));

        $response->assertSee('href="'.route('units.index').'"', false);
        $response->assertDontSee('href="'.route('milestones.index').'"', false);
        // Confirmed 2026-09-08: rekam.org now runs Publikasi too.
        $response->assertSee('href="'.route('publications.index').'"', false);
    }

    public function test_switching_company_changes_the_available_modules(): void
    {
        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        $this->put(route('tenant.switch', $this->perikanan->id))
            ->assertRedirect(route('dashboard'));

        $response = $this->get(route('dashboard'));
        $response->assertSee('href="'.route('milestones.index').'"', false);
        $response->assertDontSee('href="'.route('units.index').'"', false);
    }

    public function test_a_user_cannot_switch_to_a_company_they_are_not_assigned_to(): void
    {
        $this->actingAsUserWith('admin', [$this->rekam->id]);

        $this->from(route('dashboard'))
            ->put(route('tenant.switch', $this->perikanan->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertSame($this->rekam->id, app(\App\Services\TenantManager::class)->currentId());
    }

    public function test_a_super_admin_reaches_every_company_without_being_assigned(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\App\Models\User::SUPER_ADMIN);
        // Deliberately no tenant_user rows.
        $this->actingAs($user);

        $this->put(route('tenant.switch', $this->perikanan->id))->assertRedirect(route('dashboard'));
        $this->assertSame($this->perikanan->id, app(\App\Services\TenantManager::class)->currentId());
    }

    public function test_core_features_cannot_be_switched_off(): void
    {
        // `news` is marked core in config, so a stored false must not disable it
        // and take the CMS's backbone with it.
        $this->rekam->update(['features' => ['news' => false]]);

        $this->assertTrue($this->rekam->fresh()->hasFeature('news'));
    }

    public function test_sync_features_drops_unknown_keys(): void
    {
        $this->rekam->syncFeatures(['units' => true, 'tidak_dikenal' => true]);

        $features = $this->rekam->fresh()->features;

        $this->assertArrayNotHasKey('tidak_dikenal', $features);
        $this->assertTrue($features['units']);
    }

    public function test_a_user_with_no_company_is_told_rather_than_shown_an_error(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('editor');
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(409);
        $response->assertSee('belum ditugaskan ke company');
    }
}
