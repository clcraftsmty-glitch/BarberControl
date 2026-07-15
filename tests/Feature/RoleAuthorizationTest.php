<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_role-check', fn () => response()->noContent())
            ->middleware(['auth', 'role:administrador']);
    }

    public function test_administrator_can_access_an_administrator_route(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($user)->get('/_role-check')->assertNoContent();
    }

    public function test_receptionist_cannot_access_an_administrator_route(): void
    {
        $user = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($user)->get('/_role-check')->assertForbidden();
    }

    public function test_barber_cannot_access_an_administrator_route(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);

        $this->actingAs($user)->get('/_role-check')->assertForbidden();
    }

    public function test_role_is_cast_to_the_domain_enum(): void
    {
        $user = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->assertSame(UserRole::Receptionist, $user->role);
    }
}
