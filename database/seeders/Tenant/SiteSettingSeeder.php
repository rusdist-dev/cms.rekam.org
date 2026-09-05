<?php

namespace Database\Seeders\Tenant;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Settings every tenant starts with. Company-specific taxonomy (team levels,
 * programs, categories) is seeded by the per-tenant seeder instead — those are
 * exactly the values the two companies do not share.
 *
 * Never overwrites an existing value: re-seeding must not wipe what an editor
 * has configured.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['site', 'identity', [
                'name' => '',
                'tagline' => ['id' => '', 'en' => ''],
                'email' => '',
                'phone' => '',
                'whatsapp' => '',
                'address' => ['id' => '', 'en' => ''],
            ]],
            ['site', 'socials', [
                'instagram' => '',
                'linkedin' => '',
                'youtube' => '',
                'facebook' => '',
                'x' => '',
                'tiktok' => '',
            ]],
            ['site', 'seo', [
                'meta_title' => ['id' => '', 'en' => ''],
                'meta_description' => ['id' => '', 'en' => ''],
                'og_image' => null,
                'keywords' => [],
            ]],
            ['site', 'map', ['embed' => '']],
        ];

        $created = 0;

        foreach ($defaults as [$group, $key, $value]) {
            $exists = SiteSetting::where('group', $group)->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            SiteSetting::put($group, $key, $value);
            $created++;
        }

        $this->command?->info("site_settings: {$created} pengaturan dasar dibuat.");
    }
}
