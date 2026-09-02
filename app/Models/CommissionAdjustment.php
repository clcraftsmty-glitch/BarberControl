<?php

namespace App\Models;

use App\Enums\CommissionAdjustmentStatus;
use App\Enums\CommissionAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionAdjustment extends Model
{
    protected $fillable = [
        'barber_id',
        'commission_settlement_id',
        'type',
        'amount',
        'reason',
        'status',
        'authorized_by',
        'authorized_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommissionAdjustmentType::class,
            'status' => CommissionAdjustmentStatus::class,
            'amount' => 'decimal:2',
            'authorized_at' => 'datetime',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedAmount(): float
    {
        return $this->type->signedAmount((float) $this->amount);
    }
}
