<?php

namespace App\Models;

use App\Enums\CommissionPeriod;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommissionSettlement extends Model
{
    protected $fillable = [
        'folio_number',
        'folio',
        'barber_id',
        'period_type',
        'period_start',
        'period_end',
        'commissions_total',
        'adjustments_total',
        'total_paid',
        'payment_method',
        'payment_reference',
        'notes',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => CommissionPeriod::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'commissions_total' => 'decimal:2',
            'adjustments_total' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class);
    }

    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }
}
