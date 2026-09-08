<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Partner> */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'title' => ['id' => 'Mitra Strategis', 'en' => null],
            'logo_path' => null,
            'url' => $this->faker->url(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
