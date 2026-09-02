<?php

namespace App\Models;

use App\Enums\CashMovementCategory;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = [
        'cash_register_session_id',
        'sale_id',
        'commission_settlement_id',
        'type',
        'category',
        'amount',
        'payment_method',
        'description',
        'occurred_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category' => CashMovementCategory::class,
            'payment_method' => PaymentMethod::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function commissionSettlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
