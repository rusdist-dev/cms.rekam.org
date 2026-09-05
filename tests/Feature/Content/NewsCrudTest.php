<?php

namespace Tests\Feature\Content;

use App\Models\News;
use App\Models\NewsCategory;
use Tests\TenantTestCase;

class NewsCrudTest extends TenantTestCase
{
    private NewsCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        $this->category = NewsCategory::first();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['id' => 'Restorasi Mangrove', 'en' => 'Mangrove Restoration'],
            'slug' => ['id' => '', 'en' => ''],
            'excerpt' => ['id' => 'Ringkasan.', 'en' => null],
            'body' => ['id' => '<p>Isi berita.</p>', 'en' => null],
            'category_id' => $this->category->id,
            'related_programs' => ['forest'],
            'status' => 'draft',
            'author_name' => 'Rina Hartati',
        ], $overrides);
    }

    public function test_it_creates_an_article(): void
    {
        $response = $this->postJson(route('dash-api.news.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.title.id', 'Restorasi Mangrove')
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame(1, News::count());
    }

    public function test_the_slug_is_generated_per_locale_from_the_title(): void
    {
        $response = $this->postJson(route('dash-api.news.store'), $this->payload());

        $response->assertJsonPath('data.slug.id', 'restorasi-mangrove')
            ->assertJsonPath('data.slug.en', 'mangrove-restoration');
    }

    public function test_a_duplicate_slug_gets_a_suffix_rather_than_colliding(): void
    {
        $this->postJson(route('dash-api.news.store'), $this->payload())->assertCreated();
        $second = $this->postJson(route('dash-api.news.store'), $this->payload());

        $second->assertCreated()->assertJsonPath('data.slug.id', 'restorasi-mangrove-2');
    }

    public function test_indonesian_is_required_but_english_is_optional(): void
    {
        // context.md §6.4 — the asymmetry is deliberate: a draft may be
        // Indonesian-only and the English site falls back.
        $this->postJson(route('dash-api.news.store'), $this->payload([
            'title' => ['id' => 'Hanya Indonesia', 'en' => null],
            'body' => ['id' => '<p>Isi.</p>', 'en' => null],
        ]))->assertCreated();

        $this->postJson(route('dash-api.news.store'), $this->payload([
            'title' => ['id' => '', 'en' => 'English only'],
        ]))->assertStatus(422)->assertJsonValidationErrors('title.id');
    }

    public function test_translation_completeness_is_reported(): void
    {
        $response = $this->postJson(route('dash-api.news.store'), $this->payload([
            'title' => ['id' => 'Judul', 'en' => null],
        ]));

        $response->assertJsonPath('data.translation_complete.id', true)
            ->assertJsonPath('data.translation_complete.en', false);
    }

    public function test_scheduling_requires_a_future_date(): void
    {
        $this->postJson(route('dash-api.news.store'), $this->payload([
            'status' => 'scheduled',
        ]))->assertStatus(422)->assertJsonValidationErrors('published_at');

        $this->postJson(route('dash-api.news.store'), $this->payload([
            'status' => 'scheduled',
            'published_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]))->assertStatus(422)->assertJsonValidationErrors('published_at');

        $this->postJson(route('dash-api.news.store'), $this->payload([
            'status' => 'scheduled',
            'published_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]))->assertCreated();
    }

    public function test_publishing_without_a_date_stamps_it_now(): void
    {
        $response = $this->postJson(route('dash-api.news.store'), $this->payload(['status' => 'published']));

        $response->assertCreated();
        $this->assertNotNull(News::first()->published_at);
    }

    public function test_it_updates_an_article(): void
    {
        $news = News::factory()->create(['category_id' => $this->category->id]);

        $this->postJson(route('dash-api.news.update', $news), $this->payload([
            'title' => ['id' => 'Judul Diperbarui', 'en' => null],
        ]))->assertOk()->assertJsonPath('data.title.id', 'Judul Diperbarui');
    }

    public function test_programs_store_slugs_not_labels(): void
    {
        // Renaming a programme in settings must not rewrite content rows
        // (context.md §5.12).
        $response = $this->postJson(route('dash-api.news.store'), $this->payload([
            'related_programs' => ['forest', 'ocean'],
        ]));

        $response->assertCreated();
        $this->assertSame(['forest', 'ocean'], News::first()->related_programs);
    }

    public function test_soft_delete_then_restore(): void
    {
        $news = News::factory()->create();

        $this->deleteJson(route('dash-api.news.destroy', $news))->assertNoContent();
        $this->assertSoftDeleted('news', ['id' => $news->id], 'tenant');

        // Deleted rows are out of the default list but reachable via ?trashed=1.
        $this->assertSame(0, $this->getJson(route('dash-api.news.index'))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.news.index', ['trashed' => 1]))->json('meta.total'));

        $this->postJson(route('dash-api.news.restore', $news->id))->assertOk();
        $this->assertSame(1, $this->getJson(route('dash-api.news.index'))->json('meta.total'));
    }

    public function test_force_delete_removes_the_row_for_good(): void
    {
        $news = News::factory()->create();
        $news->delete();

        $this->deleteJson(route('dash-api.news.force-destroy', $news->id))->assertNoContent();
        $this->assertSame(0, News::withTrashed()->count());
    }

    public function test_filters_narrow_the_list(): void
    {
        News::factory()->published()->create(['related_programs' => ['ocean']]);
        News::factory()->create(['related_programs' => ['forest']]);
        News::factory()->scheduled()->create();

        $this->assertSame(3, $this->getJson(route('dash-api.news.index'))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.news.index', ['status' => 'published']))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.news.index', ['program' => 'ocean']))->json('meta.total'));
    }

    public function test_search_matches_the_title_in_either_locale(): void
    {
        News::factory()->create(['title' => ['id' => 'Konservasi Terumbu', 'en' => 'Reef Conservation']]);
        News::factory()->create(['title' => ['id' => 'Hutan Adat', 'en' => null]]);

        $this->assertSame(1, $this->getJson(route('dash-api.news.index', ['search' => 'Terumbu']))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.news.index', ['search' => 'Reef']))->json('meta.total'));
    }

    public function test_sorting_is_limited_to_a_whitelist(): void
    {
        News::factory()->count(3)->create();

        // An arbitrary column must never reach the query builder.
        $this->getJson(route('dash-api.news.index', ['sort' => 'password', 'direction' => 'asc']))
            ->assertOk();
    }

    public function test_bulk_publish_updates_every_selected_row(): void
    {
        $ids = News::factory()->count(3)->create()->pluck('id')->all();

        $this->postJson(route('dash-api.news.bulk'), ['action' => 'publish', 'ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.affected', 3);

        $this->assertSame(3, News::where('status', 'published')->count());
    }

    public function test_bulk_delete_soft_deletes(): void
    {
        $ids = News::factory()->count(2)->create()->pluck('id')->all();

        $this->postJson(route('dash-api.news.bulk'), ['action' => 'delete', 'ids' => $ids])->assertOk();

        $this->assertSame(0, News::count());
        $this->assertSame(2, News::onlyTrashed()->count());
    }

    public function test_an_editor_cannot_publish(): void
    {
        // Writing and publishing are separate rights (plan.md Fase 2).
        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->postJson(route('dash-api.news.store'), $this->payload())->assertCreated();

        $this->postJson(route('dash-api.news.store'), $this->payload([
            'title' => ['id' => 'Coba Terbit', 'en' => null],
            'status' => 'published',
        ]))->assertForbidden();
    }

    public function test_an_editor_cannot_bulk_publish_either(): void
    {
        $ids = News::factory()->count(2)->create()->pluck('id')->all();

        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->postJson(route('dash-api.news.bulk'), ['action' => 'publish', 'ids' => $ids])
            ->assertForbidden();
    }

    public function test_articles_are_invisible_from_the_other_company(): void
    {
        News::factory()->count(2)->create();

        $this->useTenant($this->perikanan);

        $this->assertSame(0, News::count());
        $this->assertSame(0, $this->getJson(route('dash-api.news.index'))->json('meta.total'));
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        News::factory()->create(['category_id' => $this->category->id]);

        $this->deleteJson(route('dash-api.news-categories.destroy', $this->category))
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }
}
