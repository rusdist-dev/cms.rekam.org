<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<News> */
class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->sentence(5));

        return [
            'title' => ['id' => $title, 'en' => $title],
            'slug' => ['id' => Str::slug($title), 'en' => Str::slug($title)],
            'excerpt' => ['id' => $this->faker->sentence(12), 'en' => null],
            'body' => ['id' => '<p>'.$this->faker->paragraph(6).'</p>', 'en' => null],
            'meta_title' => ['id' => null, 'en' => null],
            'meta_description' => ['id' => null, 'en' => null],
            'related_programs' => [],
            'cover_path' => null,
            'status' => 'draft',
            'published_at' => null,
            'author_name' => $this->faker->name(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->subDays(rand(1, 60)),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'published_at' => now()->addDays(rand(1, 30)),
        ]);
    }

    /** Indonesian only — the state most content is actually in. */
    public function withoutEnglish(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => ['id' => $attributes['title']['id'], 'en' => null],
        ]);
    }
}
