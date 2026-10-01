<?php

namespace Database\Factories;

use App\Models\EventBenefit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventBenefit> */
class EventBenefitFactory extends Factory
{
    protected $model = EventBenefit::class;

    public function definition(): array
    {
        return [
            'title' => ['id' => $this->faker->sentence(3), 'en' => null],
            'sort_order' => 0,
        ];
    }
}
