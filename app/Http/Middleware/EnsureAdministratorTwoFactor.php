<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministratorTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== UserRole::Administrator || ! config('security.two_factor.enabled')) {
            return $next($request);
        }
        if (app()->environment('testing') && ! config('security.two_factor.enforce_in_tests')) {
            return $next($request);
        }
        if ($request->routeIs('two-factor.*') || $request->routeIs('livewire.update')) {
            return $next($request);
        }
        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }
        if ((int) $request->session()->get('two_factor_verified_for') !== (int) $user->id) {
            $request->session()->put('two_factor_pending_user_id', $user->id);

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
