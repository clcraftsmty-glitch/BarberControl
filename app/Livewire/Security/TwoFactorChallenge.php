<?php

namespace App\Livewire\Security;

use App\Enums\UserAccessEvent;
use App\Livewire\Actions\Logout;
use App\Services\AuditLogger;
use App\Services\AuthenticationCompletionService;
use App\Services\TotpService;
use App\Services\UserAccessLogger;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';

    public string $recoveryCode = '';

    public bool $useRecoveryCode = false;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        if (! auth()->user()->hasConfirmedTwoFactor()) {
            $this->redirectRoute('two-factor.setup');
        }
    }

    public function verify(TotpService $totp, AuthenticationCompletionService $authentication, AuditLogger $audit): void
    {
        $key = 'two-factor:'.auth()->id().'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($key).' segundos.']);
        }

        $user = auth()->user();
        $event = UserAccessEvent::TwoFactorChallenge;
        $valid = false;

        if ($this->useRecoveryCode) {
            $submitted = strtoupper(trim($this->recoveryCode));
            $codes = $user->two_factor_recovery_codes ?? [];
            $index = collect($codes)->search(fn (string $code): bool => hash_equals($code, $submitted));
            if ($index !== false) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();
                $event = UserAccessEvent::RecoveryCodeUsed;
                $valid = true;
                $audit->record('two_factor_recovery_used', 'Código de recuperación utilizado por '.$user->email, $user);
            }
        } else {
            $valid = $totp->verify((string) $user->two_factor_secret, $this->code);
        }

        if (! $valid) {
            RateLimiter::hit($key, 60);
            app(UserAccessLogger::class)->record(UserAccessEvent::TwoFactorFailed, $user);
            throw ValidationException::withMessages([
                $this->useRecoveryCode ? 'recoveryCode' : 'code' => 'El código proporcionado no es válido.',
            ]);
        }

        RateLimiter::clear($key);
        $authentication->complete($user, $event);
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function cancel(Logout $logout): void
    {
        $logout();
        $this->redirectRoute('login', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.security.two-factor-challenge')->layout('layouts.guest');
    }
}
