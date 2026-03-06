<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function json_out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $pdo = db();

  // Parámetros iguales a api_dashboard.php
  $mode = (string)($_GET['mode'] ?? 'invoice');
  $office = (string)($_GET['office'] ?? 'all');
  $traffic = (string)($_GET['traffic'] ?? 'all');
  $chargeType = (string)($_GET['charge_type'] ?? 'all');
  $financialStatus = (string)($_GET['financial_status'] ?? 'all');
  $currency = strtoupper(trim((string)($_GET['currency'] ?? 'MXN')));
  if ($currency === '') $currency = 'MXN';

  $months = (int)($_GET['months'] ?? 12);
  if ($months < 3) $months = 3;
  if ($months > 24) $months = 24;

  $manualBase = (string)($_GET['date_base'] ?? 'invoice');
  $from = (string)($_GET['from'] ?? '');
  $to   = (string)($_GET['to'] ?? '');

  // date field principal
  $dateField = 'j.invoice_date';
  if ($mode === 'economic') $dateField = 'j.economic_date';
  if ($mode === 'manual')   $dateField = ($manualBase === 'economic') ? 'j.economic_date' : 'j.invoice_date';

  // Rango default
  if ($mode !== 'manual') {
    $wMax = [];
    $pMax = [];

    $wMax[] = "$dateField IS NOT NULL";
    if ($currency !== 'ALL') { $wMax[] = "j.amount_currency = :currency"; $pMax[':currency'] = $currency; }
    if ($office !== 'all') { $wMax[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $pMax[':office'] = $office; }
    if ($traffic !== 'all') { $wMax[] = "o.conveyance_type = :traffic"; $pMax[':traffic'] = $traffic; }
    if ($chargeType !== 'all') { $wMax[] = "j.charge_type_code = :chargeType"; $pMax[':chargeType'] = $chargeType; }
    if ($financialStatus !== 'all') { $wMax[] = "o.financial_status = :financialStatus"; $pMax[':financialStatus'] = $financialStatus; }

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

  // WHERE base
  $w = [];
  $p = [];

  $w[] = "$dateField IS NOT NULL";
  $w[] = "DATE($dateField) >= :from AND DATE($dateField) <= :to";
  $p[':from'] = $from;
  $p[':to'] = $to;

  if ($currency !== 'ALL') { $w[] = "j.amount_currency = :currency"; $p[':currency'] = $currency; }
  if ($office !== 'all') { $w[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $p[':office'] = $office; }
  if ($traffic !== 'all') { $w[] = "o.conveyance_type = :traffic"; $p[':traffic'] = $traffic; }
  if ($chargeType !== 'all') { $w[] = "j.charge_type_code = :chargeType"; $p[':chargeType'] = $chargeType; }
  if ($financialStatus !== 'all') { $w[] = "o.financial_status = :financialStatus"; $p[':financialStatus'] = $financialStatus; }

  // Obtener facturas agrupadas (SIN duplicados) con suma de montos
  $sql = "
    SELECT 
      o.id as order_id,
      o.order_number as invoice_number,
      o.customer_name as client_name,
      DATE($dateField) as fecha,
      SUM(ABS(j.local_amount_value)) as monto_neto_total,
      SUM(ABS(j.local_tax_value)) as iva_total,
      SUM(ABS(j.local_amount_value) + ABS(j.local_tax_value)) as total,
      COUNT(j.id) as entry_count
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE ".implode(' AND ', $w)."
    GROUP BY o.id, DATE($dateField)
    ORDER BY 
      DATE($dateField) DESC,
      o.order_number
    LIMIT 500
  ";

  $st = $pdo->prepare($sql);
  $st->execute($p);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  json_out(200, [
    'success' => true,
    'rows' => $rows ?: [],
    'count' => count($rows),
    'range' => ['from' => $from, 'to' => $to]
  ]);

} catch (Exception $e) {
  json_out(500, [
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
?>
