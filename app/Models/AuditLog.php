<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id', 'action', 'auditable_type', 'auditable_id', 'description',
        'before_values', 'after_values', 'ip_address', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'encrypted:array',
            'after_values' => 'encrypted:array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
