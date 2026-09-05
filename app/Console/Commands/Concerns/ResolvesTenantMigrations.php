<?php

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Shared by the tenants:* commands: which migration folders apply to a tenant,
 * and which tenants a run covers.
 */
trait ResolvesTenantMigrations
{
    /**
     * Migration paths for one tenant: the shared core schema first, then that
     * company's own tables (context.md §5.5).
     *
     * Mapping a slug to a folder is the single sanctioned place where tenant
     * identity is inspected (context.md §5.8) — everywhere else it is features.
     */
    protected function migrationPathsFor(Tenant $tenant): array
    {
        $paths = ['database/migrations/tenant/shared'];
        $own = "database/migrations/tenant/{$tenant->slug}";

        if (is_dir(base_path($own))) {
            $paths[] = $own;
        }

        return array_values(array_filter($paths, fn (string $p) => is_dir(base_path($p))));
    }

    /** @return Collection<int, Tenant> */
    protected function targetTenants(): Collection
    {
        $slug = $this->option('tenant');

        $query = Tenant::query()->orderBy('sort_order')->orderBy('id');

        if ($slug) {
            $query->where('slug', $slug);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->components->error(
                $slug
                    ? "Tenant '{$slug}' tidak ditemukan di registri."
                    : 'Belum ada tenant terdaftar. Jalankan `php artisan db:seed` lebih dulu.'
            );
        }

        return $tenants;
    }
}
