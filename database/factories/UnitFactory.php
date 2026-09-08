<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $domain = $this->faker->unique()->domainName();

        return [
            'name' => $this->faker->unique()->company(),
            'description' => ['id' => $this->faker->sentence(10), 'en' => null],
            'url' => "https://{$domain}",
            'domain' => $domain,
            'logo_path' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
