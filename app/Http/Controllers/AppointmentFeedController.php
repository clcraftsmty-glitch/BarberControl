<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);

        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
        ]);

        $canUpdate = $request->user()->can('create', Appointment::class);
        $rangeStart = CarbonImmutable::parse($data['start'])->setTimezone(config('app.timezone'));
        $rangeEnd = CarbonImmutable::parse($data['end'])->setTimezone(config('app.timezone'));
        $appointments = Appointment::query()
            ->with(['client:id,first_name,last_name', 'barber:id,display_name', 'service:id,name,duration_minutes'])
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->when($data['barber_id'] ?? null, fn ($query, $barberId) => $query->where('barber_id', $barberId))
            ->orderBy('starts_at')
            ->get();

        return response()->json($appointments->map(function (Appointment $appointment) use ($canUpdate): array {
            $movable = $canUpdate && ! in_array($appointment->status, [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ], true);

            return [
                'id' => (string) $appointment->id,
                'title' => "{$appointment->client->full_name} · {$appointment->service->name}",
                'start' => $appointment->starts_at->toIso8601String(),
                'end' => $appointment->ends_at->toIso8601String(),
                'backgroundColor' => $appointment->barber->calendarColor(),
                'borderColor' => $appointment->barber->calendarColor(),
                'editable' => $movable,
                'startEditable' => $movable,
                'durationEditable' => false,
                'extendedProps' => [
                    'barber' => $appointment->barber->display_name,
                    'service' => $appointment->service->name,
                    'status' => $appointment->status->value,
                    'statusLabel' => $appointment->status->label(),
                    'price' => $appointment->price,
                ],
            ];
        }));
    }
}
