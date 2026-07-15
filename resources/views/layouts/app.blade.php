<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'BarberControl') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js" data-navigate-once></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/locales-all.global.min.js" data-navigate-once></script>
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            <livewire:layout.navigation />

            <div class="lg:pl-72">
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div class="flex h-16 items-center gap-4 px-4 sm:px-6 lg:px-8">
                        <button type="button" @click="sidebarOpen = true" class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Abrir menú">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <div class="min-w-0 flex-1">
                            @isset($header)
                                {{ $header }}
                            @endisset
                        </div>
                        <div class="hidden items-center gap-3 sm:flex">
                            <div class="text-right">
                                <p class="text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700">
                                {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                            </div>
                        </div>
                    </div>
                </header>

                <main class="p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
