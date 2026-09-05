<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions are `{module}.{action}` and are derived from the module list, so
 * adding a module means adding one line here rather than remembering five
 * permission names.
 *
 * Idempotent: re-running only adds what is missing and re-syncs role
 * assignments, so a deploy that introduces a permission grants it without a
 * manual step.
 */
class RolePermissionSeeder extends Seeder
{
    /** Modules that can be permissioned, and whether they can be published. */
    private const MODULES = [
        'news' => true,
        'events' => true,
        'team' => false,
        'publications' => false,
        'partners' => false,
        'contacts' => false,
        'milestones' => false,
        'units' => false,
        'media' => false,
        'settings' => false,
        'tenants' => false,
        'users' => false,
        'roles' => false,
        'activity' => false,
    ];

    private const BASE_ACTIONS = ['view', 'create', 'update', 'delete'];

    public function run(): void
    {
        $permissions = $this->permissionNames();

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->syncRole(self::superAdmin(), $permissions);
        $this->syncRole('admin', $this->adminPermissions($permissions));
        $this->syncRole('editor', $this->editorPermissions());
        $this->syncRole('viewer', $this->viewerPermissions());

        // The package caches the permission map; a stale cache means a freshly
        // granted permission appears not to work.
        Artisan::call('permission:cache-reset');

        $this->command?->info(count($permissions).' izin dan 4 peran disiapkan.');
    }

    /** @return array<int, string> */
    private function permissionNames(): array
    {
        $names = [];

        foreach (self::MODULES as $module => $publishable) {
            foreach (self::BASE_ACTIONS as $action) {
                $names[] = "{$module}.{$action}";
            }

            if ($publishable) {
                $names[] = "{$module}.publish";
            }
        }

        // Reordering content is its own action: an editor may sort the team
        // without being allowed to delete anyone from it.
        foreach (['team', 'partners', 'publications', 'milestones', 'units'] as $module) {
            $names[] = "{$module}.reorder";
        }

        sort($names);

        return array_values(array_unique($names));
    }

    private function adminPermissions(array $all): array
    {
        // Everything except the levers that could lock the organisation out of
        // its own CMS: tenant provisioning and role definitions.
        return array_values(array_filter(
            $all,
            fn (string $p) => ! str_starts_with($p, 'tenants.') && ! str_starts_with($p, 'roles.')
        ));
    }

    private function editorPermissions(): array
    {
        $content = ['news', 'events', 'team', 'publications', 'partners', 'milestones', 'units', 'media'];

        $names = [];

        foreach ($content as $module) {
            foreach (['view', 'create', 'update'] as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        // Editors write and arrange, but publishing and deletion stay with
        // admins — the two actions that are visible to the public or permanent.
        $names[] = 'contacts.view';
        $names[] = 'contacts.update';
        $names[] = 'settings.view';

        foreach (['team', 'partners', 'publications', 'milestones', 'units'] as $module) {
            $names[] = "{$module}.reorder";
        }

        sort($names);

        return $names;
    }

    private function viewerPermissions(): array
    {
        return collect(self::MODULES)
            ->keys()
            ->reject(fn (string $m) => in_array($m, ['tenants', 'users', 'roles'], true))
            ->map(fn (string $m) => "{$m}.view")
            ->sort()
            ->values()
            ->all();
    }

    private function syncRole(string $name, array $permissions): void
    {
        Role::findOrCreate($name, 'web')->syncPermissions($permissions);
    }

    private static function superAdmin(): string
    {
        return \App\Models\User::SUPER_ADMIN;
    }
}
