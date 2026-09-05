<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * The two companies this CMS was built for (plan.md §2.1).
 *
 * Feature flags come from plan.md §5.2.g and §5.3.f. Where a company's need was
 * not confirmed the flag is on: switching a module off later is cheap, adding
 * one late is not (plan.md §5.1).
 *
 * Idempotent on identity, but it deliberately does NOT overwrite feature flags
 * of an existing tenant — those are edited in the CMS, and re-seeding must not
 * silently undo an administrator's choice.
 */
class TenantSeeder extends Seeder
{
    private const TENANTS = [
        [
            'slug' => 'rekam',
            'name' => 'Rekam',
            'domain' => 'rekam.org',
            'sort_order' => 1,
            'features' => [
                'news' => true,
                'news_programs' => true,
                'events' => true,
                'event_rundown' => true,
                'team' => true,
                'partners' => true,
                'contacts' => true,
                'units' => true,
                'publications' => false,
                'milestones' => false,
            ],
        ],
        [
            'slug' => 'perikanan',
            'name' => 'Perikanan',
            'domain' => 'perikanan.org',
            'sort_order' => 2,
            'features' => [
                'news' => true,
                'news_programs' => true,
                // Confirmed 2026-09-04: perikanan.org does not run events.
                'events' => false,
                'event_rundown' => false,
                'team' => true,
                'partners' => true,
                'contacts' => true,
                'publications' => true,
                'milestones' => true,
                'units' => false,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::TENANTS as $data) {
            $existing = Tenant::where('slug', $data['slug'])->first();

            if ($existing) {
                // Identity may be corrected; capability may not be reset.
                $existing->update([
                    'name' => $data['name'],
                    'domain' => $data['domain'],
                    'sort_order' => $data['sort_order'],
                ]);

                $this->command?->info("Tenant {$data['slug']} sudah ada — feature flag dipertahankan.");

                continue;
            }

            $tenant = Tenant::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                // Derived, never typed: the registry is the only source of
                // database names (context.md §5.2).
                'db_name' => Tenant::databaseNameFor($data['slug']),
                'domain' => $data['domain'],
                'sort_order' => $data['sort_order'],
                'features' => $data['features'],
                'is_active' => true,
            ]);

            $key = $tenant->rotateApiKey();

            $this->command?->info("Tenant dibuat: {$tenant->name} ({$tenant->db_name})");
            $this->command?->warn("  API key {$tenant->slug}: {$key}");
            $this->command?->warn('  Hanya ditampilkan sekali — hanya hash-nya yang disimpan.');
        }
    }
}
