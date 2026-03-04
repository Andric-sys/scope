<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = db();

  $mode = (string)($_GET['mode'] ?? 'invoice');
  if (!in_array($mode, ['invoice','economic','manual'], true)) $mode = 'invoice';

  $office = (string)($_GET['office'] ?? 'all');
  $traffic = (string)($_GET['traffic'] ?? 'all');
  $currency = strtoupper(trim((string)($_GET['currency'] ?? 'MXN')));
  if ($currency === '') $currency = 'MXN';

  $months = (int)($_GET['months'] ?? 12);
  if ($months < 3) $months = 3;
  if ($months > 24) $months = 24;

  $manualBase = (string)($_GET['date_base'] ?? 'invoice');
  if (!in_array($manualBase, ['invoice','economic'], true)) $manualBase = 'invoice';

  $from = (string)($_GET['from'] ?? '');
  $to   = (string)($_GET['to'] ?? '');

  $dateField = 'j.invoice_date';
  if ($mode === 'economic') $dateField = 'j.economic_date';
  if ($mode === 'manual')   $dateField = ($manualBase === 'economic') ? 'j.economic_date' : 'j.invoice_date';

  // Construcción del WHERE y parámetros (alineado con dashboard principal)
  $wMax = [];
  $pMax = [];

  $wMax[] = "$dateField IS NOT NULL";
  if ($office !== 'all') { $wMax[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $pMax[':office'] = $office; }
  if ($traffic !== 'all') { $wMax[] = "o.conveyance_type = :traffic"; $pMax[':traffic'] = $traffic; }
  if ($currency !== 'ALL') { $wMax[] = "j.amount_currency = :currency"; $pMax[':currency'] = $currency; }

  $whereB = implode(' AND ', $wMax);

  if ($mode !== 'manual') {
    $maxDateSql = "SELECT MAX($dateField) as md FROM scope_jobcosting_entries j LEFT JOIN scope_orders o ON o.id = j.order_id WHERE $whereB";
    $st = $pdo->prepare($maxDateSql);
    $st->execute($pMax);
    $maxRow = $st->fetch(PDO::FETCH_ASSOC);
    $maxDate = $maxRow['md'] ?? null;

    if ($maxDate) {
      $max = new DateTimeImmutable((string)$maxDate, new DateTimeZone('America/Mexico_City'));
      $to = $max->format('Y-m-d');
      $from = $max
        ->modify('first day of this month')
        ->sub(new DateInterval('P'.($months-1).'M'))
        ->format('Y-m-d');
    } else {
      $now = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
      $to = $now->format('Y-m-d');
      $from = $now
        ->modify('first day of this month')
        ->sub(new DateInterval('P'.($months-1).'M'))
        ->format('Y-m-d');
    }
  }

  $p = $pMax;
  $where = $whereB . " AND DATE($dateField) BETWEEN :from AND :to";
  $p[':from'] = $from;
  $p[':to'] = $to;

  // TODOS los clientes (sin LIMIT)
  $sqlAllClients = "
    SELECT
      o.customer_code,
      o.customer_name,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%'
        THEN j.local_amount_value ELSE 0 END),0) AS costs
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE $where
    GROUP BY o.customer_code, o.customer_name
    ORDER BY sales DESC
  ";
  
  $st = $pdo->prepare($sqlAllClients);
  $st->execute($p);
  $allClientsRaw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  
  // Calcular totales
  $totalSales = 0;
  foreach ($allClientsRaw as $r) {
    $totalSales += (float)$r['sales'];
  }

  $allClients = array_map(function($r) use ($totalSales) {
    $s = (float)$r['sales'];
    $c = (float)$r['costs'];
    $p = $s - $c;
    $m = ($s > 0) ? ($p / $s) : 0.0;
    $pct = ($totalSales > 0) ? ($s / $totalSales * 100) : 0;
    return [
      'customer_code' => $r['customer_code'],
      'customer_name' => $r['customer_name'],
      'sales' => $s,
      'costs' => $c,
      'profit' => $p,
      'margin' => $m,
      'percentage' => $pct,
    ];
  }, $allClientsRaw);

  http_response_code(200);
  echo json_encode([
    'success' => true,
    'filters' => [
      'mode' => $mode,
      'office' => $office,
      'traffic' => $traffic,
      'currency' => $currency,
      'from' => $from,
      'to' => $to,
      'date_field' => str_replace('j.','',$dateField),
    ],
    'total_sales' => $totalSales,
    'total_clients' => count($allClients),
    'clients' => $allClients,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
