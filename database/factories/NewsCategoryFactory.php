<?php

namespace Database\Factories;

use App\Models\NewsCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NewsCategory> */
class NewsCategoryFactory extends Factory
{
    protected $model = NewsCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ['id' => Str::title($name), 'en' => Str::title($name)],
            'slug' => ['id' => Str::slug($name), 'en' => Str::slug($name)],
            'sort_order' => 0,
        ];
    }
}
