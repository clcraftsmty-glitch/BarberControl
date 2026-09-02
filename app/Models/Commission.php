<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'sale_id',
        'barber_id',
        'commission_settlement_id',
        'base_amount',
        'percentage',
        'amount',
        'status',
        'paid_at',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => CommissionStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
