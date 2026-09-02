<?php

namespace App\Livewire\Actions;

use App\Enums\UserAccessEvent;
use App\Services\UserAccessLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): void
    {
        if ($user = Auth::user()) {
            app(UserAccessLogger::class)->record(UserAccessEvent::Logout, $user);
        }

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
