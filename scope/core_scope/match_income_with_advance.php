<?php
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');
$pdo = db();

// Leer ANTICIPOS del archivo Excel
$archivo_mxn = 'c:\xampp\htdocs\scope\scope\excel\Facturacion Febrero  2026 (3)(ENE MXN).csv';

$anticipos_excel = [];
$handle = fopen($archivo_mxn, 'r');
$header = fgetcsv($handle, 0, ';');
$idx_factura = array_search('FACTURA', $header);
$idx_anticipo = array_search('ANTICIPO', $header);

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (empty($row[0]) && empty($row[$idx_factura ?? 0])) continue;
    if (strpos(implode(';', $row), 'TOTAL') !== false) continue;
    
    if (isset($row[$idx_factura]) && isset($row[$idx_anticipo])) {
        $factura = trim($row[$idx_factura] ?? '');
        $anticipo_str = trim($row[$idx_anticipo] ?? '');
        
        if ($factura !== '' && $anticipo_str !== '') {
            $anticipo = (float) str_replace(['.', ','], ['', '.'], $anticipo_str);
            $anticipos_excel[$factura] = $anticipo;
        }
    }
}
fclose($handle);

echo "═══════════════════════════════════════════════════════════\n";
echo "CRUZANDO DATOS: ANTICIPOS EXCEL vs INCOME BD\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Obtener facturas INCOME de BD y sus anticipos
$sql = "
  SELECT DISTINCT
    j.entry_number as factura,
    LOWER(j.entry_type) as tipo_entry,
    j.amount_currency as moneda,
    o.financial_status,
    SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0)) as subtotal
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND LOWER(j.entry_type) LIKE '%income%'
    AND j.entry_number IS NOT NULL
    AND j.entry_number <> ''
  GROUP BY j.entry_number
  ORDER BY j.entry_number
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$facturas_bd = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$suma_income_bd = 0;
$suma_anticipo_de_income = 0;

echo "FACTURAS INCOME EN BD (con ANTICIPOS del Excel):\n";
echo "┌─ Factura ──┬─ Status ──┬─ Subtotal ──┬─ Anticipo Excel ──┐\n";

foreach ($facturas_bd as $f) {
    $factura = $f['factura'];
    $anticipo = $anticipos_excel[$factura] ?? 0;
    $subtotal = $f['subtotal'];
    
    $suma_income_bd += $subtotal;
    $suma_anticipo_de_income += $anticipo;
    
    echo "│ " . str_pad($factura, 12) . "│ " . str_pad($f['financial_status'] ?? 'NULL', 10) . "│ " . str_pad(number_format($subtotal, 2, '.', ','), 12) . "│ " . str_pad(number_format($anticipo, 2, '.', ','), 18) . "│\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "RESUMEN DEL CRUCE:\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Suma SUBTOTAL INCOME (BD):              " . number_format($suma_income_bd, 2, '.', ',') . " MXN\n";
echo "Suma ANTICIPO de facturas INCOME (Excel): " . number_format($suma_anticipo_de_income, 2, '.', ',') . " MXN\n";
echo "TOTAL Neto (SUBTOTAL - ANTICIPO):       " . number_format($suma_income_bd - $suma_anticipo_de_income, 2, '.', ',') . " MXN\n";

$meta = 3666487.68;
echo "\nMeta EXCEL:                            " . number_format($meta, 2, '.', ',') . " MXN\n";
echo "Diferencia:                            " . number_format(($suma_income_bd - $suma_anticipo_de_income) - $meta, 2, '.', ',') . " MXN\n";

if (abs(($suma_income_bd - $suma_anticipo_de_income) - $meta) < 1) {
    echo "\n✓✓✓ ¡PATRÓN ENCONTRADO! ✓✓✓\n";
    echo "FÓRMULA: SUBTOTAL (INCOME) - ANTICIPO (del Excel) = TOTAL\n";
}

?>
