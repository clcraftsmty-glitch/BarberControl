<?php

namespace App\Models;

use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    protected $fillable = [
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'opening_amount',
        'expected_cash',
        'actual_cash',
        'difference',
        'difference_reason',
        'difference_authorized_by',
        'difference_authorized_at',
        'opening_notes',
        'closing_notes',
        'status',
        'open_guard',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'difference_authorized_at' => 'datetime',
            'status' => CashRegisterStatus::class,
        ];
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function differenceAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'difference_authorized_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function expectedCashNow(): float
    {
        $cashIncome = (float) $this->movements()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->where('type', 'ingreso')
            ->sum('amount');
        $cashExpenses = (float) $this->movements()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->where('type', 'gasto')
            ->sum('amount');

        return round((float) $this->opening_amount + $cashIncome - $cashExpenses, 2);
    }
}
