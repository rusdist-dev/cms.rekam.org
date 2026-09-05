<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class UserManagementTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    public function test_it_creates_a_user_with_a_role_and_tenant_access(): void
    {
        $response = $this->postJson(route('dash-api.users.store'), [
            'name' => 'Rina Hartati',
            'email' => 'rina@rekam.org',
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
            'role' => 'editor',
            'tenants' => ['rekam'],
            'is_active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'rina@rekam.org');

        $user = User::where('email', 'rina@rekam.org')->first();

        $this->assertTrue($user->hasRole('editor'));
        $this->assertSame(['rekam'], $user->tenants->pluck('slug')->all());
    }

    public function test_the_response_never_leaks_the_password_hash(): void
    {
        $response = $this->getJson(route('dash-api.users.index'));

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.password');
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }

    public function test_validation_errors_come_back_field_keyed(): void
    {
        // context.md §4.5 — a 422 must be renderable inline, per field.
        $response = $this->postJson(route('dash-api.users.store'), [
            'name' => '',
            'email' => 'bukan-email',
            'password' => 'pendek',
            'password_confirmation' => 'lain',
            'role' => 'tidak-ada',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name', 'email', 'password', 'role']]);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'ada@rekam.org']);

        $this->postJson(route('dash-api.users.store'), [
            'name' => 'Ada',
            'email' => 'ada@rekam.org',
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
            'role' => 'editor',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_updating_without_a_password_keeps_the_existing_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('sandi-lama-123')]);
        $user->assignRole('editor');

        $this->putJson(route('dash-api.users.update', $user), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'password' => '',
            'role' => 'editor',
            'tenants' => ['rekam'],
            'is_active' => true,
        ])->assertOk();

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertTrue(Hash::check('sandi-lama-123', $user->password));
    }

    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        // Demoting the only super-admin leaves nobody able to manage roles or
        // tenants — a state with no way out through the UI.
        $admin = User::role(User::SUPER_ADMIN)->first();

        $this->putJson(route('dash-api.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'editor',
            'tenants' => ['rekam'],
            'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('role');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_the_last_super_admin_cannot_be_deactivated(): void
    {
        $admin = User::role(User::SUPER_ADMIN)->first();

        $this->putJson(route('dash-api.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::SUPER_ADMIN,
            'tenants' => ['rekam'],
            'is_active' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_a_second_super_admin_may_be_demoted(): void
    {
        $second = User::factory()->create();
        $second->assignRole(User::SUPER_ADMIN);

        $this->putJson(route('dash-api.users.update', $second), [
            'name' => $second->name,
            'email' => $second->email,
            'role' => 'admin',
            'tenants' => ['rekam'],
            'is_active' => true,
        ])->assertOk();

        $this->assertFalse($second->fresh()->isSuperAdmin());
    }

    public function test_a_user_cannot_delete_themselves(): void
    {
        $me = auth()->user();

        $this->deleteJson(route('dash-api.users.destroy', $me))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $me->id]);
    }

    public function test_an_admin_cannot_edit_a_super_admin(): void
    {
        $target = User::role(User::SUPER_ADMIN)->first();

        $this->actingAsUserWith('admin');

        $this->putJson(route('dash-api.users.update', $target), [
            'name' => 'Diambil Alih',
            'email' => $target->email,
            'role' => 'viewer',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_search_and_role_filter_narrow_the_list(): void
    {
        User::factory()->create(['name' => 'Zulfikar Ahmad'])->assignRole('viewer');

        $found = $this->getJson(route('dash-api.users.index', ['search' => 'Zulfikar']));
        $found->assertOk();
        $this->assertSame(1, $found->json('meta.total'));

        $viewers = $this->getJson(route('dash-api.users.index', ['role' => 'viewer']));
        $this->assertSame(1, $viewers->json('meta.total'));
    }
}
