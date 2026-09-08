<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 *
 * There is no public submission endpoint yet (that's Fase 6), so this is how
 * tests and manual QA simulate a message having arrived.
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'subject' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(3),
            'status' => 'unread',
            'ip' => $this->faker->ipv4(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['status' => 'read']);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }
}
