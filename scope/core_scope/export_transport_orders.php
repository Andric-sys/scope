<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';

require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

function hcsv($v): string {
  $s = (string)$v;
  $s = str_replace(["\r\n","\r"], "\n", $s);
  return $s;
}
function build_like(string $q): string { return '%' . trim($q) . '%'; }
function valid_ymd(string $s): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }

$q  = trim((string)($_GET['q'] ?? ''));
$tt = trim((string)($_GET['tt'] ?? ''));
$cv = trim((string)($_GET['cv'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$pc = trim((string)($_GET['pc'] ?? ''));
$uc = trim((string)($_GET['uc'] ?? ''));

if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(
    t.transport_order_number LIKE :q OR
    t.transport_order_uuid LIKE :q OR
    o.order_number LIKE :q OR
    o.customer_name LIKE :q OR
    t.pickup_partner_name LIKE :q OR
    t.unloading_partner_name LIKE :q
  )";
  $params[':q'] = build_like($q);
}
if ($tt !== '') {
  if ($tt === '__EMPTY__') $where[] = "(t.transport_type IS NULL OR TRIM(t.transport_type)='')";
  else { $where[] = "(t.transport_type = :tt)"; $params[':tt'] = $tt; }
}
if ($cv !== '') {
  if ($cv === '__EMPTY__') $where[] = "(t.conveyance_type IS NULL OR TRIM(t.conveyance_type)='')";
  else { $where[] = "(t.conveyance_type = :cv)"; $params[':cv'] = $cv; }
}
if ($from !== '') { $where[] = "(t.transport_date >= :from)"; $params[':from'] = $from; }
if ($to !== '') { $where[] = "(t.transport_date <= :to)"; $params[':to'] = $to; }

if ($pc !== '') {
  if ($pc === '__EMPTY__') $where[] = "(t.pickup_country IS NULL OR TRIM(t.pickup_country)='')";
  else { $where[] = "(t.pickup_country = :pc)"; $params[':pc'] = $pc; }
}
if ($uc !== '') {
  if ($uc === '__EMPTY__') $where[] = "(t.unloading_country IS NULL OR TRIM(t.unloading_country)='')";
  else { $where[] = "(t.unloading_country = :uc)"; $params[':uc'] = $uc; }
}

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    t.id,
    t.order_id,
    o.order_number,
    o.customer_name,
    t.transport_order_uuid,
    t.transport_order_number,
    t.transport_type,
    t.conveyance_type,
    t.transport_date,
    t.pickup_partner_code,
    t.pickup_partner_name,
    t.pickup_country,
    t.pickup_city,
    t.pickup_unlocode,
    t.pickup_location_name,
    t.unloading_partner_code,
    t.unloading_partner_name,
    t.unloading_country,
    t.unloading_city,
    t.unloading_unlocode,
    t.unloading_location_name,
    t.pickup_window_start,
    t.pickup_window_end,
    t.delivery_window_start,
    t.delivery_window_end,
    t.pieces,
    t.gross_weight_kg,
    t.chargeable_weight_kg,
    t.volume_m3,
    t.freight_term,
    t.nature_of_goods,
    t.updated_at
  FROM scope_transport_orders t
  LEFT JOIN scope_orders o ON o.id = t.order_id
  $whereSql
  ORDER BY t.updated_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'scope_transport_orders_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
  'id','order_id','order_number','customer_name',
  'transport_order_uuid','transport_order_number',
  'transport_type','conveyance_type','transport_date',
  'pickup_partner_code','pickup_partner_name','pickup_country','pickup_city','pickup_unlocode','pickup_location_name',
  'unloading_partner_code','unloading_partner_name','unloading_country','unloading_city','unloading_unlocode','unloading_location_name',
  'pickup_window_start','pickup_window_end','delivery_window_start','delivery_window_end',
  'pieces','gross_weight_kg','chargeable_weight_kg','volume_m3',
  'freight_term','nature_of_goods','updated_at'
]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    hcsv($r['id'] ?? ''),
    hcsv($r['order_id'] ?? ''),
    hcsv($r['order_number'] ?? ''),
    hcsv($r['customer_name'] ?? ''),
    hcsv($r['transport_order_uuid'] ?? ''),
    hcsv($r['transport_order_number'] ?? ''),
    hcsv($r['transport_type'] ?? ''),
    hcsv($r['conveyance_type'] ?? ''),
    hcsv($r['transport_date'] ?? ''),
    hcsv($r['pickup_partner_code'] ?? ''),
    hcsv($r['pickup_partner_name'] ?? ''),
    hcsv($r['pickup_country'] ?? ''),
    hcsv($r['pickup_city'] ?? ''),
    hcsv($r['pickup_unlocode'] ?? ''),
    hcsv($r['pickup_location_name'] ?? ''),
    hcsv($r['unloading_partner_code'] ?? ''),
    hcsv($r['unloading_partner_name'] ?? ''),
    hcsv($r['unloading_country'] ?? ''),
    hcsv($r['unloading_city'] ?? ''),
    hcsv($r['unloading_unlocode'] ?? ''),
    hcsv($r['unloading_location_name'] ?? ''),
    hcsv($r['pickup_window_start'] ?? ''),
    hcsv($r['pickup_window_end'] ?? ''),
    hcsv($r['delivery_window_start'] ?? ''),
    hcsv($r['delivery_window_end'] ?? ''),
    hcsv($r['pieces'] ?? ''),
    hcsv($r['gross_weight_kg'] ?? ''),
    hcsv($r['chargeable_weight_kg'] ?? ''),
    hcsv($r['volume_m3'] ?? ''),
    hcsv($r['freight_term'] ?? ''),
    hcsv($r['nature_of_goods'] ?? ''),
    hcsv($r['updated_at'] ?? ''),
  ]);
}

fclose($out);
exit;
