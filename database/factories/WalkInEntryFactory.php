<?php

namespace Database\Factories;

use App\Enums\WalkInStatus;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkInEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WalkInEntry> */
class WalkInEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'service_id' => Service::factory(),
            'status' => WalkInStatus::Waiting,
            'arrived_at' => now(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
