<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_see_dashboard_and_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Barber',
            'role' => UserRole::Barber,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Barbero')
            ->assertSee('Ana');
    }
}
