<?php

namespace Tests\Feature\Barbers;

use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BarberPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_view_and_only_administrator_manages_barbers(): void
    {
        $barber = Barber::factory()->create();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(Gate::forUser($user)->allows('view', $barber));
            $this->assertSame($role === UserRole::Administrator, Gate::forUser($user)->allows('update', $barber));
        }
    }

    public function test_routes_require_authentication_and_policy(): void
    {
        $barber = Barber::factory()->create();
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->get(route('barbers.index'))->assertRedirect(route('login'));
        $this->actingAs($receptionist)->get(route('barbers.index'))->assertOk();
        $this->actingAs($receptionist)->get(route('barbers.show', $barber))->assertOk();
        $this->actingAs($receptionist)->get(route('barbers.create'))->assertForbidden();
        $this->actingAs($receptionist)->get(route('barbers.edit', $barber))->assertForbidden();
    }
}
