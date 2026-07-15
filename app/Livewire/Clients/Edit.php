<?php

namespace App\Livewire\Clients;

use App\Enums\UserRole;
use App\Livewire\Forms\ClientForm;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public ClientForm $form;

    public function mount(Client $client): void
    {
        $this->authorize('update', $client);
        $this->form->setClient($client);
    }

    public function save(): void
    {
        $this->authorize('update', $this->form->client);

        $client = $this->form->update();

        session()->flash('status', 'Cliente actualizado correctamente.');
        $this->redirectRoute('clients.show', $client, navigate: true);
    }

    public function render(): View
    {
        $barbers = User::query()
            ->where('role', UserRole::Barber)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.clients.edit', compact('barbers'))
            ->layout('layouts.app');
    }
}
