<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();

        if (auth()->user()->role === \App\Enums\UserRole::Administrator && config('security.two_factor.enabled')) {
            $this->redirectRoute(auth()->user()->hasConfirmedTwoFactor() ? 'two-factor.challenge' : 'two-factor.setup', navigate: true);

            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-7">
        <p class="text-sm font-semibold text-brand-600">Bienvenido de nuevo</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">Inicia sesión</h2>
        <p class="mt-2 text-sm text-slate-500">Ingresa tus credenciales para acceder al panel.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input wire:model="form.email" id="email" class="mt-2 block w-full" type="email" name="email" required autofocus autocomplete="username" placeholder="nombre@barberia.com" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Contraseña" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-brand-600 hover:text-brand-700" href="{{ route('password.request') }}" wire:navigate>¿Olvidaste tu contraseña?</a>
                @endif
            </div>
            <x-text-input wire:model="form.password" id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <label for="remember" class="flex items-center gap-2 text-sm text-slate-600">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
            Mantener mi sesión iniciada
        </label>

        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-brand-200 transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2" wire:loading.attr="disabled">
            <span wire:loading.remove>Ingresar al sistema</span>
            <span wire:loading>Validando...</span>
        </button>
    </form>
</div>
