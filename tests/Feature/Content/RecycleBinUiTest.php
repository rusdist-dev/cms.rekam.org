<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Js;
use Tests\TenantTestCase;

/**
 * The backend recycle bin (`?trashed=1`, restore, force-destroy) has existed
 * since Fase 3 and was already tested in NewsCrudTest/EventCrudTest, but no
 * Blade page ever exposed it — this covers the Fase 8 addition that does.
 */
class RecycleBinUiTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
    }

    public function test_the_news_index_page_exposes_the_trash_toggle_and_actions(): void
    {
        $this->useTenant($this->rekam);

        $html = $this->get(route('news.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Sampah', $html);
        $this->assertStringContainsString('restore(item.id)', $html);
        $this->assertStringContainsString('confirmForceDelete(item.id)', $html);
        // Route templates are embedded via @js(), which escapes slashes —
        // Js::from() reproduces exactly what lands in the HTML.
        $this->assertStringContainsString((string) Js::from(route('dash-api.news.restore', ['news' => '__ID__'])), $html);
        $this->assertStringContainsString((string) Js::from(route('dash-api.news.force-destroy', ['news' => '__ID__'])), $html);
    }

    public function test_the_events_index_page_exposes_the_trash_toggle_and_actions(): void
    {
        $this->useTenant($this->rekam);

        $html = $this->get(route('events.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Sampah', $html);
        $this->assertStringContainsString('restore(item.id)', $html);
        $this->assertStringContainsString('confirmForceDelete(item.id)', $html);
        $this->assertStringContainsString((string) Js::from(route('dash-api.events.restore', ['event' => '__ID__'])), $html);
        $this->assertStringContainsString((string) Js::from(route('dash-api.events.force-destroy', ['event' => '__ID__'])), $html);
    }
}
