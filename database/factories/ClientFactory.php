<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('55########'),
            'email' => fake()->boolean(70) ? fake()->unique()->safeEmail() : null,
            'birth_date' => fake()->optional()->dateTimeBetween('-80 years', '-15 years'),
            'preferred_barber_id' => null,
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
