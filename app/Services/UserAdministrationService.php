<?php

namespace App\Services;

use App\Enums\UserAccessEvent;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAdministrationService
{
    public function __construct(private UserAccessLogger $accessLogger, private AuditLogger $audit) {}

    /** @param array{name:string,email:string,role:string,permissions?:array<int,string>,password:string} $data */
    public function create(array $data, User $actor): User
    {
        $this->assertAdministrator($actor);
        $role = UserRole::from($data['role']);

        if (! in_array($role, [UserRole::Administrator, UserRole::Receptionist], true)) {
            $this->fail('Desde este módulo sólo puedes crear administradores y recepcionistas.', 'role');
        }

        return User::query()->create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'role' => $role,
            'permissions' => $this->normalizePermissions($role, $data['permissions'] ?? []),
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => $data['password'],
        ]);
    }

    /** @param array{name:string,email:string,role:string,permissions?:array<int,string>} $data */
    public function update(User $user, array $data, User $actor): User
    {
        $this->assertAdministrator($actor);
        $role = UserRole::from($data['role']);

        if ($user->barberProfile()->exists() && $role !== UserRole::Barber) {
            $this->fail('El usuario está relacionado con un barbero y no puede cambiar de rol.', 'role');
        }

        if (! $user->barberProfile()->exists() && ! in_array($role, [UserRole::Administrator, UserRole::Receptionist], true)) {
            $this->fail('Selecciona el rol administrador o recepcionista.', 'role');
        }

        if ($actor->is($user) && $role !== UserRole::Administrator) {
            $this->fail('No puedes retirar tu propio rol de administrador.', 'role');
        }

        if ($user->role === UserRole::Administrator && $role !== UserRole::Administrator) {
            $this->assertAnotherActiveAdministratorExists($user);
        }

        $user->update([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'role' => $role,
            'permissions' => $this->normalizePermissions($role, $data['permissions'] ?? []),
        ]);

        return $user->refresh();
    }

    public function suspend(User $user, string $reason, User $actor): User
    {
        $this->assertAdministrator($actor);

        if ($actor->is($user)) {
            $this->fail('No puedes suspender tu propia cuenta.', 'suspension_reason');
        }

        if ($user->role === UserRole::Administrator) {
            $this->assertAnotherActiveAdministratorExists($user);
        }

        if (mb_strlen(trim($reason)) < 5) {
            $this->fail('Escribe un motivo de al menos 5 caracteres.', 'suspension_reason');
        }

        return DB::transaction(function () use ($user, $reason, $actor): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->update([
                'is_active' => false,
                'suspended_at' => now(),
                'suspended_by' => $actor->id,
                'suspension_reason' => trim($reason),
                'remember_token' => Str::random(60),
            ]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->accessLogger->record(UserAccessEvent::Suspended, $user, $actor, details: trim($reason));

            return $user->refresh();
        });
    }

    public function reactivate(User $user, User $actor): User
    {
        $this->assertAdministrator($actor);
        $user->update([
            'is_active' => true,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);
        $this->accessLogger->record(UserAccessEvent::Reactivated, $user, $actor);

        return $user->refresh();
    }

    public function resetPassword(User $user, string $password, User $actor): User
    {
        $this->assertAdministrator($actor);

        return DB::transaction(function () use ($user, $password, $actor): User {
            $user->update([
                'password' => $password,
                'remember_token' => Str::random(60),
            ]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->accessLogger->record(UserAccessEvent::PasswordReset, $user, $actor);

            return $user->refresh();
        });
    }

    public function resetTwoFactor(User $user, User $actor): User
    {
        $this->assertAdministrator($actor);
        if ($actor->is($user)) {
            $this->fail('Otro administrador debe restablecer tu segundo factor.', 'two_factor');
        }
        if ($user->role !== UserRole::Administrator || ! $user->hasConfirmedTwoFactor()) {
            $this->fail('El usuario no tiene un segundo factor activo.', 'two_factor');
        }

        return DB::transaction(function () use ($user): User {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'last_two_factor_at' => null,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->audit->record('two_factor_reset', 'Segundo factor restablecido para '.$user->email, $user);

            return $user->refresh();
        });
    }

    /** @param array<int,string> $permissions @return list<string> */
    private function normalizePermissions(UserRole $role, array $permissions): array
    {
        if ($role === UserRole::Administrator) {
            return UserPermission::defaultsFor($role);
        }

        if ($role === UserRole::Barber) {
            return [];
        }

        $valid = array_column(UserPermission::cases(), 'value');
        $permissions = array_values(array_unique(array_intersect($permissions, $valid)));
        $delegatedActions = [
            UserPermission::CancelSales->value,
            UserPermission::AdjustCash->value,
            UserPermission::SettleCommissions->value,
        ];

        if (array_intersect($permissions, $delegatedActions)) {
            $permissions[] = UserPermission::ViewFinancialInformation->value;
        }

        return array_values(array_unique($permissions));
    }

    private function assertAdministrator(User $actor): void
    {
        if (! $actor->is_active || $actor->role !== UserRole::Administrator) {
            throw new AuthorizationException('Sólo un administrador activo puede gestionar usuarios.');
        }
    }

    private function assertAnotherActiveAdministratorExists(User $excluded): void
    {
        if (! User::query()
            ->whereKeyNot($excluded->id)
            ->where('role', UserRole::Administrator->value)
            ->where('is_active', true)
            ->exists()) {
            $this->fail('Debe permanecer al menos un administrador activo.', 'role');
        }
    }

    private function fail(string $message, string $field): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
