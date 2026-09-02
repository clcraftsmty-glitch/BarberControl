<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    protected $fillable = [
        'folio_number',
        'folio',
        'status',
        'appointment_id',
        'client_id',
        'barber_id',
        'service_id',
        'subtotal',
        'total',
        'payment_method',
        'payment_reference',
        'paid_at',
        'created_by',
        'client_name_snapshot',
        'client_phone_snapshot',
        'barber_name_snapshot',
        'service_name_snapshot',
        'service_description_snapshot',
        'service_duration_minutes_snapshot',
        'unit_price_snapshot',
        'commission_percentage_snapshot',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'refunded_amount',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'commission_percentage_snapshot' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => SaleStatus::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'service_duration_minutes_snapshot' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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

    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class)->where('type', 'ingreso');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function ticketLogs(): HasMany
    {
        return $this->hasMany(SaleTicketLog::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
