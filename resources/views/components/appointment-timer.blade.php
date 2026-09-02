@props([
    'label',
    'startedAt' => null,
    'seconds' => null,
    'tone' => 'slate',
    'icon' => 'clock',
])

@php
    $toneClasses = match ($tone) {
        'cyan' => 'border-cyan-200 bg-cyan-100 text-cyan-950',
        'violet' => 'border-violet-200 bg-violet-100 text-violet-950',
        default => 'border-slate-200 bg-slate-100 text-slate-800',
    };
    $isoStart = $startedAt?->toIso8601String();
    $formattedSeconds = $seconds !== null
        ? sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
        : null;
@endphp

<div
    @if ($isoStart)
        x-data="{
            startedAt: new Date(@js($isoStart)).getTime(),
            elapsed: 0,
            timer: null,
            init() {
                this.tick();
                this.timer = setInterval(() => this.tick(), 1000);
            },
            destroy() {
                clearInterval(this.timer);
            },
            tick() {
                this.elapsed = Math.max(0, Math.floor((Date.now() - this.startedAt) / 1000));
            },
            formatted() {
                const hours = Math.floor(this.elapsed / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((this.elapsed % 3600) / 60).toString().padStart(2, '0');
                const seconds = (this.elapsed % 60).toString().padStart(2, '0');

                return `${hours}:${minutes}:${seconds}`;
            }
        }"
    @endif
    class="mt-2 inline-flex min-w-[168px] items-center gap-2 rounded-xl border px-2.5 py-2 {{ $toneClasses }}"
    title="{{ $label }}"
>
    @if ($icon === 'wait')
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3.75h10.5m-10.5 16.5h10.5M8.25 3.75v3.086c0 .597.237 1.169.659 1.591L12 11.518l3.091-3.091a2.25 2.25 0 0 0 .659-1.591V3.75m0 16.5v-3.086a2.25 2.25 0 0 0-.659-1.591L12 12.482l-3.091 3.091a2.25 2.25 0 0 0-.659 1.591v3.086" />
        </svg>
    @else
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    @endif

    <span class="min-w-0 leading-tight">
        <span class="block text-[10px] font-extrabold uppercase tracking-wide opacity-70">{{ $label }}</span>
        @if ($isoStart)
            <strong class="mt-0.5 block font-mono text-base font-black tabular-nums" x-text="formatted()">00:00:00</strong>
        @elseif ($formattedSeconds !== null)
            <strong class="mt-0.5 block font-mono text-base font-black tabular-nums">{{ $formattedSeconds }}</strong>
        @else
            <strong class="mt-0.5 block text-xs font-bold">Sin medición</strong>
        @endif
    </span>
</div>
