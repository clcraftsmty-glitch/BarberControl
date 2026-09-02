<?php

namespace App\Models;

use App\Enums\UserAccessEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'details',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => UserAccessEvent::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
