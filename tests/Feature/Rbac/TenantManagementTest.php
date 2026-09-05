<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class TenantManagementTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    public function test_toggling_a_feature_flag_takes_effect_immediately(): void
    {
        $this->get(route('milestones.index'))->assertNotFound();

        $features = $this->rekam->featureMap();
        $features['milestones'] = true;

        $this->putJson(route('dash-api.tenants.update', $this->rekam), [
            'name' => $this->rekam->name,
            'domain' => $this->rekam->domain,
            'is_active' => true,
            'features' => $features,
        ])->assertOk();

        $this->get(route('milestones.index'))->assertOk();
    }

    public function test_the_response_never_exposes_the_api_key_hash(): void
    {
        $this->rekam->rotateApiKey();

        $response = $this->getJson(route('dash-api.tenants.show', $this->rekam));

        $response->assertOk()->assertJsonPath('data.has_api_key', true);
        $response->assertJsonMissingPath('data.api_key');
    }

    public function test_rotating_returns_the_plaintext_key_exactly_once(): void
    {
        $response = $this->postJson(route('dash-api.tenants.api-key', $this->rekam));

        $response->assertOk();
        $plain = $response->json('data.api_key');

        $this->assertNotEmpty($plain);
        $this->assertStringStartsWith('rekam_', $plain);

        // Only a hash is stored, so the key can never be shown again.
        $stored = $this->rekam->fresh()->getAttributes()['api_key'];
        $this->assertNotSame($plain, $stored);
        $this->assertTrue(Hash::check($plain, $stored));
    }

    public function test_rotating_invalidates_the_previous_key(): void
    {
        $first = $this->rekam->rotateApiKey();
        $second = $this->rekam->fresh()->rotateApiKey();

        $tenant = $this->rekam->fresh();

        $this->assertFalse($tenant->matchesApiKey($first));
        $this->assertTrue($tenant->matchesApiKey($second));
    }

    public function test_the_database_name_cannot_be_changed_through_the_api(): void
    {
        // Repointing a live tenant at another database would strand its content
        // (context.md §5.2).
        $original = $this->rekam->db_name;

        $this->putJson(route('dash-api.tenants.update', $this->rekam), [
            'name' => 'Rekam',
            'domain' => 'rekam.org',
            'is_active' => true,
            'features' => $this->rekam->featureMap(),
            'db_name' => 'cms_disusupi',
            'slug' => 'disusupi',
        ])->assertOk();

        $tenant = $this->rekam->fresh();

        $this->assertSame($original, $tenant->db_name);
        $this->assertSame('rekam', $tenant->slug);
    }

    public function test_unknown_feature_keys_are_rejected(): void
    {
        $this->putJson(route('dash-api.tenants.update', $this->rekam), [
            'name' => 'Rekam',
            'is_active' => true,
            'features' => ['modul_hantu' => true],
        ])->assertStatus(422)->assertJsonValidationErrors('features');
    }

    public function test_a_tenant_cannot_be_deleted_through_the_api(): void
    {
        // Deleting the registry row would orphan an entire content database.
        $this->assertFalse(auth()->user()->can('delete', $this->rekam));
    }

    public function test_super_admin_role_is_locked_from_editing(): void
    {
        $role = Role::where('name', User::SUPER_ADMIN)->first();

        $this->putJson(route('dash-api.roles.update', $role), [
            'name' => User::SUPER_ADMIN,
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_a_new_role_can_be_created_with_permissions(): void
    {
        $response = $this->postJson(route('dash-api.roles.store'), [
            'name' => 'editor-berita',
            'permissions' => ['news.view', 'news.create', 'news.update'],
        ]);

        $response->assertCreated();

        $role = Role::where('name', 'editor-berita')->first();
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(
            ['news.view', 'news.create', 'news.update'],
            $role->permissions->pluck('name')->all()
        );
    }

    public function test_a_role_cannot_be_named_super_admin(): void
    {
        // A second "super-admin" would look privileged but would not be the one
        // Gate::before recognises.
        $this->postJson(route('dash-api.roles.store'), [
            'name' => User::SUPER_ADMIN,
            'permissions' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_a_role_still_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'sementara', 'guard_name' => 'web']);
        User::factory()->create()->assignRole('sementara');

        $this->deleteJson(route('dash-api.roles.destroy', $role))->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'sementara']);
    }

    public function test_the_permission_matrix_is_grouped_by_module(): void
    {
        $response = $this->getJson(route('dash-api.permissions.index'));

        $response->assertOk()->assertJsonStructure([
            'data' => [['module', 'label', 'actions' => [['name', 'action', 'label']]]],
        ]);

        // Labels are Indonesian (context.md §6.1).
        $labels = collect($response->json('data'))->pluck('label');
        $this->assertContains('Berita', $labels);
    }
}
