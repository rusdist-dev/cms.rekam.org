<?php

namespace Tests\Feature\Dashboard;

use App\Models\Activity;
use App\Models\ContactMessage;
use App\Models\News;
use App\Models\Partner;
use Tests\TenantTestCase;

/**
 * Nothing logged anything before Fase 7 (plan.md) — this covers the trait
 * that now does, the tenant scoping on a table shared by every company, and
 * the new Riwayat Aktivitas page built against it.
 */
class ActivityLogTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    public function test_creating_a_news_row_logs_a_tenant_scoped_activity(): void
    {
        $news = News::factory()->create();

        $this->assertSame(1, Activity::count());

        $activity = Activity::first();
        $this->assertSame('Berita dibuat', $activity->description);
        $this->assertSame($this->rekam->id, $activity->tenant_id);
        $this->assertSame(News::class, $activity->subject_type);
        $this->assertSame($news->id, $activity->subject_id);
    }

    public function test_updating_and_deleting_also_log(): void
    {
        $news = News::factory()->create();

        $news->update(['author_name' => 'Nama Baru']);
        $news->delete();

        $descriptions = Activity::orderBy('id')->pluck('description')->all();

        $this->assertSame(['Berita dibuat', 'Berita diperbarui', 'Berita dihapus'], $descriptions);
    }

    public function test_the_causer_is_the_signed_in_user(): void
    {
        $user = $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        Partner::factory()->create();

        $this->assertSame($user->id, Activity::first()->causer_id);
    }

    public function test_a_contact_message_arriving_does_not_log_an_activity(): void
    {
        ContactMessage::factory()->create();

        $this->assertSame(0, Activity::count());
    }

    public function test_activity_from_another_tenant_never_leaks(): void
    {
        News::factory()->create();

        $this->useTenant($this->perikanan);
        News::factory()->count(2)->create();

        $this->assertSame(2, Activity::forCurrentTenant()->count());

        $this->tenants->refresh();
        $this->useTenant($this->rekam);
        $this->tenants->refresh();

        $this->assertSame(1, Activity::forCurrentTenant()->count());
    }

    public function test_the_history_page_paginates_and_filters_by_search(): void
    {
        News::factory()->create(['author_name' => 'Rina']);
        Partner::factory()->create();

        $response = $this->getJson(route('dash-api.activity.index'))->assertOk();

        $this->assertSame(2, $response->json('meta.total'));

        $filtered = $this->getJson(route('dash-api.activity.index', ['search' => 'Partner']))->assertOk();
        $this->assertSame(1, $filtered->json('meta.total'));
    }

    public function test_the_history_page_is_gated_behind_the_activity_permission(): void
    {
        // Editors are not granted activity.view (RolePermissionSeeder).
        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->get(route('activity.index'))->assertForbidden();
        $this->getJson(route('dash-api.activity.index'))->assertForbidden();
    }
}
