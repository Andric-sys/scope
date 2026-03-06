<?php
declare(strict_types=1);

// SCRIPT PARA SINCRONIZAR TODOS LOS DATOS DESDE OCTUBRE 2025

require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

echo "\n=== SCOPE DATA SYNC TOOL (Oct 2025 - Present) ===\n\n";

// Configuración
$cfg = require __DIR__ . '/config.php';
$scopeCfg = $cfg['scope'] ?? [];
$org = (string)($scopeCfg['organizationCode'] ?? '');
$le  = (string)($scopeCfg['legalEntityCode'] ?? '');
$br  = (string)($scopeCfg['branchCode'] ?? '');

if ($org==='' || $le==='' || $br==='') {
  echo "❌ ERROR: Configuración incompleta en config.php\n";
  exit(1);
}

echo "Organización: $org\n";
echo "Legal Entity: $le\n";
echo "Branch: $br\n\n";

// Opción 1: Limpiar el estado de sincronización (para backfill completo)
echo "Opción 1: RESET DE ESTADO\n";
echo "(Limpia el cursor para hacer backfill completo desde octubre)\n";

try {
  $pdo->prepare("
    DELETE FROM scope_sync_state
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ")->execute([$org, $le, $br]);
  
  echo "✓ Estado de sincronización limpiado\n\n";
} catch (Exception $e) {
  echo "⚠ Advertencia al limpiar estado: " . $e->getMessage() . "\n\n";
}

// Opción 2: Ver estado actual
echo "Opción 2: ESTADO ACTUAL\n";

try {
  $st = $pdo->prepare("
    SELECT * FROM scope_sync_state
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ");
  $st->execute([$org, $le, $br]);
  $state = $st->fetch(PDO::FETCH_ASSOC);
  
  if ($state) {
    echo "Last Modified Max (UTC): " . ($state['last_modified_max_utc'] ?? 'NULL') . "\n";
    echo "Last Modified Max (Raw): " . ($state['last_modified_max_raw'] ?? 'NULL') . "\n";
    echo "Last Page: " . ($state['last_page'] ?? 'NULL') . "\n";
    echo "Last Run UUID: " . ($state['last_run_uuid'] ?? 'NULL') . "\n";
    echo "Updated At: " . ($state['updated_at'] ?? 'NULL') . "\n";
  } else {
    echo "No hay estado previo (hará backfill completo)\n";
  }
} catch (Exception $e) {
  echo "⚠ Error al obtener estado: " . $e->getMessage() . "\n";
}

echo "\n";

// Información sobre las ejecuciones previas
echo "Opción 3: HISTORIAL DE SINCRONIZACIONES\n";

try {
  $st = $pdo->prepare("
    SELECT 
      run_uuid, cursor_from_utc, cursor_to_utc, 
      fetched_count, upserted_orders, upserted_jobcosting_entries,
      mensaje, http_status, started_at, finished_at
    FROM scope_sync_runs
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
    ORDER BY started_at DESC
    LIMIT 5
  ");
  $st->execute([$org, $le, $br]);
  $runs = $st->fetchAll(PDO::FETCH_ASSOC);
  
  if (empty($runs)) {
    echo "No hay registros de sincronización previos\n";
  } else {
    echo "Últimas 5 sincronizaciones:\n";
    printf("%-15s | %20s | %15s | %20s\n", "Fecha", "Órdenes", "Entries", "Estado");
    echo str_repeat("-", 75) . "\n";
    
    foreach ($runs as $run) {
      printf("%-15s | %15d | %15d | %20s\n",
        substr($run['started_at'] ?? '', 0, 15),
        (int)($run['upserted_orders'] ?? 0),
        (int)($run['upserted_jobcosting_entries'] ?? 0),
        $run['mensaje'] ?? 'N/A'
      );
    }
  }
} catch (Exception $e) {
  echo "⚠ Error al obtener historial: " . $e->getMessage() . "\n";
}

echo "\n";

// Datos actuales en BD
echo "Opción 4: DATOS EN BASE DE DATOS\n";

try {
  $st = $pdo->prepare("
    SELECT 
      COUNT(DISTINCT id) as total_orders,
      MIN(DATE(created_at)) as min_date,
      MAX(DATE(created_at)) as max_date
    FROM scope_orders
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
  ");
  $st->execute([$org, $le, $br]);
  $data = $st->fetch(PDO::FETCH_ASSOC);
  
  echo "Órdenes en BD: " . ($data['total_orders'] ?? 0) . "\n";
  echo "Rango de fechas: " . ($data['min_date'] ?? 'N/A') . " a " . ($data['max_date'] ?? 'N/A') . "\n";
} catch (Exception $e) {
  echo "⚠ Error al contar órdenes: " . $e->getMessage() . "\n";
}

echo "\n";

// Instrucciones para sincronizar
echo "═══════════════════════════════════════════════════════════════\n";
echo "PARA SINCRONIZAR DATOS DESDE OCTUBRE HASTA HOY:\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "OPCIÓN A - VÍA HTTP (desde el navegador):\n";
echo "  1. Ir a: http://localhost/scope/scope/core_scope/scope_sync_panel.php\n";
echo "  2. Configurar:\n";
echo "     - Mode: backfill\n";
echo "     - Max Pages: 100\n";
echo "     - Page Size: 100\n";
echo "     - Runtime: 900 (15 minutos)\n";
echo "  3. Clic en 'Iniciar sincronización'\n\n";

echo "OPCIÓN B - VÍA API (cURL desde terminal):\n";
$baseUrl = "http://localhost/scope/scope/core_scope/scope_sync.php";
echo "  curl \"$baseUrl?mode=backfill&max_pages=100&size=100&days=150&runtime_sec=900\"\n\n";

echo "OPCIÓN C - AUTOMÁTICO (ejecutar esto directamente):\n";
echo "  php resetHistorial.php\n\n";

echo "Notas:\n";
echo "  - days=150 es ~5 meses (octubre 2025 a marzo 2026)\n";
echo "  - max_pages=100 traerá ~10,000 órdenes (100 páginas × 100 tamaño)\n";
echo "  - El proceso puede tomar 5-15 minutos dependiendo de la API\n";

echo "\n";
