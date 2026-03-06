<?php
declare(strict_types=1);

/**
 * auto_sync_backfill.php - Script automático para sincronizar datos desde Scope
 * Ejecuta backfill completo desde octubre até marzo con reintentos
 */

define('CORE_SCOPE_SKIP_ACTIVE_SESSION_CHECK', true);

if (php_sapi_name() !== 'cli') {
  header('Content-Type: application/json');
  http_response_code(400);
  echo json_encode(['error' => 'CLI only']);
  exit(1);
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/scope_api.php';
require __DIR__ . '/scope_upsert.php';

date_default_timezone_set('America/Mexico_City');
set_time_limit(0);

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

// Parámetros
$maxRounds = 20;  // Máximo 20 rondas de sincronización
$pageSize = 100;
$maxPagesPerRound = 50; // 50 páginas × 100 items = 5,000 por ronda
$runtimePerRound = 600; // 10 minutos por ronda

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║    AUTO BACKFILL SYNC - Oct 2025 to March 2026            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Configuración:\n";
echo "  - Max Rounds: $maxRounds\n";
echo "  - Pages per Round: $maxPagesPerRound × $pageSize items = " . ($maxPagesPerRound * $pageSize) . " items/ronda\n";
echo "  - Runtime per Round: $runtimePerRound segundos\n";
echo "  - Total expected: " . ($maxRounds * $maxPagesPerRound * $pageSize) . " órdenes máximo\n\n";

$round = 0;
$totalOrders = 0;
$totalEntries = 0;
$totalErrors = 0;
$hasMoreData = true;

while ($round < $maxRounds && $hasMoreData) {
  $round++;
  echo "═══════════════════════════════════════════════════════════\n";
  echo "ROUND $round / $maxRounds\n";
  echo "═══════════════════════════════════════════════════════════\n\n";

  // Construir URL de sincronización
  $url = "scope_sync.php?mode=backfill&max_pages=$maxPagesPerRound&size=$pageSize&days=150&runtime_sec=$runtimePerRound&page_retries=3";
  
  echo "Iniciando sincronización...\n";
  echo "  URL: $url\n\n";

  $startTime = microtime(true);
  
  // Ejecutar sync como HTTP simulation (re-usar scope_sync.php logic)
  try {
    // Incluir y ejecutar scope_sync.php directamente
    ob_start();
    
    // Simular GET parameters
    $_GET['mode'] = 'backfill';
    $_GET['max_pages'] = (string)$maxPagesPerRound;
    $_GET['size'] = (string)$pageSize;
    $_GET['days'] = '150';
    $_GET['runtime_sec'] = (string)$runtimePerRound;
    $_GET['page_retries'] = '3';

    // Capturar salida
    include __DIR__ . '/scope_sync.php';
    $output = ob_get_clean();

    $result = json_decode($output, true);
    
    if (is_array($result) && $result['success'] === true) {
      $fetched    = (int)($result['fetched'] ?? 0);
      $upOrders   = (int)($result['upserted_orders'] ?? 0);
      $upJC       = (int)($result['upserted_jobcosting_entries'] ?? 0);
      $errors     = (int)($result['errors_total'] ?? 0);
      $partial    = (bool)($result['partial'] ?? false);
      $message    = (string)($result['mensaje'] ?? '');
      
      $totalOrders   += $upOrders;
      $totalEntries  += $upJC;
      $totalErrors   += $errors;

      $elapsed = round(microtime(true) - $startTime, 1);

      echo "✓ Ronda completada en {$elapsed}s\n";
      echo "  • Fetched: $fetched órdenes\n";
      echo "  • Upserted: $upOrders órdenes\n";
      echo "  • Jobcosting Entries: $upJC\n";
      echo "  • Errores: $errors\n";
      echo "  • Estado: $message\n\n";

      // Si fue parcial o no came more data, pero keep going
      if (!$partial && $fetched === 0) {
        echo "  ➜ No hay más datos (fin de paginación)\n";
        $hasMoreData = false;
      } else if ($partial) {
        echo "  ➜ Parcial (probablemente por timeout o skip pages)\n";
        echo "    Continuando en próxima ronda...\n\n";
        sleep(2); // Pausa antes de siguiente ronda
      }

    } else {
      $err = $result['message'] ?? 'Unknown error';
      echo "❌ Error en sincronización: $err\n\n";
      $hasMoreData = false;
    }

  } catch (Throwable $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n\n";
    $totalErrors++;
  }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "RESUMEN FINAL\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Rondas completadas: $round / $maxRounds\n";
echo "Total órdenes sincronizadas: $totalOrders\n";
echo "Total jobcosting entries: $totalEntries\n";
echo "Errores totales: $totalErrors\n\n";

// Verificar estado final de BD
try {
  $st = $pdo->prepare("
    SELECT 
      COUNT(DISTINCT id) as total_orders,
      COUNT(DISTINCT json_extract(raw_json, '$.identifier')) as unique_identifiers,
      MIN(DATE(created_at)) as min_date,
      MAX(DATE(created_at)) as max_date
    FROM scope_orders
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ");
  $st->execute([$org, $le, $br]);
  $final = $st->fetch(PDO::FETCH_ASSOC);

  echo "Estado final de BD:\n";
  echo "  • Total órdenes: " . ($final['total_orders'] ?? 0) . "\n";
  echo "  • Rango de fechas: " . ($final['min_date'] ?? 'N/A') . " a " . ($final['max_date'] ?? 'N/A') . "\n\n";
} catch (Exception $e) {
  echo "⚠ Error al verificar BD: " . $e->getMessage() . "\n\n";
}

echo "╔════════════════════════════════════════════════════════════╗\n";
if ($totalOrders > 0) {
  echo "║    ✓ Sincronización completada                              ║\n";
} else {
  echo "║    ⚠ No se sincronizó ningún dato                           ║\n";
}
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Próximos pasos:\n";
echo "  1. Verifica el dashboard: http://localhost/scope/scope/core_scope/dashboard_pro.php\n";
echo "  2. Ejecuta debug_financial_status.php para ver desglose de estatus\n";
echo "  3. Ajusta filtros si es necesario\n\n";
