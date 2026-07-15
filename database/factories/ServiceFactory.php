<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60, 90]),
            'price' => fake()->randomFloat(2, 100, 1500),
            'commission_percentage' => fake()->randomFloat(2, 0, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
