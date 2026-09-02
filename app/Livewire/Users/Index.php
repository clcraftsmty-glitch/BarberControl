<?php

namespace App\Livewire\Users;

use App\Enums\UserAccessEvent;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAccessLog;
use App\Services\UserAdministrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $activeTab = 'users';

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public string $accessSearch = '';

    public string $eventFilter = '';

    public bool $showUserModal = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'recepcionista';

    /** @var array<int,string> */
    public array $permissions = [];

    public string $password = '';

    public string $password_confirmation = '';

    public bool $showPasswordModal = false;

    public ?int $passwordUserId = null;

    public string $resetPasswordValue = '';

    public string $resetPasswordConfirmation = '';

    public bool $showSuspensionModal = false;

    public ?int $suspensionUserId = null;

    public string $suspensionReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter', 'statusFilter'], true)) {
            $this->resetPage('usersPage');
        }

        if (in_array($property, ['accessSearch', 'eventFilter'], true)) {
            $this->resetPage('accessPage');
        }
    }

    public function updatedRole(): void
    {
        $role = UserRole::tryFrom($this->role);
        $this->permissions = $role ? UserPermission::defaultsFor($role) : [];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['users', 'access'], true) ? $tab : 'users';
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetUserForm();
        $this->permissions = UserPermission::defaultsFor(UserRole::Receptionist);
        $this->showUserModal = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::query()->with('barberProfile:id,user_id')->findOrFail($userId);
        $this->authorize('update', $user);
        $this->resetUserForm();
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->permissions = $user->effectivePermissions();
        $this->showUserModal = true;
    }

    public function save(UserAdministrationService $users): void
    {
        $target = $this->editingUserId ? User::query()->findOrFail($this->editingUserId) : null;
        $this->authorize($target ? 'update' : 'create', $target ?? User::class);
        $allowedRoles = $target?->barberProfile()->exists()
            ? [UserRole::Barber->value]
            : [UserRole::Administrator->value, UserRole::Receptionist->value];
        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'role' => ['required', Rule::in($allowedRoles)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(UserPermission::class)],
        ];

        if (! $target) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $data = $this->validate($rules, [], [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'permissions' => 'permisos',
            'password' => 'contraseña temporal',
        ]);

        if ($target) {
            $users->update($target, $data, auth()->user());
            $message = 'Usuario actualizado correctamente.';
        } else {
            $users->create($data, auth()->user());
            $message = 'Usuario creado correctamente.';
        }

        $this->showUserModal = false;
        $this->resetUserForm();
        session()->flash('status', $message);
    }

    public function openPasswordReset(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('resetPassword', $user);
        $this->passwordUserId = $user->id;
        $this->resetPasswordValue = '';
        $this->resetPasswordConfirmation = '';
        $this->resetValidation();
        $this->showPasswordModal = true;
    }

    public function resetPassword(UserAdministrationService $users): void
    {
        $user = User::query()->findOrFail($this->passwordUserId);
        $this->authorize('resetPassword', $user);
        $data = $this->validate([
            'resetPasswordValue' => ['required', 'string', 'min:8'],
            'resetPasswordConfirmation' => ['required', 'same:resetPasswordValue'],
        ], [], [
            'resetPasswordValue' => 'nueva contraseña',
            'resetPasswordConfirmation' => 'confirmación',
        ]);
        $users->resetPassword($user, $data['resetPasswordValue'], auth()->user());
        $this->showPasswordModal = false;
        $this->passwordUserId = null;
        session()->flash('status', "Contraseña restablecida para {$user->name}. Sus sesiones anteriores se cerraron.");
    }

    public function openSuspension(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('changeStatus', $user);
        $this->suspensionUserId = $user->id;
        $this->suspensionReason = '';
        $this->resetValidation();
        $this->showSuspensionModal = true;
    }

    public function suspend(UserAdministrationService $users): void
    {
        $user = User::query()->findOrFail($this->suspensionUserId);
        $this->authorize('changeStatus', $user);
        $data = $this->validate([
            'suspensionReason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [], ['suspensionReason' => 'motivo de suspensión']);
        $users->suspend($user, $data['suspensionReason'], auth()->user());
        $this->showSuspensionModal = false;
        $this->suspensionUserId = null;
        session()->flash('status', "{$user->name} fue suspendido y sus sesiones fueron cerradas.");
    }

    public function reactivate(int $userId, UserAdministrationService $users): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('changeStatus', $user);
        $users->reactivate($user, auth()->user());
        session()->flash('status', "{$user->name} fue reactivado correctamente.");
    }

    public function resetTwoFactor(int $userId, UserAdministrationService $users): void
    {
        $this->authorize('manage-security');
        $user = User::query()->findOrFail($userId);
        $users->resetTwoFactor($user, auth()->user());
        session()->flash('status', "Segundo factor restablecido para {$user->name}. Deberá configurarlo al volver a ingresar.");
    }

    public function closeModal(string $modal): void
    {
        if (in_array($modal, ['showUserModal', 'showPasswordModal', 'showSuspensionModal'], true)) {
            $this->{$modal} = false;
        }
        $this->resetValidation();
    }

    public function render(): View
    {
        $users = User::query()
            ->with(['barberProfile:id,user_id,display_name', 'suspender:id,name'])
            ->withCount('accessLogs')
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($this->roleFilter !== '', fn (Builder $query) => $query->where('role', $this->roleFilter))
            ->when($this->statusFilter === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'suspended', fn (Builder $query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12, pageName: 'usersPage');

        $accessLogs = UserAccessLog::query()
            ->with(['user:id,name,email', 'actor:id,name'])
            ->when(trim($this->accessSearch) !== '', function (Builder $query): void {
                $search = trim($this->accessSearch);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->eventFilter !== '', fn (Builder $query) => $query->where('event', $this->eventFilter))
            ->latest('occurred_at')
            ->paginate(15, pageName: 'accessPage');

        $editingUser = $this->editingUserId ? User::query()->with('barberProfile:id,user_id')->find($this->editingUserId) : null;
        $passwordUser = $this->passwordUserId ? User::query()->find($this->passwordUserId) : null;
        $suspensionUser = $this->suspensionUserId ? User::query()->find($this->suspensionUserId) : null;

        return view('livewire.users.index', [
            'users' => $users,
            'accessLogs' => $accessLogs,
            'roles' => UserRole::cases(),
            'permissionOptions' => UserPermission::cases(),
            'accessEvents' => UserAccessEvent::cases(),
            'editingUser' => $editingUser,
            'passwordUser' => $passwordUser,
            'suspensionUser' => $suspensionUser,
        ])->layout('layouts.app');
    }

    private function resetUserForm(): void
    {
        $this->editingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->role = UserRole::Receptionist->value;
        $this->permissions = [];
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetValidation();
    }
}
