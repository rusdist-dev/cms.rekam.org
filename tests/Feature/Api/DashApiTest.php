<?php

namespace Tests\Feature\Api;

use App\Models\News;
use Tests\TenantTestCase;

/**
 * The internal API is what every table and form on the dashboard talks to, so
 * its envelope is a contract (context.md §4.5). These tests pin that shape now
 * so the real controllers replacing the prototype in Fase 3 cannot drift.
 */
class DashApiTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        // News is a real module from Fase 3, so the envelope is pinned against
        // real rows rather than a fixture.
        News::factory()->published()->create(['title' => ['id' => 'Restorasi Mangrove', 'en' => 'Mangrove Restoration']]);
        News::factory()->count(4)->published()->create();
        News::factory()->count(3)->create(['status' => 'draft']);
    }

    public function test_guests_cannot_reach_the_internal_api(): void
    {
        auth()->logout();

        $this->getJson(route('dash-api.news.index'))->assertUnauthorized();
    }

    public function test_list_responses_use_the_agreed_envelope(): void
    {
        $this->getJson(route('dash-api.news.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'status']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_item_responses_are_wrapped_in_data(): void
    {
        $id = News::first()->id;

        $this->getJson(route('dash-api.news.show', $id))
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'title']])
            ->assertJsonPath('data.id', $id);
    }

    public function test_a_missing_record_returns_404_with_a_message(): void
    {
        $this->getJson(route('dash-api.news.show', 99999))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_search_filters_the_list(): void
    {
        $all = $this->getJson(route('dash-api.news.index'))->json('meta.total');
        $found = $this->getJson(route('dash-api.news.index', ['search' => 'Restorasi Mangrove']));

        $found->assertOk();
        $this->assertLessThan($all, $found->json('meta.total'));
        $this->assertSame(1, $found->json('meta.total'));
    }

    public function test_search_with_no_match_returns_an_empty_list_not_an_error(): void
    {
        $response = $this->getJson(route('dash-api.news.index', ['search' => 'zzz-tidak-ada-zzz']));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
        // last_page must stay >= 1 or the pagination component divides by zero.
        $this->assertSame(1, $response->json('meta.last_page'));
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $response = $this->getJson(route('dash-api.news.index', ['status' => 'draft']));

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertSame('draft', $item['status']);
        }
    }

    public function test_array_column_filters_match_on_membership(): void
    {
        // related_programs is a JSON array; filtering by one slug must match a
        // row that holds it among others (plan.md §5.3.a).
        News::factory()->create(['related_programs' => ['forest', 'ocean']]);

        $response = $this->getJson(route('dash-api.news.index', ['program' => 'ocean']));

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));

        foreach ($response->json('data') as $item) {
            $this->assertContains('ocean', $item['related_programs']);
        }
    }

    public function test_sorting_reverses_with_direction(): void
    {
        $asc = $this->getJson(route('dash-api.news.index', ['sort' => 'title.id', 'direction' => 'asc']))->json('data.0.title.id');
        $desc = $this->getJson(route('dash-api.news.index', ['sort' => 'title.id', 'direction' => 'desc']))->json('data.0.title.id');

        $this->assertNotSame($asc, $desc);
    }

    public function test_pagination_respects_per_page_and_caps_it(): void
    {
        $response = $this->getJson(route('dash-api.news.index', ['per_page' => 3, 'page' => 2]));

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertSame(2, $response->json('meta.current_page'));

        // An absurd per_page must not let one request pull the whole table.
        $capped = $this->getJson(route('dash-api.news.index', ['per_page' => 99999]));
        $this->assertSame(config('cms.max_per_page'), $capped->json('meta.per_page'));
    }

    public function test_a_page_beyond_the_last_is_clamped_rather_than_erroring(): void
    {
        $response = $this->getJson(route('dash-api.news.index', ['page' => 9999]));

        $response->assertOk();
        $this->assertSame($response->json('meta.last_page'), $response->json('meta.current_page'));
    }

    public function test_endpoints_of_a_disabled_module_are_not_reachable(): void
    {
        // context.md §5.6 — the API surface follows the feature flags too.
        $this->getJson(route('dash-api.units.index'))->assertOk();
        $this->getJson('/dash-api/v1/milestones')->assertNotFound();

        $this->useTenant($this->perikanan);

        $this->getJson(route('dash-api.milestones.index'))->assertOk();
        $this->getJson('/dash-api/v1/units')->assertNotFound();
    }

    public function test_stats_sections_are_restricted_to_known_names(): void
    {
        $this->getJson(route('dash-api.stats.show', 'summary'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['news_published', 'news_draft', 'messages_unread']]);

        $this->getJson('/dash-api/v1/stats/rahasia')->assertNotFound();
    }

    public function test_chart_endpoints_return_labels_and_datasets(): void
    {
        $this->getJson(route('dash-api.stats.show', 'publishing_trend'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['labels', 'datasets' => [['label', 'data']]]]);
    }

    public function test_taxonomy_returns_value_label_pairs(): void
    {
        $this->getJson(route('dash-api.taxonomy.show', 'news_programs'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['value', 'label']]]);
    }

    public function test_the_resource_name_cannot_escape_the_fixtures_directory(): void
    {
        $this->getJson('/dash-api/v1/taxonomy/..%2F..%2Fconfig')->assertNotFound();
    }
}
