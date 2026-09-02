<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'filename', 'disk', 'path', 'database_connection', 'database_driver',
        'source_format', 'status', 'size_bytes', 'sha256', 'encrypted',
        'created_by', 'started_at', 'completed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'completed' && filled($this->sha256);
    }
}
