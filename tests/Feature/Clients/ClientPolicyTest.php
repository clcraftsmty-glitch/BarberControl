<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ClientPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_authenticated_roles_can_view_clients(): void
    {
        $client = Client::factory()->create();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Client::class));
            $this->assertTrue(Gate::forUser($user)->allows('view', $client));
        }
    }

    public function test_administrator_and_receptionist_can_manage_clients(): void
    {
        $client = Client::factory()->create();

        foreach ([UserRole::Administrator, UserRole::Receptionist] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertTrue(Gate::forUser($user)->allows('create', Client::class));
            $this->assertTrue(Gate::forUser($user)->allows('update', $client));
            $this->assertTrue(Gate::forUser($user)->allows('deactivate', $client));
        }
    }

    public function test_barber_cannot_manage_clients(): void
    {
        $barber = User::factory()->create(['role' => UserRole::Barber]);
        $client = Client::factory()->create();

        $this->assertFalse(Gate::forUser($barber)->allows('create', Client::class));
        $this->assertFalse(Gate::forUser($barber)->allows('update', $client));
        $this->assertFalse(Gate::forUser($barber)->allows('deactivate', $client));
    }

    public function test_client_routes_require_authentication_and_respect_policy(): void
    {
        $client = Client::factory()->create();

        $this->get(route('clients.index'))->assertRedirect(route('login'));

        $barber = User::factory()->create(['role' => UserRole::Barber]);

        $this->actingAs($barber)->get(route('clients.index'))->assertOk();
        $this->actingAs($barber)->get(route('clients.show', $client))->assertOk();
        $this->actingAs($barber)->get(route('clients.create'))->assertForbidden();
        $this->actingAs($barber)->get(route('clients.edit', $client))->assertForbidden();
    }
}
