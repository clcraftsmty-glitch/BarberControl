<?php

namespace App\Livewire\Sales;

use App\Models\Sale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;

    public Sale $sale;

    public function mount(Sale $sale): void
    {
        $this->authorize('view', $sale);
        $this->sale = $sale;
    }

    public function render(): View
    {
        $this->sale->load([
            'creator:id,name',
            'canceller:id,name',
            'refunder:id,name',
            'cashMovements.creator:id,name',
            'commission',
            'ticketLogs.creator:id,name',
        ]);

        return view('livewire.sales.show')->layout('layouts.app');
    }
}
