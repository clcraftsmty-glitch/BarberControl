<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function __construct(private BackupCipher $cipher, private AuditLogger $audit) {}

    public function create(?User $actor = null, ?string $connectionName = null): DatabaseBackup
    {
        if (! config('security.backups.enabled')) {
            throw new RuntimeException('Los respaldos están desactivados.');
        }

        $connectionName ??= config('security.backups.connection') ?: config('database.default');
        $connection = config("database.connections.{$connectionName}");
        if (! is_array($connection)) {
            throw new RuntimeException("La conexión {$connectionName} no existe.");
        }
        $driver = (string) ($connection['driver'] ?? '');
        $format = match ($driver) {
            'mysql' => 'sql',
            'sqlite' => 'sqlite',
            default => throw new RuntimeException("El respaldo todavía no admite el controlador {$driver}."),
        };
        $disk = (string) config('security.backups.disk', 'local');
        $directory = trim((string) config('security.backups.directory', 'backups/database'), '/');
        $filename = 'barbercontrol-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.'.$format.'.bcbk';
        $path = $directory.'/'.$filename;

        $backup = DatabaseBackup::query()->create([
            'filename' => $filename,
            'disk' => $disk,
            'path' => $path,
            'database_connection' => $connectionName,
            'database_driver' => $driver,
            'source_format' => $format,
            'status' => 'running',
            'encrypted' => true,
            'created_by' => $actor?->id,
            'started_at' => now(),
        ]);

        $temporary = storage_path('app/private/backup-tmp/'.Str::uuid().'.'.$format);
        try {
            if (! is_dir(dirname($temporary)) && ! mkdir(dirname($temporary), 0750, true) && ! is_dir(dirname($temporary))) {
                throw new RuntimeException('No fue posible crear el directorio temporal de respaldos.');
            }
            Storage::disk($disk)->makeDirectory($directory);
            $destination = Storage::disk($disk)->path($path);

            match ($driver) {
                'mysql' => $this->dumpMysql($connection, $temporary),
                'sqlite' => $this->dumpSqlite($connectionName, $temporary),
            };
            $this->cipher->encrypt($temporary, $destination);

            $backup->update([
                'status' => 'completed',
                'size_bytes' => filesize($destination),
                'sha256' => hash_file('sha256', $destination),
                'completed_at' => now(),
            ]);
            $this->audit->record('backup_created', 'Respaldo cifrado '.$filename.' creado', $backup);
            $this->prune();

            return $backup->refresh();
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            throw $exception;
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function restore(DatabaseBackup $backup): void
    {
        if (! $backup->isAvailable() || ! Storage::disk($backup->disk)->exists($backup->path)) {
            throw new RuntimeException('El respaldo no está disponible.');
        }
        $encrypted = Storage::disk($backup->disk)->path($backup->path);
        if (! hash_equals((string) $backup->sha256, hash_file('sha256', $encrypted))) {
            throw new RuntimeException('La verificación SHA-256 falló; el respaldo pudo ser alterado.');
        }
        $temporary = storage_path('app/private/backup-tmp/restore-'.Str::uuid().'.'.$backup->source_format);

        try {
            $this->cipher->decrypt($encrypted, $temporary);
            $connection = config("database.connections.{$backup->database_connection}");
            match ($backup->database_driver) {
                'mysql' => $this->restoreMysql($connection, $temporary),
                'sqlite' => $this->restoreSqlite($backup->database_connection, $connection, $temporary),
                default => throw new RuntimeException('Controlador de restauración no compatible.'),
            };
            $this->audit->record('backup_restored', 'Respaldo '.$backup->filename.' restaurado', $backup);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function prune(): int
    {
        $threshold = now()->subDays(max(1, (int) config('security.backups.retention_days', 30)));
        $expired = DatabaseBackup::query()->where('completed_at', '<', $threshold)->get();
        foreach ($expired as $backup) {
            Storage::disk($backup->disk)->delete($backup->path);
            $backup->delete();
        }

        return $expired->count();
    }

    private function dumpSqlite(string $connectionName, string $destination): void
    {
        $quoted = str_replace("'", "''", str_replace('\\', '/', $destination));
        DB::connection($connectionName)->unprepared("VACUUM INTO '{$quoted}'");
    }

    /** @param array<string,mixed> $configuration */
    private function dumpMysql(array $configuration, string $destination): void
    {
        $arguments = [
            (string) config('security.backups.mysqldump_path', 'mysqldump'),
            '--single-transaction', '--quick', '--routines', '--triggers', '--events',
            '--no-tablespaces', '--default-character-set=utf8mb4',
            '--host='.$configuration['host'], '--port='.(string) $configuration['port'],
            '--user='.$configuration['username'], (string) $configuration['database'],
        ];
        $output = fopen($destination, 'wb');
        $process = new Process($arguments, null, ['MYSQL_PWD' => (string) ($configuration['password'] ?? '')]);
        $process->setTimeout(900);
        $process->run(function (string $type, string $buffer) use ($output): void {
            if ($type === Process::OUT) {
                fwrite($output, $buffer);
            }
        });
        fclose($output);
        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump falló: '.trim($process->getErrorOutput()));
        }
    }

    /** @param array<string,mixed> $configuration */
    private function restoreMysql(array $configuration, string $source): void
    {
        $arguments = [
            (string) config('security.backups.mysql_path', 'mysql'),
            '--default-character-set=utf8mb4', '--host='.$configuration['host'],
            '--port='.(string) $configuration['port'], '--user='.$configuration['username'],
            (string) $configuration['database'],
        ];
        $input = fopen($source, 'rb');
        $process = new Process($arguments, null, ['MYSQL_PWD' => (string) ($configuration['password'] ?? '')]);
        $process->setInput($input);
        $process->setTimeout(900);
        $process->run();
        fclose($input);
        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysql falló durante la restauración: '.trim($process->getErrorOutput()));
        }
    }

    /** @param array<string,mixed> $configuration */
    private function restoreSqlite(string $connectionName, array $configuration, string $source): void
    {
        $database = (string) ($configuration['database'] ?? '');
        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('No se puede sobrescribir una base SQLite en memoria.');
        }
        DB::purge($connectionName);
        if (! copy($source, $database)) {
            throw new RuntimeException('No fue posible reemplazar la base SQLite.');
        }
        DB::reconnect($connectionName);
    }
}
