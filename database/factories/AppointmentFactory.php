<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->next('monday')->setTime(10, 0);
        $duration = fake()->randomElement([30, 45, 60]);

        return [
            'client_id' => Client::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($duration),
            'price' => fake()->randomFloat(2, 100, 1000),
            'status' => AppointmentStatus::Pending,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
