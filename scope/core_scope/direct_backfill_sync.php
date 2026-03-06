<?php
declare(strict_types=1);

/**
 * direct_backfill_sync.php - Sincronización directa sin HTTP
 * Ejecuta el código de scope_sync.php directamente en CLI
 */

define('CORE_SCOPE_SKIP_ACTIVE_SESSION_CHECK', true);

if (php_sapi_name() !== 'cli') {
  die("CLI only\n");
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/scope_api.php';
require __DIR__ . '/scope_upsert.php';

date_default_timezone_set('America/Mexico_City');
set_time_limit(0);
ini_set('display_errors', '1');

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

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║    DIRECT BACKFILL SYNC - Oct 2025 to March 2026          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Funciones auxiliares (copiadas de scope_sync.php)
function extract_orders_any($data): array {
  if (!is_array($data)) return [];
  $best = [];
  $walk = function($node) use (&$walk, &$best) {
    if (!is_array($node)) return;
    $isIndexed = array_keys($node) === range(0, count($node)-1);
    if ($isIndexed && count($node)>0 && is_array($node[0])) {
      $hits=0;
      foreach($node as $x){
        if (is_array($x) && isset($x['identifier']) && is_string($x['identifier']) && trim($x['identifier'])!=='') $hits++;
      }
      if ($hits>0 && count($node)>count($best)) $best=$node;
    }
    foreach($node as $v) $walk($v);
  };
  $walk($data);
  return $best;
}

// Parámetros
$maxRounds = 2;
$pageSize = 100;
$maxPagesPerRound = 5;
$daysBack = 150; // Oct 2025 - March 2026
$pageRetries = 0;  // SIN reintentos
$throttleMs = 0;   // SIN espera

echo "Configuración:\n";
echo "  - Backfill desde hace $daysBack días (Oct 2025 - Presente)\n";
echo "  - Max Rondas: $maxRounds\n";
echo "  - Páginas por ronda: $maxPagesPerRound × $pageSize = " . ($maxPagesPerRound * $pageSize) . " items/ronda\n";
echo "  - Reintentos por página: " . ($pageRetries + 1) . "\n\n";

$round = 0;
$totalOrders = 0;
$totalEntries = 0;
$totalFetched = 0;
$totalErrors = 0;
$hasMoreData = true;

while ($round < $maxRounds && $hasMoreData) {
  $round++;
  
  echo "═══════════════════════════════════════════════════════════\n";
  echo "ROUND $round / $maxRounds\n";
  echo "═══════════════════════════════════════════════════════════\n";

  // Obtener state previo
  $st = $pdo->prepare("
    SELECT last_modified_max_utc
    FROM scope_sync_state
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ");
  $st->execute([$org, $le, $br]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  
  $cursorBackMinutes = 10;
  $cursorFrom = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->sub(new DateInterval('P'.$daysBack.'D'));
  
  if ($row && !empty($row['last_modified_max_utc'])) {
    $cursorFrom = new DateTimeImmutable((string)$row['last_modified_max_utc'], new DateTimeZone('UTC'));
    $cursorFrom = $cursorFrom->sub(new DateInterval('PT'.$cursorBackMinutes.'M'));
  }

  echo "Cursor From: " . $cursorFrom->format('Y-m-d H:i:s') . "\n\n";

  // Loop de páginas
  $page = 0;
  $roundOrders = 0;
  $roundFetched = 0;
  $roundErrors = 0;
  $lastMaxUtc = null;
  $lastMaxRaw = null;
  $roundHasData = false;

  for ($pageLoop=0; $pageLoop < $maxPagesPerRound; $pageLoop++) {
    $page = $pageLoop;
    echo "  Página " . ($page+1) . "/$maxPagesPerRound...";

    // Construir filter
    $filter = scope_lastModified_filter($cursorFrom, 'ge', 'UTC');
    
    $params = [
      'page' => $page,
      'size' => $pageSize,
      'orderBy' => '+lastModified',
      'lastModified' => $filter,
    ];

    // Listar órdenes - SIN reintentos
    try {
      $list = scope_list_orders($params);
    } catch (Throwable $e) {
      echo " ❌ Error: " . $e->getMessage() . "\n";
      $roundErrors++;
      continue;
    }

    $orders = extract_orders_any($list);
    if (!$orders) {
      echo " ➜ Sin más datos\n";
      $hasMoreData = false;
      break;
    }

    $pageCount = count($orders);
    echo " ✓ $pageCount órdenes";

    $roundHasData = true;
    $roundFetched += $pageCount;

    // Procesar cada orden
    foreach($orders as $item) {
      $uuid = (string)($item['identifier'] ?? '');
      if ($uuid === '') continue;

      try {
        $detail = scope_get_order($uuid);
        
        $r = scope_upsert_order($pdo, $detail);
        $roundOrders++;
        
        $lmRaw = (string)($detail['lastModified'] ?? '');
        $lm = iso_to_utc_parts($lmRaw);
        if (!empty($lm['utc'])) {
          if ($lastMaxUtc===null || $lm['utc']>$lastMaxUtc) {
            $lastMaxUtc=$lm['utc']; 
            $lastMaxRaw=$lmRaw;
          }
        }

      } catch (Throwable $e) {
        $roundErrors++;
      }

      // SIN throttle - ejecutar lo más rápido posible
    }

    echo "\n";
  }

  // Actualizar state
  if ($lastMaxUtc !== null) {
    $pdo->prepare("
      INSERT INTO scope_sync_state (
        organization_code, legal_entity_code, branch_code,
        last_modified_max_raw, last_modified_max_utc,
        last_page, last_run_uuid
      ) VALUES (?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        last_modified_max_raw = VALUES(last_modified_max_raw),
        last_modified_max_utc = VALUES(last_modified_max_utc),
        last_page = VALUES(last_page),
        last_run_uuid = VALUES(last_run_uuid),
        updated_at = CURRENT_TIMESTAMP
    ")->execute([
      $org, $le, $br,
      $lastMaxRaw, $lastMaxUtc, $page,
      sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
      )
    ]);
  }

  $totalFetched += $roundFetched;
  $totalOrders  += $roundOrders;
  $totalErrors  += $roundErrors;

  echo "  ✓ Ronda completada: $roundOrders órdenes\n\n";

  if (!$roundHasData || !$hasMoreData) {
    break;
  }

  // Siguiente ronda sin espera
}

echo "═══════════════════════════════════════════════════════════\n";
echo "RESUMEN FINAL\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Rondas: $round / $maxRounds\n";
echo "Total órdenes descargadas: " . number_format($totalFetched) . "\n";
echo "Total órdenes sincronizadas: " . number_format($totalOrders) . "\n";
echo "Errores: $totalErrors\n\n";

// Estado final
try {
  $st = $pdo->prepare("
    SELECT 
      COUNT(DISTINCT id) as total_orders,
      COUNT(DISTINCT id) as unique_orders,
      MIN(DATE(created_at)) as min_date,
      MAX(DATE(created_at)) as max_date
    FROM scope_orders
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ");
  $st->execute([$org, $le, $br]);
  $final = $st->fetch(PDO::FETCH_ASSOC);

  if ($final) {
    echo "Estado de BD:\n";
    echo "  • Total órdenes: " . number_format($final['total_orders'] ?? 0) . "\n";
    echo "  • Rango: " . ($final['min_date'] ?? 'N/A') . " a " . ($final['max_date'] ?? 'N/A') . "\n\n";
  }
} catch (Exception $e) {
  echo "⚠ Error BD: " . $e->getMessage() . "\n\n";
}

echo "╔════════════════════════════════════════════════════════════╗\n";
if ($totalOrders > 0) {
  echo "║    ✓ ¡Sincronización exitosa!                               ║\n";
} else {
  echo "║    ⚠ No se sincronizó ningún dato                           ║\n";
}
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Próximos pasos:\n";
echo "  1. Verificar BD:\n";
echo "     php show_all_breakdown.php\n";
echo "  2. Ver en Dashboard:\n";
echo "     http://localhost/scope/scope/core_scope/dashboard_pro.php\n\n";
