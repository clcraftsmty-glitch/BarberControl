<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'price',
        'status',
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
            'price' => 'decimal:2',
            'status' => AppointmentStatus::class,
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
}
