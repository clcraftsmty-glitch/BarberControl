<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Vista general</p>
            <h1 class="truncate text-lg font-bold text-slate-900">Dashboard</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl shadow-slate-200 sm:p-8">
            <div class="relative z-10 max-w-2xl">
                <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200">{{ auth()->user()->role->label() }}</span>
                <h2 class="mt-5 text-2xl font-bold tracking-tight sm:text-3xl">Hola, {{ str(auth()->user()->name)->before(' ') }}.</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">BarberControl está listo para centralizar la operación de tu barbería. Esta base incluye autenticación, perfiles y control de acceso por roles.</p>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    <div><p class="text-sm font-semibold text-slate-900">Sistema disponible</p><p class="text-xs text-slate-500">Servicios base activos</p></div>
                </div>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 18.75h-9m9 0a3 3 0 0 0 3-3V8.25a3 3 0 0 0-3-3h-9a3 3 0 0 0-3 3v7.5a3 3 0 0 0 3 3m9 0H12m-4.5-9h9m-9 3h6" /></svg>
                    </span>
                    <div><p class="text-sm font-semibold text-slate-900">Arquitectura modular</p><p class="text-xs text-slate-500">Preparada para crecer</p></div>
                </div>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </span>
                    <div><p class="text-sm font-semibold text-slate-900">Acceso protegido</p><p class="text-xs text-slate-500">Roles y sesión configurados</p></div>
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-900">Base de la aplicación</h3>
            <p class="mt-1 text-sm text-slate-500">Componentes habilitados en esta primera etapa.</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['Autenticación segura', 'Diseño responsivo', 'Roles de usuario', 'Componentes Livewire'] as $feature)
                    <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-brand-500"></span>{{ $feature }}
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
