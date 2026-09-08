<?php

namespace Tests\Feature\PublicApi;

use App\Models\Partner;
use Tests\TenantTestCase;

/**
 * Representative of every plain list module (partners, publications, units) —
 * same shared pattern (hard is_active filter, sort_order, feature gate), so
 * this one stands in for all of them rather than duplicating near-identical
 * tests per module.
 */
class PartnersPublicApiTest extends TenantTestCase
{
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();
    }

    private function getPublic(string $uri)
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])->getJson($uri);
    }

    public function test_only_active_partners_are_visible(): void
    {
        Partner::factory()->create(['is_active' => true]);
        Partner::factory()->inactive()->create();

        $this->getPublic('/api/v1/partners')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_ordered_by_sort_order(): void
    {
        $second = Partner::factory()->create(['sort_order' => 1]);
        $first = Partner::factory()->create(['sort_order' => 0]);

        $response = $this->getPublic('/api/v1/partners');

        $this->assertSame([$first->id, $second->id], $response->json('data.*.id'));
    }

    public function test_partners_are_absent_for_a_tenant_without_the_feature(): void
    {
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['partners' => false])]);

        $this->getPublic('/api/v1/partners')->assertNotFound();
    }

    public function test_milestones_are_absent_for_rekam_units_are_absent_for_perikanan(): void
    {
        // Milestone is perikanan-only, Unit is rekam-only (context.md §5.5).
        $this->getPublic('/api/v1/milestones')->assertNotFound();
        $this->getPublic('/api/v1/units')->assertOk();

        $perikananKey = $this->perikanan->rotateApiKey();

        $this->withHeaders(['X-Api-Key' => $perikananKey])
            ->getJson('/api/v1/milestones')
            ->assertOk();

        $this->withHeaders(['X-Api-Key' => $perikananKey])
            ->getJson('/api/v1/units')
            ->assertNotFound();
    }

    public function test_publications_are_available_for_rekam(): void
    {
        // Confirmed 2026-09-08: rekam.org now runs Publikasi too — locking
        // this in, since it was off until this date (context.md §5.2.g).
        $this->getPublic('/api/v1/publications')->assertOk();
    }
}
