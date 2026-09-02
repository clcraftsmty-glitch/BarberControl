<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupDownloadController extends Controller
{
    public function __invoke(DatabaseBackup $databaseBackup, AuditLogger $audit): BinaryFileResponse
    {
        Gate::authorize('manage-security');
        abort_unless($databaseBackup->isAvailable() && Storage::disk($databaseBackup->disk)->exists($databaseBackup->path), 404);
        $path = Storage::disk($databaseBackup->disk)->path($databaseBackup->path);
        abort_unless(hash_equals((string) $databaseBackup->sha256, hash_file('sha256', $path)), 409, 'El respaldo no superó la verificación de integridad.');
        $audit->record('backup_downloaded', 'Respaldo '.$databaseBackup->filename.' descargado', $databaseBackup);

        return response()->download($path, $databaseBackup->filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
