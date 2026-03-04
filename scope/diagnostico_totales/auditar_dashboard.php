<?php
declare(strict_types=1);

/**
 * Script aislado para auditar diferencias de totales vs lógica dashboard.
 * No modifica datos, solo consulta.
 *
 * Uso:
 *   php auditar_dashboard.php
 *   php auditar_dashboard.php --from=2025-03-01 --to=2026-02-28
 */

$root = dirname(__DIR__);
$configPath = $root . '/core_scope/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "No se encontró config: $configPath\n");
    exit(1);
}

$config = require $configPath;
$db = $config['db'] ?? null;
if (!is_array($db)) {
    fwrite(STDERR, "Config inválida: falta sección db\n");
    exit(1);
}

$fromArg = null;
$toArg = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--from=') === 0) {
        $fromArg = substr($arg, 7);
    }
    if (strpos($arg, '--to=') === 0) {
        $toArg = substr($arg, 5);
    }
}

$to = $toArg;
$from = $fromArg;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$to) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$from)) {
    $to = null;
    $from = null;
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    (string)($db['host'] ?? '127.0.0.1'),
    (string)($db['name'] ?? ''),
    (string)($db['charset'] ?? 'utf8mb4')
);

$pdo = new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if ($from === null || $to === null) {
    $maxInvoice = (string)$pdo->query("SELECT DATE(MAX(invoice_date)) FROM scope_jobcosting_entries WHERE invoice_date IS NOT NULL")->fetchColumn();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maxInvoice)) {
        $maxInvoice = date('Y-m-d');
    }

    $to = $maxInvoice;
    $from = (new DateTimeImmutable($to))
        ->modify('first day of this month')
        ->sub(new DateInterval('P11M'))
        ->format('Y-m-d');
}

$kpiSql = "
SELECT
  COALESCE(SUM(CASE WHEN entry_type='income' AND (charge_type_code IS NULL OR charge_type_code NOT LIKE 'PT%') THEN local_amount_value ELSE 0 END),0) AS kpi_sales,
  COALESCE(SUM(CASE WHEN entry_type='payable' THEN local_amount_value ELSE 0 END),0) AS kpi_costs,
  COALESCE(SUM(CASE WHEN entry_type='income' AND charge_type_code LIKE 'PT%' THEN local_amount_value ELSE 0 END),0) AS kpi_ter,
  COALESCE(SUM(CASE WHEN entry_type='income' THEN local_amount_value ELSE 0 END),0) AS income_raw,
  COALESCE(SUM(CASE WHEN entry_type NOT IN ('income','payable') THEN local_amount_value ELSE 0 END),0) AS other_entry_types,
  COALESCE(SUM(local_amount_value),0) AS all_amount,
  COALESCE(SUM(local_tax_value),0) AS all_tax,
  COUNT(*) AS total_rows
FROM scope_jobcosting_entries
WHERE invoice_date IS NOT NULL
  AND DATE(invoice_date) BETWEEN :from AND :to
";

$st = $pdo->prepare($kpiSql);
$st->execute([':from' => $from, ':to' => $to]);
$kpi = $st->fetch() ?: [];

$typesSql = "
SELECT entry_type, COUNT(*) AS rows_count,
       COALESCE(SUM(local_amount_value),0) AS amount_sum,
       COALESCE(SUM(local_tax_value),0) AS tax_sum
FROM scope_jobcosting_entries
WHERE invoice_date IS NOT NULL
  AND DATE(invoice_date) BETWEEN :from AND :to
GROUP BY entry_type
ORDER BY rows_count DESC
";
$st2 = $pdo->prepare($typesSql);
$st2->execute([':from' => $from, ':to' => $to]);
$types = $st2->fetchAll();

$kpiSales = (float)($kpi['kpi_sales'] ?? 0);
$kpiCosts = (float)($kpi['kpi_costs'] ?? 0);
$kpiProfit = $kpiSales - $kpiCosts;
$kpiMargin = $kpiSales > 0 ? ($kpiProfit / $kpiSales) : 0.0;
$incomeRaw = (float)($kpi['income_raw'] ?? 0);
$kpiTer = (float)($kpi['kpi_ter'] ?? 0);

$fmtMoney = static fn(float $n): string => number_format($n, 2, '.', ',');
$fmtPct = static fn(float $n): string => number_format($n * 100, 2, '.', ',') . '%';

echo "\n=== Auditoría Totales Dashboard (aislada) ===\n";
echo "Rango invoice_date: $from -> $to\n\n";

echo "KPI Sales (income sin PT):  " . $fmtMoney($kpiSales) . "\n";
echo "KPI Costs (payable):        " . $fmtMoney($kpiCosts) . "\n";
echo "KPI Profit:                 " . $fmtMoney($kpiProfit) . "\n";
echo "KPI Margin:                 " . $fmtPct($kpiMargin) . "\n";
echo "TER (income PT%):           " . $fmtMoney($kpiTer) . "\n\n";

echo "Income raw (incluye PT):    " . $fmtMoney($incomeRaw) . "\n";
echo "Diff raw vs KPI Sales:      " . $fmtMoney($incomeRaw - $kpiSales) . "\n";
echo "Otros entry_type (fuera income/payable): " . $fmtMoney((float)($kpi['other_entry_types'] ?? 0)) . "\n";
echo "Suma total amount (todos):  " . $fmtMoney((float)($kpi['all_amount'] ?? 0)) . "\n";
echo "Suma total tax (todos):     " . $fmtMoney((float)($kpi['all_tax'] ?? 0)) . "\n";
echo "Filas consideradas:         " . (int)($kpi['total_rows'] ?? 0) . "\n\n";

echo "--- Desglose por entry_type ---\n";
foreach ($types as $t) {
    $et = (string)($t['entry_type'] ?? '');
    $rc = (int)($t['rows_count'] ?? 0);
    $am = (float)($t['amount_sum'] ?? 0);
    $tx = (float)($t['tax_sum'] ?? 0);
    echo str_pad("[$et]", 28) . " rows=" . str_pad((string)$rc, 6, ' ', STR_PAD_LEFT)
        . " amount=" . str_pad($fmtMoney($am), 16, ' ', STR_PAD_LEFT)
        . " tax=" . str_pad($fmtMoney($tx), 14, ' ', STR_PAD_LEFT) . "\n";
}

echo "\nListo. No se modificó ningún dato.\n";
