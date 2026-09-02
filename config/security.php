<?php

return [
    'force_https' => env('SECURITY_FORCE_HTTPS', false),
    'trusted_proxies' => env('SECURITY_TRUSTED_PROXIES'),

    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', true),
        'enforce_in_tests' => env('TWO_FACTOR_ENFORCE_IN_TESTS', false),
        'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'BarberControl')),
        'window' => (int) env('TWO_FACTOR_WINDOW', 1),
    ],

    'backups' => [
        'enabled' => env('BACKUP_ENABLED', true),
        'connection' => env('BACKUP_DB_CONNECTION'),
        'disk' => env('BACKUP_DISK', 'local'),
        'directory' => env('BACKUP_DIRECTORY', 'backups/database'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'run_at' => env('BACKUP_RUN_AT', '02:00'),
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),
        'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),
    ],

    'monitoring' => [
        'enabled' => env('ERROR_MONITORING_ENABLED', true),
        'retention_days' => (int) env('ERROR_RETENTION_DAYS', 90),
    ],
];
