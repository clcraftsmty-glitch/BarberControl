<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Livewire\Services\Create;
use App\Livewire\Services\Edit;
use App\Livewire\Services\Index;
use App\Livewire\Services\Show;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_service(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));

        Livewire::test(Create::class)
            ->set('form.name', ' Corte clásico ')
            ->set('form.description', ' Corte tradicional con tijera. ')
            ->set('form.duration_minutes', 45)
            ->set('form.price', '350.50')
            ->set('form.commission_percentage', '30.25')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Corte clásico',
            'duration_minutes' => 45,
            'price' => 350.50,
            'commission_percentage' => 30.25,
            'is_active' => true,
        ]);
    }

    public function test_service_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));

        Livewire::test(Create::class)
            ->set('form.name', '')
            ->set('form.description', '')
            ->set('form.duration_minutes', 0)
            ->set('form.price', -1)
            ->set('form.commission_percentage', 101)
            ->call('save')
            ->assertHasErrors([
                'form.name' => 'required',
                'form.description' => 'required',
                'form.duration_minutes' => 'min',
                'form.price' => 'min',
                'form.commission_percentage' => 'max',
            ]);
    }

    public function test_service_name_must_be_unique(): void
    {
        Service::factory()->create(['name' => 'Barba premium']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Administrator]));

        Livewire::test(Create::class)
            ->set('form.name', 'Barba premium')
            ->set('form.description', 'Descripción')
            ->set('form.duration_minutes', 30)
            ->set('form.price', 200)
            ->set('form.commission_percentage', 20)
            ->call('save')
            ->assertHasErrors(['form.name' => 'unique']);
    }

    public function test_administrator_can_edit_and_view_a_service(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $service = Service::factory()->create(['name' => 'Corte básico']);
        $this->actingAs($administrator);

        Livewire::test(Edit::class, ['service' => $service])
            ->set('form.name', 'Corte ejecutivo')
            ->set('form.price', '450.00')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(Show::class, ['service' => $service->refresh()])
            ->assertSee('Corte ejecutivo')
            ->assertSee('$450.00');
    }

    public function test_services_can_be_searched_filtered_and_paginated(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);
        Service::factory()->create(['name' => 'Corte especial', 'description' => 'Con tijera']);
        Service::factory()->inactive()->create(['name' => 'Servicio oculto']);

        foreach (range(1, 10) as $number) {
            Service::factory()->create(['name' => sprintf('Servicio %02d', $number)]);
        }

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->set('search', 'tijera')
            ->assertSee('Corte especial')
            ->assertDontSee('Servicio oculto')
            ->set('search', '')
            ->set('status', 'active')
            ->assertViewHas('services', fn (LengthAwarePaginator $services): bool => $services->total() === 11 && $services->count() === 10)
            ->call('gotoPage', 2)
            ->assertViewHas('services', fn (LengthAwarePaginator $services): bool => $services->currentPage() === 2 && $services->count() === 1);
    }

    public function test_administrator_can_deactivate_and_reactivate_a_service(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $service = Service::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(Index::class)->call('changeStatus', $service)->assertHasNoErrors();
        $this->assertFalse($service->refresh()->is_active);

        Livewire::test(Show::class, ['service' => $service])->call('changeStatus')->assertHasNoErrors();
        $this->assertTrue($service->refresh()->is_active);
    }
}
