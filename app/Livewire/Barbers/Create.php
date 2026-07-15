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

class Create extends Component
{
    use AuthorizesRequests;

    public BarberForm $form;

    public function mount(): void
    {
        $this->authorize('create', Barber::class);
        $this->form->setDefaults();
    }

    public function save(): void
    {
        $this->authorize('create', Barber::class);
        $barber = $this->form->store();

        session()->flash('status', 'Barbero creado correctamente.');
        $this->redirectRoute('barbers.show', $barber, navigate: true);
    }

    public function render(): View
    {
        $users = User::query()->where('role', UserRole::Barber)->whereDoesntHave('barberProfile')->orderBy('name')->get(['id', 'name', 'email']);
        $services = Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('livewire.barbers.create', [
            'users' => $users,
            'services' => $services,
            'creating' => true,
        ])->layout('layouts.app');
    }
}
