@echo off
setlocal
title BarberControl

powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0BarberControl.ps1"
set "EXIT_CODE=%ERRORLEVEL%"

echo.
if not "%EXIT_CODE%"=="0" (
    echo No se pudo completar la operacion. Revisa el mensaje anterior.
)
echo Presiona una tecla para cerrar esta ventana.
pause >nul

exit /b %EXIT_CODE%
