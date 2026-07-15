<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ServicePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_can_view_but_only_administrator_can_manage_services(): void
    {
        $service = Service::factory()->create();

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Service::class));
            $this->assertTrue(Gate::forUser($user)->allows('view', $service));

            $expected = $role === UserRole::Administrator;
            $this->assertSame($expected, Gate::forUser($user)->allows('create', Service::class));
            $this->assertSame($expected, Gate::forUser($user)->allows('update', $service));
            $this->assertSame($expected, Gate::forUser($user)->allows('changeStatus', $service));
        }
    }

    public function test_service_routes_require_login_and_protect_management(): void
    {
        $service = Service::factory()->create();
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->get(route('services.index'))->assertRedirect(route('login'));
        $this->actingAs($receptionist)->get(route('services.index'))->assertOk();
        $this->actingAs($receptionist)->get(route('services.show', $service))->assertOk();
        $this->actingAs($receptionist)->get(route('services.create'))->assertForbidden();
        $this->actingAs($receptionist)->get(route('services.edit', $service))->assertForbidden();
    }
}
