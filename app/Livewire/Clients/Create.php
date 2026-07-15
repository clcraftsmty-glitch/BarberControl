<?php

namespace App\Livewire\Clients;

use App\Enums\UserRole;
use App\Livewire\Forms\ClientForm;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ClientForm $form;

    public function mount(): void
    {
        $this->authorize('create', Client::class);
    }

    public function save(): void
    {
        $this->authorize('create', Client::class);

        $client = $this->form->store();

        session()->flash('status', 'Cliente creado correctamente.');
        $this->redirectRoute('clients.show', $client, navigate: true);
    }

    public function render(): View
    {
        $barbers = User::query()
            ->where('role', UserRole::Barber)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.clients.create', compact('barbers'))
            ->layout('layouts.app');
    }
}
