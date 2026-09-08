<?php

namespace Database\Factories;

use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Publication> */
class PublicationFactory extends Factory
{
    protected $model = Publication::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(6);

        return [
            'title' => ['id' => $title, 'en' => null],
            'description' => ['id' => $this->faker->sentence(15), 'en' => null],
            'category' => 'laporan',
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'cover_path' => null,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
