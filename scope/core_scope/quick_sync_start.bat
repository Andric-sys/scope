@echo off
REM ========================================
REM  CORE SCOPE SYNC QUICK START
REM  Sincronizar datos Oct 2025 - Mar 2026
REM ========================================

setlocal enabledelayedexpansion

set PHP=C:\xampp\php\php.exe
set SCOPE_DIR=C:\xampp\htdocs\scope\scope\core_scope

echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║    CORE SCOPE SYNC QUICK START                            ║
echo ║    Sincronizar Octubre 2025 - Marzo 2026                  ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

REM PASO 1: Verificar estado actual
echo [PASO 1] Verificando estado actual...
"%PHP%" "%SCOPE_DIR%\check_data_range.php"
echo.

REM PASO 2: Limpiar estado
echo [PASO 2] Limpiando estado de sincronización...
"%PHP%" "%SCOPE_DIR%\resetHistorial.php"
echo.

REM PASO 3: Mostrar instrucciones
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║    ✓ LISTO PARA SINCRONIZAR                               ║
echo ╚════════════════════════════════════════════════════════════╝
echo.
echo Abre en tu navegador:
echo   http://localhost/scope/scope/core_scope/scope_sync_panel.php
echo.
echo Configura:
echo   - Mode: backfill
echo   - Max Pages: 100
echo   - Page Size: 100
echo   - Runtime: 900 segundos
echo.
echo El proceso tardará ~15-30 minutos dependiendo de cuántas órdenes haya.
echo.
echo VERIFICACIÓN POST-SYNC:
echo.
echo 1. Después de que termine, ejecuta:
echo    "%PHP%" "%SCOPE_DIR%\show_all_breakdown.php"
echo.
echo 2. Abre dashboard:
echo    http://localhost/scope/scope/core_scope/dashboard_pro.php
echo.
echo 3. Usa los filtros de financial_status para verificar datos
echo.

pause
