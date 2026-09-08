<?php

namespace Tests\Feature\Settings;

use Tests\TenantTestCase;

class TaxonomyEditorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    public function test_edit_returns_the_raw_bilingual_shape(): void
    {
        // RekamSeeder seeds team_levels with 5 entries (context.md §5.12).
        $response = $this->getJson(route('dash-api.taxonomy.edit', 'team_levels'));

        $response->assertOk();
        $this->assertSame('advisor-board', $response->json('data.0.slug'));
        $this->assertSame('Dewan Penasihat', $response->json('data.0.label.id'));
        $this->assertSame('Advisor Board', $response->json('data.0.label.en'));
    }

    public function test_update_replaces_the_list_and_persists(): void
    {
        $payload = [
            'options' => [
                ['slug' => 'ketua', 'label' => ['id' => 'Ketua', 'en' => 'Chair']],
                ['slug' => 'anggota', 'label' => ['id' => 'Anggota', 'en' => null]],
            ],
        ];

        $this->putJson(route('dash-api.taxonomy.update', 'team_levels'), $payload)
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'ketua')
            ->assertJsonPath('data.1.label.id', 'Anggota');

        // The flattened, consumer-facing shape must reflect the replace too.
        $this->getJson(route('dash-api.taxonomy.show', 'team_levels'))
            ->assertJsonPath('data.0.value', 'ketua')
            ->assertJsonPath('data.0.label', 'Ketua');
    }

    public function test_duplicate_slugs_are_rejected(): void
    {
        $this->putJson(route('dash-api.taxonomy.update', 'team_levels'), [
            'options' => [
                ['slug' => 'dup', 'label' => ['id' => 'Satu']],
                ['slug' => 'dup', 'label' => ['id' => 'Dua']],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['options.0.slug', 'options.1.slug']);
    }

    public function test_a_missing_indonesian_label_is_rejected(): void
    {
        $this->putJson(route('dash-api.taxonomy.update', 'team_levels'), [
            'options' => [['slug' => 'x', 'label' => ['id' => '']]],
        ])->assertStatus(422)->assertJsonValidationErrors('options.0.label.id');
    }

    public function test_news_categories_is_not_a_taxonomy_group(): void
    {
        // News categories are the real relational NewsCategory model
        // (NewsCategoryApiController), not a site_settings option list.
        $this->getJson(route('dash-api.taxonomy.edit', 'news_categories'))->assertNotFound();
        $this->putJson(route('dash-api.taxonomy.update', 'news_categories'), ['options' => []])->assertNotFound();
    }

    public function test_taxonomy_is_isolated_per_tenant(): void
    {
        $this->putJson(route('dash-api.taxonomy.update', 'team_levels'), [
            'options' => [['slug' => 'only-rekam', 'label' => ['id' => 'Hanya Rekam']]],
        ])->assertOk();

        $this->useTenant($this->perikanan);

        $slugs = collect($this->getJson(route('dash-api.taxonomy.edit', 'team_levels'))->json('data'))
            ->pluck('slug');

        $this->assertNotContains('only-rekam', $slugs);
    }

    public function test_a_viewer_can_read_but_not_update(): void
    {
        $this->actingAsUserWith('viewer');
        $this->useTenant($this->rekam);

        $this->getJson(route('dash-api.taxonomy.edit', 'team_levels'))->assertOk();
        $this->putJson(route('dash-api.taxonomy.update', 'team_levels'), ['options' => []])->assertForbidden();
    }

    public function test_an_editor_can_read_but_not_update(): void
    {
        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->getJson(route('dash-api.taxonomy.edit', 'team_levels'))->assertOk();
        $this->putJson(route('dash-api.taxonomy.update', 'team_levels'), ['options' => []])->assertForbidden();
    }
}
