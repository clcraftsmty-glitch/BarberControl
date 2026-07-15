<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@barbercontrol.local')],
            [
                'name' => env('ADMIN_NAME', 'Administrador BarberControl'),
                'role' => UserRole::Administrator,
                'email_verified_at' => now(),
                'password' => env('ADMIN_PASSWORD', 'Cambiar123!'),
            ],
        );
    }
}
