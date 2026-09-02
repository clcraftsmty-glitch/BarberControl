<?php

namespace App\Livewire\Security;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\SystemError;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Center extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $auditSearch = '';

    public string $auditAction = 'all';

    public bool $onlyOpenErrors = true;

    public function mount(): void
    {
        $this->authorize('manage-security');
    }

    public function createBackup(DatabaseBackupService $backups): void
    {
        $this->authorize('manage-security');
        try {
            $backup = $backups->create(auth()->user());
            session()->flash('status', 'Respaldo cifrado creado: '.$backup->filename);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'No fue posible crear el respaldo: '.$exception->getMessage());
        }
    }

    public function resolveError(int $errorId, AuditLogger $audit): void
    {
        $this->authorize('manage-security');
        $error = SystemError::query()->findOrFail($errorId);
        $error->update(['resolved_at' => now(), 'resolved_by' => auth()->id()]);
        $audit->record('error_resolved', 'Error #'.$error->id.' marcado como resuelto', $error);
    }

    public function updatedAuditSearch(): void
    {
        $this->resetPage('auditPage');
    }

    public function render(): View
    {
        $this->authorize('manage-security');
        $backups = DatabaseBackup::query()->with('creator:id,name')->latest('started_at')->paginate(8, ['*'], 'backupPage');
        foreach ($backups as $backup) {
            $backup->file_exists = $backup->isAvailable() && Storage::disk($backup->disk)->exists($backup->path);
        }

        $audits = AuditLog::query()->with('actor:id,name')
            ->when($this->auditAction !== 'all', fn ($query) => $query->where('action', $this->auditAction))
            ->when($this->auditSearch !== '', function ($query): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->auditSearch)).'%';
                $query->where(fn ($inner) => $inner->where('description', 'like', $search)->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', $search)));
            })
            ->latest('occurred_at')
            ->paginate(15, ['*'], 'auditPage');

        $errors = SystemError::query()->with(['user:id,name', 'resolver:id,name'])
            ->when($this->onlyOpenErrors, fn ($query) => $query->whereNull('resolved_at'))
            ->latest('last_occurred_at')
            ->paginate(10, ['*'], 'errorPage');

        $administrators = User::query()->where('role', UserRole::Administrator)->where('is_active', true);

        return view('livewire.security.center', [
            'backups' => $backups,
            'audits' => $audits,
            'errors' => $errors,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'securityChecks' => [
                ['label' => 'HTTPS obligatorio', 'ok' => (bool) config('security.force_https'), 'detail' => config('security.force_https') ? 'Redirección y enlaces seguros activos' : 'Actívalo en producción con SECURITY_FORCE_HTTPS=true'],
                ['label' => 'Sesiones cifradas', 'ok' => (bool) config('session.encrypt'), 'detail' => config('session.encrypt') ? 'Contenido de sesión cifrado' : 'Configure SESSION_ENCRYPT=true'],
                ['label' => 'Cookie HttpOnly', 'ok' => (bool) config('session.http_only'), 'detail' => 'Protege la cookie contra acceso desde JavaScript'],
                ['label' => 'Segundo factor', 'ok' => (bool) config('security.two_factor.enabled'), 'detail' => $administrators->clone()->whereNotNull('two_factor_confirmed_at')->count().' de '.$administrators->count().' administradores activos configurados'],
                ['label' => 'Monitoreo de errores', 'ok' => (bool) config('security.monitoring.enabled'), 'detail' => 'Agrupa excepciones sin guardar contraseñas ni parámetros'],
            ],
        ])->layout('layouts.app');
    }
}
