<?php

namespace Database\Seeders\Tenant\Concerns;

use App\Models\SiteSetting;

/**
 * Shared writer for taxonomy lists, so each company's seeder only declares its
 * own options and not the mechanics of storing them.
 */
trait SeedsTaxonomy
{
    /**
     * Writes one option list, unless an editor has already configured it.
     *
     * @param  array<int, array{0: string, 1: string, 2?: string}>  $options
     *                                                                        [slug, label ID, label EN]
     */
    protected function seedTaxonomy(string $group, string $key, array $options): void
    {
        if (SiteSetting::where('group', $group)->where('key', $key)->exists()) {
            $this->command?->info("  {$group}.{$key} sudah ada — dilewati.");

            return;
        }

        SiteSetting::put($group, $key, array_map(
            fn (array $o) => [
                'slug' => $o[0],
                'label' => ['id' => $o[1], 'en' => $o[2] ?? $o[1]],
            ],
            $options
        ));

        $this->command?->info('  '.$group.'.'.$key.': '.count($options).' pilihan.');
    }
}
