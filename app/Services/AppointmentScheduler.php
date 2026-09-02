<?php

namespace App\Services;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkInEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentScheduler
{
    public function __construct(
        private WhatsAppNotificationService $whatsApp,
        private BarberOccupancyService $occupancy,
    ) {}

    public function create(array $data, User $actor, bool $allowOverride = false): Appointment
    {
        $appointment = DB::transaction(function () use ($data, $actor, $allowOverride): Appointment {
            $data['status'] = AppointmentStatus::Pending->value;
            $context = $this->validatedContext($data, null, $actor, $allowOverride);

            $appointment = new Appointment($this->attributes($data, $context));
            $appointment->created_by = $actor->id;
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $appointment->refresh();
        });

        $this->whatsApp->confirmation($appointment, $actor);

        return $appointment;
    }

    public function update(Appointment $appointment, array $data, User $actor, bool $allowOverride = false): Appointment
    {
        $originalSchedule = implode('|', [
            $appointment->starts_at->format('Y-m-d H:i'),
            $appointment->barber_id,
            $appointment->service_id,
        ]);
        $updated = DB::transaction(function () use ($appointment, $data, $actor, $allowOverride): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $data['status'] = $appointment->status->value;
            $context = $this->validatedContext($data, $appointment, $actor, $allowOverride);

            $appointment->fill($this->attributes($data, $context));
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $appointment->refresh();
        });

        $newSchedule = implode('|', [
            $updated->starts_at->format('Y-m-d H:i'),
            $updated->barber_id,
            $updated->service_id,
        ]);

        if ($originalSchedule !== $newSchedule) {
            $this->whatsApp->rescheduled($updated, $actor);
        }

        return $updated;
    }

    public function createWalkIn(WalkInEntry $entry, Barber $barber, User $actor): Appointment
    {
        return DB::transaction(function () use ($entry, $barber, $actor): Appointment {
            $service = Service::query()->findOrFail($entry->service_id);
            $startedAt = now();
            $data = [
                'client_id' => $entry->client_id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'starts_at' => $startedAt,
                'price' => $service->price,
                'status' => AppointmentStatus::InService->value,
                'notes' => $entry->notes,
            ];
            $context = $this->validatedContext(
                $data,
                null,
                $actor,
                false,
                false,
                "walkInBarberSelections.{$entry->id}",
                false,
            );

            $appointment = new Appointment([
                ...$this->attributes($data, $context),
                'source' => AppointmentSource::WalkIn,
                'arrived_at' => $entry->arrived_at,
                'service_started_at' => $startedAt,
            ]);
            $appointment->created_by = $actor->id;
            $appointment->updated_by = $actor->id;
            $appointment->save();

            return $appointment->refresh();
        });
    }

    public function move(Appointment $appointment, string $startsAt, User $actor, bool $allowOverride = false): Appointment
    {
        return $this->update($appointment, [
            'client_id' => $appointment->client_id,
            'barber_id' => $appointment->barber_id,
            'service_id' => $appointment->service_id,
            'starts_at' => $startsAt,
            'price' => $appointment->price,
            'status' => $appointment->status->value,
            'notes' => $appointment->notes,
        ], $actor, $allowOverride);
    }

    /** @return array{barber: Barber, service: Service, start: CarbonImmutable, end: CarbonImmutable, status: AppointmentStatus} */
    private function validatedContext(
        array $data,
        ?Appointment $appointment,
        User $actor,
        bool $allowOverride,
        bool $checkClientDay = true,
        string $occupancyField = 'schedule.overlap',
        bool $checkBookingRules = true,
    ): array {
        $barber = Barber::query()->lockForUpdate()->findOrFail($data['barber_id']);
        $service = Service::query()->findOrFail($data['service_id']);
        $client = Client::query()->findOrFail($data['client_id']);
        $status = AppointmentStatus::from($data['status']);
        $start = CarbonImmutable::parse($data['starts_at'])->seconds(0);
        $end = $start->addMinutes($service->duration_minutes);

        if (! $client->is_active) {
            $this->fail('form.client_id', 'El cliente seleccionado está inactivo.');
        }

        if (! $barber->is_active) {
            $this->fail('form.barber_id', 'El barbero seleccionado está inactivo.');
        }

        if (! $service->is_active) {
            $this->fail('form.service_id', 'El servicio seleccionado está inactivo.');
        }

        if (! $barber->services()->whereKey($service->id)->exists()) {
            $this->fail('form.service_id', 'El barbero seleccionado no realiza este servicio.');
        }

        if ($service->duration_minutes < 1 || $service->duration_minutes > 480) {
            $this->fail('form.service_id', 'La duración del servicio debe estar entre 1 y 480 minutos.');
        }

        if ($allowOverride && $actor->role !== UserRole::Administrator) {
            $this->fail('schedule.override', 'Solo un administrador puede autorizar una excepción de agenda.');
        }

        if ($status === AppointmentStatus::InService) {
            $this->occupancy->assertCanStart($barber->id, $appointment?->id, $occupancyField);
        }

        $violations = [
            ...$this->businessHoursViolations($start, $end),
            ...$this->workingHoursViolations($barber, $start, $end),
            ...$this->overlapViolations($barber, $start, $end, $appointment, $status),
            ...($checkClientDay ? $this->clientDayViolations($client, $start, $appointment, $status) : []),
            ...($checkBookingRules ? $this->bookingRuleViolations($start) : []),
        ];

        if ($violations !== [] && ! $allowOverride) {
            throw ValidationException::withMessages($violations);
        }

        return compact('barber', 'service', 'start', 'end', 'status');
    }

    /** @param array{barber: Barber, service: Service, start: CarbonImmutable, end: CarbonImmutable, status: AppointmentStatus} $context */
    private function attributes(array $data, array $context): array
    {
        return [
            'client_id' => $data['client_id'],
            'barber_id' => $context['barber']->id,
            'service_id' => $context['service']->id,
            'starts_at' => $context['start'],
            'ends_at' => $context['end'],
            'price' => $data['price'],
            'status' => $context['status'],
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
    }

    /** @return array<string, string> */
    private function workingHoursViolations(Barber $barber, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $day = strtolower($start->englishDayOfWeek);
        $schedule = $barber->work_schedule[$day] ?? null;

        if (! ($schedule['enabled'] ?? false) || blank($schedule['start'] ?? null) || blank($schedule['end'] ?? null)) {
            return ['schedule.work_hours' => 'El barbero no trabaja el día seleccionado.'];
        }

        if (! $start->isSameDay($end)) {
            return ['schedule.duration' => 'La cita debe iniciar y terminar el mismo día.'];
        }

        $workStart = $start->setTimeFromTimeString($schedule['start']);
        $workEnd = $start->setTimeFromTimeString($schedule['end']);

        if ($start->lessThan($workStart) || $end->greaterThan($workEnd)) {
            return [
                'schedule.work_hours' => "La cita debe quedar dentro del horario de {$schedule['start']} a {$schedule['end']}.",
            ];
        }

        return [];
    }

    /** @return array<string, string> */
    private function businessHoursViolations(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $settings = BusinessSetting::current();
        $day = strtolower($start->englishDayOfWeek);
        $schedule = $settings->general_schedule[$day] ?? null;

        if (! ($schedule['enabled'] ?? false) || blank($schedule['start'] ?? null) || blank($schedule['end'] ?? null)) {
            return ['schedule.business_hours' => 'La barbería está cerrada el día seleccionado.'];
        }

        if (! $start->isSameDay($end)) {
            return ['schedule.duration' => 'La cita debe iniciar y terminar el mismo día.'];
        }

        $businessStart = $start->setTimeFromTimeString($schedule['start']);
        $businessEnd = $start->setTimeFromTimeString($schedule['end']);

        if ($start->lessThan($businessStart) || $end->greaterThan($businessEnd)) {
            return [
                'schedule.business_hours' => "La barbería opera de {$schedule['start']} a {$schedule['end']} ese día.",
            ];
        }

        return [];
    }

    /** @return array<string, string> */
    private function bookingRuleViolations(CarbonImmutable $start): array
    {
        $settings = BusinessSetting::current();
        $now = CarbonImmutable::now($settings->timezone)->seconds(0);
        $violations = [];

        if (
            $settings->minimum_booking_notice_minutes > 0
            && $start->lessThan($now->addMinutes($settings->minimum_booking_notice_minutes))
        ) {
            $violations['schedule.minimum_notice'] = "La cita requiere al menos {$settings->minimum_booking_notice_minutes} minutos de anticipación.";
        }

        if ($start->greaterThan($now->addDays($settings->maximum_booking_advance_days)->endOfDay())) {
            $violations['schedule.maximum_advance'] = "La cita no puede reservarse con más de {$settings->maximum_booking_advance_days} días de anticipación.";
        }

        return $violations;
    }

    /** @return array<string, string> */
    private function overlapViolations(
        Barber $barber,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?Appointment $appointment,
        AppointmentStatus $status,
    ): array {
        if (! $status->blocksSchedule()) {
            return [];
        }

        $overlaps = Appointment::query()
            ->where('barber_id', $barber->id)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Rescheduled->value,
            ])
            ->when($appointment, fn ($query) => $query->whereKeyNot($appointment->id))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            return ['schedule.overlap' => 'El barbero ya tiene una cita en ese horario.'];
        }

        return [];
    }

    /** @return array<string, string> */
    private function clientDayViolations(
        Client $client,
        CarbonImmutable $start,
        ?Appointment $appointment,
        AppointmentStatus $status,
    ): array {
        if (! $status->blocksSchedule()) {
            return [];
        }

        $hasAnotherAppointment = Appointment::query()
            ->where('client_id', $client->id)
            ->whereDate('starts_at', $start->toDateString())
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Rescheduled->value,
            ])
            ->when($appointment, fn ($query) => $query->whereKeyNot($appointment->id))
            ->lockForUpdate()
            ->exists();

        return $hasAnotherAppointment
            ? ['schedule.client_day' => 'El cliente ya tiene otra cita programada este día.']
            : [];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
