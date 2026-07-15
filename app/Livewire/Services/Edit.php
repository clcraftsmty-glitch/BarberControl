<?php

namespace App\Livewire\Services;

use App\Livewire\Forms\ServiceForm;
use App\Models\Service;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public ServiceForm $form;

    public function mount(Service $service): void
    {
        $this->authorize('update', $service);
        $this->form->setService($service);
    }

    public function save(): void
    {
        $this->authorize('update', $this->form->service);
        $service = $this->form->update();

        session()->flash('status', 'Servicio actualizado correctamente.');
        $this->redirectRoute('services.show', $service, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.services.edit')->layout('layouts.app');
    }
}
