<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Tests\TenantTestCase;

class DashboardShellTest extends TenantTestCase
{
    private function actingAsUser(): User
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);
        $user->assignRole(User::SUPER_ADMIN);
        $user->tenants()->sync([$this->rekam->id, $this->perikanan->id]);

        $this->actingAs($user);
        $this->useTenant($this->rekam);

        return $user;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_public_registration_does_not_exist(): void
    {
        // Accounts are created by an administrator, never self-registered.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    public function test_login_page_renders_in_indonesian(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Masuk');
        $response->assertSee('Kata Sandi');
        $response->assertSee('Ingat saya');
        $response->assertDontSee('Password', false);
        $response->assertDontSee('Remember me', false);
    }

    public function test_dashboard_renders_the_full_shell(): void
    {
        $this->actingAsUser();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Dasbor');
        // Sidebar, topbar and the Alpine shell root must all be server-rendered
        // (context.md §1.3) — the page is never an empty div filled by JS.
        $response->assertSee('aria-label="Menu utama"', false);
        $response->assertSee('x-data="sidebar(', false);
        $response->assertSee('Ciutkan menu');
        $response->assertSee('Budi Santoso');
    }

    public function test_layout_loads_assets_through_vite_not_a_cdn(): void
    {
        $this->actingAsUser();

        $html = $this->get(route('dashboard'))->getContent();

        // context.md §1.6 — no CDN <script>, everything through Vite.
        // Vite's dev server (npm run dev) is a local address too — it answers
        // on localhost, 127.0.0.1, or the IPv6 loopback [::1] depending on the
        // machine — so all three are excluded, not just the literal string
        // "localhost".
        $this->assertDoesNotMatchRegularExpression(
            '/<script[^>]+src="https?:\/\/(?!localhost|127\.0\.0\.1|\[?::1\]?)/i',
            $html
        );
        $this->assertStringContainsString('csrf-token', $html);
    }

    public function test_sidebar_only_shows_modules_the_active_tenant_has(): void
    {
        $this->actingAsUser();

        // Rekam has units and (since 2026-09-08) publications, not milestones.
        $this->useTenant($this->rekam);

        // Assert on the nav links themselves: page copy elsewhere legitimately
        // contains words like "Publikasi" (the chart is titled "Tren Publikasi").
        $response = $this->get(route('dashboard'));
        $response->assertSee('href="'.route('units.index').'"', false);
        $response->assertDontSee('href="'.route('milestones.index').'"', false);
        $response->assertSee('href="'.route('publications.index').'"', false);
    }

    public function test_switching_tenant_changes_the_visible_modules(): void
    {
        $this->actingAsUser();

        $this->put(route('tenant.switch', $this->perikanan->id))
            ->assertRedirect(route('dashboard'));

        $response = $this->get(route('dashboard'));
        $response->assertSee('href="'.route('milestones.index').'"', false);
        $response->assertSee('href="'.route('publications.index').'"', false);
        $response->assertDontSee('href="'.route('units.index').'"', false);
    }

    public function test_routes_of_a_disabled_module_return_404(): void
    {
        // context.md §5.6 — a module the tenant lacks is absent, not forbidden.
        $this->actingAsUser();

        $this->useTenant($this->rekam);

        $this->get(route('milestones.index'))->assertNotFound();
        $this->get(route('units.index'))->assertOk();
    }

    public function test_tenant_switch_rejects_an_unknown_tenant(): void
    {
        $this->actingAsUser();

        $this->from(route('dashboard'))
            ->put(route('tenant.switch', 9999))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }
}
