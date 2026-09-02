<?php

namespace App\Livewire\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WalkInStatus;
use App\Livewire\Sales\ReceiptDelivery;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\Service;
use App\Models\WalkInEntry;
use App\Services\AppointmentPaymentService;
use App\Services\AppointmentWorkflow;
use App\Services\WalkInQueueService;
use App\Services\WalkInWaitEstimator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Today extends Component
{
    use AuthorizesRequests;

    public string $barberFilter = '';

    public string $groupFilter = 'all';

    public string $selectedDate = '';

    public bool $showPaymentModal = false;

    public ?int $paymentAppointmentId = null;

    public string $payment_method = 'efectivo';

    public ?string $payment_reference = null;

    public bool $showWalkInModal = false;

    public bool $walkInCreateClient = false;

    public string $walkInClientSearch = '';

    public string $walkInClientId = '';

    public string $walkInFirstName = '';

    public string $walkInLastName = '';

    public string $walkInPhone = '';

    public string $walkInServiceId = '';

    public string $walkInPreferredBarberId = '';

    public string $walkInNotes = '';

    /** @var array<int, string> */
    public array $walkInBarberSelections = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);
        $this->selectedDate = today()->toDateString();
        $requestedGroup = request()->query('group');

        if (in_array($requestedGroup, ['waiting', 'in_service', 'pending_payment', 'upcoming', 'finished'], true)) {
            $this->groupFilter = $requestedGroup;
        }

        if (auth()->user()->role === UserRole::Barber) {
            $this->barberFilter = (string) (auth()->user()->barberProfile?->id ?? '');
        }

        $this->walkInBarberSelections = WalkInEntry::query()
            ->where('status', WalkInStatus::Waiting->value)
            ->whereNotNull('preferred_barber_id')
            ->pluck('preferred_barber_id', 'id')
            ->map(fn ($barberId) => (string) $barberId)
            ->all();
    }

    public function previousDay(): void
    {
        $this->selectedDate = $this->selectedDay()->subDay()->toDateString();
        $this->resetDailyView();
    }

    public function nextDay(): void
    {
        $this->selectedDate = $this->selectedDay()->addDay()->toDateString();
        $this->resetDailyView();
    }

    public function goToday(): void
    {
        $this->selectedDate = today()->toDateString();
        $this->resetDailyView();
    }

    public function updatedSelectedDate(): void
    {
        $this->selectedDate = $this->selectedDay()->toDateString();
        $this->resetDailyView();
    }

    public function filterGroup(string $group): void
    {
        $allowedGroups = ['all', 'waiting', 'in_service', 'pending_payment', 'upcoming', 'finished'];

        if (! in_array($group, $allowedGroups, true)) {
            return;
        }

        $this->groupFilter = $this->groupFilter === $group && $group !== 'all' ? 'all' : $group;
    }

    public function openWalkIn(): void
    {
        $this->authorize('create', WalkInEntry::class);
        $this->resetWalkInForm();
        $this->showWalkInModal = true;
    }

    public function updatedWalkInClientSearch(): void
    {
        $this->walkInClientId = '';
        $this->resetErrorBag('walkInClientId');
        $phone = trim($this->walkInClientSearch);

        if ($phone === '') {
            return;
        }

        $client = Client::query()
            ->where('is_active', true)
            ->where('phone', $phone)
            ->first();

        if ($client && ! $client->walkInEntries()->where('status', WalkInStatus::Waiting->value)->exists()) {
            $this->walkInClientId = (string) $client->id;
        }
    }

    public function selectWalkInClient(int $clientId): void
    {
        $this->authorize('create', WalkInEntry::class);
        $client = Client::query()->where('is_active', true)->findOrFail($clientId);

        if ($client->walkInEntries()->where('status', WalkInStatus::Waiting->value)->exists()) {
            $this->addError('walkInClientId', 'Este cliente ya se encuentra en la fila de espera.');

            return;
        }

        $this->walkInClientId = (string) $client->id;
        $this->walkInClientSearch = "{$client->full_name} · {$client->phone}";
        $this->resetErrorBag('walkInClientId');
    }

    public function clearWalkInClient(): void
    {
        $this->walkInClientId = '';
        $this->walkInClientSearch = '';
        $this->resetErrorBag('walkInClientId');
    }

    public function closeWalkIn(): void
    {
        $this->showWalkInModal = false;
        $this->resetWalkInForm();
    }

    public function registerWalkIn(WalkInQueueService $queue): void
    {
        $this->authorize('create', WalkInEntry::class);

        $data = $this->validate([
            'walkInClientId' => [
                $this->walkInCreateClient ? 'nullable' : 'required',
                'nullable',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'walkInFirstName' => [$this->walkInCreateClient ? 'required' : 'nullable', 'string', 'max:100'],
            'walkInLastName' => [$this->walkInCreateClient ? 'required' : 'nullable', 'string', 'max:100'],
            'walkInPhone' => [$this->walkInCreateClient ? 'required' : 'nullable', 'string', 'max:25'],
            'walkInServiceId' => ['required', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'walkInPreferredBarberId' => ['nullable', Rule::exists('barbers', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'walkInNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'walkInClientId.required' => 'Busca y selecciona un cliente.',
            'walkInClientId.exists' => 'El cliente seleccionado no está disponible.',
            'walkInFirstName.required' => 'Escribe el nombre del cliente.',
            'walkInLastName.required' => 'Escribe los apellidos del cliente.',
            'walkInPhone.required' => 'Escribe el teléfono del cliente.',
            'walkInServiceId.required' => 'Selecciona el servicio solicitado.',
            'walkInServiceId.exists' => 'El servicio seleccionado no está disponible.',
            'walkInPreferredBarberId.exists' => 'El barbero seleccionado no está disponible.',
        ], [
            'walkInClientId' => 'cliente',
            'walkInFirstName' => 'nombre',
            'walkInLastName' => 'apellidos',
            'walkInPhone' => 'teléfono',
            'walkInServiceId' => 'servicio',
            'walkInPreferredBarberId' => 'barbero preferido',
            'walkInNotes' => 'notas',
        ]);

        $entry = $queue->register([
            'client_id' => $this->walkInCreateClient ? null : $data['walkInClientId'],
            'new_client' => [
                'first_name' => $data['walkInFirstName'] ?? null,
                'last_name' => $data['walkInLastName'] ?? null,
                'phone' => $data['walkInPhone'] ?? null,
            ],
            'service_id' => $data['walkInServiceId'],
            'preferred_barber_id' => $data['walkInPreferredBarberId'] ?: null,
            'notes' => $data['walkInNotes'] ?: null,
        ], auth()->user());

        if ($entry->preferred_barber_id) {
            $this->walkInBarberSelections[$entry->id] = (string) $entry->preferred_barber_id;
        }

        $this->closeWalkIn();
        session()->flash('status', 'Cliente agregado a la fila sin cita. El cronómetro de espera ya está activo.');
    }

    public function startWalkIn(int $entryId, WalkInQueueService $queue): void
    {
        $entry = WalkInEntry::query()->findOrFail($entryId);
        $this->authorize('start', $entry);

        $barberId = auth()->user()->role === UserRole::Barber
            ? auth()->user()->barberProfile?->id
            : ($this->walkInBarberSelections[$entryId] ?? $entry->preferred_barber_id);

        if (! $barberId) {
            $this->addError("walkInBarberSelections.{$entryId}", 'Selecciona el barbero que atenderá al cliente.');

            return;
        }

        $appointment = $queue->start($entry, Barber::query()->findOrFail($barberId), auth()->user());
        unset($this->walkInBarberSelections[$entryId]);
        session()->flash('status', "Servicio iniciado con {$appointment->barber->display_name}. La espera quedó registrada.");
    }

    public function markWalkInLeft(int $entryId, WalkInQueueService $queue): void
    {
        $entry = WalkInEntry::query()->findOrFail($entryId);
        $this->authorize('markLeft', $entry);
        $queue->markLeft($entry, auth()->user());
        unset($this->walkInBarberSelections[$entryId]);
        session()->flash('status', 'El cliente fue marcado como retirado de la fila.');
    }

    public function advance(int $appointmentId, AppointmentWorkflow $workflow): void
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('transition', $appointment);

        if ($appointment->status === AppointmentStatus::PendingPayment) {
            $this->openPayment($appointmentId);

            return;
        }

        $target = $appointment->status->nextOperationalStatus();

        if (! $target) {
            return;
        }

        $appointment = $workflow->transition($appointment, $target, auth()->user());

        if (
            $appointment->status === AppointmentStatus::PendingPayment
            && auth()->user()->can('registerPayment', $appointment)
        ) {
            $this->openPayment($appointment->id);
        }

        session()->flash('status', "Cita actualizada a {$appointment->status->label()}.");
    }

    public function exceptionalTransition(
        int $appointmentId,
        string $status,
        AppointmentWorkflow $workflow,
    ): void {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('manageException', $appointment);

        $validated = validator(
            ['status' => $status],
            ['status' => ['required', Rule::in([
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Rescheduled->value,
            ])]],
        )->validate();

        $target = AppointmentStatus::from($validated['status']);
        $appointment = $workflow->transition($appointment, $target, auth()->user());
        session()->flash('status', "Cita actualizada a {$appointment->status->label()}.");
    }

    public function forceStatus(
        int $appointmentId,
        string $status,
        AppointmentWorkflow $workflow,
    ): void {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('updateStatus', $appointment);

        $validated = validator(
            ['status' => $status],
            ['status' => ['required', Rule::enum(AppointmentStatus::class)]],
        )->validate();
        $target = AppointmentStatus::from($validated['status']);

        if ($target === AppointmentStatus::Completed && ! $appointment->sale()->exists()) {
            if ($appointment->status === AppointmentStatus::PendingPayment) {
                $this->openPayment($appointment->id);
            } else {
                $this->addError('status', 'Para terminar una cita, avanza primero hasta pendiente de cobro y registra el pago.');
            }

            return;
        }

        $appointment = $workflow->transition($appointment, $target, auth()->user(), true);
        session()->flash('status', "Estado administrativo actualizado a {$appointment->status->label()}.");
    }

    public function openPayment(int $appointmentId): void
    {
        if (! CashRegisterSession::query()->where('status', CashRegisterStatus::Open->value)->exists()) {
            $this->addError('payment', 'Debes abrir la caja antes de registrar el cobro.');

            return;
        }

        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('registerPayment', $appointment);

        if ($appointment->status !== AppointmentStatus::PendingPayment) {
            $this->addError('payment', 'La cita aún no está pendiente de cobro.');

            return;
        }

        $this->resetValidation();
        $this->paymentAppointmentId = $appointment->id;
        $this->payment_method = PaymentMethod::Cash->value;
        $this->payment_reference = null;
        $this->showPaymentModal = true;
    }

    public function closePayment(): void
    {
        $this->showPaymentModal = false;
        $this->paymentAppointmentId = null;
        $this->resetValidation();
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
        $this->closePayment();
        $this->dispatch('sale-paid', saleId: $sale->id)->to(ReceiptDelivery::class);
        session()->flash('status', 'Pago registrado. Se crearon la venta, el movimiento de caja y la comisión.');
        session()->flash('sale_id', $sale->id);
    }

    private function resetWalkInForm(): void
    {
        $this->reset([
            'walkInCreateClient',
            'walkInClientSearch',
            'walkInClientId',
            'walkInFirstName',
            'walkInLastName',
            'walkInPhone',
            'walkInServiceId',
            'walkInPreferredBarberId',
            'walkInNotes',
        ]);
        $this->resetValidation();
    }

    private function selectedDay(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->selectedDate)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    private function resetDailyView(): void
    {
        $this->groupFilter = 'all';
        $this->showPaymentModal = false;
        $this->paymentAppointmentId = null;
        $this->showWalkInModal = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $viewDate = $this->selectedDay();
        $isSelectedToday = $viewDate->isToday();
        $currentBarberId = auth()->user()->role === UserRole::Barber
            ? auth()->user()->barberProfile?->id
            : null;
        $selectedBarberId = $currentBarberId ?: (filled($this->barberFilter) ? (int) $this->barberFilter : null);

        $barbers = Barber::query()
            ->where('is_active', true)
            ->when($currentBarberId, fn ($query) => $query->whereKey($currentBarberId))
            ->orderBy('display_name')
            ->get();

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price']);

        $walkInPreferredBarbers = Barber::query()
            ->where('is_active', true)
            ->when($this->walkInServiceId, fn ($query) => $query->whereHas(
                'services',
                fn ($query) => $query->whereKey($this->walkInServiceId),
            ))
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        $walkInClients = Client::query()
            ->where('is_active', true)
            ->withExists(['walkInEntries as is_waiting_in_queue' => fn ($query) => $query
                ->where('status', WalkInStatus::Waiting->value)])
            ->search($this->walkInClientSearch)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $selectedWalkInClient = filled($this->walkInClientId)
            ? Client::query()->where('is_active', true)->find($this->walkInClientId)
            : null;

        $walkInEntries = WalkInEntry::query()
            ->with([
                'client:id,first_name,last_name,phone',
                'service:id,name,duration_minutes,price',
                'service.barbers' => fn ($query) => $query->where('barbers.is_active', true)->orderBy('display_name'),
                'preferredBarber:id,user_id,display_name,is_active',
            ])
            ->where('status', WalkInStatus::Waiting->value)
            ->when(! $isSelectedToday, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('arrived_at')
            ->orderBy('id')
            ->get();

        $leftWalkInEntries = WalkInEntry::query()
            ->with([
                'client:id,first_name,last_name,phone',
                'service:id,name,duration_minutes,price',
                'preferredBarber:id,display_name',
            ])
            ->where('status', WalkInStatus::Left->value)
            ->whereDate('arrived_at', $viewDate->toDateString())
            ->orderByDesc('left_at')
            ->orderByDesc('id')
            ->get();

        $walkInEntries = app(WalkInWaitEstimator::class)->estimate(
            $walkInEntries,
            CarbonImmutable::now()->seconds(0),
        );

        $appointments = Appointment::query()
            ->with([
                'client:id,first_name,last_name,phone',
                'barber:id,user_id,display_name',
                'service:id,name,duration_minutes',
                'sale:id,appointment_id',
            ])
            ->whereDate('starts_at', $viewDate->toDateString())
            ->when(
                auth()->user()->role === UserRole::Barber,
                fn ($query) => $currentBarberId
                    ? $query->where('barber_id', $currentBarberId)
                    : $query->whereRaw('1 = 0'),
                fn ($query) => $query->when($selectedBarberId, fn ($query) => $query->where('barber_id', $selectedBarberId)),
            )
            ->orderBy('starts_at')
            ->get();

        $groups = $appointments->groupBy(fn (Appointment $appointment) => $appointment->status->dailyGroup());
        $groupCounts = collect(['waiting', 'in_service', 'pending_payment', 'upcoming', 'finished'])
            ->mapWithKeys(fn (string $group) => [$group => $groups->get($group, collect())->count()]);
        $remainingCount = $appointments->reject(fn (Appointment $appointment) => $appointment->status->isFinal())->count();
        $hasOpenCashRegister = CashRegisterSession::query()
            ->where('status', CashRegisterStatus::Open->value)
            ->exists();
        $paymentAppointment = $this->paymentAppointmentId
            ? $appointments->firstWhere('id', $this->paymentAppointmentId)
            : null;

        return view('livewire.appointments.today', [
            'barbers' => $barbers,
            'viewDate' => $viewDate,
            'isSelectedToday' => $isSelectedToday,
            'services' => $services,
            'walkInPreferredBarbers' => $walkInPreferredBarbers,
            'walkInClients' => $walkInClients,
            'selectedWalkInClient' => $selectedWalkInClient,
            'walkInEntries' => $walkInEntries,
            'leftWalkInEntries' => $leftWalkInEntries,
            'groups' => $groups,
            'groupCounts' => $groupCounts,
            'remainingCount' => $remainingCount,
            'hasOpenCashRegister' => $hasOpenCashRegister,
            'paymentAppointment' => $paymentAppointment,
            'paymentMethods' => PaymentMethod::cases(),
            'statuses' => AppointmentStatus::cases(),
        ])->layout('layouts.app');
    }
}
