<?php

namespace Database\Seeders\Tenant;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter categories so the news form has something to select on a fresh
 * install. Editors rename or replace them; re-seeding never overwrites.
 */
class NewsCategorySeeder extends Seeder
{
    private const CATEGORIES = [
        ['Siaran Pers', 'Press Release'],
        ['Riset', 'Research'],
        ['Kegiatan', 'Activity'],
    ];

    public function run(): void
    {
        if (NewsCategory::exists()) {
            $this->command?->info('news_categories sudah terisi — dilewati.');

            return;
        }

        foreach (self::CATEGORIES as $index => [$id, $en]) {
            NewsCategory::create([
                'name' => ['id' => $id, 'en' => $en],
                'slug' => ['id' => Str::slug($id), 'en' => Str::slug($en)],
                'sort_order' => $index,
            ]);
        }

        $this->command?->info('news_categories: '.count(self::CATEGORIES).' kategori dibuat.');
    }
}
