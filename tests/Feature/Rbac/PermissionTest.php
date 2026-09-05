<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Tests\TenantTestCase;

/**
 * Menus follow permissions as well as feature flags, and every endpoint is
 * gated (context.md §4.7, §7.5). Tested with the roles the plan names:
 * super-admin, admin, editor, viewer.
 */
class PermissionTest extends TenantTestCase
{
    public function test_viewer_can_read_but_not_reach_management_screens(): void
    {
        $this->actingAsUserWith('viewer');

        $this->get(route('news.index'))->assertOk();

        // A viewer holds no users./roles./tenants. permissions at all.
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('tenants.index'))->assertForbidden();
    }

    public function test_editor_sees_content_but_not_system_menus(): void
    {
        $this->actingAsUserWith('editor');

        $html = $this->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('href="'.route('news.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('users.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('roles.index').'"', $html);
    }

    public function test_admin_manages_users_but_not_roles_or_tenants(): void
    {
        $this->actingAsUserWith('admin');

        $this->get(route('users.index'))->assertOk();

        // Roles and tenant provisioning stay with the super-admin: they are the
        // levers that could lock the organisation out of its own CMS.
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('tenants.index'))->assertForbidden();
    }

    public function test_super_admin_reaches_everything(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['users.index', 'roles.index', 'tenants.index', 'settings.index', 'news.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_the_internal_api_is_gated_too(): void
    {
        // Hiding the menu is not authorisation; the endpoint has to refuse.
        $this->actingAsUserWith('editor');

        $this->getJson(route('dash-api.users.index'))->assertForbidden();
        $this->getJson(route('dash-api.roles.index'))->assertForbidden();
        $this->getJson(route('dash-api.news.index'))->assertOk();
    }

    public function test_an_editor_cannot_create_a_user_through_the_api(): void
    {
        $this->actingAsUserWith('editor');

        $this->postJson(route('dash-api.users.store'), [
            'name' => 'Penyusup',
            'email' => 'penyusup@rekam.org',
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
            'role' => User::SUPER_ADMIN,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@rekam.org']);
    }

    public function test_a_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $user = $this->actingAsUserWith('admin');

        $this->get(route('dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        // Deactivation must bite immediately, not at the next login.
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'nonaktif@rekam.org',
            'password' => bcrypt('rahasia-sekali'),
            'is_active' => false,
        ]);
        $user->assignRole('admin');

        $this->post(route('login'), [
            'email' => 'nonaktif@rekam.org',
            'password' => 'rahasia-sekali',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_successful_login_records_the_timestamp(): void
    {
        $user = User::factory()->create([
            'email' => 'aktif@rekam.org',
            'password' => bcrypt('rahasia-sekali'),
            'last_login_at' => null,
        ]);
        $user->assignRole('admin');
        $user->tenants()->sync([$this->rekam->id]);

        $this->post(route('login'), [
            'email' => 'aktif@rekam.org',
            'password' => 'rahasia-sekali',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
