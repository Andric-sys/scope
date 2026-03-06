<?php
declare(strict_types=1);

/**
 * resetHistorial.php - Script para limpiar estado y ejecutar backfill
 * Sincroniza TODOS los datos desde oct 2025 a la fecha actual
 */

define('CORE_SCOPE_SKIP_ACTIVE_SESSION_CHECK', true);

// No necesita auth para ejecutarse vía CLI
if (php_sapi_name() !== 'cli') {
  die("Este script solo se ejecuta desde CLI\n");
}

require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();
$cfg = require __DIR__ . '/config.php';
$scopeCfg = $cfg['scope'] ?? [];

$org = (string)($scopeCfg['organizationCode'] ?? '');
$le  = (string)($scopeCfg['legalEntityCode'] ?? '');
$br  = (string)($scopeCfg['branchCode'] ?? '');

if ($org==='' || $le==='' || $br==='') {
  echo "❌ ERROR: Configuración incompleta\n";
  exit(1);
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║    RESET DE HISTORIAL Y BACKFILL DE DATOS                ║\n";
echo "║    (Oct 2025 - Presente)                                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// PASO 1: Limpiar estado de sincronización
echo "PASO 1: Limpiando estado de sincronización...\n";

try {
  $pdo->beginTransaction();
  
  // Primero, guardar backup de cuántos registros hay
  $backup = $pdo->prepare("SELECT COUNT(*) as cnt FROM scope_orders WHERE organization_code=? AND legal_entity_code=? AND branch_code=?");
  $backup->execute([$org, $le, $br]);
  $cnt = $backup->fetch()['cnt'] ?? 0;
  
  echo "  Órdenes actuales en BD: $cnt\n";
  
  // Limpiar state para que haga backfill
  $pdo->prepare("
    DELETE FROM scope_sync_state
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ")->execute([$org, $le, $br]);
  
  $pdo->commit();
  echo "  ✓ Estado limpiado - próximo sync hará backfill completo\n\n";
  
} catch (Exception $e) {
  $pdo->rollBack();
  echo "  ❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}

// PASO 2: Información sobre datos previos
echo "PASO 2: Información de sincronizaciones previas...\n";

try {
  $st = $pdo->prepare("
    SELECT COUNT(*) as runs, MAX(started_at) as last_sync
    FROM scope_sync_runs
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ");
  $st->execute([$org, $le, $br]);
  $info = $st->fetch(PDO::FETCH_ASSOC);
  
  echo "  Total de sincronizaciones: " . ($info['runs'] ?? 0) . "\n";
  echo "  Última sincronización: " . ($info['last_sync'] ?? 'Nunca') . "\n\n";
  
} catch (Exception $e) {
  echo "  ⚠ Advertencia: " . $e->getMessage() . "\n\n";
}

// PASO 3: Instrucciones finales
echo "PASO 3: Instrucciones para ejecutar el backfill\n\n";

echo "El estado ha sido limpiado. Para sincronizar todos los datos:\n\n";

echo "OPCIÓN 1 - Desde HTML Panel (Recomendado):\n";
echo "  1. Abre: http://localhost/scope/scope/core_scope/scope_sync_panel.php\n";
echo "  2. Configura:\n";
echo "     • Mode: backfill\n";
echo "     • Max Pages: 150\n";
echo "     • Page Size: 100\n";
echo "     • Runtime: 900 segundos\n";
echo "  3. Clic en 'Iniciar sincronización'\n\n";

echo "OPCIÓN 2 - Desde cURL:\n";
$url = "http://localhost/scope/scope/core_scope/scope_sync.php?mode=backfill&max_pages=150&size=100&days=150&runtime_sec=900";
echo "  curl \"$url\"\n\n";

echo "INFORMACIÓN ÚTIL:\n";
echo "  • Este backfill será pausable y reanudable\n";
echo "  • Traerá ~15,000 órdenes (150 pág × 100 tamaño)\n";
echo "  • Durará 5-20 minutos dependiendo de API\n";
echo "  • El avance se guarda automáticamente\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║    ✓ Listo para sincronizar                                ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
