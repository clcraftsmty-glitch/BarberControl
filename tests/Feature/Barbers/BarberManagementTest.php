<?php

namespace Tests\Feature\Barbers;

use App\Enums\UserRole;
use App\Livewire\Barbers\Create;
use App\Livewire\Barbers\Edit;
use App\Livewire\Barbers\Index;
use App\Livewire\Barbers\Show;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class BarberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_barber_with_schedule_and_services(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $user = User::factory()->create(['role' => UserRole::Barber]);
        $services = Service::factory()->count(2)->create();
        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.user_mode', 'existing')
            ->set('form.user_id', $user->id)
            ->set('form.display_name', ' Alex Barber ')
            ->set('form.phone', '55 1234 5678')
            ->set('form.default_commission_percentage', '35.50')
            ->set('form.service_ids', $services->pluck('id')->all())
            ->call('save')
            ->assertHasNoErrors();

        $barber = Barber::query()->firstOrFail();
        $this->assertSame('Alex Barber', $barber->display_name);
        $this->assertSame($user->id, $barber->user_id);
        $this->assertCount(2, $barber->services);
        $this->assertTrue($barber->work_schedule['monday']['enabled']);
    }

    public function test_administrator_can_create_barber_account_and_profile_in_same_form(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $service = Service::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.user_mode', 'new')
            ->set('form.user_name', 'Carlos Nava')
            ->set('form.user_email', 'CARLOS@BARBERCONTROL.LOCAL')
            ->set('form.user_password', 'ClaveSegura123!')
            ->set('form.user_password_confirmation', 'ClaveSegura123!')
            ->set('form.display_name', 'Carlos')
            ->set('form.phone', '5512345678')
            ->set('form.default_commission_percentage', 40)
            ->set('form.service_ids', [$service->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'carlos@barbercontrol.local')->firstOrFail();
        $barber = Barber::query()->firstOrFail();

        $this->assertSame(UserRole::Barber, $user->role);
        $this->assertTrue(Hash::check('ClaveSegura123!', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($user->id, $barber->user_id);
        $this->assertSame('Carlos', $barber->display_name);
    }

    public function test_new_barber_account_fields_are_validated(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $service = Service::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.user_mode', 'new')
            ->set('form.user_name', '')
            ->set('form.user_email', 'correo-invalido')
            ->set('form.user_password', 'corta')
            ->set('form.user_password_confirmation', 'diferente')
            ->set('form.display_name', 'Carlos')
            ->set('form.phone', '5512345678')
            ->set('form.service_ids', [$service->id])
            ->call('save')
            ->assertHasErrors(['form.user_name', 'form.user_email', 'form.user_password']);

        $this->assertDatabaseCount('barbers', 0);
    }

    public function test_user_commission_services_and_schedule_are_validated(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.user_mode', 'existing')
            ->set('form.user_id', $receptionist->id)
            ->set('form.display_name', '')
            ->set('form.phone', 'abc')
            ->set('form.default_commission_percentage', 101)
            ->set('form.service_ids', [])
            ->set('form.work_schedule.monday.start', '18:00')
            ->set('form.work_schedule.monday.end', '09:00')
            ->call('save')
            ->assertHasErrors(['form.user_id', 'form.display_name', 'form.phone', 'form.default_commission_percentage', 'form.service_ids']);

        $barberUser = User::factory()->create(['role' => UserRole::Barber]);
        $service = Service::factory()->create();

        Livewire::test(Create::class)
            ->set('form.user_mode', 'existing')
            ->set('form.user_id', $barberUser->id)
            ->set('form.display_name', 'Barbero válido')
            ->set('form.phone', '5512345678')
            ->set('form.default_commission_percentage', 25)
            ->set('form.service_ids', [$service->id])
            ->set('form.work_schedule.monday.start', '18:00')
            ->set('form.work_schedule.monday.end', '09:00')
            ->call('save')
            ->assertHasErrors(['form.work_schedule.monday.end']);
    }

    public function test_one_user_cannot_have_two_barber_profiles(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $existing = Barber::factory()->create();
        $service = Service::factory()->create();
        $this->actingAs($administrator);

        Livewire::test(Create::class)
            ->set('form.user_mode', 'existing')
            ->set('form.user_id', $existing->user_id)
            ->set('form.display_name', 'Duplicado')
            ->set('form.phone', '5512345678')
            ->set('form.default_commission_percentage', 20)
            ->set('form.service_ids', [$service->id])
            ->call('save')
            ->assertHasErrors(['form.user_id' => 'unique']);
    }

    public function test_administrator_can_edit_sync_services_and_change_status(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $barber = Barber::factory()->create();
        $oldService = Service::factory()->create();
        $newService = Service::factory()->create();
        $barber->services()->attach($oldService);
        $this->actingAs($administrator);

        Livewire::test(Edit::class, ['barber' => $barber])
            ->set('form.display_name', 'Nombre actualizado')
            ->set('form.service_ids', [$newService->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$newService->id], $barber->services()->pluck('services.id')->all());
        Livewire::test(Show::class, ['barber' => $barber->refresh()])->call('changeStatus')->assertHasNoErrors();
        $this->assertFalse($barber->refresh()->is_active);
    }

    public function test_barbers_can_be_searched_filtered_and_paginated(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Receptionist]);
        Barber::factory()->create(['display_name' => 'Barbero especial', 'phone' => '5511122233']);
        Barber::factory()->inactive()->create(['display_name' => 'Oculto']);
        Barber::factory()->count(10)->create();
        $this->actingAs($viewer);

        Livewire::test(Index::class)
            ->set('search', '551112')
            ->assertSee('Barbero especial')
            ->assertDontSee('Oculto')
            ->set('search', '')
            ->set('status', 'active')
            ->assertViewHas('barbers', fn (LengthAwarePaginator $barbers): bool => $barbers->total() === 11 && $barbers->count() === 10)
            ->call('gotoPage', 2)
            ->assertViewHas('barbers', fn (LengthAwarePaginator $barbers): bool => $barbers->currentPage() === 2 && $barbers->count() === 1);
    }
}
