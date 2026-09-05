<?php

namespace Database\Factories;

use App\Models\EventRundown;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventRundown> */
class EventRundownFactory extends Factory
{
    protected $model = EventRundown::class;

    public function definition(): array
    {
        return [
            'time' => sprintf('%02d:00', rand(8, 16)),
            'title' => ['id' => $this->faker->sentence(3), 'en' => null],
            'description' => ['id' => null, 'en' => null],
            'sort_order' => 0,
        ];
    }
}
