<?php

namespace Database\Factories;

use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Milestone> */
class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'title' => ['id' => $title, 'en' => null],
            'body' => ['id' => $this->faker->sentence(10), 'en' => null],
            'cover_path' => null,
            'year' => $this->faker->numberBetween(2010, 2026),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
