# BarberControl

Base de una aplicación web para la gestión de barberías, construida con Laravel 12, Livewire 3, Volt, Breeze y Tailwind CSS.

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

## Verificación

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
```

Esta entrega no incluye agenda, ventas ni reportes.
