<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'barber_id',
        'service_id',
        'starts_at',
        'ends_at',
        'arrived_at',
        'service_started_at',
        'service_finished_at',
        'price',
        'status',
        'source',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            if (auth()->check()) {
                $appointment->created_by ??= auth()->id();
                $appointment->updated_by ??= auth()->id();
            }
        });

        static::updating(function (Appointment $appointment): void {
            if (auth()->check()) {
                $appointment->updated_by = auth()->id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'arrived_at' => 'datetime',
            'service_started_at' => 'datetime',
            'service_finished_at' => 'datetime',
            'price' => 'decimal:2',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function walkInEntry(): HasOne
    {
        return $this->hasOne(WalkInEntry::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function waitingDurationSeconds(): ?int
    {
        if (! $this->arrived_at || ! $this->service_started_at) {
            return null;
        }

        return (int) $this->arrived_at->diffInSeconds($this->service_started_at, true);
    }

    public function serviceDurationSeconds(): ?int
    {
        if (! $this->service_started_at || ! $this->service_finished_at) {
            return null;
        }

        return (int) $this->service_started_at->diffInSeconds($this->service_finished_at, true);
    }

    public function totalDurationSeconds(): ?int
    {
        $waitingSeconds = $this->waitingDurationSeconds();
        $serviceSeconds = $this->serviceDurationSeconds();

        if ($waitingSeconds === null || $serviceSeconds === null) {
            return null;
        }

        return $waitingSeconds + $serviceSeconds;
    }
}
