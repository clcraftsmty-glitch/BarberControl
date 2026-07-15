<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'birth_date',
        'preferred_barber_id',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function preferredBarber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_barber_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $terms = preg_split('/\s+/', trim((string) $search), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return $query->when($terms, function (Builder $query) use ($terms): void {
            foreach ($terms as $term) {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
