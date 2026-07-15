<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"></div>

    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-950 text-white transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-20 items-center justify-between border-b border-white/10 px-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 shadow-lg shadow-brand-950/40">
                    <x-application-logo class="h-6 w-6 text-white" />
                </span>
                <span>
                    <strong class="block text-lg tracking-tight">BarberControl</strong>
                    <small class="block text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-400">Gestión profesional</small>
                </span>
            </a>
            <button type="button" @click="sidebarOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:hidden" aria-label="Cerrar menú">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-8 overflow-y-auto px-4 py-7">
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Principal</p>
                <a href="{{ route('dashboard') }}" wire:navigate class="mt-3 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12 12 3l9 9M5 10v10h14V10M9 20v-6h6v6" /></svg>
                    Dashboard
                </a>
                <a href="{{ route('appointments.index') }}" wire:navigate class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('appointments.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5V19.5a1.5 1.5 0 0 0 1.5 1.5Z" /></svg>
                    Agenda
                </a>
                <a href="{{ route('clients.index') }}" wire:navigate class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('clients.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" /></svg>
                    Clientes
                </a>
                <a href="{{ route('services.index') }}" wire:navigate class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('services.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m-3-9h6M5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25V6.75A2.25 2.25 0 0 1 5.25 4.5Z" /></svg>
                    Servicios
                </a>
                <a href="{{ route('barbers.index') }}" wire:navigate class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('barbers.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 7.5a6 6 0 0 1-12 0M4.5 20.25a7.5 7.5 0 0 1 15 0M9 7.5h6" /></svg>
                    Barberos
                </a>
            </div>

            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Cuenta</p>
                <a href="{{ route('profile') }}" wire:navigate class="mt-3 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('profile') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" /></svg>
                    Mi perfil
                </a>
            </div>
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="mb-3 rounded-xl bg-white/5 p-3">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-xs text-slate-400">{{ auth()->user()->role->label() }}</p>
            </div>
            <button wire:click="logout" type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-red-500/10 hover:text-red-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                Cerrar sesión
            </button>
        </div>
    </aside>
</div>
