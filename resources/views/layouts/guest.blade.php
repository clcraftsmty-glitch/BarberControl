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
    </head>
    <body class="bg-slate-950 font-sans text-slate-900 antialiased">
        @php($businessSettings = \App\Models\BusinessSetting::current())
        <main class="grid min-h-screen lg:grid-cols-2">
            <section class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-brand-600/20 blur-3xl"></div>
                <a href="/" class="relative flex items-center gap-3">
                    <span class="flex items-center justify-center {{ $businessSettings->logoUrl() ? 'h-24 w-24' : 'h-11 w-11 rounded-xl bg-brand-600' }}">@if($businessSettings->logoUrl())<img src="{{ $businessSettings->logoUrl() }}" alt="" class="h-24 w-24 object-contain">@else<x-application-logo class="h-7 w-7 text-white" />@endif</span>
                    <span class="text-xl font-bold">{{ $businessSettings->business_name }}</span>
                </a>
                <div class="relative max-w-lg">
                    <p class="text-sm font-bold uppercase tracking-[0.22em] text-brand-400">Control que se nota</p>
                    <h1 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight xl:text-5xl">Tu barbería, organizada desde el primer corte.</h1>
                    <p class="mt-5 text-lg leading-8 text-slate-400">Una base moderna y segura para administrar tu equipo y hacer crecer la operación.</p>
                </div>
                <p class="relative text-sm text-slate-500">© {{ date('Y') }} {{ $businessSettings->business_name }}</p>
            </section>

            <section class="flex items-center justify-center bg-slate-50 px-5 py-10 sm:px-8">
                <div class="w-full max-w-md">
                    <a href="/" class="mb-8 flex items-center justify-center gap-3 lg:hidden">
                        <span class="flex items-center justify-center {{ $businessSettings->logoUrl() ? 'h-24 w-24' : 'h-11 w-11 rounded-xl bg-brand-600' }}">@if($businessSettings->logoUrl())<img src="{{ $businessSettings->logoUrl() }}" alt="" class="h-24 w-24 object-contain">@else<x-application-logo class="h-7 w-7 text-white" />@endif</span>
                        <span class="text-xl font-bold">{{ $businessSettings->business_name }}</span>
                    </a>
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
                        {{ $slot }}
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
