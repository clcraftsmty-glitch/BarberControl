<?php

namespace App\Enums;

enum UserAccessEvent: string
{
    case Login = 'inicio_sesion';
    case Logout = 'cierre_sesion';
    case FailedLogin = 'intento_fallido';
    case BlockedLogin = 'acceso_bloqueado';
    case PasswordReset = 'contrasena_restablecida';
    case Suspended = 'usuario_suspendido';
    case Reactivated = 'usuario_reactivado';
    case TwoFactorChallenge = 'segundo_factor_validado';
    case TwoFactorFailed = 'segundo_factor_fallido';
    case TwoFactorEnabled = 'segundo_factor_activado';
    case RecoveryCodeUsed = 'codigo_recuperacion_usado';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Inicio de sesión',
            self::Logout => 'Cierre de sesión',
            self::FailedLogin => 'Intento fallido',
            self::BlockedLogin => 'Acceso bloqueado',
            self::PasswordReset => 'Contraseña restablecida',
            self::Suspended => 'Usuario suspendido',
            self::Reactivated => 'Usuario reactivado',
            self::TwoFactorChallenge => 'Segundo factor validado',
            self::TwoFactorFailed => 'Segundo factor incorrecto',
            self::TwoFactorEnabled => 'Segundo factor activado',
            self::RecoveryCodeUsed => 'Código de recuperación utilizado',
        };
    }
}
