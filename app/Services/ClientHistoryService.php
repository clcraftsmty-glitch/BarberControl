<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\SaleStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;

class ClientHistoryService
{
    /** @return array<string, mixed> */
    public function forClient(Client $client, User $viewer): array
    {
        $completedVisits = Appointment::query()
            ->with(['service:id,name', 'barber:id,display_name'])
            ->where('client_id', $client->id)
            ->where('status', AppointmentStatus::Completed->value)
            ->where('starts_at', '<=', now())
            ->orderBy('starts_at')
            ->get(['id', 'client_id', 'service_id', 'barber_id', 'starts_at']);
        $lastVisit = $completedVisits->last()?->starts_at;
        $latestAppointments = Appointment::query()
            ->with([
                'service:id,name,duration_minutes',
                'barber:id,display_name',
                'sale:id,appointment_id,folio,status,total,payment_method',
            ])
            ->where('client_id', $client->id)
            ->where('starts_at', '<=', now())
            ->latest('starts_at')
            ->limit(10)
            ->get();
        $incidentCounts = Appointment::query()
            ->where('client_id', $client->id)
            ->whereIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $canSeeFinancials = $viewer->can('viewAny', Sale::class);
        $sales = $canSeeFinancials
            ? Sale::query()
                ->withCount('ticketLogs')
                ->where('client_id', $client->id)
                ->latest('paid_at')
                ->limit(12)
                ->get()
            : collect();
        $completedSales = $canSeeFinancials
            ? Sale::query()
                ->where('client_id', $client->id)
                ->where('status', SaleStatus::Completed->value)
            : null;

        return [
            'latestAppointments' => $latestAppointments,
            'completedVisitCount' => $completedVisits->count(),
            'frequentServices' => $this->frequentServices($completedVisits),
            'usualBarber' => $this->usualBarber($completedVisits),
            'averageVisitIntervalDays' => $this->averageVisitIntervalDays($completedVisits),
            'lastVisit' => $lastVisit,
            'daysSinceLastVisit' => $lastVisit
                ? max(0, (int) $lastVisit->copy()->startOfDay()->diffInDays(today()))
                : null,
            'cancelledCount' => (int) ($incidentCounts[AppointmentStatus::Cancelled->value] ?? 0),
            'noShowCount' => (int) ($incidentCounts[AppointmentStatus::NoShow->value] ?? 0),
            'canSeeFinancials' => $canSeeFinancials,
            'sales' => $sales,
            'totalSpent' => $completedSales ? (float) (clone $completedSales)->sum('total') : 0,
            'paymentCount' => $completedSales ? (clone $completedSales)->count() : 0,
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $visits
     * @return Collection<int, array{service_id: int, name: string, visits: int, percentage: int}>
     */
    private function frequentServices(Collection $visits): Collection
    {
        $total = $visits->count();

        return $visits
            ->groupBy('service_id')
            ->map(function (Collection $serviceVisits) use ($total): array {
                /** @var Appointment $first */
                $first = $serviceVisits->first();

                return [
                    'service_id' => $first->service_id,
                    'name' => $first->service->name,
                    'visits' => $serviceVisits->count(),
                    'percentage' => $total > 0
                        ? (int) round(($serviceVisits->count() / $total) * 100)
                        : 0,
                ];
            })
            ->sortByDesc('visits')
            ->take(5)
            ->values();
    }

    /** @param Collection<int, Appointment> $visits
     * @return array{barber_id: int, name: string, visits: int}|null
     */
    private function usualBarber(Collection $visits): ?array
    {
        $group = $visits
            ->groupBy('barber_id')
            ->sortByDesc(fn (Collection $barberVisits) => $barberVisits->count())
            ->first();

        if (! $group) {
            return null;
        }

        /** @var Appointment $first */
        $first = $group->first();

        return [
            'barber_id' => $first->barber_id,
            'name' => $first->barber->display_name,
            'visits' => $group->count(),
        ];
    }

    /** @param Collection<int, Appointment> $visits */
    private function averageVisitIntervalDays(Collection $visits): ?int
    {
        if ($visits->count() < 2) {
            return null;
        }

        $orderedVisits = $visits->values();
        $intervals = $orderedVisits
            ->slice(1)
            ->values()
            ->map(fn (Appointment $visit, int $index) => $orderedVisits[$index]
                ->starts_at
                ->diffInDays($visit->starts_at));

        return (int) round($intervals->average());
    }
}
