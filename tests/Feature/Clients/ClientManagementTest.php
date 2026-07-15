<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Livewire\Clients\Create;
use App\Livewire\Clients\Edit;
use App\Livewire\Clients\Index;
use App\Livewire\Clients\Show;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_create_a_client_with_optional_fields(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = User::factory()->create(['role' => UserRole::Barber]);

        $this->actingAs($receptionist);

        Livewire::test(Create::class)
            ->set('form.first_name', '  María ')
            ->set('form.last_name', ' López  ')
            ->set('form.phone', '55 1234 5678')
            ->set('form.email', 'MARIA@example.com')
            ->set('form.birth_date', '1992-05-20')
            ->set('form.preferred_barber_id', $barber->id)
            ->set('form.notes', ' Prefiere corte clásico. ')
            ->call('save')
            ->assertHasNoErrors();

        $client = Client::query()->firstOrFail();

        $this->assertSame('María', $client->first_name);
        $this->assertSame('López', $client->last_name);
        $this->assertSame('maria@example.com', $client->email);
        $this->assertSame($barber->id, $client->preferred_barber_id);
        $this->assertTrue($client->is_active);
    }

    public function test_client_fields_are_validated(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $notABarber = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.first_name', '')
            ->set('form.last_name', '')
            ->set('form.phone', 'abc')
            ->set('form.email', 'correo-invalido')
            ->set('form.birth_date', now()->addDay()->toDateString())
            ->set('form.preferred_barber_id', $notABarber->id)
            ->set('form.notes', str_repeat('x', 2001))
            ->call('save')
            ->assertHasErrors([
                'form.first_name' => 'required',
                'form.last_name' => 'required',
                'form.phone',
                'form.email' => 'email',
                'form.birth_date' => 'before_or_equal',
                'form.preferred_barber_id' => 'exists',
                'form.notes' => 'max',
            ]);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_email_must_be_unique_when_present(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        Client::factory()->create(['email' => 'cliente@example.com']);

        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.first_name', 'Otro')
            ->set('form.last_name', 'Cliente')
            ->set('form.phone', '5512345678')
            ->set('form.email', 'cliente@example.com')
            ->call('save')
            ->assertHasErrors(['form.email' => 'unique']);
    }

    public function test_receptionist_can_edit_a_client(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->create(['first_name' => 'Nombre anterior']);

        $this->actingAs($receptionist);

        Livewire::test(Edit::class, ['client' => $client])
            ->set('form.first_name', 'Nombre nuevo')
            ->set('form.phone', '+52 55 9999 0000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'first_name' => 'Nombre nuevo',
            'phone' => '+52 55 9999 0000',
        ]);
    }

    public function test_client_detail_displays_all_information(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);
        $preferredBarber = User::factory()->create([
            'name' => 'Carlos Barbero',
            'role' => UserRole::Barber,
        ]);
        $client = Client::factory()->create([
            'first_name' => 'Lucía',
            'last_name' => 'García',
            'phone' => '5587654321',
            'email' => 'lucia@example.com',
            'preferred_barber_id' => $preferredBarber->id,
            'notes' => 'Cliente frecuente',
        ]);

        $this->actingAs($user);

        Livewire::test(Show::class, ['client' => $client])
            ->assertSee('Lucía García')
            ->assertSee('5587654321')
            ->assertSee('lucia@example.com')
            ->assertSee('Carlos Barbero')
            ->assertSee('Cliente frecuente');
    }

    public function test_clients_can_be_searched_by_full_name_or_phone(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);
        $target = Client::factory()->create([
            'first_name' => 'María',
            'last_name' => 'López',
            'phone' => '5512349876',
        ]);
        $other = Client::factory()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Gómez',
            'phone' => '5599990000',
        ]);

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->set('search', 'María 551234')
            ->assertSee($target->full_name)
            ->assertDontSee($other->full_name);
    }

    public function test_client_results_are_paginated_by_ten(): void
    {
        $user = User::factory()->create(['role' => UserRole::Barber]);

        foreach (range(1, 11) as $number) {
            Client::factory()->create([
                'first_name' => 'Cliente',
                'last_name' => sprintf('Apellido %02d', $number),
                'email' => null,
            ]);
        }

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->assertViewHas('clients', fn (LengthAwarePaginator $clients): bool => $clients->count() === 10 && $clients->total() === 11)
            ->call('gotoPage', 2)
            ->assertViewHas('clients', fn (LengthAwarePaginator $clients): bool => $clients->count() === 1 && $clients->currentPage() === 2);
    }

    public function test_administrator_can_deactivate_a_client_without_deleting_it(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->create();

        $this->actingAs($administrator);

        Livewire::test(Index::class)
            ->call('deactivate', $client)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('clients', 1);
    }
}
