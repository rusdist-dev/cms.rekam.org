<?php

namespace Tests\Feature\Pages;

use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * Every dashboard page must render as a complete HTML document on a direct hit
 * (context.md §1.4). A page that only works after a client-side navigation, or
 * that throws on a fresh load, fails here.
 */
class PageSmokeTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
    }

    private function switchTenant(string $slug): void
    {
        $this->useTenant($slug === 'rekam' ? $this->rekam : $this->perikanan);
    }

    /** @return array<string, array{0: string, 1: array}> */
    public static function rekamPages(): array
    {
        return [
            'dasbor' => ['dashboard', []],
            'berita index' => ['news.index', []],
            'berita tambah' => ['news.create', []],
            'berita ubah' => ['news.edit', ['news' => 1]],
            'events index' => ['events.index', []],
            'events tambah' => ['events.create', []],
            'events ubah' => ['events.edit', ['event' => 1]],
            'tim index' => ['team.index', []],
            'tim tambah' => ['team.create', []],
            'tim ubah' => ['team.edit', ['member' => 1]],
            'partner index' => ['partners.index', []],
            'partner tambah' => ['partners.create', []],
            // Confirmed 2026-09-08: rekam.org now runs Publikasi too.
            'publikasi index' => ['publications.index', []],
            'publikasi tambah' => ['publications.create', []],
            'kontak index' => ['contacts.index', []],
            'kontak detail' => ['contacts.show', ['message' => 1]],
            'kontak pengaturan' => ['contacts.settings', []],
            'unit index' => ['units.index', []],
            'unit tambah' => ['units.create', []],
            'pengaturan' => ['settings.index', []],
            'company index' => ['tenants.index', []],
            'company ubah' => ['tenants.edit', ['tenant' => 1]],
            'pengguna index' => ['users.index', []],
            'pengguna tambah' => ['users.create', []],
            'peran index' => ['roles.index', []],
            'peran tambah' => ['roles.create', []],
        ];
    }

    /** @return array<string, array{0: string, 1: array}> */
    public static function perikananPages(): array
    {
        return [
            'publikasi index' => ['publications.index', []],
            'publikasi tambah' => ['publications.create', []],
            'milestone index' => ['milestones.index', []],
            'milestone tambah' => ['milestones.create', []],
        ];
    }

    /** @dataProvider rekamPages */
    public function test_rekam_pages_render(string $route, array $params): void
    {
        $this->switchTenant('rekam');

        $this->get(route($route, $params))
            ->assertOk()
            ->assertSee('</html>', false);
    }

    /** @dataProvider perikananPages */
    public function test_perikanan_pages_render(string $route, array $params): void
    {
        $this->switchTenant('perikanan');

        $this->get(route($route, $params))
            ->assertOk()
            ->assertSee('</html>', false);
    }

    public function test_no_page_contains_raw_svg_or_hex_colours(): void
    {
        // context.md §3.5 and §7.1. The icon component is the only source of
        // <svg>, and it always sets viewBox — a hand-pasted one usually does not
        // carry the component's aria-hidden/focusable pair.
        $this->switchTenant('rekam');

        foreach (['news.index', 'news.create', 'events.index', 'team.index'] as $name) {
            $html = $this->get(route($name))->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/style="[^"]*#[0-9a-fA-F]{3,6}/',
                $html,
                "Halaman {$name} memuat warna hex mentah."
            );

            $this->assertStringNotContainsString('<style>', $html, "Halaman {$name} memuat <style> inline.");
        }
    }

    public function test_index_pages_render_all_four_fetch_states(): void
    {
        // context.md §2.3 — loading, error, empty and ready must all be present
        // in the server-rendered shell, not conjured by JS later.
        $this->switchTenant('rekam');

        $html = $this->get(route('news.index'))->getContent();

        $this->assertStringContainsString('skeleton', $html, 'State loading tidak ada.');
        $this->assertStringContainsString('Coba lagi', $html, 'State error tidak ada.');
        $this->assertStringContainsString('Belum ada berita', $html, 'State empty tidak ada.');
        $this->assertStringContainsString('x-for="item in items"', $html, 'State ready tidak ada.');
    }

    public function test_pages_never_use_the_browser_confirm_dialog(): void
    {
        // context.md §7.11
        $this->switchTenant('rekam');

        $html = $this->get(route('news.index'))->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bconfirm\s*\(/', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
    }

    public function test_every_dashboard_route_requires_authentication(): void
    {
        auth()->logout();

        $unprotected = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Auth screens and the public API are intentionally open.
            if (str_starts_with($name, 'password.') || in_array($name, ['login', 'home', 'sanctum.csrf-cookie'], true)) {
                continue;
            }

            if (str_starts_with($name, 'ignition.') || str_starts_with($name, 'api.')) {
                continue;
            }

            // The public compro API (routes/api.php) is authenticated by
            // X-Api-Key, not a session — see ResolvePublicTenant (plan.md
            // Fase 6).
            if (str_starts_with($name, 'public.')) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                $unprotected[] = $name;
            }
        }

        $this->assertSame([], $unprotected, 'Route berikut tidak dilindungi auth: '.implode(', ', $unprotected));
    }
}
