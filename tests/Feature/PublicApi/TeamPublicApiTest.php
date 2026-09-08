<?php

namespace Tests\Feature\PublicApi;

use App\Models\TeamMember;
use Tests\TenantTestCase;

class TeamPublicApiTest extends TenantTestCase
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

    public function test_members_are_grouped_per_level_in_taxonomy_order(): void
    {
        // RekamSeeder's team_levels order: advisor-board, supervisor-board,
        // chairperson, director, manager.
        TeamMember::factory()->create(['group' => 'manager', 'name' => 'Manajer']);
        TeamMember::factory()->create(['group' => 'director', 'name' => 'Direktur']);

        $response = $this->getPublic('/api/v1/team');

        $response->assertOk();
        $levels = collect($response->json('data'))->pluck('level.value');
        $this->assertSame(['director', 'manager'], $levels->all());
    }

    public function test_inactive_members_are_hidden(): void
    {
        TeamMember::factory()->inactive()->create(['group' => 'manager']);

        $response = $this->getPublic('/api/v1/team');

        $this->assertSame([], $response->json('data'));
    }

    public function test_an_orphaned_level_is_dropped_rather_than_shown_unlabelled(): void
    {
        TeamMember::factory()->create(['group' => 'level-since-deleted']);

        $response = $this->getPublic('/api/v1/team');

        $this->assertSame([], $response->json('data'));
    }

    public function test_team_is_absent_for_a_tenant_without_the_feature(): void
    {
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['team' => false])]);

        $this->getPublic('/api/v1/team')->assertNotFound();
    }
}
