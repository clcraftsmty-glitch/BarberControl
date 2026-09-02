<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\WalkInStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkInEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalkInQueueService
{
    public function __construct(private readonly AppointmentScheduler $scheduler) {}

    /** @param array<string, mixed> $data */
    public function register(array $data, User $actor): WalkInEntry
    {
        $this->assertReception($actor);

        return DB::transaction(function () use ($data, $actor): WalkInEntry {
            $client = filled($data['client_id'] ?? null)
                ? Client::query()->lockForUpdate()->findOrFail($data['client_id'])
                : Client::query()->create([
                    'first_name' => trim($data['new_client']['first_name']),
                    'last_name' => trim($data['new_client']['last_name']),
                    'phone' => trim($data['new_client']['phone']),
                    'is_active' => true,
                ]);
            $service = Service::query()->findOrFail($data['service_id']);
            $preferredBarber = filled($data['preferred_barber_id'] ?? null)
                ? Barber::query()->findOrFail($data['preferred_barber_id'])
                : null;

            if (! $client->is_active) {
                $this->fail('walkInClientId', 'El cliente seleccionado está inactivo.');
            }

            if (! $service->is_active) {
                $this->fail('walkInServiceId', 'El servicio seleccionado está inactivo.');
            }

            if ($preferredBarber && (! $preferredBarber->is_active || ! $preferredBarber->services()->whereKey($service->id)->exists())) {
                $this->fail('walkInPreferredBarberId', 'El barbero preferido no está disponible para este servicio.');
            }

            $alreadyWaiting = WalkInEntry::query()
                ->where('client_id', $client->id)
                ->where('status', WalkInStatus::Waiting->value)
                ->lockForUpdate()
                ->exists();

            if ($alreadyWaiting) {
                $this->fail('walkInClientId', 'Este cliente ya se encuentra en la fila de espera.');
            }

            return WalkInEntry::query()->create([
                'client_id' => $client->id,
                'service_id' => $service->id,
                'preferred_barber_id' => $preferredBarber?->id,
                'status' => WalkInStatus::Waiting,
                'arrived_at' => now(),
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->refresh();
        });
    }

    public function start(WalkInEntry $entry, Barber $barber, User $actor): Appointment
    {
        return DB::transaction(function () use ($entry, $barber, $actor): Appointment {
            $entry = WalkInEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== WalkInStatus::Waiting) {
                $this->fail('walkInQueue', 'Este turno ya no se encuentra en espera.');
            }

            if ($actor->role === UserRole::Barber && $barber->user_id !== $actor->id) {
                throw new AuthorizationException('Un barbero solo puede tomar clientes para sí mismo.');
            }

            if (! $actor->hasRole(UserRole::Administrator, UserRole::Receptionist, UserRole::Barber)) {
                throw new AuthorizationException('No tienes permiso para iniciar este servicio.');
            }

            if (! $barber->is_active || ! $barber->services()->whereKey($entry->service_id)->exists()) {
                $this->fail("walkInBarberSelections.{$entry->id}", 'El barbero seleccionado no realiza este servicio.');
            }

            $appointment = $this->scheduler->createWalkIn($entry, $barber, $actor);

            $entry->update([
                'assigned_barber_id' => $barber->id,
                'appointment_id' => $appointment->id,
                'status' => WalkInStatus::Converted,
                'called_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $appointment;
        });
    }

    public function markLeft(WalkInEntry $entry, User $actor): WalkInEntry
    {
        $this->assertReception($actor);

        return DB::transaction(function () use ($entry, $actor): WalkInEntry {
            $entry = WalkInEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== WalkInStatus::Waiting) {
                $this->fail('walkInQueue', 'Este turno ya no se encuentra en espera.');
            }

            $entry->update([
                'status' => WalkInStatus::Left,
                'left_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $entry->refresh();
        });
    }

    private function assertReception(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Administrator, UserRole::Receptionist)) {
            throw new AuthorizationException('No tienes permiso para administrar la fila sin cita.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
