<?php

namespace Tests\Feature\Content;

use App\Models\Event;
use App\Models\EventRundown;
use Tests\TenantTestCase;

class EventCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        // Only rekam runs events, confirmed 2026-09-04 (plan.md §5.1).
        $this->useTenant($this->rekam);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['id' => 'Lokakarya Pesisir', 'en' => 'Coastal Workshop'],
            'slug' => ['id' => '', 'en' => ''],
            'description' => ['id' => '<p>Deskripsi.</p>', 'en' => null],
            'location' => ['id' => 'Jakarta', 'en' => null],
            'category' => 'workshop',
            'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_at' => now()->addWeek()->addHours(7)->format('Y-m-d H:i:s'),
            'is_all_day' => false,
            'status' => 'draft',
        ], $overrides);
    }

    public function test_it_creates_an_event(): void
    {
        $this->postJson(route('dash-api.events.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title.id', 'Lokakarya Pesisir')
            ->assertJsonPath('data.slug.id', 'lokakarya-pesisir');
    }

    public function test_the_end_time_cannot_precede_the_start(): void
    {
        $this->postJson(route('dash-api.events.store'), $this->payload([
            'start_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]))->assertStatus(422)->assertJsonValidationErrors('end_at');
    }

    public function test_a_missing_fee_reads_as_free(): void
    {
        // plan.md §5.2.c — null or 0 is free; the compro shows wording, not Rp0.
        $this->postJson(route('dash-api.events.store'), $this->payload())
            ->assertJsonPath('data.is_free', true);

        $this->postJson(route('dash-api.events.store'), $this->payload([
            'title' => ['id' => 'Berbayar', 'en' => null],
            'fee' => 150000,
            'fee_note' => ['id' => 'Gratis untuk mahasiswa', 'en' => null],
        ]))->assertJsonPath('data.is_free', false)
            ->assertJsonPath('data.fee', 150000);
    }

    public function test_rundown_rows_are_saved_with_the_event_in_one_request(): void
    {
        // context.md §4.11 — no per-row endpoint exists.
        $response = $this->postJson(route('dash-api.events.store'), $this->payload([
            'rundowns' => [
                ['time' => '09:00', 'title' => ['id' => 'Pembukaan', 'en' => 'Opening'], 'description' => ['id' => null, 'en' => null]],
                ['time' => '10:30', 'title' => ['id' => 'Sesi Pertama', 'en' => null], 'description' => ['id' => 'Ruang A', 'en' => null]],
            ],
        ]));

        $response->assertCreated();

        $event = Event::first();
        $this->assertSame(2, $event->rundowns()->count());
        $this->assertSame('Pembukaan', $event->rundowns()->first()->title['id']);
    }

    public function test_rundown_order_follows_the_submitted_row_order(): void
    {
        $response = $this->postJson(route('dash-api.events.store'), $this->payload([
            'rundowns' => [
                ['time' => '13:00', 'title' => ['id' => 'Sesi Siang', 'en' => null]],
                ['time' => '09:00', 'title' => ['id' => 'Sesi Pagi', 'en' => null]],
            ],
        ]));

        $response->assertCreated();

        // The editor dragged "Sesi Siang" first, so that is the stored order —
        // not a re-sort by time.
        $titles = Event::first()->rundowns->pluck('title.id')->all();
        $this->assertSame(['Sesi Siang', 'Sesi Pagi'], $titles);
    }

    public function test_updating_creates_updates_and_deletes_rows_in_one_pass(): void
    {
        $create = $this->postJson(route('dash-api.events.store'), $this->payload([
            'rundowns' => [
                ['time' => '09:00', 'title' => ['id' => 'Pembukaan', 'en' => null]],
                ['time' => '10:00', 'title' => ['id' => 'Akan Dihapus', 'en' => null]],
            ],
        ]));

        $event = Event::first();
        $keepId = $event->rundowns()->first()->id;

        $this->postJson(route('dash-api.events.update', $event), $this->payload([
            'rundowns' => [
                ['id' => $keepId, 'time' => '09:30', 'title' => ['id' => 'Pembukaan (diubah)', 'en' => null]],
                ['time' => '11:00', 'title' => ['id' => 'Sesi Baru', 'en' => null]],
            ],
        ]))->assertOk();

        $rows = $event->fresh()->rundowns;

        $this->assertSame(2, $rows->count());
        $this->assertSame('Pembukaan (diubah)', $rows[0]->title['id']);
        $this->assertSame('Sesi Baru', $rows[1]->title['id']);
        $this->assertSame(0, EventRundown::where('title->id', 'Akan Dihapus')->count());
    }

    public function test_an_empty_rundown_array_clears_every_row(): void
    {
        $this->postJson(route('dash-api.events.store'), $this->payload([
            'rundowns' => [['time' => '09:00', 'title' => ['id' => 'Pembukaan', 'en' => null]]],
        ]));

        $event = Event::first();

        $this->postJson(route('dash-api.events.update', $event), $this->payload(['rundowns' => []]))
            ->assertOk();

        $this->assertSame(0, $event->fresh()->rundowns()->count());
    }

    public function test_rundown_validation_errors_are_indexed_by_row(): void
    {
        // The form shows the message on the offending row, so the key has to
        // carry the index (context.md §4.11).
        $response = $this->postJson(route('dash-api.events.store'), $this->payload([
            'rundowns' => [
                ['time' => '09:00', 'title' => ['id' => 'Pembukaan', 'en' => null]],
                ['time' => 'bukan-jam', 'title' => ['id' => '', 'en' => null]],
            ],
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rundowns.1.time', 'rundowns.1.title.id'])
            ->assertJsonMissingValidationErrors(['rundowns.0.time', 'rundowns.0.title.id']);
    }

    public function test_deleting_an_event_takes_its_rundown_with_it(): void
    {
        $this->postJson(route('dash-api.events.store'), $this->payload([
            'rundowns' => [['time' => '09:00', 'title' => ['id' => 'Pembukaan', 'en' => null]]],
        ]));

        $event = Event::first();

        // Soft delete keeps the rows; a force delete cascades.
        $this->deleteJson(route('dash-api.events.destroy', $event))->assertNoContent();
        $this->assertSame(1, EventRundown::count());

        $this->deleteJson(route('dash-api.events.force-destroy', $event->id))->assertNoContent();
        $this->assertSame(0, EventRundown::count());
    }

    public function test_the_upcoming_filter_excludes_finished_events(): void
    {
        Event::factory()->create(['start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHours(6)]);
        Event::factory()->create(['start_at' => now()->subMonth(), 'end_at' => now()->subMonth()->addHours(6)]);

        $this->assertSame(2, $this->getJson(route('dash-api.events.index'))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.events.index', ['upcoming' => 1]))->json('meta.total'));
    }

    public function test_events_are_absent_for_a_company_without_the_module(): void
    {
        // Perikanan does not run events at all (confirmed 2026-09-04).
        $this->useTenant($this->perikanan);

        $this->getJson('/dash-api/v1/events')->assertNotFound();
        $this->get('/events')->assertNotFound();
    }
}
