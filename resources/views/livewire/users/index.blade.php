<div class="mx-auto max-w-[1500px] space-y-6">
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Seguridad y equipo</p>
            <h1 class="text-xl font-bold text-slate-900">Usuarios y permisos</h1>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-slate-950">Administración de accesos</h2>
            <p class="mt-1 text-sm text-slate-500">Crea cuentas, delega funciones y revisa la actividad de acceso.</p>
        </div>
        <button type="button" wire:click="openCreate" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-brand-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Nuevo usuario
        </button>
    </div>

    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
        <button type="button" wire:click="switchTab('users')" class="rounded-lg px-4 py-2 text-sm font-bold {{ $activeTab === 'users' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Usuarios</button>
        <button type="button" wire:click="switchTab('access')" class="rounded-lg px-4 py-2 text-sm font-bold {{ $activeTab === 'access' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Historial de accesos</button>
    </div>

    @if ($activeTab === 'users')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[minmax(0,1fr)_190px_190px]">
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre o correo..." class="rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <select wire:model.live="roleFilter" class="rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $roleOption)<option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>@endforeach
                </select>
                <select wire:model.live="statusFilter" class="rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos los estados</option><option value="active">Activos</option><option value="suspended">Suspendidos</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">Usuario</th><th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">Rol</th><th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">Permisos efectivos</th><th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">Último acceso</th><th class="px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">Estado</th><th class="px-5 py-3 text-right text-xs font-black uppercase tracking-wide text-slate-500">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="align-top hover:bg-slate-50/70">
                                <td class="px-5 py-4"><p class="font-bold text-slate-950">{{ $user->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $user->email }}</p>@if($user->barberProfile)<p class="mt-1 text-xs font-semibold text-violet-700">Perfil: {{ $user->barberProfile->display_name }}</p>@endif</td>
                                <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $user->role->label() }}</span></td>
                                <td class="max-w-md px-5 py-4">
                                    @if ($user->role === \App\Enums\UserRole::Administrator)
                                        <span class="text-xs font-bold text-brand-700">Acceso administrativo completo</span><p class="mt-1 text-xs font-bold {{ $user->hasConfirmedTwoFactor() ? 'text-emerald-700' : 'text-amber-700' }}">2FA: {{ $user->hasConfirmedTwoFactor() ? 'activo' : 'pendiente' }}</p>
                                    @elseif ($user->effectivePermissions() === [])
                                        <span class="text-xs text-slate-400">Sin permisos financieros especiales</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">@foreach($user->effectivePermissions() as $permissionValue) @if($permission = \App\Enums\UserPermission::tryFrom($permissionValue))<span class="rounded-full bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700">{{ $permission->label() }}</span>@endif @endforeach</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">@if($user->last_login_at)<p>{{ $user->last_login_at->format('d/m/Y H:i') }}</p><p class="text-xs text-slate-400">{{ $user->last_login_ip }}</p>@else<span class="text-slate-400">Nunca</span>@endif</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $user->is_active ? 'Activo' : 'Suspendido' }}</span>
                                    @if(!$user->is_active && $user->suspension_reason)<p class="mt-2 max-w-xs text-xs text-slate-500">{{ $user->suspension_reason }}</p>@endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <button type="button" wire:click="openEdit({{ $user->id }})" class="px-2 py-1 text-xs font-bold text-blue-700">Editar</button>
                                    <button type="button" wire:click="openPasswordReset({{ $user->id }})" class="px-2 py-1 text-xs font-bold text-violet-700">Contraseña</button>
                                    @if($user->role === \App\Enums\UserRole::Administrator && $user->hasConfirmedTwoFactor() && auth()->id() !== $user->id)<button type="button" wire:click="resetTwoFactor({{ $user->id }})" wire:confirm="¿Restablecer el segundo factor de {{ $user->name }} y cerrar sus sesiones?" class="px-2 py-1 text-xs font-bold text-amber-700">Restablecer 2FA</button>@endif
                                    @if(auth()->id() !== $user->id)
                                        @if($user->is_active)<button type="button" wire:click="openSuspension({{ $user->id }})" class="px-2 py-1 text-xs font-bold text-red-700">Suspender</button>@else<button type="button" wire:click="reactivate({{ $user->id }})" wire:confirm="¿Reactivar la cuenta de {{ $user->name }}?" class="px-2 py-1 text-xs font-bold text-emerald-700">Reactivar</button>@endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500">No se encontraron usuarios.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $users->links() }}</div>@endif
        </section>
    @else
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[minmax(0,1fr)_240px]">
                <input type="search" wire:model.live.debounce.350ms="accessSearch" placeholder="Buscar por usuario o correo..." class="rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                <select wire:model.live="eventFilter" class="rounded-xl border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"><option value="">Todos los eventos</option>@foreach($accessEvents as $event)<option value="{{ $event->value }}">{{ $event->label() }}</option>@endforeach</select>
            </div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-black uppercase text-slate-500">Fecha</th><th class="px-5 py-3 text-left text-xs font-black uppercase text-slate-500">Usuario</th><th class="px-5 py-3 text-left text-xs font-black uppercase text-slate-500">Evento</th><th class="px-5 py-3 text-left text-xs font-black uppercase text-slate-500">Dirección IP</th><th class="px-5 py-3 text-left text-xs font-black uppercase text-slate-500">Dispositivo / detalle</th><th class="px-5 py-3 text-left text-xs font-black uppercase text-slate-500">Realizado por</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($accessLogs as $log)<tr wire:key="access-log-{{ $log->id }}"><td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $log->occurred_at->format('d/m/Y H:i:s') }}</td><td class="px-5 py-4"><p class="text-sm font-bold text-slate-900">{{ $log->user?->name ?? 'Usuario no identificado' }}</p><p class="text-xs text-slate-500">{{ $log->email }}</p></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ in_array($log->event, [\App\Enums\UserAccessEvent::FailedLogin, \App\Enums\UserAccessEvent::BlockedLogin, \App\Enums\UserAccessEvent::Suspended], true) ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700' }}">{{ $log->event->label() }}</span></td><td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{{ $log->ip_address ?: 'Sin dato' }}</td><td class="max-w-sm px-5 py-4"><p class="truncate text-xs text-slate-500" title="{{ $log->user_agent }}">{{ $log->user_agent ?: 'Sin información del dispositivo' }}</p>@if($log->details)<p class="mt-1 text-xs font-semibold text-slate-700">{{ $log->details }}</p>@endif</td><td class="px-5 py-4 text-sm text-slate-600">{{ $log->actor?->name ?? 'Sistema' }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500">Todavía no hay eventos de acceso.</td></tr>@endforelse</tbody>
            </table></div>
            @if($accessLogs->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $accessLogs->links() }}</div>@endif
        </section>
    @endif

    @if($showUserModal)
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4"><button type="button" wire:click="closeModal('showUserModal')" class="fixed inset-0 bg-slate-950/60" aria-label="Cerrar"></button><section class="relative z-10 max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <form wire:submit="save"><header class="border-b border-slate-200 px-6 py-5"><p class="text-xs font-black uppercase tracking-[0.18em] text-brand-600">{{ $editingUser ? 'Editar cuenta' : 'Nueva cuenta' }}</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $editingUser?->name ?? 'Crear usuario' }}</h2></header>
                <div class="space-y-5 p-6">
                    <div class="grid gap-4 sm:grid-cols-2"><div><x-input-label for="user-name" value="Nombre completo" /><x-text-input id="user-name" wire:model="name" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div><div><x-input-label for="user-email" value="Correo electrónico" /><x-text-input id="user-email" wire:model="email" type="email" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div></div>
                    <div><x-input-label for="user-role" value="Rol" /><select id="user-role" wire:model.live="role" @disabled($editingUser?->barberProfile || auth()->id() === $editingUser?->id) class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">@if($editingUser?->barberProfile)<option value="barbero">Barbero</option>@else<option value="recepcionista">Recepcionista</option><option value="administrador">Administrador</option>@endif</select><x-input-error :messages="$errors->get('role')" class="mt-2" /></div>
                    @if($role === \App\Enums\UserRole::Administrator->value)<div class="rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm font-semibold text-brand-800">Los administradores reciben automáticamente todos los permisos.</div>@elseif($role === \App\Enums\UserRole::Receptionist->value)
                        <fieldset><legend class="text-sm font-black text-slate-900">Permisos especiales</legend><p class="mt-1 text-xs text-slate-500">Las operaciones delegadas incluyen automáticamente la consulta financiera necesaria.</p><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($permissionOptions as $permission)<label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 hover:border-brand-300"><input type="checkbox" wire:model="permissions" value="{{ $permission->value }}" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"><span><strong class="block text-sm text-slate-900">{{ $permission->label() }}</strong><small class="mt-1 block leading-5 text-slate-500">{{ $permission->description() }}</small></span></label>@endforeach</div><x-input-error :messages="$errors->get('permissions')" class="mt-2" /></fieldset>
                    @endif
                    @if(!$editingUser)<div class="grid gap-4 sm:grid-cols-2"><div><x-input-label for="user-password" value="Contraseña temporal" /><x-text-input id="user-password" wire:model="password" type="password" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div><div><x-input-label for="user-password-confirmation" value="Confirmar contraseña" /><x-text-input id="user-password-confirmation" wire:model="password_confirmation" type="password" class="mt-1 block w-full" /></div></div>@endif
                </div>
                <footer class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4"><button type="button" wire:click="closeModal('showUserModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-black text-white">{{ $editingUser ? 'Guardar cambios' : 'Crear usuario' }}</button></footer>
            </form>
        </section></div>
    @endif

    @if($showPasswordModal && $passwordUser)
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4"><button type="button" wire:click="closeModal('showPasswordModal')" class="fixed inset-0 bg-slate-950/60"></button><section class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl"><form wire:submit="resetPassword"><header class="border-b border-slate-200 px-6 py-5"><p class="text-xs font-black uppercase tracking-[0.18em] text-violet-600">Seguridad</p><h2 class="mt-1 text-xl font-black text-slate-950">Restablecer contraseña</h2><p class="mt-1 text-sm text-slate-500">{{ $passwordUser->name }}</p></header><div class="space-y-4 p-6"><p class="rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">Al guardar se cerrarán todas las sesiones actuales de este usuario.</p><div><x-input-label for="reset-password" value="Nueva contraseña" /><x-text-input id="reset-password" wire:model="resetPasswordValue" type="password" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('resetPasswordValue')" class="mt-2" /></div><div><x-input-label for="reset-password-confirm" value="Confirmar contraseña" /><x-text-input id="reset-password-confirm" wire:model="resetPasswordConfirmation" type="password" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('resetPasswordConfirmation')" class="mt-2" /></div></div><footer class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4"><button type="button" wire:click="closeModal('showPasswordModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-black text-white">Restablecer</button></footer></form></section></div>
    @endif

    @if($showSuspensionModal && $suspensionUser)
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4"><button type="button" wire:click="closeModal('showSuspensionModal')" class="fixed inset-0 bg-slate-950/60"></button><section class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl"><form wire:submit="suspend"><header class="border-b border-slate-200 px-6 py-5"><p class="text-xs font-black uppercase tracking-[0.18em] text-red-600">Suspender acceso</p><h2 class="mt-1 text-xl font-black text-slate-950">{{ $suspensionUser->name }}</h2></header><div class="p-6"><p class="mb-4 text-sm text-slate-600">El usuario perderá el acceso inmediatamente y se cerrarán sus sesiones activas.</p><x-input-label for="suspension-reason" value="Motivo de suspensión" /><textarea id="suspension-reason" wire:model="suspensionReason" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-red-500 focus:ring-red-500" placeholder="Describe por qué se suspende esta cuenta"></textarea><x-input-error :messages="$errors->get('suspensionReason')" class="mt-2" /></div><footer class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4"><button type="button" wire:click="closeModal('showSuspensionModal')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="submit" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-black text-white">Suspender usuario</button></footer></form></section></div>
    @endif
</div>
