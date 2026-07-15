<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'duration_minutes',
        'price',
        'commission_percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        return $query->when($search, fn (Builder $query) => $query
            ->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%"));
    }

    public function barbers(): BelongsToMany
    {
        return $this->belongsToMany(Barber::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
