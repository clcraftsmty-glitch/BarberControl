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

@php($businessSettings = \App\Models\BusinessSetting::current())

<div>
    <div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"></div>

    <aside
        :class="[
            sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            sidebarCollapsed ? 'lg:w-20' : 'lg:w-72',
        ]"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col overflow-x-hidden bg-slate-950 text-white transition-[width,transform] duration-200 ease-out lg:translate-x-0"
    >
        <div :class="sidebarCollapsed ? 'lg:justify-center lg:px-3' : ''" class="flex h-20 shrink-0 items-center justify-between border-b border-white/10 px-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                <span class="flex shrink-0 items-center justify-center {{ $businessSettings->logoUrl() ? 'h-16 w-16' : 'h-10 w-10 rounded-xl bg-brand-600 shadow-lg shadow-brand-950/40' }}">
                    @if($businessSettings->logoUrl())
                        <img src="{{ $businessSettings->logoUrl() }}" alt="{{ $businessSettings->business_name }}" class="h-16 w-16 object-contain">
                    @else
                        <x-application-logo class="h-6 w-6 text-white" />
                    @endif
                </span>
                <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">
                    <strong class="block max-w-44 truncate text-lg tracking-tight">{{ $businessSettings->business_name }}</strong>
                    <small class="block max-w-44 truncate text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-400">{{ $businessSettings->ticket_header ?: 'Gestión profesional' }}</small>
                </span>
            </a>
            <button type="button" @click="sidebarOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:hidden" aria-label="Cerrar menú">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <nav :class="sidebarCollapsed ? 'lg:px-3' : ''" class="flex-1 space-y-8 overflow-y-auto overflow-x-hidden px-4 py-7">
            <div>
                <p :class="{ 'lg:hidden': sidebarCollapsed }" class="px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Principal</p>
                <a href="{{ route('dashboard') }}" wire:navigate title="Dashboard" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-3 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('dashboard') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12 12 3l9 9M5 10v10h14V10M9 20v-6h6v6" /></svg>
                    <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Dashboard</span>
                </a>
                <a href="{{ route('appointments.index') }}" wire:navigate title="Agenda" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('appointments.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15 12h13.5a1.5 1.5 0 0 0 1.5-1.5V6.75a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5V19.5a1.5 1.5 0 0 0 1.5 1.5Z" /></svg>
                    <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Agenda</span>
                </a>
                @can('viewAny', \App\Models\CashRegisterSession::class)
                    <a href="{{ route('cash-register.index') }}" wire:navigate title="Caja" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('cash-register.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.5 7.5h15m-12-3h9A1.5 1.5 0 0 1 18 6v12a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 18V6a1.5 1.5 0 0 1 1.5-1.5Zm3 7.5h3" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Caja</span>
                    </a>
                @endcan
                @can('viewAny', \App\Models\Sale::class)
                    <a href="{{ route('sales.index') }}" wire:navigate title="Ventas" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('sales.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 2.25h10.5A2.25 2.25 0 0 1 19.5 4.5v17.25L16.5 20l-3 1.75-3-1.75-3 1.75V4.5a2.25 2.25 0 0 1 2.25-2.25ZM10 7.5h6m-6 4h6m-6 4h3" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Ventas</span>
                    </a>
                @endcan
                @can('viewAny', \App\Models\CommissionSettlement::class)
                    <a href="{{ route('commissions.index') }}" wire:navigate title="Comisiones" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('commissions.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m3-9.75c-.75-.75-1.8-1.125-3-1.125-1.657 0-3 .84-3 1.875s1.343 1.875 3 1.875 3 .84 3 1.875-1.343 1.875-3 1.875c-1.2 0-2.25-.375-3-1.125M4.5 4.5h15v15h-15z" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Comisiones</span>
                    </a>
                @endcan
                @can('viewAny', \App\Models\WhatsAppMessage::class)
                    <a href="{{ route('whatsapp.index') }}" wire:navigate title="WhatsApp" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('whatsapp.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 11.25c0 4.142-4.03 7.5-9 7.5a10.2 10.2 0 0 1-3.04-.456L3 20.25l1.78-4.15A6.7 6.7 0 0 1 3 11.25c0-4.142 4.03-7.5 9-7.5s9 3.358 9 7.5Z" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">WhatsApp</span>
                    </a>
                @endcan
                <a href="{{ route('clients.index') }}" wire:navigate title="Clientes" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('clients.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" /></svg>
                    <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Clientes</span>
                </a>
                <a href="{{ route('services.index') }}" wire:navigate title="Servicios" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('services.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m-3-9h6M5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25V6.75A2.25 2.25 0 0 1 5.25 4.5Z" /></svg>
                    <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Servicios</span>
                </a>
                <a href="{{ route('barbers.index') }}" wire:navigate title="Barberos" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs('barbers.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 7.5a6 6 0 0 1-12 0M4.5 20.25a7.5 7.5 0 0 1 15 0M9 7.5h6" /></svg>
                    <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Barberos</span>
                </a>
            </div>

            <div>
                <p :class="{ 'lg:hidden': sidebarCollapsed }" class="px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Cuenta</p>
                @can('viewAny', \App\Models\User::class)
                    <a href="{{ route('users.index') }}" wire:navigate title="Usuarios" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-3 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 18.72a9.1 9.1 0 0 0 3.75.78 8.96 8.96 0 0 0-1.64-5.16 4.5 4.5 0 0 0-6.22-6.22A9 9 0 0 0 12 12.75m6 5.97A9 9 0 0 0 12 12.75m6 5.97A9 9 0 0 1 3.75 19.5a8.96 8.96 0 0 1 1.64-5.16 4.5 4.5 0 1 1 6.22-6.22 9 9 0 0 1 .39 4.63m0 0a5.25 5.25 0 0 0-8.25 4.3" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Usuarios</span>
                    </a>
                @endcan
                @can('viewAny', \App\Models\BusinessSetting::class)
                    <a href="{{ route('settings.business') }}" wire:navigate title="Configuración" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.6 3.6h4.8l.65 2.1a7.1 7.1 0 0 1 1.4.8l2.1-.45 2.4 4.15-1.45 1.65v1.6l1.45 1.65-2.4 4.15-2.1-.45a7.1 7.1 0 0 1-1.4.8l-.65 2.1H9.6l-.65-2.1a7.1 7.1 0 0 1-1.4-.8l-2.1.45-2.4-4.15 1.45-1.65v-1.6L3.05 10.2l2.4-4.15 2.1.45a7.1 7.1 0 0 1 1.4-.8l.65-2.1ZM12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Configuración</span>
                    </a>
                @endcan
                @can('manage-security')
                    <a href="{{ route('security.index') }}" wire:navigate title="Respaldos y seguridad" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('security.*') ? 'bg-brand-600 text-white shadow-lg shadow-brand-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3.75 19.5 6v5.25c0 4.5-3.15 8.7-7.5 9.75-4.35-1.05-7.5-5.25-7.5-9.75V6L12 3.75Zm-2.25 8.1 1.5 1.5 3.25-3.25" /></svg>
                        <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Seguridad</span>
                    </a>
                @endcan
                <a href="{{ route('profile') }}" wire:navigate title="Mi perfil" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="mt-3 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('profile') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" /></svg>
                    <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Mi perfil</span>
                </a>
            </div>
        </nav>

        <div :class="sidebarCollapsed ? 'lg:p-3' : ''" class="border-t border-white/10 p-4">
            <div :class="{ 'lg:hidden': sidebarCollapsed }" class="mb-3 rounded-xl bg-white/5 p-3">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-xs text-slate-400">{{ auth()->user()->role->label() }}</p>
            </div>
            <button wire:click="logout" type="button" title="Cerrar sesión" :class="sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-2' : ''" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-red-500/10 hover:text-red-300">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                <span :class="{ 'lg:hidden': sidebarCollapsed }" class="whitespace-nowrap">Cerrar sesión</span>
            </button>
        </div>
    </aside>
</div>
