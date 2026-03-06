<?php
declare(strict_types=1);

/**
 * run_backfill_sync.php - Script para ejecutar backfill via HTTP requests
 * Sincroniza todos los datos desde Scope en múltiples rondas
 */

if (php_sapi_name() !== 'cli') {
  die("CLI only\n");
}

date_default_timezone_set('America/Mexico_City');

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║    BACKFILL SYNC - Oct 2025 to March 2026                 ║\n";
echo "║    (Auto HTTP requests)                                    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Configuración
$baseUrl = 'http://localhost/scope/scope/core_scope/scope_sync.php';
$maxRounds = 20;
$pageSize = 100;
$maxPagesPerRound = 50;
$runtimePerRound = 600;

echo "Configuración:\n";
echo "  - Mode: backfill\n";
echo "  - Max Rounds: $maxRounds\n";
echo "  - Size: $pageSize items per page\n";
echo "  - Max Pages per Round: $maxPagesPerRound\n";
echo "  - Runtime per Round: $runtimePerRound segundos\n";
echo "  - Days Back: 150 (Oct 2025 - March 2026)\n\n";

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

  // Construir URL con parámetros
  $params = [
    'mode' => 'backfill',
    'max_pages' => $maxPagesPerRound,
    'size' => $pageSize,
    'days' => 150,
    'runtime_sec' => $runtimePerRound,
    'page_retries' => 3,
  ];

  $url = $baseUrl . '?' . http_build_query($params);
  echo "URL: $url\n\n";

  $startTime = microtime(true);
  
  echo "Ejecutando sincronización...\n";

  // Usar file_get_contents con contexto HTTP
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => $runtimePerRound + 30,
      'ignore_errors' => true,
    ]
  ]);

  try {
    $response = @file_get_contents($url, false, $ctx);
    
    if ($response === false) {
      echo "❌ Error: No se pudo conectar a la URL\n";
      echo "   Verifica que Apache esté corriendo en http://localhost\n";
      echo "   O que scope_sync.php sea accesible\n\n";
      break;
    }

    $result = json_decode($response, true);
    
    if (!is_array($result)) {
      echo "❌ Error: Respuesta no es JSON válido\n";
      echo "   Respuesta: " . substr($response, 0, 200) . "...\n\n";
      break;
    }

    if (!($result['success'] ?? false)) {
      echo "❌ Error: " . ($result['message'] ?? 'Unknown') . "\n\n";
      break;
    }

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

    echo "✓ Completada en {$elapsed}s\n";
    echo "  Fetched Orders: $fetched\n";
    echo "  Upserted Orders: $upOrders\n";
    echo "  Jobcosting Entries: $upJC\n";
    echo "  Errors: $errors\n";
    echo "  Message: $message\n\n";

    // Lógica para continuar o parar
    if ($fetched === 0) {
      echo "  ➜ No hay más datos (fin)\n\n";
      $hasMoreData = false;
    } else if ($partial) {
      echo "  ➜ Parcial (continuando...)\n";
      sleep(2);
      echo "\n";
    } else {
      echo "  ➜ Completa\n\n";
      $hasMoreData = false;
    }

  } catch (Throwable $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n\n";
    break;
  }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "RESUMEN FINAL\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Rondas completadas: $round / $maxRounds\n";
echo "Total órdenes sincronizadas: " . number_format($totalOrders) . "\n";
echo "Total jobcosting entries: " . number_format($totalEntries) . "\n";
echo "Errores totales: $totalErrors\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║    ✓ Sincronización completada                             ║\n";
echo "║    Los datos están listos en la BD                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Próximos pasos:\n";
echo "  1. Dashboard: http://localhost/scope/scope/core_scope/dashboard_pro.php\n";
echo "  2. Sync Panel: http://localhost/scope/scope/core_scope/scope_sync_panel.php\n";
echo "  3. Debug: php show_all_breakdown.php\n\n";
