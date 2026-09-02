<?php

namespace App\Models;

use App\Enums\WalkInStatus;
use Database\Factories\WalkInEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkInEntry extends Model
{
    /** @use HasFactory<WalkInEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'service_id',
        'preferred_barber_id',
        'assigned_barber_id',
        'appointment_id',
        'status',
        'arrived_at',
        'called_at',
        'left_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => WalkInStatus::class,
            'arrived_at' => 'datetime',
            'called_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function preferredBarber(): BelongsTo
    {
        return $this->belongsTo(Barber::class, 'preferred_barber_id');
    }

    public function assignedBarber(): BelongsTo
    {
        return $this->belongsTo(Barber::class, 'assigned_barber_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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
