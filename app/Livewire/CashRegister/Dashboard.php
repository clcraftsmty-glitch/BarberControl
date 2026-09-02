<?php

namespace App\Livewire\CashRegister;

use App\Enums\AppointmentStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserPermission;
use App\Livewire\Sales\ReceiptDelivery;
use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Services\AppointmentPaymentService;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use AuthorizesRequests, WithPagination;

    public bool $showOpenModal = false;

    public string $opening_amount = '0.00';

    public ?string $opening_notes = null;

    public bool $showMovementModal = false;

    public string $movement_type = 'ingreso';

    public string $movement_amount = '';

    public string $movement_category = CashMovementCategory::ManualIncome->value;

    public string $movement_description = '';

    public bool $showCloseModal = false;

    public string $actual_cash = '';

    public ?string $closing_notes = null;

    public ?string $difference_reason = null;

    public bool $showPaymentModal = false;

    public ?int $paymentAppointmentId = null;

    public string $payment_method = 'efectivo';

    public ?string $payment_reference = null;

    public function mount(): void
    {
        $this->authorize('viewAny', CashRegisterSession::class);
    }

    public function openRegisterModal(): void
    {
        $this->authorize('create', CashRegisterSession::class);
        $this->resetValidation();
        $this->opening_amount = '0.00';
        $this->opening_notes = null;
        $this->showOpenModal = true;
    }

    public function openRegister(CashRegisterService $cashRegister): void
    {
        $this->authorize('create', CashRegisterSession::class);
        $data = $this->validate([
            'opening_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'opening_amount' => 'fondo inicial',
            'opening_notes' => 'notas',
        ]);

        $cashRegister->open((float) $data['opening_amount'], $data['opening_notes'], auth()->user());
        $this->showOpenModal = false;
        session()->flash('status', 'Caja abierta correctamente. Ya puedes registrar movimientos y cobros.');
    }

    public function openMovementModal(string $type): void
    {
        $session = $this->currentSession();
        abort_unless($session, 404);
        $this->authorize('adjust', $session);

        $validated = validator(
            ['type' => $type],
            ['type' => ['required', Rule::in(['ingreso', 'gasto'])]],
        )->validate();

        $this->resetValidation();
        $this->movement_type = $validated['type'];
        $this->movement_category = $validated['type'] === 'ingreso'
            ? CashMovementCategory::ManualIncome->value
            : CashMovementCategory::OperatingExpense->value;
        $this->movement_amount = '';
        $this->movement_description = '';
        $this->showMovementModal = true;
    }

    public function recordMovement(CashRegisterService $cashRegister): void
    {
        $session = $this->currentSession();
        abort_unless($session, 404);
        $this->authorize('adjust', $session);

        $data = $this->validate([
            'movement_type' => ['required', Rule::in(['ingreso', 'gasto'])],
            'movement_category' => ['required', Rule::enum(CashMovementCategory::class)],
            'movement_amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'movement_description' => ['required', 'string', 'max:255'],
        ], [], [
            'movement_type' => 'tipo',
            'movement_category' => 'categoría',
            'movement_amount' => 'importe',
            'movement_description' => 'concepto',
        ]);

        $cashRegister->recordMovement(
            $session,
            $data['movement_type'],
            (float) $data['movement_amount'],
            $data['movement_description'],
            auth()->user(),
            $data['movement_category'],
        );

        $this->showMovementModal = false;
        session()->flash('status', ucfirst($data['movement_type']).' registrado correctamente.');
    }

    public function openCloseModal(): void
    {
        $session = $this->currentSession();
        abort_unless($session, 404);
        $this->authorize('update', $session);

        $this->resetValidation();
        $this->actual_cash = number_format($session->expectedCashNow(), 2, '.', '');
        $this->closing_notes = null;
        $this->difference_reason = null;
        $this->showCloseModal = true;
    }

    public function closeRegister(CashRegisterService $cashRegister): void
    {
        $session = $this->currentSession();
        abort_unless($session, 404);
        $this->authorize('update', $session);

        $data = $this->validate([
            'actual_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
            'difference_reason' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'actual_cash' => 'efectivo real',
            'closing_notes' => 'notas',
            'difference_reason' => 'motivo de la diferencia',
        ]);

        $closed = $cashRegister->close(
            $session,
            (float) $data['actual_cash'],
            $data['closing_notes'],
            auth()->user(),
            $data['difference_reason'],
        );

        $this->showCloseModal = false;
        $difference = (float) $closed->difference;
        $formattedDifference = ($difference > 0 ? '+' : ($difference < 0 ? '-' : '')).'$'.number_format(abs($difference), 2);
        session()->flash(
            'status',
            'Caja cerrada. Diferencia: '.$formattedDifference,
        );
    }

    public function openPayment(int $appointmentId): void
    {
        $session = $this->currentSession();
        abort_unless($session, 404);
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('registerPayment', $appointment);

        if ($appointment->status !== AppointmentStatus::PendingPayment) {
            $this->addError('payment', 'La cita ya no está pendiente de cobro.');

            return;
        }

        $this->resetValidation();
        $this->paymentAppointmentId = $appointment->id;
        $this->payment_method = PaymentMethod::Cash->value;
        $this->payment_reference = null;
        $this->showPaymentModal = true;
    }

    public function registerPayment(AppointmentPaymentService $payments): void
    {
        $appointment = Appointment::query()->findOrFail($this->paymentAppointmentId);
        $this->authorize('registerPayment', $appointment);

        $data = $this->validate([
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ], [], [
            'payment_method' => 'método de pago',
            'payment_reference' => 'referencia',
        ]);

        $sale = $payments->register($appointment, $data, auth()->user());
        $this->showPaymentModal = false;
        $this->paymentAppointmentId = null;
        $this->dispatch('sale-paid', saleId: $sale->id)->to(ReceiptDelivery::class);
        session()->flash('status', 'Servicio cobrado y cita terminada correctamente.');
        session()->flash('sale_id', $sale->id);
    }

    public function closeModal(string $modal): void
    {
        if (in_array($modal, ['showOpenModal', 'showMovementModal', 'showCloseModal', 'showPaymentModal'], true)) {
            $this->{$modal} = false;
        }

        $this->resetValidation();
    }

    public function render(): View
    {
        $session = CashRegisterSession::query()
            ->with([
                'opener:id,name',
                'movements' => fn ($query) => $query
                    ->with(['creator:id,name', 'sale:id,appointment_id'])
                    ->latest('occurred_at'),
            ])
            ->where('status', CashRegisterStatus::Open->value)
            ->first();

        $expectedCash = $session?->expectedCashNow() ?? 0;
        $income = $session ? (float) $session->movements->where('type', 'ingreso')->sum('amount') : 0;
        $expenses = $session ? (float) $session->movements->where('type', 'gasto')->sum('amount') : 0;
        $paymentBreakdown = collect(PaymentMethod::cases())->mapWithKeys(function (PaymentMethod $method) use ($session): array {
            $movements = $session?->movements->where('payment_method', $method) ?? collect();
            $income = (float) $movements->where('type', 'ingreso')->sum('amount');
            $expenses = (float) $movements->where('type', 'gasto')->sum('amount');

            return [$method->value => ['income' => $income, 'expenses' => $expenses, 'net' => $income - $expenses]];
        });

        $categoryBreakdown = $session
            ? $session->movements
                ->groupBy(fn ($movement) => $movement->type.'|'.$movement->category->value)
                ->map(fn ($movements) => [
                    'type' => $movements->first()->type,
                    'category' => $movements->first()->category,
                    'total' => (float) $movements->sum('amount'),
                ])->values()
            : collect();

        $pendingAppointments = Appointment::query()
            ->with(['client:id,first_name,last_name,phone', 'barber:id,display_name', 'service:id,name'])
            ->where('status', AppointmentStatus::PendingPayment->value)
            ->orderBy('starts_at')
            ->get();

        $sessionHistory = CashRegisterSession::query()
            ->with(['opener:id,name', 'closer:id,name', 'differenceAuthorizer:id,name'])
            ->withCount('movements')
            ->latest('opened_at')
            ->paginate(10, pageName: 'historyPage');

        $unassignedMovements = CashMovement::query()
            ->with('creator:id,name')
            ->whereNull('cash_register_session_id')
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        $paymentAppointment = $this->paymentAppointmentId
            ? $pendingAppointments->firstWhere('id', $this->paymentAppointmentId)
            : null;

        return view('livewire.cash-register.dashboard', [
            'session' => $session,
            'expectedCash' => $expectedCash,
            'income' => $income,
            'expenses' => $expenses,
            'paymentBreakdown' => $paymentBreakdown,
            'categoryBreakdown' => $categoryBreakdown,
            'pendingAppointments' => $pendingAppointments,
            'sessionHistory' => $sessionHistory,
            'unassignedMovements' => $unassignedMovements,
            'paymentMethods' => PaymentMethod::cases(),
            'paymentAppointment' => $paymentAppointment,
            'movementCategories' => CashMovementCategory::manualFor(
                $this->movement_type,
                auth()->user()->hasPermission(UserPermission::AdjustCash),
            ),
        ])->layout('layouts.app');
    }

    private function currentSession(): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('status', CashRegisterStatus::Open->value)
            ->first();
    }
}
