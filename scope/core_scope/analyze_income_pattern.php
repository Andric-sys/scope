<?php
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');
$pdo = db();

echo "═══════════════════════════════════════════════════════════\n";
echo "ANÁLISIS CON FILTRO INCOME - ENERO 2026\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Solo INCOME entries
$sql = "
  SELECT
    j.amount_currency as moneda,
    o.financial_status as status,
    COUNT(DISTINCT j.entry_number) as facturas,
    COUNT(*) as lineas,
    LOWER(j.entry_type) as tipo,
    SUM(IFNULL(j.amount_value,0)) as neto,
    SUM(IFNULL(j.tax_value,0)) as iva,
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND LOWER(j.entry_type) LIKE '%income%'
  GROUP BY j.amount_currency, o.financial_status
  ORDER BY moneda, status
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$patterns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "PATRÓN: POR MONEDA + STATUS (SOLO INCOME):\n";
echo "┌─ Moneda ─┬─ Status ─┬─ Facturas ─┬─ Subtotal ─┐\n";

$total_income_mxn = 0;
$total_income_usd = 0;

foreach ($patterns as $p) {
    $status = $p['status'] ?? 'NULL';
    $moneda = $p['moneda'];
    $subtotal = $p['subtotal'];
    echo "│ {$moneda} │ {$status} │ {$p['facturas']} │ " . number_format($subtotal, 2, '.', ',') . " │\n";
    
    if ($moneda === 'MXN') {
        $total_income_mxn += $subtotal;
    } else if ($moneda === 'USD') {
        $total_income_usd += $subtotal;
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "TOTALES (SOLO INCOME):\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Total INCOME MXN: " . number_format($total_income_mxn, 2, '.', ',') . " MXN\n";
echo "Total INCOME USD: " . number_format($total_income_usd, 2, '.', ',') . " USD\n";

$meta = 3666487.68;
echo "\nMeta EXCEL: " . number_format($meta, 2, '.', ',') . " MXN\n";
echo "Diferencia (MXN - Meta): " . number_format($total_income_mxn - $meta, 2, '.', ',') . " MXN\n";

// ¿Qué pasa si solo contamos closed + income?
echo "\n\n═══════════════════════════════════════════════════════════\n";
echo "PRUEBA: SOLO CLOSED + INCOME + MXN\n";
echo "═══════════════════════════════════════════════════════════\n";

$sql_closed = "
  SELECT
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND LOWER(j.entry_type) LIKE '%income%'
    AND o.financial_status = 'closed'
";

$stmt = $pdo->prepare($sql_closed);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$closed_mxn = $result['subtotal'] ?? 0;

echo "Closed + Income + MXN: " . number_format($closed_mxn, 2, '.', ',') . " MXN\n";
echo "Diferencia vs Meta: " . number_format($closed_mxn - $meta, 2, '.', ',') . " MXN\n";

// ¿Qué pasa si solo contamos billed + income?
echo "\n═══════════════════════════════════════════════════════════\n";
echo "PRUEBA: SOLO BILLED + INCOME + MXN\n";
echo "═══════════════════════════════════════════════════════════\n";

$sql_billed = "
  SELECT
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND LOWER(j.entry_type) LIKE '%income%'
    AND o.financial_status = 'billed'
";

$stmt = $pdo->prepare($sql_billed);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$billed_mxn = $result['subtotal'] ?? 0;

echo "Billed + Income + MXN: " . number_format($billed_mxn, 2, '.', ',') . " MXN\n";
echo "Diferencia vs Meta: " . number_format($billed_mxn - $meta, 2, '.', ',') . " MXN\n";

// ¿Qué pasa si restamos los anticipos (USD = 100%)?
echo "\n═══════════════════════════════════════════════════════════\n";
echo "PRUEBA: INCOME (todos statuses) - ANTICIPO (USD=100%)\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "(Asumiendo USD entries = 100% anticipo)\n\n";

$sql_usd_total = "
  SELECT
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'USD'
    AND LOWER(j.entry_type) LIKE '%income%'
";

$stmt = $pdo->prepare($sql_usd_total);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$usd_income = $result['subtotal'] ?? 0;

$net_income = $total_income_mxn + $usd_income;
$with_anticipo = $total_income_mxn;  // Si USD es 100% anticipo, no se suma

echo "MXN Income: " . number_format($total_income_mxn, 2, '.', ',') . " MXN\n";
echo "USD Income: " . number_format($usd_income, 2, '.', ',') . " USD ≈ 100% anticipo\n";
echo "Total NETO (sin USD): " . number_format($with_anticipo, 2, '.', ',') . " MXN\n";
echo "Meta: " . number_format($meta, 2, '.', ',') . " MXN\n";
echo "Diferencia: " . number_format($with_anticipo - $meta, 2, '.', ',') . " MXN\n";

if (abs($with_anticipo - $meta) < 1) {
    echo "\n✓ ¡PATRÓN ENCONTRADO!\n";
}

?>
