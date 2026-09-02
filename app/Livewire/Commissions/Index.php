<?php

namespace App\Livewire\Commissions;

use App\Enums\CommissionAdjustmentStatus;
use App\Enums\CommissionAdjustmentType;
use App\Enums\CommissionPeriod;
use App\Enums\CommissionStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\CommissionSettlement;
use App\Services\CommissionSettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $periodType = 'semanal';

    public string $periodStart = '';

    public string $periodEnd = '';

    public bool $showSettlementModal = false;

    public ?int $selectedBarberId = null;

    public string $paymentMethod = 'transferencia';

    public ?string $paymentReference = null;

    public ?string $settlementNotes = null;

    public bool $showAdjustmentModal = false;

    public ?int $adjustmentBarberId = null;

    public string $adjustmentType = 'bono';

    public string $adjustmentAmount = '';

    public string $adjustmentReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CommissionSettlement::class);
        $this->setPeriodFor(CarbonImmutable::today());
    }

    public function updatedPeriodType(): void
    {
        $this->periodType = CommissionPeriod::tryFrom($this->periodType)?->value ?? CommissionPeriod::Weekly->value;
        $this->setPeriodFor(CarbonImmutable::today());
    }

    public function previousPeriod(): void
    {
        $this->setPeriodFor(CarbonImmutable::parse($this->periodStart)->subDay());
    }

    public function nextPeriod(): void
    {
        $this->setPeriodFor(CarbonImmutable::parse($this->periodEnd)->addDay());
    }

    public function currentPeriod(): void
    {
        $this->setPeriodFor(CarbonImmutable::today());
    }

    public function openSettlement(int $barberId): void
    {
        $this->authorize('create', CommissionSettlement::class);
        $this->selectedBarberId = Barber::query()->findOrFail($barberId)->id;
        $this->paymentMethod = PaymentMethod::Transfer->value;
        $this->paymentReference = null;
        $this->settlementNotes = null;
        $this->resetValidation();
        $this->showSettlementModal = true;
    }

    public function closeSettlement(): void
    {
        $this->showSettlementModal = false;
        $this->selectedBarberId = null;
        $this->resetValidation();
    }

    public function settle(CommissionSettlementService $service): void
    {
        $this->authorize('create', CommissionSettlement::class);
        $data = $this->validate([
            'selectedBarberId' => ['required', 'exists:barbers,id'],
            'periodType' => ['required', Rule::enum(CommissionPeriod::class)],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'paymentMethod' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Transfer->value])],
            'paymentReference' => ['nullable', 'string', 'max:120'],
            'settlementNotes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'selectedBarberId' => 'barbero',
            'periodType' => 'periodicidad',
            'periodStart' => 'inicio del periodo',
            'periodEnd' => 'fin del periodo',
            'paymentMethod' => 'forma de pago',
            'paymentReference' => 'referencia',
            'settlementNotes' => 'notas',
        ]);

        $settlement = $service->settle(
            Barber::query()->findOrFail($data['selectedBarberId']),
            CommissionPeriod::from($data['periodType']),
            CarbonImmutable::parse($data['periodStart'])->startOfDay(),
            CarbonImmutable::parse($data['periodEnd'])->startOfDay(),
            PaymentMethod::from($data['paymentMethod']),
            $data['paymentReference'],
            $data['settlementNotes'],
            auth()->user(),
        );

        $this->closeSettlement();
        session()->flash('status', "Liquidación {$settlement->folio} registrada correctamente.");
        session()->flash('settlement_id', $settlement->id);
    }

    public function openAdjustment(int $barberId): void
    {
        $this->authorize('adjust', CommissionSettlement::class);
        $this->adjustmentBarberId = Barber::query()->findOrFail($barberId)->id;
        $this->adjustmentType = CommissionAdjustmentType::Credit->value;
        $this->adjustmentAmount = '';
        $this->adjustmentReason = '';
        $this->resetValidation();
        $this->showAdjustmentModal = true;
    }

    public function closeAdjustment(): void
    {
        $this->showAdjustmentModal = false;
        $this->adjustmentBarberId = null;
        $this->resetValidation();
    }

    public function saveAdjustment(CommissionSettlementService $service): void
    {
        $this->authorize('adjust', CommissionSettlement::class);
        $data = $this->validate([
            'adjustmentBarberId' => ['required', 'exists:barbers,id'],
            'adjustmentType' => ['required', Rule::enum(CommissionAdjustmentType::class)],
            'adjustmentAmount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'adjustmentReason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], [
            'adjustmentBarberId' => 'barbero',
            'adjustmentType' => 'tipo',
            'adjustmentAmount' => 'importe',
            'adjustmentReason' => 'motivo',
        ]);

        $service->createAdjustment(
            Barber::query()->findOrFail($data['adjustmentBarberId']),
            CommissionAdjustmentType::from($data['adjustmentType']),
            (float) $data['adjustmentAmount'],
            $data['adjustmentReason'],
            auth()->user(),
        );

        $this->closeAdjustment();
        session()->flash('status', 'Ajuste autorizado y agregado a la siguiente liquidación.');
    }

    public function render(): View
    {
        $userBarberId = auth()->user()->role === UserRole::Barber
            ? auth()->user()->barberProfile?->id
            : null;
        $periodStart = CarbonImmutable::parse($this->periodStart)->startOfDay();
        $periodEnd = CarbonImmutable::parse($this->periodEnd)->endOfDay();

        $barbers = Barber::query()
            ->with(['commissions' => fn ($query) => $query
                ->with('sale:id,folio,paid_at,service_name_snapshot,unit_price_snapshot')
                ->where('status', CommissionStatus::Pending->value)
                ->whereHas('sale', fn ($query) => $query
                    ->whereBetween('paid_at', [$periodStart, $periodEnd]))
                ->orderBy('id'),
                'commissionAdjustments' => fn ($query) => $query
                    ->with('authorizer:id,name')
                    ->where('status', CommissionAdjustmentStatus::Pending->value)
                    ->orderBy('id')])
            ->when($userBarberId, fn ($query) => $query->whereKey($userBarberId))
            ->orderBy('display_name')
            ->get()
            ->filter(fn (Barber $barber) => $barber->commissions->isNotEmpty() || $barber->commissionAdjustments->isNotEmpty())
            ->values();

        $selectedBarber = $this->selectedBarberId
            ? $barbers->firstWhere('id', $this->selectedBarberId)
            : null;
        $history = CommissionSettlement::query()
            ->with(['barber:id,display_name', 'creator:id,name'])
            ->withCount(['commissions', 'adjustments'])
            ->when($userBarberId, fn ($query) => $query->where('barber_id', $userBarberId))
            ->latest('paid_at')
            ->paginate(12, pageName: 'historyPage');

        return view('livewire.commissions.index', [
            'barbers' => $barbers,
            'selectedBarber' => $selectedBarber,
            'history' => $history,
            'periods' => CommissionPeriod::cases(),
            'adjustmentTypes' => CommissionAdjustmentType::cases(),
            'paymentMethods' => [PaymentMethod::Transfer, PaymentMethod::Cash],
        ])->layout('layouts.app');
    }

    private function setPeriodFor(CarbonImmutable $date): void
    {
        $period = CommissionPeriod::from($this->periodType);

        if ($period === CommissionPeriod::Weekly) {
            $start = $date->startOfWeek();
            $end = $date->endOfWeek();
        } elseif ($date->day <= 15) {
            $start = $date->startOfMonth();
            $end = $date->startOfMonth()->day(15);
        } else {
            $start = $date->startOfMonth()->day(16);
            $end = $date->endOfMonth();
        }

        $this->periodStart = $start->toDateString();
        $this->periodEnd = $end->toDateString();
        $this->resetPage('historyPage');
    }
}
