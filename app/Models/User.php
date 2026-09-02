<?php

namespace App\Models;

use App\Enums\UserPermission;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'is_active',
        'permissions',
        'password',
        'email_verified_at',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
        'last_login_at',
        'last_login_ip',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_two_factor_at',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'permissions' => 'array',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_two_factor_at' => 'datetime',
        ];
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasPermission(UserPermission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->role === UserRole::Administrator) {
            return true;
        }

        $permissions = $this->permissions ?? UserPermission::defaultsFor($this->role);

        return in_array($permission->value, $permissions, true);
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        if ($this->role === UserRole::Administrator) {
            return UserPermission::defaultsFor(UserRole::Administrator);
        }

        return $this->permissions ?? UserPermission::defaultsFor($this->role);
    }

    public function preferredClients(): HasMany
    {
        return $this->hasMany(Client::class, 'preferred_barber_id');
    }

    public function barberProfile(): HasOne
    {
        return $this->hasOne(Barber::class);
    }

    public function createdAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function updatedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'updated_by');
    }

    public function createdSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function createdCashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'created_by');
    }

    public function openedCashRegisters(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class, 'opened_by');
    }

    public function closedCashRegisters(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class, 'closed_by');
    }

    public function authorizedCashDifferences(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class, 'difference_authorized_by');
    }

    public function createdCommissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class, 'created_by');
    }

    public function authorizedCommissionAdjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class, 'authorized_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(UserAccessLog::class);
    }

    public function performedAccessActions(): HasMany
    {
        return $this->hasMany(UserAccessLog::class, 'actor_id');
    }

    public function createdBackups(): HasMany
    {
        return $this->hasMany(DatabaseBackup::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function suspender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }
}
