<?php

namespace App\Livewire\Services;

use App\Livewire\Forms\ServiceForm;
use App\Models\Service;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ServiceForm $form;

    public function mount(): void
    {
        $this->authorize('create', Service::class);
    }

    public function save(): void
    {
        $this->authorize('create', Service::class);
        $service = $this->form->store();

        session()->flash('status', 'Servicio creado correctamente.');
        $this->redirectRoute('services.show', $service, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.services.create')->layout('layouts.app');
    }
}
