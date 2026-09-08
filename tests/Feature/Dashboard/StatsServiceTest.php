<?php

namespace Tests\Feature\Dashboard;

use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\News;
use App\Models\TeamMember;
use Tests\TenantTestCase;

/**
 * `StatsApiController` replaced the Fase-1 fixture (resources/prototype/stats.json)
 * without changing the shape the dashboard's charts read (plan.md Fase 7).
 */
class StatsServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    private function section(string $name)
    {
        return $this->getJson(route('dash-api.stats.show', $name))->json('data');
    }

    public function test_summary_reflects_seeded_data(): void
    {
        News::factory()->published()->create();
        News::factory()->count(2)->create(); // draft
        Event::factory()->create(['end_at' => now()->addDays(5)]);
        TeamMember::factory()->create();
        TeamMember::factory()->inactive()->create();
        ContactMessage::factory()->create();
        ContactMessage::factory()->read()->create();

        $summary = $this->section('summary');

        $this->assertSame(3, $summary['news_total']);
        $this->assertSame(1, $summary['news_published']);
        $this->assertSame(2, $summary['news_draft']);
        $this->assertSame(1, $summary['events_upcoming']);
        $this->assertSame(1, $summary['team_active']);
        $this->assertSame(1, $summary['messages_unread']);
    }

    public function test_events_upcoming_and_messages_unread_respect_feature_flags(): void
    {
        // perikanan runs no events (context.md §5.5).
        $this->useTenant($this->perikanan);

        Event::factory()->create(['end_at' => now()->addDays(5)]);
        ContactMessage::factory()->create();

        $summary = $this->section('summary');

        $this->assertSame(0, $summary['events_upcoming']);
        // perikanan does run contacts, so this one still counts.
        $this->assertSame(1, $summary['messages_unread']);
    }

    public function test_status_breakdown_counts_each_news_status(): void
    {
        News::factory()->published()->create();
        News::factory()->create(); // draft
        News::factory()->scheduled()->create();

        $breakdown = $this->section('status_breakdown');

        $this->assertSame(['Terbit', 'Draf', 'Terjadwal'], $breakdown['labels']);
        $this->assertSame([1, 1, 1], $breakdown['datasets'][0]['data']);
    }

    public function test_publishing_trend_has_twelve_months_for_both_datasets(): void
    {
        News::factory()->published()->create();

        $trend = $this->section('publishing_trend');

        $this->assertCount(12, $trend['labels']);
        $this->assertSame('Berita', $trend['datasets'][0]['label']);
        $this->assertSame('Events', $trend['datasets'][1]['label']);
        $this->assertCount(12, $trend['datasets'][0]['data']);
        $this->assertCount(12, $trend['datasets'][1]['data']);
    }

    public function test_popular_news_is_ordered_by_views_and_excludes_unpublished(): void
    {
        News::factory()->create(); // draft, must not appear
        $popular = News::factory()->published()->create(['views' => 10]);
        $mostViewed = News::factory()->published()->create(['views' => 500]);

        $result = $this->section('popular_news');

        $this->assertCount(2, $result);
        $this->assertSame($mostViewed->id, $result[0]['id']);
        $this->assertSame($popular->id, $result[1]['id']);
    }

    public function test_stats_change_when_the_active_tenant_switches(): void
    {
        News::factory()->published()->create();

        $this->assertSame(1, $this->section('summary')['news_total']);

        $this->useTenant($this->perikanan);

        $this->assertSame(0, $this->section('summary')['news_total']);
    }
}
