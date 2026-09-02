<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemError extends Model
{
    protected $fillable = [
        'fingerprint', 'exception_class', 'message', 'route_name', 'request_method',
        'request_path', 'status_code', 'user_id', 'occurrences', 'first_occurred_at',
        'last_occurred_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'first_occurred_at' => 'datetime',
            'last_occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
