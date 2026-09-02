<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'backup:database {--connection= : Conexión de base de datos}';

    protected $description = 'Crea un respaldo cifrado y verifica su integridad';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $backup = $backups->create(connectionName: $this->option('connection') ?: null);
            $this->info("Respaldo creado: {$backup->filename}");
            $this->line('SHA-256: '.$backup->sha256);
            $this->line('Tamaño: '.number_format(($backup->size_bytes ?? 0) / 1048576, 2).' MB');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No fue posible crear el respaldo: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
