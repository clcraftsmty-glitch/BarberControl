<?php

namespace App\Livewire\Security;

use App\Enums\UserAccessEvent;
use App\Enums\UserRole;
use App\Services\AuditLogger;
use App\Services\AuthenticationCompletionService;
use App\Services\TotpService;
use App\Services\TwoFactorQrCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class TwoFactorSetup extends Component
{
    public string $secret = '';

    public string $provisioningUri = '';

    public string $password = '';

    public string $code = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public bool $completed = false;

    public function mount(TotpService $totp): void
    {
        abort_unless(auth()->user()?->role === UserRole::Administrator, 403);
        if (auth()->user()->hasConfirmedTwoFactor()) {
            $this->redirectRoute('two-factor.challenge');

            return;
        }
        $this->secret = session('two_factor_setup_secret') ?: $totp->generateSecret();
        session()->put('two_factor_setup_secret', $this->secret);
        $this->provisioningUri = $totp->provisioningUri(auth()->user(), $this->secret);
    }

    public function confirm(TotpService $totp, AuthenticationCompletionService $authentication, AuditLogger $audit): void
    {
        $this->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        $user = auth()->user();
        if (! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña actual no es correcta.']);
        }
        if (! $totp->verify($this->secret, $this->code)) {
            throw ValidationException::withMessages(['code' => 'El código no es válido. Verifica la hora de tu teléfono.']);
        }

        $this->recoveryCodes = $totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => $this->recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->saveQuietly();
        $audit->record('two_factor_enabled', 'Segundo factor activado para '.$user->email, $user);
        $authentication->complete($user, UserAccessEvent::TwoFactorEnabled);
        $this->password = '';
        $this->code = '';
        $this->completed = true;
    }

    public function continue(): void
    {
        abort_unless($this->completed, 403);
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(TwoFactorQrCode $qrCode): View
    {
        return view('livewire.security.two-factor-setup', [
            'qrCodeDataUri' => $qrCode->dataUri($this->provisioningUri),
        ])->layout('layouts.guest');
    }
}
