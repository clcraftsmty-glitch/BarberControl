<div>
    <div class="mb-6"><p class="text-xs font-black uppercase tracking-[0.18em] text-brand-600">Segundo factor</p><h1 class="mt-1 text-2xl font-black text-slate-950">Confirma que eres tú</h1><p class="mt-2 text-sm text-slate-600">Introduce el código de tu aplicación de autenticación.</p></div>
    <form wire:submit="verify" class="space-y-5">
        @if(!$useRecoveryCode)
            <div><x-input-label for="challenge-code" value="Código de 6 dígitos" /><x-text-input id="challenge-code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus class="mt-2 block w-full text-center text-2xl font-black tracking-[0.4em]" /><x-input-error :messages="$errors->get('code')" class="mt-2" /></div>
        @else
            <div><x-input-label for="recovery-code" value="Código de recuperación" /><x-text-input id="recovery-code" wire:model="recoveryCode" autocomplete="one-time-code" autofocus class="mt-2 block w-full text-center font-mono text-lg font-black uppercase tracking-wide" /><x-input-error :messages="$errors->get('recoveryCode')" class="mt-2" /></div>
        @endif
        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-black text-white hover:bg-brand-700 disabled:opacity-60">Verificar acceso</button>
        <button type="button" wire:click="$toggle('useRecoveryCode')" class="w-full text-sm font-bold text-brand-700">{{ $useRecoveryCode ? 'Usar código de la aplicación' : 'Usar un código de recuperación' }}</button>
        <button type="button" wire:click="cancel" class="w-full text-sm font-semibold text-slate-500">Cancelar y cerrar sesión</button>
    </form>
</div>
