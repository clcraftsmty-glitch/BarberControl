<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

class RestoreDatabaseBackup extends Command
{
    protected $signature = 'backup:restore
        {backup : ID o nombre exacto del respaldo}
        {--confirm= : Debe repetir el nombre exacto del archivo}
        {--force : Confirma que comprende que la base actual será reemplazada}
        {--no-safety-backup : Omite el respaldo preventivo previo}';

    protected $description = 'Restaura un respaldo cifrado con verificación y confirmación explícita';

    public function handle(DatabaseBackupService $backups): int
    {
        $value = (string) $this->argument('backup');
        $backup = DatabaseBackup::query()
            ->where('id', ctype_digit($value) ? (int) $value : 0)
            ->orWhere('filename', $value)
            ->first();

        if (! $backup) {
            $this->error('No se encontró el respaldo indicado.');

            return self::FAILURE;
        }
        if (! $this->option('force') || ! hash_equals($backup->filename, (string) $this->option('confirm'))) {
            $this->error('Operación cancelada. Use --force y --confirm="'.$backup->filename.'".');

            return self::FAILURE;
        }

        $maintenanceSecret = Str::random(40);
        try {
            if (! $this->option('no-safety-backup')) {
                $this->info('Creando respaldo preventivo de la base actual...');
                $backups->create(connectionName: $backup->database_connection);
            }

            Artisan::call('down', ['--secret' => $maintenanceSecret]);
            $this->warn('Aplicación en mantenimiento. Restaurando y verificando...');
            $backups->restore($backup);
            Artisan::call('up');
            $this->info('Restauración terminada correctamente.');
            $this->line('Ejecute: php artisan migrate --force');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Artisan::call('up');
            report($exception);
            $this->error('La restauración falló: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
