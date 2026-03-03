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

$q   = trim((string)($_GET['q'] ?? ''));
$cur = trim((string)($_GET['cur'] ?? ''));
$minp = trim((string)($_GET['minp'] ?? ''));
$maxp = trim((string)($_GET['maxp'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$minpVal = ($minp !== '' && is_numeric($minp)) ? (float)$minp : null;
$maxpVal = ($maxp !== '' && is_numeric($maxp)) ? (float)$maxp : null;

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(j.order_id LIKE :q OR o.order_number LIKE :q OR o.customer_name LIKE :q)";
  $params[':q'] = build_like($q);
}
if ($cur !== '') {
  if ($cur === '__EMPTY__') $where[] = "(j.local_currency IS NULL OR TRIM(j.local_currency)='')";
  else { $where[] = "(j.local_currency = :cur)"; $params[':cur'] = $cur; }
}
if ($minpVal !== null) { $where[] = "(j.local_profit >= :minp)"; $params[':minp'] = $minpVal; }
if ($maxpVal !== null) { $where[] = "(j.local_profit <= :maxp)"; $params[':maxp'] = $maxpVal; }
if ($from !== '') { $where[] = "(DATE(j.updated_at) >= :from)"; $params[':from'] = $from; }
if ($to !== '') { $where[] = "(DATE(j.updated_at) <= :to)"; $params[':to'] = $to; }

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    j.order_id,
    o.order_number,
    o.customer_name,
    j.local_currency,
    j.local_booked_income,
    j.local_booked_cost,
    j.local_transit_booked_income,
    j.local_transit_booked_cost,
    j.local_total_income,
    j.local_total_cost,
    j.local_profit,
    j.local_gross_margin,
    j.org_currency,
    j.org_total_income,
    j.org_total_cost,
    j.org_profit,
    j.org_gross_margin,
    j.updated_at
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $whereSql
  ORDER BY j.updated_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'jobcosting_totals_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM para Excel

fputcsv($out, [
  'order_id','order_number','customer_name',
  'local_currency',
  'local_booked_income','local_booked_cost',
  'local_transit_booked_income','local_transit_booked_cost',
  'local_total_income','local_total_cost','local_profit','local_gross_margin',
  'org_currency','org_total_income','org_total_cost','org_profit','org_gross_margin',
  'updated_at'
]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    hcsv($r['order_id'] ?? ''),
    hcsv($r['order_number'] ?? ''),
    hcsv($r['customer_name'] ?? ''),
    hcsv($r['local_currency'] ?? ''),
    hcsv($r['local_booked_income'] ?? ''),
    hcsv($r['local_booked_cost'] ?? ''),
    hcsv($r['local_transit_booked_income'] ?? ''),
    hcsv($r['local_transit_booked_cost'] ?? ''),
    hcsv($r['local_total_income'] ?? ''),
    hcsv($r['local_total_cost'] ?? ''),
    hcsv($r['local_profit'] ?? ''),
    hcsv($r['local_gross_margin'] ?? ''),
    hcsv($r['org_currency'] ?? ''),
    hcsv($r['org_total_income'] ?? ''),
    hcsv($r['org_total_cost'] ?? ''),
    hcsv($r['org_profit'] ?? ''),
    hcsv($r['org_gross_margin'] ?? ''),
    hcsv($r['updated_at'] ?? ''),
  ]);
}

fclose($out);
exit;
