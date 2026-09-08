<?php

namespace Tests\Feature\Content;

use App\Models\Unit;
use Tests\TenantTestCase;

class UnitCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        // Unit is rekam-only (context.md §5.5, plan.md §5.2.e).
        $this->useTenant($this->rekam);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Perikanan',
            'description' => ['id' => 'Unit di bawah naungan Rekam.', 'en' => null],
            'url' => 'https://perikanan.org',
            'domain' => 'perikanan.org',
            'is_active' => true,
        ], $overrides);
    }

    public function test_it_creates_a_unit(): void
    {
        $response = $this->postJson(route('dash-api.units.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Perikanan')
            ->assertJsonPath('data.domain', 'perikanan.org');

        $this->assertSame(1, Unit::count());
    }

    public function test_domain_and_url_are_required(): void
    {
        $this->postJson(route('dash-api.units.store'), $this->payload(['domain' => '', 'url' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['domain', 'url']);
    }

    public function test_it_updates_a_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->postJson(route('dash-api.units.update', $unit), $this->payload([
            'name' => 'Nama Baru',
        ]))->assertOk()->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_it_appends_new_units_to_the_end_of_the_order(): void
    {
        Unit::factory()->create(['sort_order' => 2]);

        $this->postJson(route('dash-api.units.store'), $this->payload())
            ->assertJsonPath('data.sort_order', 3);
    }

    public function test_it_deletes_a_unit(): void
    {
        $unit = Unit::factory()->create();

        $this->deleteJson(route('dash-api.units.destroy', $unit))->assertNoContent();
        $this->assertSame(0, Unit::count());
    }

    public function test_reorder_persists_the_submitted_order(): void
    {
        $a = Unit::factory()->create(['sort_order' => 0]);
        $b = Unit::factory()->create(['sort_order' => 1]);

        $this->patchJson(route('dash-api.units.reorder'), [
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
        Unit::factory()->create();
        Unit::factory()->inactive()->create();

        $this->assertSame(2, count($this->getJson(route('dash-api.units.index'))->json('data')));
        $this->assertSame(1, count($this->getJson(route('dash-api.units.index', ['is_active' => '1']))->json('data')));
    }

    public function test_units_are_absent_for_a_company_without_the_module(): void
    {
        // Perikanan does not run units — the table itself does not exist
        // there (context.md §5.5).
        $this->useTenant($this->perikanan);

        $this->getJson(route('dash-api.units.index'))->assertNotFound();
        $this->postJson(route('dash-api.units.store'), $this->payload())->assertNotFound();
    }

    public function test_an_editor_can_reorder_but_not_delete(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->patchJson(route('dash-api.units.reorder'), [
            'order' => [['id' => $unit->id, 'sort_order' => 0]],
        ])->assertOk();

        $this->deleteJson(route('dash-api.units.destroy', $unit))->assertForbidden();
    }
}
