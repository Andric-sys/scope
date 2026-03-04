<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json; charset=utf-8');

function json_out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function ym(string $dateField): string {
  // dateField es un nombre de columna seguro (whitelist)
  return "DATE_FORMAT($dateField, '%Y-%m')";
}

try {
  $pdo = db();

  $mode = (string)($_GET['mode'] ?? 'invoice'); // invoice|economic|manual
  if (!in_array($mode, ['invoice','economic','manual'], true)) $mode = 'invoice';

  $office = (string)($_GET['office'] ?? 'all'); // all|1000|2000|3000
  $traffic = (string)($_GET['traffic'] ?? 'all'); // all|road|sea|air
  $currency = strtoupper(trim((string)($_GET['currency'] ?? 'MXN')));
  if ($currency === '') $currency = 'MXN';

  $months = (int)($_GET['months'] ?? 12);
  if ($months < 3) $months = 3;
  if ($months > 24) $months = 24;

  $manualBase = (string)($_GET['date_base'] ?? 'invoice'); // invoice|economic (solo modo manual)
  if (!in_array($manualBase, ['invoice','economic'], true)) $manualBase = 'invoice';

  $from = (string)($_GET['from'] ?? '');
  $to   = (string)($_GET['to'] ?? '');

  // date field principal
  $dateField = 'j.invoice_date';
  if ($mode === 'economic') $dateField = 'j.economic_date';
  if ($mode === 'manual')   $dateField = ($manualBase === 'economic') ? 'j.economic_date' : 'j.invoice_date';

  // Rango default:
  // - invoice/economic: últimos N meses desde max(dateField) considerando filtros
  // - manual: usa from/to obligatorios (si no vienen, usa mes actual)
  if ($mode !== 'manual') {
    $wMax = [];
    $pMax = [];

    $wMax[] = "$dateField IS NOT NULL";
    if ($currency !== 'ALL') { $wMax[] = "j.amount_currency = :currency"; $pMax[':currency'] = $currency; }

    if ($office !== 'all') { $wMax[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $pMax[':office'] = $office; }
    if ($traffic !== 'all') { $wMax[] = "o.conveyance_type = :traffic"; $pMax[':traffic'] = $traffic; }

    $sqlMax = "
      SELECT DATE(MAX($dateField)) AS d
      FROM scope_jobcosting_entries j
      LEFT JOIN scope_orders o ON o.id = j.order_id
      WHERE ".implode(' AND ', $wMax)."
    ";
    $st = $pdo->prepare($sqlMax);
    $st->execute($pMax);
    $maxD = (string)($st->fetchColumn() ?: '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maxD)) {
      $maxD = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
    }

    $to = $maxD;
    $from = (new DateTimeImmutable($to, new DateTimeZone('America/Mexico_City')))
      ->modify('first day of this month')
      ->sub(new DateInterval('P'.($months-1).'M'))
      ->format('Y-m-d');
  } else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
      $now = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
      $from = $now->format('Y-m-01');
      $to = $now->format('Y-m-t');
    }
  }

  // WHERE base (para todas las queries)
  $w = [];
  $p = [];

  // En dashboard queremos “ventas/costos” basados en entries (si no hay entries, no suma)
  $w[] = "$dateField IS NOT NULL";
  $w[] = "DATE($dateField) BETWEEN :from AND :to";
  $p[':from'] = $from;
  $p[':to'] = $to;
  if ($currency !== 'ALL') { $w[] = "j.amount_currency = :currency"; $p[':currency'] = $currency; }

  if ($office !== 'all') { $w[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $p[':office'] = $office; }
  if ($traffic !== 'all') { $w[] = "o.conveyance_type = :traffic"; $p[':traffic'] = $traffic; }

  $where = implode(' AND ', $w);

  // KPI: ventas (income sin PT) basadas en "total" de vistas_crudas: amount_value + tax_value
  $sqlKpis = "
    SELECT
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.tax_value,0) ELSE 0 END),0) AS vat_sales,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%'
        THEN j.local_amount_value ELSE 0 END),0) AS costs,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND UPPER(COALESCE(j.charge_type_code,'')) LIKE 'PT%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS ter_income
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE $where
  ";
  $st = $pdo->prepare($sqlKpis);
  $st->execute($p);
  $k = $st->fetch(PDO::FETCH_ASSOC) ?: ['sales'=>0,'vat_sales'=>0,'costs'=>0,'ter_income'=>0];

  $sales = (float)$k['sales'];
  $costs = (float)$k['costs'];
  $profit = $sales - $costs;
  $margin = ($sales > 0) ? ($profit / $sales) : 0.0;

  // Serie mensual (sales/costs/profit) por ym
  $ymExpr = ym($dateField);

  $sqlSeries = "
    SELECT
      $ymExpr AS ym,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%'
        THEN j.local_amount_value ELSE 0 END),0) AS costs
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE $where
    GROUP BY $ymExpr
    ORDER BY ym ASC
  ";
  $st = $pdo->prepare($sqlSeries);
  $st->execute($p);
  $series = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Deltas (último mes vs anterior)
  $salesPct = 0.0;
  $profitPct = 0.0;
  $marginPts = 0.0;

  if (count($series) >= 2) {
    $a = $series[count($series)-2];
    $b = $series[count($series)-1];

    $aSales = (float)$a['sales']; $aCosts = (float)$a['costs']; $aProfit = $aSales - $aCosts;
    $bSales = (float)$b['sales']; $bCosts = (float)$b['costs']; $bProfit = $bSales - $bCosts;

    $aMargin = ($aSales > 0) ? ($aProfit / $aSales) : 0.0;
    $bMargin = ($bSales > 0) ? ($bProfit / $bSales) : 0.0;

    $salesPct = ($aSales != 0.0) ? (($bSales - $aSales) / $aSales) : 0.0;
    $profitPct = ($aProfit != 0.0) ? (($bProfit - $aProfit) / $aProfit) : 0.0;
    $marginPts = ($bMargin - $aMargin); // en “puntos” (ej 0.01 = 1pt)
  }

  // Distribución por tráfico (ventas sin PT con total de vistas_crudas)
  $sqlTraffic = "
    SELECT
      COALESCE(o.conveyance_type,'unknown') AS traffic,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE $where
    GROUP BY o.conveyance_type
    ORDER BY sales DESC
  ";
  $st = $pdo->prepare($sqlTraffic);
  $st->execute($p);
  $trafficRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Oficinas (ventas vs utilidad)
  $sqlOffice = "
    SELECT
      COALESCE(j.cost_center_code, o.cost_center_code) AS office,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%'
        THEN j.local_amount_value ELSE 0 END),0) AS costs
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE $where
    GROUP BY COALESCE(j.cost_center_code, o.cost_center_code)
    ORDER BY sales DESC
  ";
  $st = $pdo->prepare($sqlOffice);
  $st->execute($p);
  $officeRowsRaw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $officeRows = array_map(function($r){
    $s = (float)$r['sales'];
    $c = (float)$r['costs'];
    $p = $s - $c;
    return ['office'=>$r['office'], 'sales'=>$s, 'profit'=>$p];
  }, $officeRowsRaw);

  // Top clientes
  $sqlTop = "
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
    LIMIT 10
  ";
  $st = $pdo->prepare($sqlTop);
  $st->execute($p);
  $topRaw = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $topClients = array_map(function($r){
    $s = (float)$r['sales'];
    $c = (float)$r['costs'];
    $p = $s - $c;
    $m = ($s > 0) ? ($p / $s) : 0.0;
    return [
      'customer_code'=>$r['customer_code'],
      'customer_name'=>$r['customer_name'],
      'sales'=>$s,
      'profit'=>$p,
      'margin'=>$m,
    ];
  }, $topRaw);

  json_out(200, [
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
    'kpis' => [
      'sales' => $sales,
      'vat_sales' => (float)$k['vat_sales'],
      'costs' => $costs,
      'profit' => $profit,
      'margin' => $margin,
      'ter_income' => (float)$k['ter_income'],
      'deltas' => [
        'sales_pct' => $salesPct,
        'profit_pct' => $profitPct,
        'margin_pts' => $marginPts,
      ],
    ],
    'series' => array_map(function($r){
      $s = (float)$r['sales'];
      $c = (float)$r['costs'];
      return ['ym'=>$r['ym'], 'sales'=>$s, 'costs'=>$c, 'profit'=>($s-$c)];
    }, $series),
    'traffic' => $trafficRows,
    'offices' => $officeRows,
    'top_clients' => $topClients,
  ]);

} catch (Throwable $e) {
  json_out(500, ['success'=>false,'message'=>'Error','error'=>$e->getMessage()]);
}