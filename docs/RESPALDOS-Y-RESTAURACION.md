# Respaldos y restauración de BarberControl

Los respaldos contienen datos personales y financieros. BarberControl los guarda fuera de `public/`, los cifra por bloques con AES-256-GCM y registra un SHA-256 para detectar alteraciones.

## Operación diaria

- El programador ejecuta `php artisan backup:database` diariamente a la hora indicada por `BACKUP_RUN_AT`.
- En el servidor debe permanecer activo `php artisan schedule:work`, o debe configurarse el cron oficial de Laravel para ejecutar `schedule:run` cada minuto.
- Los administradores pueden crear y descargar copias desde **Seguridad > Respaldos y seguridad**.
- La retención predeterminada es de 30 días. Se cambia con `BACKUP_RETENTION_DAYS`.
- Copie periódicamente los archivos descargados a un medio externo cifrado. Un respaldo en el mismo disco no protege contra una falla física.

## Requisitos de MySQL

Los ejecutables `mysqldump` y `mysql` deben estar disponibles. En Windows configure las rutas completas:

```env
BACKUP_MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
BACKUP_MYSQL_PATH="C:\xampp\mysql\bin\mysql.exe"
```

La contraseña se entrega al proceso mediante `MYSQL_PWD`; no se incluye en la línea de comandos ni en el archivo.

## Procedimiento de restauración probado

1. Guarde una copia del archivo `.bcbk` y confirme que aparece como **Completado** en la pantalla de seguridad.
2. Identifique su ID y nombre exacto.
3. Ejecute desde la carpeta del proyecto:

```powershell
php artisan backup:restore 25 --force --confirm="barbercontrol-20260718-020000-abc123.sql.bcbk"
```

4. El comando crea primero un respaldo preventivo de la base actual, activa mantenimiento, valida SHA-256, autentica cada bloque cifrado y restaura la base.
5. Al terminar ejecute:

```powershell
php artisan migrate --force
php artisan optimize:clear
php artisan up
```

6. Verifique inicio de sesión, clientes, citas, ventas, caja y la última liquidación de comisiones.
7. Documente fecha, responsable, respaldo utilizado y resultado de la prueba.

Para una prueba en un servidor alterno, copie `.env`, use una base vacía y conserve la misma `BACKUP_ENCRYPTION_KEY`. Nunca cambie `APP_KEY` ni la clave de respaldo sin mantener una copia segura de la clave anterior; de lo contrario los archivos no podrán descifrarse.

## Recuperación ante una falla

- Si la restauración se interrumpe, la aplicación vuelve a salir de mantenimiento, pero la base puede estar parcialmente restaurada.
- Corrija la causa y restaure el respaldo preventivo que el comando creó justo antes de comenzar.
- No utilice `--no-safety-backup` salvo que la base actual esté irrecuperable y exista otra copia verificada.

## HTTPS y tareas programadas en producción

Configure como mínimo:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://su-dominio.com
SECURITY_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
LOG_CHANNEL=daily
```

Si utiliza Nginx, Apache, Cloudflare u otro proxy, configure `SECURITY_TRUSTED_PROXIES` únicamente con sus IPs. Después ejecute `php artisan optimize:clear`.
