<?php

namespace App\Livewire\Barbers;

use App\Enums\UserRole;
use App\Livewire\Forms\BarberForm;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public BarberForm $form;

    public function mount(Barber $barber): void
    {
        $this->authorize('update', $barber);
        $this->form->setBarber($barber);
    }

    public function save(): void
    {
        $this->authorize('update', $this->form->barber);
        $barber = $this->form->update();

        session()->flash('status', 'Barbero actualizado correctamente.');
        $this->redirectRoute('barbers.show', $barber, navigate: true);
    }

    public function render(): View
    {
        $currentUserId = $this->form->barber->user_id;
        $users = User::query()->where('role', UserRole::Barber)->where(fn ($query) => $query->whereDoesntHave('barberProfile')->orWhere('id', $currentUserId))->orderBy('name')->get(['id', 'name', 'email']);
        $selectedServices = $this->form->service_ids;
        $services = Service::query()->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $selectedServices))->orderBy('name')->get(['id', 'name']);

        return view('livewire.barbers.edit', [
            'users' => $users,
            'services' => $services,
            'creating' => false,
        ])->layout('layouts.app');
    }
}
