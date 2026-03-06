<?php
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');
$pdo = db();

echo "═══════════════════════════════════════════════════════════\n";
echo "ANALIZANDO PATRÓN DE ANTICIPOS - ENERO 2026\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Leer datos de BD agrupados por factura (entry_number)
$sql = "
  SELECT
    j.entry_number                                       AS factura,
    j.amount_currency                                    AS moneda,
    o.financial_status,
    DATE(j.invoice_date)                                 AS fecha_factura,
    COUNT(*) AS lineas,
    SUM(IFNULL(j.amount_value,0))                        AS neto,
    SUM(IFNULL(j.tax_value,0))                           AS iva,
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) AS subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.entry_number IS NOT NULL
    AND j.entry_number <> ''
  GROUP BY
    j.entry_number, j.amount_currency, DATE(j.invoice_date),
    o.financial_status
  ORDER BY fecha_factura ASC, factura ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$facturas_bd = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo "FACTURAS EN BD:\n";
echo "┌─ Factura ─┬─ Moneda ─┬─ Status ─┬─ Subtotal ─┐\n";
foreach ($facturas_bd as $f) {
    $fact = str_pad($f['factura'], 15);
    $mon = str_pad($f['moneda'], 6);
    $st = str_pad($f['financial_status'] ?? 'NULL', 10);
    $subt = number_format($f['subtotal'], 2);
    echo "│ $fact │ $mon │ $st │ $subt │\n";
}

// Buscar patrón: USD vs MXN, Status, etc
echo "\n\nANÁLISIS DE PATRONES:\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Patrón 1: Por Moneda
echo "1. PATRÓN POR MONEDA:\n";
$sql_moneda = "
  SELECT
    j.amount_currency as moneda,
    o.financial_status as status,
    COUNT(DISTINCT j.entry_number) as facturas,
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
  GROUP BY j.amount_currency, o.financial_status
  ORDER BY j.amount_currency, o.financial_status
";

$stmt = $pdo->prepare($sql_moneda);
$stmt->execute();
$patterns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($patterns as $p) {
    $status = $p['status'] ?? 'NULL';
    echo "  Moneda: {$p['moneda']} | Status: $status | Facturas: {$p['facturas']} | Subtotal: " . number_format($p['subtotal'], 2) . "\n";
}

// Patrón 2: Por Financial Status
echo "\n2. PATRÓN POR FINANCIAL_STATUS:\n";
$sql_status = "
  SELECT
    o.financial_status as status,
    COUNT(DISTINCT j.entry_number) as facturas,
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
  GROUP BY o.financial_status
  ORDER BY subtotal DESC
";

$stmt = $pdo->prepare($sql_status);
$stmt->execute();
$status_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($status_patterns as $p) {
    $status = $p['status'] ?? 'NULL';
    echo "  Status: $status | Facturas: {$p['facturas']} | Subtotal: " . number_format($p['subtotal'], 2) . "\n";
}

// Teoría: Si USD = 100% anticipo, MXN = 0% anticipo
echo "\n\nTEORÍA: USD tiene 100% anticipo, MXN tiene 0%\n";
echo "═════════════════════════════════════════════════════════════\n";

$total_usd = 0;
$total_mxn = 0;

foreach ($facturas_bd as $f) {
    if ($f['moneda'] === 'USD') {
        $total_usd += $f['subtotal'];
    } else if ($f['moneda'] === 'MXN') {
        $total_mxn += $f['subtotal'];
    }
}

echo "Si USD (100% anticipo) → No se cuenta para TOTAL\n";
echo "Si MXN (0% anticipo) → Se cuenta 100% para TOTAL\n\n";
echo "Total MXN (que sería el TOTAL): " . number_format($total_mxn, 2, '.', ',') . " MXN\n";
echo "Total USD (que sería 100% anticipo): " . number_format($total_usd, 2, '.', ',') . " USD\n";

// Meta
$meta = 3666487.68;
echo "\nMeta EXCEL: " . number_format($meta, 2, '.', ',') . " MXN\n";
echo "Diferencia (MXN - Meta): " . number_format($total_mxn - $meta, 2, '.', ',') . " MXN\n";

if (abs($total_mxn - $meta) < 1) {
    echo "\n✓ ¡PATRÓN ENCONTRADO! Anticipo = 100% si USD, 0% si MXN\n";
}

?>
