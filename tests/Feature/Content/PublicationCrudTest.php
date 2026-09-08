<?php

namespace Tests\Feature\Content;

use App\Models\Publication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

class PublicationCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        // Both companies run `publications` since 2026-09-08 (context.md
        // §5.2.g, §5.3.f) — perikanan is just as good a fixture as rekam here.
        $this->useTenant($this->perikanan);

        Storage::fake('public');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['id' => 'Laporan Tahunan', 'en' => null],
            'description' => ['id' => 'Ringkasan.', 'en' => null],
            'category' => 'laporan',
            'file' => UploadedFile::fake()->create('laporan.pdf', 500, 'application/pdf'),
            'is_featured' => false,
        ], $overrides);
    }

    public function test_it_creates_a_publication(): void
    {
        $response = $this->postJson(route('dash-api.publications.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.title.id', 'Laporan Tahunan')
            ->assertJsonPath('data.file_name', 'laporan.pdf');

        $this->assertSame(1, Publication::count());
    }

    public function test_a_publication_without_a_file_is_rejected(): void
    {
        $this->postJson(route('dash-api.publications.store'), $this->payload(['file' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_it_updates_a_publication_without_replacing_the_file(): void
    {
        $created = $this->postJson(route('dash-api.publications.store'), $this->payload())->json('data');

        $response = $this->postJson(route('dash-api.publications.update', $created['id']), $this->payload([
            'title' => ['id' => 'Judul Diperbarui', 'en' => null],
            'file' => null,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.title.id', 'Judul Diperbarui')
            ->assertJsonPath('data.file_name', 'laporan.pdf');
    }

    public function test_it_appends_new_publications_to_the_end_of_the_order(): void
    {
        Publication::factory()->create(['sort_order' => 2]);

        $this->postJson(route('dash-api.publications.store'), $this->payload())
            ->assertJsonPath('data.sort_order', 3);
    }

    public function test_it_deletes_a_publication(): void
    {
        $publication = Publication::factory()->create();

        $this->deleteJson(route('dash-api.publications.destroy', $publication))->assertNoContent();
        $this->assertSame(0, Publication::count());
    }

    public function test_filters_narrow_the_list(): void
    {
        Publication::factory()->create(['category' => 'laporan']);
        Publication::factory()->featured()->create(['category' => 'panduan']);

        $this->assertSame(2, $this->getJson(route('dash-api.publications.index'))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.publications.index', ['category' => 'panduan']))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.publications.index', ['is_featured' => '1']))->json('meta.total'));
    }

    public function test_publications_are_absent_for_a_company_without_the_module(): void
    {
        // Both companies run `publications` by default now, so the "module
        // off" fixture is built here rather than borrowed from either
        // tenant's current defaults (context.md §5.2.g, §5.3.f).
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['publications' => false])]);
        $this->useTenant($this->rekam);
        // setUp() already switched to perikanan once, memoizing
        // TenantManager::accessible() — without this, switchTo() above would
        // resolve rekam from that stale, pre-update copy.
        $this->tenants->refresh();

        $this->postJson(route('dash-api.publications.store'), $this->payload())->assertNotFound();
        $this->getJson(route('dash-api.publications.index'))->assertNotFound();
    }
}
