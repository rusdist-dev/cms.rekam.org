<?php

namespace Tests\Feature\Content;

use App\Models\TeamMember;
use Tests\TenantTestCase;

class TeamMemberCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Hartati',
            'position' => ['id' => 'Direktur Program', 'en' => null],
            'bio' => ['id' => 'Profil singkat.', 'en' => null],
            'group' => 'manager',
            'email' => 'rina@example.com',
            'socials' => ['linkedin' => '', 'instagram' => ''],
            'is_active' => true,
        ], $overrides);
    }

    public function test_it_creates_a_member(): void
    {
        $response = $this->postJson(route('dash-api.team.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Rina Hartati')
            ->assertJsonPath('data.slug', 'rina-hartati')
            ->assertJsonPath('data.group', 'manager');

        $this->assertSame(1, TeamMember::count());
    }

    public function test_a_duplicate_name_gets_a_slug_suffix(): void
    {
        $this->postJson(route('dash-api.team.store'), $this->payload())->assertCreated();
        $second = $this->postJson(route('dash-api.team.store'), $this->payload());

        $second->assertCreated()->assertJsonPath('data.slug', 'rina-hartati-2');
    }

    public function test_it_updates_a_member(): void
    {
        $member = TeamMember::factory()->create();

        $this->postJson(route('dash-api.team.update', $member), $this->payload([
            'name' => 'Nama Baru',
        ]))->assertOk()->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_it_appends_new_members_to_the_end_of_the_order(): void
    {
        TeamMember::factory()->create(['sort_order' => 5]);

        $response = $this->postJson(route('dash-api.team.store'), $this->payload());

        $response->assertJsonPath('data.sort_order', 6);
    }

    public function test_it_deletes_a_member(): void
    {
        $member = TeamMember::factory()->create();

        $this->deleteJson(route('dash-api.team.destroy', $member))->assertNoContent();
        $this->assertSame(0, TeamMember::count());
    }

    public function test_reorder_persists_the_submitted_order(): void
    {
        $a = TeamMember::factory()->create(['sort_order' => 0]);
        $b = TeamMember::factory()->create(['sort_order' => 1]);

        $this->patchJson(route('dash-api.team.reorder'), [
            'order' => [
                ['id' => $b->id, 'sort_order' => 0],
                ['id' => $a->id, 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_filters_narrow_the_list(): void
    {
        TeamMember::factory()->create(['group' => 'manager']);
        TeamMember::factory()->create(['group' => 'director']);
        TeamMember::factory()->inactive()->create(['group' => 'manager']);

        $this->assertSame(3, $this->getJson(route('dash-api.team.index'))->json('meta.total'));
        $this->assertSame(2, $this->getJson(route('dash-api.team.index', ['group' => 'manager']))->json('meta.total'));
        $this->assertSame(2, $this->getJson(route('dash-api.team.index', ['is_active' => '1']))->json('meta.total'));
    }

    public function test_members_are_invisible_from_the_other_company(): void
    {
        TeamMember::factory()->count(2)->create();

        $this->useTenant($this->perikanan);

        $this->assertSame(0, TeamMember::count());
        $this->assertSame(0, $this->getJson(route('dash-api.team.index'))->json('meta.total'));
    }

    public function test_an_editor_cannot_delete(): void
    {
        $member = TeamMember::factory()->create();

        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->deleteJson(route('dash-api.team.destroy', $member))->assertForbidden();
    }
}
