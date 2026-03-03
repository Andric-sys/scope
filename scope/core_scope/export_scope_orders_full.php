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

$q = trim((string)($_GET['q'] ?? ''));
$st = trim((string)($_GET['st'] ?? ''));
$dateField = trim((string)($_GET['df'] ?? 'transport_date'));
if (!in_array($dateField, ['transport_date','order_date','economic_date'], true)) $dateField = 'transport_date';

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$cancelled = trim((string)($_GET['cancelled'] ?? '')); // ''|0|1
$blocked   = trim((string)($_GET['blocked'] ?? ''));   // ''|0|1

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(order_number LIKE :q OR customer_name LIKE :q OR scope_uuid LIKE :q OR legacy_identifier LIKE :q)";
  $params[':q'] = build_like($q);
}
if ($st !== '') {
  if ($st === '__EMPTY__') $where[] = "(financial_status IS NULL OR TRIM(financial_status)='')";
  else { $where[] = "(financial_status = :st)"; $params[':st'] = $st; }
}
if ($from !== '') { $where[] = "($dateField >= :from)"; $params[':from'] = $from; }
if ($to !== '') { $where[] = "($dateField <= :to)"; $params[':to'] = $to; }

if ($cancelled !== '' && ($cancelled==='0' || $cancelled==='1')) {
  $where[] = "(cancelled = :c)"; $params[':c'] = (int)$cancelled;
}
if ($blocked !== '' && ($blocked==='0' || $blocked==='1')) {
  $where[] = "(blocked = :b)"; $params[':b'] = (int)$blocked;
}

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    id,
    scope_uuid,
    legacy_identifier,
    order_number,
    usi,
    organization_code,
    legal_entity_code,
    branch_code,
    module,
    conveyance_type,
    clerk,
    order_date,
    economic_date,
    transport_date,
    etd_date,
    atd_date,
    eta_date,
    ata_date,
    movement_scope,
    customer_code,
    customer_name,
    customer_city,
    customer_state,
    customer_country,
    shipper_code,
    shipper_name,
    shipper_country,
    consignee_code,
    consignee_name,
    consignee_country,
    departure_country,
    departure_unlocode,
    departure_name,
    destination_country,
    destination_unlocode,
    destination_name,
    pieces,
    gross_weight_kg,
    chargeable_weight_kg,
    volume_m3,
    nature_of_goods,
    dgr,
    financial_status,
    status_to_closed_date,
    cost_center_code,
    cancelled,
    blocked,
    consolidated,
    last_modified_utc,
    updated_at
  FROM scope_orders
  $whereSql
  ORDER BY updated_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'scope_orders_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
  'id','scope_uuid','legacy_identifier','order_number','usi',
  'organization_code','legal_entity_code','branch_code',
  'module','conveyance_type','clerk',
  'order_date','economic_date','transport_date','etd_date','atd_date','eta_date','ata_date',
  'movement_scope',
  'customer_code','customer_name','customer_city','customer_state','customer_country',
  'shipper_code','shipper_name','shipper_country',
  'consignee_code','consignee_name','consignee_country',
  'departure_country','departure_unlocode','departure_name',
  'destination_country','destination_unlocode','destination_name',
  'pieces','gross_weight_kg','chargeable_weight_kg','volume_m3',
  'nature_of_goods','dgr',
  'financial_status','status_to_closed_date','cost_center_code',
  'cancelled','blocked','consolidated',
  'last_modified_utc','updated_at'
]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    hcsv($r['id'] ?? ''),
    hcsv($r['scope_uuid'] ?? ''),
    hcsv($r['legacy_identifier'] ?? ''),
    hcsv($r['order_number'] ?? ''),
    hcsv($r['usi'] ?? ''),
    hcsv($r['organization_code'] ?? ''),
    hcsv($r['legal_entity_code'] ?? ''),
    hcsv($r['branch_code'] ?? ''),
    hcsv($r['module'] ?? ''),
    hcsv($r['conveyance_type'] ?? ''),
    hcsv($r['clerk'] ?? ''),
    hcsv($r['order_date'] ?? ''),
    hcsv($r['economic_date'] ?? ''),
    hcsv($r['transport_date'] ?? ''),
    hcsv($r['etd_date'] ?? ''),
    hcsv($r['atd_date'] ?? ''),
    hcsv($r['eta_date'] ?? ''),
    hcsv($r['ata_date'] ?? ''),
    hcsv($r['movement_scope'] ?? ''),
    hcsv($r['customer_code'] ?? ''),
    hcsv($r['customer_name'] ?? ''),
    hcsv($r['customer_city'] ?? ''),
    hcsv($r['customer_state'] ?? ''),
    hcsv($r['customer_country'] ?? ''),
    hcsv($r['shipper_code'] ?? ''),
    hcsv($r['shipper_name'] ?? ''),
    hcsv($r['shipper_country'] ?? ''),
    hcsv($r['consignee_code'] ?? ''),
    hcsv($r['consignee_name'] ?? ''),
    hcsv($r['consignee_country'] ?? ''),
    hcsv($r['departure_country'] ?? ''),
    hcsv($r['departure_unlocode'] ?? ''),
    hcsv($r['departure_name'] ?? ''),
    hcsv($r['destination_country'] ?? ''),
    hcsv($r['destination_unlocode'] ?? ''),
    hcsv($r['destination_name'] ?? ''),
    hcsv($r['pieces'] ?? ''),
    hcsv($r['gross_weight_kg'] ?? ''),
    hcsv($r['chargeable_weight_kg'] ?? ''),
    hcsv($r['volume_m3'] ?? ''),
    hcsv($r['nature_of_goods'] ?? ''),
    hcsv($r['dgr'] ?? ''),
    hcsv($r['financial_status'] ?? ''),
    hcsv($r['status_to_closed_date'] ?? ''),
    hcsv($r['cost_center_code'] ?? ''),
    hcsv($r['cancelled'] ?? 0),
    hcsv($r['blocked'] ?? 0),
    hcsv($r['consolidated'] ?? 0),
    hcsv($r['last_modified_utc'] ?? ''),
    hcsv($r['updated_at'] ?? ''),
  ]);
}

fclose($out);
exit;
