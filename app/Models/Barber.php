<?php

namespace App\Models;

use Database\Factories\BarberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barber extends Model
{
    /** @use HasFactory<BarberFactory> */
    use HasFactory;

    public const DAYS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ];

    protected $fillable = [
        'user_id',
        'display_name',
        'phone',
        'default_commission_percentage',
        'work_schedule',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_commission_percentage' => 'decimal:2',
            'work_schedule' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function commissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }

    public function commissionAdjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class);
    }

    public function preferredWalkInEntries(): HasMany
    {
        return $this->hasMany(WalkInEntry::class, 'preferred_barber_id');
    }

    public function assignedWalkInEntries(): HasMany
    {
        return $this->hasMany(WalkInEntry::class, 'assigned_barber_id');
    }

    public function calendarColor(): string
    {
        $palette = [
            '#8c6513', '#2563eb', '#059669', '#7c3aed', '#ea580c', '#0891b2',
            '#4f46e5', '#c026d3', '#65a30d', '#475569', '#0284c7', '#9333ea',
        ];

        return $palette[($this->id - 1) % count($palette)];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        return $query->when($search, fn (Builder $query) => $query
            ->where('display_name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhereHas('user', fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")));
    }
}
