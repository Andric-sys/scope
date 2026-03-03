<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

function valid_ymd(string $s): bool {
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

function es_financial_status(?string $v): string {
  $v = trim((string)$v);
  if ($v === '') return 'Sin estatus';

  $key = mb_strtolower($v);
  $map = [
    'open' => 'Abierto',
    'closed' => 'Cerrado',
    'invoiced' => 'Facturado',
    'notinvoiced' => 'No facturado',
    'not invoiced' => 'No facturado',
    'partlyinvoiced' => 'Parcialmente facturado',
    'partly invoiced' => 'Parcialmente facturado',
    'readyforinvoice' => 'Listo para facturar',
    'ready for invoice' => 'Listo para facturar',
    'unknown' => 'Desconocido',
  ];
  return $map[$key] ?? $v;
}

$q    = trim((string)($_GET['q'] ?? ''));
$st   = trim((string)($_GET['st'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(order_number LIKE :q OR customer_name LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}
if ($st !== '') {
  if ($st === '__EMPTY__') {
    $where[] = "(financial_status IS NULL OR TRIM(financial_status) = '')";
  } else {
    $where[] = "(financial_status = :st)";
    $params[':st'] = $st;
  }
}
if ($from !== '') {
  $where[] = "(transport_date >= :from)";
  $params[':from'] = $from;
}
if ($to !== '') {
  $where[] = "(transport_date <= :to)";
  $params[':to'] = $to;
}

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    order_number,
    customer_name,
    transport_date,
    last_modified_utc,
    financial_status
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

// BOM para Excel (UTF-8)
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados (ES)
fputcsv($out, [
  'Orden',
  'Cliente',
  'Fecha de transporte',
  'Última modificación (UTC)',
  'Estatus financiero',
]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    (string)($r['order_number'] ?? ''),
    (string)($r['customer_name'] ?? ''),
    (string)($r['transport_date'] ?? ''),
    (string)($r['last_modified_utc'] ?? ''),
    es_financial_status((string)($r['financial_status'] ?? '')),
  ]);
}

fclose($out);
exit;
