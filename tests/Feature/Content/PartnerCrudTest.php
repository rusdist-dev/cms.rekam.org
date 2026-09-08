<?php

namespace Tests\Feature\Content;

use App\Models\Partner;
use Tests\TenantTestCase;

class PartnerCrudTest extends TenantTestCase
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
            'name' => 'Yayasan Konservasi',
            'title' => ['id' => 'Mitra Strategis', 'en' => null],
            'url' => 'https://example.org',
            'is_active' => true,
        ], $overrides);
    }

    public function test_it_creates_a_partner(): void
    {
        $response = $this->postJson(route('dash-api.partners.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Yayasan Konservasi')
            ->assertJsonPath('data.title.id', 'Mitra Strategis');

        $this->assertSame(1, Partner::count());
    }

    public function test_it_updates_a_partner(): void
    {
        $partner = Partner::factory()->create();

        $this->postJson(route('dash-api.partners.update', $partner), $this->payload([
            'name' => 'Nama Baru',
        ]))->assertOk()->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_it_appends_new_partners_to_the_end_of_the_order(): void
    {
        Partner::factory()->create(['sort_order' => 3]);

        $this->postJson(route('dash-api.partners.store'), $this->payload())
            ->assertJsonPath('data.sort_order', 4);
    }

    public function test_it_deletes_a_partner(): void
    {
        $partner = Partner::factory()->create();

        $this->deleteJson(route('dash-api.partners.destroy', $partner))->assertNoContent();
        $this->assertSame(0, Partner::count());
    }

    public function test_reorder_persists_the_submitted_order(): void
    {
        $a = Partner::factory()->create(['sort_order' => 0]);
        $b = Partner::factory()->create(['sort_order' => 1]);

        $this->patchJson(route('dash-api.partners.reorder'), [
            'order' => [
                ['id' => $b->id, 'sort_order' => 0],
                ['id' => $a->id, 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_the_url_field_is_validated(): void
    {
        $this->postJson(route('dash-api.partners.store'), $this->payload(['url' => 'not-a-url']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_partners_are_invisible_from_the_other_company(): void
    {
        Partner::factory()->count(2)->create();

        $this->useTenant($this->perikanan);

        $this->assertSame(0, Partner::count());
        $this->assertSame(0, $this->getJson(route('dash-api.partners.index'))->json('meta.total'));
    }

    public function test_an_editor_can_reorder_but_not_delete(): void
    {
        $partner = Partner::factory()->create();

        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->patchJson(route('dash-api.partners.reorder'), [
            'order' => [['id' => $partner->id, 'sort_order' => 0]],
        ])->assertOk();

        $this->deleteJson(route('dash-api.partners.destroy', $partner))->assertForbidden();
    }
}
