<?php

namespace Tests\Feature\PublicApi;

use App\Models\News;
use App\Models\NewsCategory;
use Tests\TenantTestCase;

class NewsPublicApiTest extends TenantTestCase
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

    public function test_only_published_news_is_visible(): void
    {
        News::factory()->published()->create();
        News::factory()->create(); // draft
        News::factory()->scheduled()->create(); // future

        $this->getPublic('/api/v1/news')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_it_finds_an_article_by_either_locales_slug(): void
    {
        $news = News::factory()->published()->create([
            'slug' => ['id' => 'artikel-id', 'en' => 'article-en'],
        ]);

        $this->getPublic('/api/v1/news/artikel-id')->assertOk()->assertJsonPath('data.id', $news->id);
        $this->getPublic('/api/v1/news/article-en')->assertOk()->assertJsonPath('data.id', $news->id);
    }

    public function test_an_unpublished_slug_404s(): void
    {
        $news = News::factory()->create(['slug' => ['id' => 'draf-saja', 'en' => null]]);

        $this->getPublic('/api/v1/news/draf-saja')->assertNotFound();
    }

    public function test_lang_en_flattens_with_fallback_to_id_when_blank(): void
    {
        News::factory()->published()->create([
            'title' => ['id' => 'Judul Indonesia', 'en' => 'English Title'],
            'excerpt' => ['id' => 'Ringkasan.', 'en' => null],
        ]);

        $response = $this->withHeaders(['X-Api-Key' => $this->key])
            ->getJson('/api/v1/news?lang=en');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'English Title')
            // EN excerpt is blank — falls back to Indonesian rather than "".
            ->assertJsonPath('data.0.excerpt', 'Ringkasan.');
    }

    public function test_default_locale_is_indonesian(): void
    {
        News::factory()->published()->create(['title' => ['id' => 'Judul Indonesia', 'en' => 'English Title']]);

        $this->getPublic('/api/v1/news')->assertJsonPath('data.0.title', 'Judul Indonesia');
    }

    public function test_program_filter(): void
    {
        News::factory()->published()->create(['related_programs' => ['forest']]);
        News::factory()->published()->create(['related_programs' => ['ocean']]);

        $this->getPublic('/api/v1/news?program=ocean')->assertJsonCount(1, 'data');
    }

    public function test_category_filter_matches_either_locale_slug(): void
    {
        $category = NewsCategory::factory()->create(['slug' => ['id' => 'kategori-id', 'en' => 'category-en']]);
        News::factory()->published()->create(['category_id' => $category->id]);
        News::factory()->published()->create();

        $this->getPublic('/api/v1/news?category=kategori-id')->assertJsonCount(1, 'data');
        $this->getPublic('/api/v1/news?category=category-en')->assertJsonCount(1, 'data');
    }

    public function test_search_matches_the_title_in_either_locale(): void
    {
        News::factory()->published()->create(['title' => ['id' => 'Panen Raya Mangrove', 'en' => 'Mangrove Harvest']]);
        News::factory()->published()->create(['title' => ['id' => 'Rapat Tahunan', 'en' => 'Annual Meeting']]);

        $this->getPublic('/api/v1/news?search=mangrove')->assertOk()->assertJsonCount(1, 'data');
        $this->getPublic('/api/v1/news?search=Harvest')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_search_matches_the_article_body(): void
    {
        News::factory()->published()->create([
            'title' => ['id' => 'Laporan Lapangan', 'en' => null],
            'excerpt' => ['id' => 'Ringkasan singkat.', 'en' => null],
            'body' => ['id' => '<p>Kegiatan restorasi terumbu karang berjalan lancar.</p>', 'en' => null],
        ]);
        News::factory()->published()->create([
            'title' => ['id' => 'Berita Lain', 'en' => null],
            'excerpt' => ['id' => 'Tidak relevan.', 'en' => null],
            'body' => ['id' => '<p>Isi yang berbeda.</p>', 'en' => null],
        ]);

        $this->getPublic('/api/v1/news?search=terumbu karang')->assertOk()->assertJsonCount(1, 'data');
        $this->getPublic('/api/v1/news?search=Ringkasan')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_blank_or_non_string_search_is_ignored(): void
    {
        News::factory()->published()->create();

        $this->getPublic('/api/v1/news?search=')->assertOk()->assertJsonCount(1, 'data');
        $this->getPublic('/api/v1/news?search=%20%20')->assertOk()->assertJsonCount(1, 'data');
        // ?search[]=x — an array must not blow up the endpoint.
        $this->getPublic('/api/v1/news?search[]=x')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_year_filter(): void
    {
        News::factory()->published()->create(['published_at' => '2023-05-10 08:00:00']);
        News::factory()->published()->create(['published_at' => '2024-01-02 08:00:00']);
        News::factory()->published()->create(['published_at' => '2024-11-30 08:00:00']);

        $this->getPublic('/api/v1/news?year=2024')->assertOk()->assertJsonCount(2, 'data');
        $this->getPublic('/api/v1/news?year=2023')->assertOk()->assertJsonCount(1, 'data');
        $this->getPublic('/api/v1/news?year=2020')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_invalid_year_is_ignored_rather_than_erroring(): void
    {
        News::factory()->published()->create();

        $this->getPublic('/api/v1/news?year=abcd')->assertOk()->assertJsonCount(1, 'data');
        $this->getPublic('/api/v1/news?year=24')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_search_and_year_combine(): void
    {
        News::factory()->published()->create([
            'title' => ['id' => 'Pelatihan Kader', 'en' => null],
            'published_at' => '2024-03-01 08:00:00',
        ]);
        News::factory()->published()->create([
            'title' => ['id' => 'Pelatihan Kader', 'en' => null],
            'published_at' => '2023-03-01 08:00:00',
        ]);

        $this->getPublic('/api/v1/news?search=pelatihan&year=2024')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_news_is_newest_first_by_default(): void
    {
        $older = News::factory()->published()->create(['published_at' => '2023-01-01 08:00:00']);
        $newer = News::factory()->published()->create(['published_at' => '2024-01-01 08:00:00']);

        $this->getPublic('/api/v1/news')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_sort_asc_returns_the_oldest_first(): void
    {
        $older = News::factory()->published()->create(['published_at' => '2023-01-01 08:00:00']);
        $newer = News::factory()->published()->create(['published_at' => '2024-01-01 08:00:00']);

        $this->getPublic('/api/v1/news?sort=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_an_unknown_sort_value_falls_back_to_newest_first(): void
    {
        $older = News::factory()->published()->create(['published_at' => '2023-01-01 08:00:00']);
        $newer = News::factory()->published()->create(['published_at' => '2024-01-01 08:00:00']);

        $this->getPublic('/api/v1/news?sort=views')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id);
    }

    public function test_fields_sparse_fieldset(): void
    {
        News::factory()->published()->create();

        $response = $this->getPublic('/api/v1/news?fields=id,title');

        $response->assertOk();
        $this->assertSame(['id', 'title'], array_keys($response->json('data.0')));
    }

    public function test_a_stale_cached_list_is_invalidated_when_a_new_article_is_published(): void
    {
        // Warms the canonical (no-filter) cache entry.
        $this->getPublic('/api/v1/news')->assertJsonCount(0, 'data');

        // The dashboard flow is session-based, not X-Api-Key — a separate
        // request context from the public API calls above.
        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        $this->postJson(route('dash-api.news.store'), [
            'title' => ['id' => 'Baru', 'en' => null],
            'slug' => ['id' => '', 'en' => ''],
            'body' => ['id' => '<p>Isi.</p>', 'en' => null],
            'status' => 'published',
        ])->assertCreated();

        $this->getPublic('/api/v1/news')->assertJsonCount(1, 'data');
    }
}
