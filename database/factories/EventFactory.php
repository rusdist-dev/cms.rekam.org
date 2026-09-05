<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->sentence(4));
        $start = now()->addDays(rand(-30, 60))->setTime(9, 0);

        return [
            'title' => ['id' => $title, 'en' => $title],
            'slug' => ['id' => Str::slug($title), 'en' => Str::slug($title)],
            'description' => ['id' => '<p>'.$this->faker->paragraph(4).'</p>', 'en' => null],
            'location' => ['id' => $this->faker->city(), 'en' => null],
            'fee_note' => ['id' => null, 'en' => null],
            'meta_title' => ['id' => null, 'en' => null],
            'meta_description' => ['id' => null, 'en' => null],
            'category' => 'workshop',
            'start_at' => $start,
            'end_at' => (clone $start)->setTime(16, 0),
            'is_all_day' => false,
            'fee' => null,
            'quota' => rand(0, 1) ? rand(20, 200) : null,
            'registration_url' => null,
            'cover_path' => null,
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published']);
    }

    public function paid(float $fee = 150000): static
    {
        return $this->state(fn () => [
            'fee' => $fee,
            'fee_note' => ['id' => 'Gratis untuk mahasiswa', 'en' => null],
        ]);
    }
}
