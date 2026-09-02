<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'client_id',
        'appointment_id',
        'sale_id',
        'type',
        'template_name',
        'deduplication_key',
        'recipient',
        'payload',
        'status',
        'provider_message_id',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'attempts',
        'last_error',
        'initiated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WhatsAppMessageType::class,
            'status' => WhatsAppMessageStatus::class,
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
