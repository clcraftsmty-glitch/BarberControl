<?php

namespace Tests\Feature\CashRegister;

use App\Enums\UserRole;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_receptionist_can_access_cash_register(): void
    {
        foreach ([UserRole::Administrator, UserRole::Receptionist] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertTrue($user->can('viewAny', CashRegisterSession::class));
            $this->actingAs($user)->get(route('cash-register.index'))->assertOk();
        }
    }

    public function test_barber_cannot_access_cash_register(): void
    {
        $barber = User::factory()->create(['role' => UserRole::Barber]);

        $this->assertFalse($barber->can('viewAny', CashRegisterSession::class));
        $this->actingAs($barber)->get(route('cash-register.index'))->assertForbidden();
    }
}
