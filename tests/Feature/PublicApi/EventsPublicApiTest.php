<?php

namespace Tests\Feature\PublicApi;

use App\Models\Event;
use Tests\TenantTestCase;

class EventsPublicApiTest extends TenantTestCase
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

    public function test_only_published_events_are_visible(): void
    {
        Event::factory()->create(['status' => 'published']);
        Event::factory()->create(['status' => 'draft']);

        $this->getPublic('/api/v1/events')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_it_finds_an_event_by_either_locales_slug(): void
    {
        $event = Event::factory()->create([
            'status' => 'published',
            'slug' => ['id' => 'acara-id', 'en' => 'event-en'],
        ]);

        $this->getPublic('/api/v1/events/acara-id')->assertOk()->assertJsonPath('data.id', $event->id);
        $this->getPublic('/api/v1/events/event-en')->assertOk()->assertJsonPath('data.id', $event->id);
    }

    public function test_detail_includes_rundown_when_the_feature_is_on(): void
    {
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['event_rundown' => true])]);

        $event = Event::factory()->create(['status' => 'published', 'slug' => ['id' => 'acara-rundown', 'en' => null]]);
        $event->rundowns()->create([
            'time' => '09:00',
            'title' => ['id' => 'Pembukaan', 'en' => null],
            'description' => ['id' => null, 'en' => null],
            'sort_order' => 0,
        ]);

        $this->getPublic('/api/v1/events/acara-rundown')
            ->assertOk()
            ->assertJsonPath('data.rundowns.0.title', 'Pembukaan')
            ->assertJsonPath('data.rundowns.0.time', '09:00');
    }

    public function test_rundown_is_absent_when_the_feature_is_off(): void
    {
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['event_rundown' => false])]);

        $event = Event::factory()->create(['status' => 'published', 'slug' => ['id' => 'acara-tanpa-rundown', 'en' => null]]);
        $event->rundowns()->create([
            'time' => '09:00',
            'title' => ['id' => 'Pembukaan', 'en' => null],
            'description' => ['id' => null, 'en' => null],
            'sort_order' => 0,
        ]);

        $response = $this->getPublic('/api/v1/events/acara-tanpa-rundown');

        $response->assertOk();
        $this->assertArrayNotHasKey('rundowns', $response->json('data'));
    }

    public function test_upcoming_filter(): void
    {
        Event::factory()->create(['status' => 'published', 'start_at' => now()->addWeek(), 'end_at' => now()->addWeek()->addHour()]);
        Event::factory()->create(['status' => 'published', 'start_at' => now()->subWeek(), 'end_at' => now()->subWeek()->addHour()]);

        $this->getPublic('/api/v1/events?upcoming=1')->assertJsonCount(1, 'data');
    }

    public function test_events_are_absent_for_a_tenant_without_the_feature(): void
    {
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['events' => false])]);

        $this->getPublic('/api/v1/events')->assertNotFound();
    }
}
