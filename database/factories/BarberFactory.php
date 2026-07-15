<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Barber> */
class BarberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Barber]),
            'display_name' => fake()->name(),
            'phone' => fake()->numerify('55########'),
            'default_commission_percentage' => fake()->randomFloat(2, 10, 50),
            'work_schedule' => collect(Barber::DAYS)->map(fn (string $label, string $day): array => [
                'enabled' => $day !== 'sunday',
                'start' => $day !== 'sunday' ? '09:00' : null,
                'end' => $day !== 'sunday' ? '18:00' : null,
            ])->all(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
