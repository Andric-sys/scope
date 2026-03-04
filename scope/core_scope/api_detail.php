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

function ym_expr(string $field): string {
  return "DATE_FORMAT($field, '%Y-%m')";
}

try {
  $pdo = db();

  $kind = (string)($_GET['kind'] ?? '');
  $dateField = (string)($_GET['date_field'] ?? 'invoice_date'); // invoice_date|economic_date
  if (!in_array($dateField, ['invoice_date','economic_date'], true)) $dateField = 'invoice_date';
  $field = "j.$dateField";

  $from = (string)($_GET['from'] ?? '');
  $to   = (string)($_GET['to'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_out(400, ['success'=>false,'message'=>'from/to requeridos (YYYY-MM-DD).']);
  }

  $office = (string)($_GET['office'] ?? 'all');
  $traffic = (string)($_GET['traffic'] ?? 'all');
  $currency = strtoupper(trim((string)($_GET['currency'] ?? 'MXN')));
  if ($currency === '') $currency = 'MXN';

  $w = [];
  $p = [];

  $w[] = "$field IS NOT NULL";
  $w[] = "DATE($field) BETWEEN :from AND :to";
  $p[':from'] = $from;
  $p[':to'] = $to;
  if ($currency !== 'ALL') { $w[] = "j.amount_currency = :currency"; $p[':currency'] = $currency; }

  if ($office !== 'all') { $w[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $p[':office']=$office; }
  if ($traffic !== 'all') { $w[] = "o.conveyance_type = :traffic"; $p[':traffic']=$traffic; }

  $where = implode(' AND ', $w);
  $ym = ym_expr($field);

  if ($kind === 'customer') {
    $customerCode = (string)($_GET['customer_code'] ?? '');
    if ($customerCode === '') json_out(400, ['success'=>false,'message'=>'customer_code requerido']);

    $p[':cust'] = $customerCode;

    $sql = "
      SELECT
        $ym AS ym,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
          THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%'
          THEN j.local_amount_value ELSE 0 END),0) AS costs
      FROM scope_jobcosting_entries j
      LEFT JOIN scope_orders o ON o.id = j.order_id
      WHERE $where AND o.customer_code = :cust
      GROUP BY $ym
      ORDER BY ym ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_out(200, ['success'=>true,'rows'=>$rows]);
  }

  if ($kind === 'traffic') {
    $trafficCode = (string)($_GET['traffic_code'] ?? '');
    if ($trafficCode === '') json_out(400, ['success'=>false,'message'=>'traffic_code requerido']);

    $p[':t'] = $trafficCode;

    $sql = "
      SELECT
        COALESCE(j.charge_type_code,'—') AS concept_code,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
          THEN (IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) ELSE 0 END),0) AS sales,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%'
          THEN j.local_amount_value ELSE 0 END),0) AS costs
      FROM scope_jobcosting_entries j
      LEFT JOIN scope_orders o ON o.id = j.order_id
      WHERE $where AND o.conveyance_type = :t
      GROUP BY COALESCE(j.charge_type_code,'—')
      ORDER BY sales DESC
      LIMIT 30
    ";
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_out(200, ['success'=>true,'rows'=>$rows]);
  }

  if ($kind === 'concept') {
    $conceptCode = (string)($_GET['concept_code'] ?? '');
    if ($conceptCode === '') json_out(400, ['success'=>false,'message'=>'concept_code requerido']);

    $p[':c'] = $conceptCode;

    $sql = "
      SELECT
        o.order_number,
        o.customer_name,
        o.conveyance_type,
        COALESCE(j.cost_center_code, o.cost_center_code) AS office,
        DATE_FORMAT(DATE($field), '%d/%m/%Y') AS date_field,
        j.entry_type,
        j.local_amount_value AS amount_local,
        j.local_tax_value AS tax_local
      FROM scope_jobcosting_entries j
      LEFT JOIN scope_orders o ON o.id = j.order_id
      WHERE $where AND j.charge_type_code = :c
      ORDER BY DATE($field) DESC
      LIMIT 300
    ";
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_out(200, ['success'=>true,'rows'=>$rows]);
  }

  json_out(400, ['success'=>false,'message'=>'kind inválido']);

} catch (Throwable $e) {
  json_out(500, ['success'=>false,'message'=>'Error','error'=>$e->getMessage()]);
}