# BarberControl

Base de una aplicación web para la gestión de barberías, construida con Laravel 12, Livewire 3, Volt, Breeze y Tailwind CSS.

## Inicio rápido en esta computadora

La instalación principal está preparada para ejecutarse sin escribir comandos:

1. Haz doble clic en `BarberControl.cmd` para iniciar la aplicación.
2. El navegador abrirá `http://127.0.0.1:8088`.
3. Para detener BarberControl, vuelve a hacer doble clic en el mismo archivo.

El iniciador también controla el programador de respaldos y recordatorios, además de la cola de mensajes. El PHP portátil requerido está incluido en `runtime/php`; no depende de la antigua carpeta de Codex.

## Requisitos

- PHP 8.2 o superior con `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` y `curl`.
- Composer 2.
- MySQL 8 o MariaDB compatible.
- Node.js 20 o superior y npm.

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Crea la base de datos `barbercontrol`, ajusta las variables `DB_*` y las credenciales `ADMIN_*` en `.env`, y después ejecuta:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

El registro público está deshabilitado. El primer usuario administrador se crea con los valores `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD`; cambia la contraseña inicial antes de desplegar.

## Estructura preparada

- `app/Enums`: valores de dominio tipados, como los roles.
- `app/Http/Middleware`: reglas de acceso reutilizables.
- `app/Livewire`: acciones y formularios reactivos.
- `resources/views/livewire`: componentes y páginas Livewire/Volt.
- `database/migrations` y `database/seeders`: esquema y datos iniciales.

Para proteger un módulo futuro por rol:

```php
Route::middleware(['auth', 'role:administrador,recepcionista'])->group(function () {
    // Rutas del módulo.
});
```

Roles disponibles: `administrador`, `recepcionista` y `barbero`.

## WhatsApp con Meta Cloud API

Por seguridad, BarberControl inicia con `WHATSAPP_DRIVER=log`: registra el flujo completo sin enviar mensajes reales. Para conectar la cuenta oficial configura en `.env`:

```dotenv
WHATSAPP_DRIVER=meta
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_DEFAULT_COUNTRY_CODE=52
```

Registra en Meta el webhook público HTTPS `https://tu-dominio.com/webhooks/whatsapp` y utiliza el mismo `WHATSAPP_VERIFY_TOKEN`. Las plantillas configuradas en `.env.example` deben estar aprobadas en Meta. Las plantillas de citas reciben, en orden: cliente, servicio, fecha, hora y barbero. La plantilla de ticket usa un documento PDF en el encabezado y recibe cliente, folio e importe en el cuerpo.

Los recordatorios de 24 y 2 horas están registrados en el programador de Laravel. En un servidor configura el cron de Laravel; durante desarrollo puedes mantener abierto:

```bash
php artisan schedule:work
```

El envío solo se intenta para clientes que hayan marcado su consentimiento. El historial, los estados de entrega y los errores están disponibles en `/whatsapp` para administración y recepción.

## Respaldos y seguridad

El centro de seguridad incorpora respaldos cifrados, restauración verificada, auditoría, segundo factor obligatorio para administradores y monitoreo de errores. Antes de usar datos reales, revisa [docs/RESPALDOS-Y-RESTAURACION.md](docs/RESPALDOS-Y-RESTAURACION.md).

## Verificación

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
```
