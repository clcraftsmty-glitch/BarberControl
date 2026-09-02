<?php

namespace App\Http\Middleware;

use App\Enums\UserAccessEvent;
use App\Services\UserAccessLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(private UserAccessLogger $accessLogger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $this->accessLogger->record(UserAccessEvent::BlockedLogin, $user, email: $user->email, details: 'Sesión invalidada por suspensión.');
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Tu cuenta está suspendida. Contacta a un administrador.');
        }

        return $next($request);
    }
}
