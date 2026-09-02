<?php

namespace App\Livewire\Forms;

use App\Enums\UserAccessEvent;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserAccessLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
            'is_active' => true,
        ], $this->remember)) {
            $user = User::query()->where('email', mb_strtolower(trim($this->email)))->first();
            $event = $user && ! $user->is_active && Hash::check($this->password, $user->password)
                ? UserAccessEvent::BlockedLogin
                : UserAccessEvent::FailedLogin;
            app(UserAccessLogger::class)->record($event, $user, email: mb_strtolower(trim($this->email)));
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => $event === UserAccessEvent::BlockedLogin
                    ? 'Esta cuenta está suspendida. Contacta a un administrador.'
                    : trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        /** @var User $user */
        $user = Auth::user();
        if ($user->role === UserRole::Administrator && config('security.two_factor.enabled')) {
            session()->put('two_factor_pending_user_id', $user->id);

            return;
        }
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();
        app(UserAccessLogger::class)->record(UserAccessEvent::Login, $user);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
