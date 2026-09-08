<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TeamMember> */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->name();
        $position = $this->faker->jobTitle();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => ['id' => $position, 'en' => null],
            'bio' => ['id' => $this->faker->sentence(15), 'en' => null],
            'photo_path' => null,
            'group' => 'manager',
            'email' => $this->faker->unique()->safeEmail(),
            'socials' => ['linkedin' => '', 'instagram' => ''],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
