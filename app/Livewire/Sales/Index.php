<?php

namespace App\Livewire\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\SaleAdjustmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $paymentFilter = '';

    public bool $showAdjustmentModal = false;

    public ?int $adjustmentSaleId = null;

    public string $adjustmentType = 'cancel';

    public string $adjustment_reason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Sale::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'dateFrom', 'dateTo', 'statusFilter', 'paymentFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo', 'statusFilter', 'paymentFilter']);
        $this->resetPage();
    }

    public function openAdjustment(int $saleId, string $type): void
    {
        $sale = Sale::query()->findOrFail($saleId);
        $ability = $type === 'refund' ? 'refund' : 'cancel';
        $this->authorize($ability, $sale);

        $this->resetValidation();
        $this->adjustmentSaleId = $sale->id;
        $this->adjustmentType = $ability;
        $this->adjustment_reason = '';
        $this->showAdjustmentModal = true;
    }

    public function closeAdjustment(): void
    {
        $this->showAdjustmentModal = false;
        $this->adjustmentSaleId = null;
        $this->resetValidation();
    }

    public function applyAdjustment(SaleAdjustmentService $adjustments): void
    {
        $sale = Sale::query()->findOrFail($this->adjustmentSaleId);
        $ability = $this->adjustmentType === 'refund' ? 'refund' : 'cancel';
        $this->authorize($ability, $sale);

        $data = $this->validate([
            'adjustment_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [], ['adjustment_reason' => 'motivo']);

        if ($ability === 'refund') {
            $adjustments->refund($sale, $data['adjustment_reason'], auth()->user());
            $message = 'Devolución registrada y movimiento de caja creado.';
        } else {
            $adjustments->cancel($sale, $data['adjustment_reason'], auth()->user());
            $message = 'Venta cancelada y movimiento de caja creado.';
        }

        $this->closeAdjustment();
        session()->flash('status', $message);
    }

    public function render(): View
    {
        $sales = Sale::query()
            ->with(['creator:id,name', 'ticketLogs:id,sale_id,action,created_at'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('folio', 'like', "%{$search}%")
                        ->orWhere('client_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('client_phone_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('paid_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('paid_at', '<=', $this->dateTo))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->paymentFilter !== '', fn (Builder $query) => $query->where('payment_method', $this->paymentFilter))
            ->latest('paid_at')
            ->paginate(15);

        return view('livewire.sales.index', [
            'sales' => $sales,
            'statuses' => SaleStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ])->layout('layouts.app');
    }
}
