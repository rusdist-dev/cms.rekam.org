<?php

namespace Tests\Feature\Content;

use App\Models\Milestone;
use Tests\TenantTestCase;

class MilestoneCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        // Milestone is perikanan-only (context.md §5.5, plan.md §5.3.d).
        $this->useTenant($this->perikanan);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['id' => 'Pendirian Lembaga', 'en' => null],
            'body' => ['id' => 'Catatan pencapaian.', 'en' => null],
            'year' => 2015,
            'is_active' => true,
        ], $overrides);
    }

    public function test_it_creates_a_milestone(): void
    {
        $response = $this->postJson(route('dash-api.milestones.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.title.id', 'Pendirian Lembaga')
            ->assertJsonPath('data.year', 2015);

        $this->assertSame(1, Milestone::count());
    }

    public function test_a_milestone_without_a_year_is_rejected(): void
    {
        $this->postJson(route('dash-api.milestones.store'), $this->payload(['year' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');
    }

    public function test_it_updates_a_milestone(): void
    {
        $milestone = Milestone::factory()->create();

        $this->postJson(route('dash-api.milestones.update', $milestone), $this->payload([
            'title' => ['id' => 'Judul Diperbarui', 'en' => null],
        ]))->assertOk()->assertJsonPath('data.title.id', 'Judul Diperbarui');
    }

    public function test_it_appends_new_milestones_to_the_end_of_the_order(): void
    {
        Milestone::factory()->create(['sort_order' => 4]);

        $this->postJson(route('dash-api.milestones.store'), $this->payload())
            ->assertJsonPath('data.sort_order', 5);
    }

    public function test_it_deletes_a_milestone(): void
    {
        $milestone = Milestone::factory()->create();

        $this->deleteJson(route('dash-api.milestones.destroy', $milestone))->assertNoContent();
        $this->assertSame(0, Milestone::count());
    }

    public function test_the_index_is_ordered_by_year_then_sort_order(): void
    {
        $later = Milestone::factory()->create(['year' => 2023, 'sort_order' => 0]);
        $earlier = Milestone::factory()->create(['year' => 2015, 'sort_order' => 1]);

        $ids = $this->getJson(route('dash-api.milestones.index'))->json('data.*.id');

        $this->assertSame([$earlier->id, $later->id], $ids);
    }

    public function test_reorder_persists_the_submitted_order(): void
    {
        $a = Milestone::factory()->create(['year' => 2015, 'sort_order' => 0]);
        $b = Milestone::factory()->create(['year' => 2015, 'sort_order' => 1]);

        $this->patchJson(route('dash-api.milestones.reorder'), [
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
        Milestone::factory()->create(['year' => 2015]);
        Milestone::factory()->create(['year' => 2020]);
        Milestone::factory()->inactive()->create(['year' => 2020]);

        $this->assertSame(3, count($this->getJson(route('dash-api.milestones.index'))->json('data')));
        $this->assertSame(2, count($this->getJson(route('dash-api.milestones.index', ['year' => 2020]))->json('data')));
        $this->assertSame(2, count($this->getJson(route('dash-api.milestones.index', ['is_active' => '1']))->json('data')));
    }

    public function test_milestones_are_absent_for_a_company_without_the_module(): void
    {
        // Rekam does not run milestones — the table itself does not exist
        // there (context.md §5.5).
        $this->useTenant($this->rekam);

        $this->getJson(route('dash-api.milestones.index'))->assertNotFound();
        $this->postJson(route('dash-api.milestones.store'), $this->payload())->assertNotFound();
    }

    public function test_an_editor_cannot_delete(): void
    {
        $milestone = Milestone::factory()->create();

        $this->actingAsUserWith('editor', [$this->perikanan->id]);
        $this->useTenant($this->perikanan);

        $this->deleteJson(route('dash-api.milestones.destroy', $milestone))->assertForbidden();
    }
}
