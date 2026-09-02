<div>
    <div class="mb-6"><p class="text-xs font-black uppercase tracking-[0.18em] text-brand-600">Seguridad obligatoria</p><h1 class="mt-1 text-2xl font-black text-slate-950">Activa la verificación en dos pasos</h1><p class="mt-2 text-sm leading-6 text-slate-600">Los administradores deben usar un código temporal además de su contraseña.</p></div>
    @if(!$completed)
        <div class="space-y-5">
            <ol class="space-y-3 text-sm text-slate-700"><li><strong>1.</strong> Abre Google Authenticator, Microsoft Authenticator, Authy o cualquier aplicación TOTP.</li><li><strong>2.</strong> Presiona el botón <strong>+</strong>, elige <strong>Escanear código QR</strong> y apunta la cámara al recuadro:</li></ol>
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5 text-center">
                <div class="mx-auto w-fit rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <img src="{{ $qrCodeDataUri }}" alt="Código QR para configurar el autenticador" width="280" height="280" class="h-auto w-64 max-w-full sm:w-72">
                </div>
                <p class="mt-3 text-xs font-bold text-brand-800">Escanea este QR solamente con tu aplicación autenticadora.</p>
            </div>
            <details class="rounded-xl border border-slate-200 p-3 text-sm text-slate-600">
                <summary class="cursor-pointer font-bold text-slate-800">No puedo escanear el QR</summary>
                <p class="mt-3">Selecciona la opción para introducir una clave de configuración y escribe:</p>
                <div class="mt-2 rounded-lg bg-slate-100 p-3 text-center"><code class="break-all text-base font-black tracking-[0.12em] text-slate-900">{{ $secret }}</code></div>
                <p class="mt-2 text-xs">Tipo de clave: <strong>Basada en tiempo</strong>.</p>
            </details>
            <details class="rounded-xl border border-slate-200 p-3 text-xs text-slate-500"><summary class="cursor-pointer font-bold text-slate-700">Mostrar URI de configuración avanzada</summary><code class="mt-2 block break-all">{{ $provisioningUri }}</code></details>
            <form wire:submit="confirm" class="space-y-4 border-t border-slate-200 pt-5">
                <div><x-input-label for="two-factor-password" value="Confirma tu contraseña" /><x-text-input id="two-factor-password" wire:model="password" type="password" autocomplete="current-password" class="mt-1 block w-full" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                <div><x-input-label for="two-factor-code" value="Código de 6 dígitos de la aplicación" /><x-text-input id="two-factor-code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="mt-1 block w-full text-center text-xl font-black tracking-[0.35em]" /><x-input-error :messages="$errors->get('code')" class="mt-2" /></div>
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-black text-white hover:bg-brand-700 disabled:opacity-60">Verificar y activar</button>
            </form>
        </div>
    @else
        <div class="space-y-5"><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">Segundo factor activado correctamente.</div><div><h2 class="font-black text-slate-950">Guarda estos códigos de recuperación</h2><p class="mt-1 text-sm text-slate-600">Cada código funciona una sola vez. Consérvalos fuera de este equipo.</p><div class="mt-3 grid grid-cols-1 gap-2 rounded-xl bg-slate-950 p-4 sm:grid-cols-2">@foreach($recoveryCodes as $recoveryCode)<code class="text-center text-sm font-bold tracking-wide text-white">{{ $recoveryCode }}</code>@endforeach</div></div><button type="button" wire:click="continue" class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-black text-white">Ya guardé los códigos</button></div>
    @endif
</div>
