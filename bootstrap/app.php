<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureAdministratorTwoFactor;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Services\ErrorMonitor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('web', ForceHttps::class);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->appendToGroup('web', EnsureAdministratorTwoFactor::class);

        if ($proxies = env('SECURITY_TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: array_map('trim', explode(',', $proxies)));
        }

        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            app(ErrorMonitor::class)->record($exception);
        });
    })->create();
