<?php

namespace App\Services;

use App\Models\SystemError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorMonitor
{
    public function record(Throwable $exception): void
    {
        if (! config('security.monitoring.enabled') || $this->shouldIgnore($exception)) {
            return;
        }

        try {
            if (! Schema::hasTable('system_errors')) {
                return;
            }

            $request = request();
            $path = $request?->path();
            $fingerprint = hash('sha256', $exception::class.'|'.$exception->getFile().'|'.$exception->getLine().'|'.$path);
            $message = mb_substr(preg_replace('/\s+/', ' ', $exception->getMessage()) ?: 'Error sin mensaje', 0, 1000);

            $error = SystemError::query()->firstOrNew(['fingerprint' => $fingerprint]);
            if (! $error->exists) {
                $error->first_occurred_at = now();
                $error->occurrences = 0;
            }
            $error->fill([
                'exception_class' => $exception::class,
                'message' => $message,
                'route_name' => $request?->route()?->getName(),
                'request_method' => $request?->method(),
                'request_path' => $path ? mb_substr($path, 0, 500) : null,
                'status_code' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
                'user_id' => auth()->id(),
                'occurrences' => $error->occurrences + 1,
                'last_occurred_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ])->save();
        } catch (Throwable $monitoringFailure) {
            error_log('BarberControl error monitor failed: '.$monitoringFailure->getMessage());
        }
    }

    private function shouldIgnore(Throwable $exception): bool
    {
        if ($exception instanceof ValidationException || $exception instanceof AuthenticationException || $exception instanceof AuthorizationException) {
            return true;
        }

        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500;
    }
}
