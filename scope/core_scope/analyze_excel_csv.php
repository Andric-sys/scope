<?php
$archivo = 'c:\xampp\htdocs\scope\scope\excel\Facturacion Febrero  2026 (3)(ENE MXN).csv';
$handle = fopen($archivo, 'r');

echo "═══════════════════════════════════════════════════════════\n";
echo "ANÁLISIS DEL CSV EXCEL - ENERO 2026\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Leer encabezados (primera línea)
$linea = fgets($handle);
$encabezados = str_getcsv($linea, ';');

echo "PRIMERAS 15 COLUMNAS:\n";
for ($i = 0; $i < 15; $i++) {
    echo "[$i] " . $encabezados[$i] . "\n";
}

// Encontrar columnas relevantes
$idx_complementarios = array_search('COMPLEMENTARIOS', $encabezados);
$idx_iva = array_search('IVA', $encabezados);
$idx_subtotal = array_search('SUBTOTAL', $encabezados);
$idx_total = array_search('TOTAL', $encabezados);

echo "\n📍 POSICIONES DE COLUMNAS CLAVE:\n";
echo "COMPLEMENTARIOS: $idx_complementarios\n";
echo "IVA: $idx_iva\n";
echo "SUBTOTAL: $idx_subtotal\n";
echo "TOTAL: $idx_total\n";

// Procesar filas de datos
$suma_complementarios = 0;
$suma_subtotal = 0;
$suma_total = 0;
$suma_iva = 0;
$contador_facturas = 0;

echo "\nPROCESANDO FACTURAS:\n";

while (($linea = fgets($handle)) !== false) {
    // Saltar líneas vacías
    $linea = trim($linea);
    if (empty($linea)) continue;
    
    // Saltar líneas de totales (tienen "TOTAL" en el texto)
    if (strpos($linea, 'TOTAL') !== false || strpos($linea, '(') !== false) {
        continue;
    }
    
    $fila = str_getcsv($linea, ';');
    
    // Verificar que sea una factura válida (debe tener factura #)
    if (empty($fila[1])) continue;
    
    $contador_facturas++;
    
    // Extraer y suma r valores
    $comp = floatval(str_replace([' ', ','], ['.', '.'], $fila[$idx_complementarios] ?? 0));
    $iva_val = floatval(str_replace([' ', ','], ['.', '.'], $fila[$idx_iva] ?? 0));
    $subtot = floatval(str_replace([' ', ','], ['.', '.'], $fila[$idx_subtotal] ?? 0));
    $tot = floatval(str_replace([' ', ','], ['.', '.'], $fila[$idx_total] ?? 0));
    
    if ($comp > 0 || $subtot > 0 || $tot > 0) {
        $suma_complementarios += $comp;
        $suma_iva += $iva_val;
        $suma_subtotal += $subtot;
        $suma_total += $tot;
    }
}

fclose($handle);

echo "Total de facturas procesadas: $contador_facturas\n\n";

echo "SUMAS CALCULADAS:\n";
echo "Sum COMPLEMENTARIOS: " . number_format($suma_complementarios, 2, '.', ',') . " MXN\n";
echo "Sum IVA:             " . number_format($suma_iva, 2, '.', ',') . " MXN\n";
echo "Sum SUBTOTAL:        " . number_format($suma_subtotal, 2, '.', ',') . " MXN\n";
echo "Sum TOTAL:           " . number_format($suma_total, 2, '.', ',') . " MXN\n";

echo "\n═══════════════════════════════════════════════════════════\n";
echo "COMPARACIÓN CON META EXCEL:\n";
echo "═══════════════════════════════════════════════════════════\n";
$meta = 3666487.68;
echo "EXCEL TOTAL FACTURADOS: 3.666.487,68 MXN\n\n";

echo "Diferencia con COMPLEMENTARIOS: " . number_format($meta - $suma_complementarios, 2, '.', ',') . "\n";
echo "Diferencia con SUBTOTAL: ". number_format($meta - $suma_subtotal, 2, '.', ',') . "\n";
echo "Diferencia con TOTAL: ". number_format($meta - $suma_total, 2, '.', ',') . "\n";
echo "Diferencia con (COMP + algunoIVA): ... probando fórmulas\n";

// Probar si es COMPLEMENTARIOS + parte del IVA
$test1 = $suma_complementarios + ($suma_iva * 0.5);
$test2 = $suma_complementarios - ($suma_iva * 0.5);

echo "\nFÓRMULAS ALTERNATIVAS:\n";
echo "COMP + (IVA/2): " . number_format($test1, 2, '.', ',') . " | Diferencia: " . number_format($meta - $test1, 2, '.', ',') . "\n";
echo "COMP - (IVA/2): " . number_format($test2, 2, '.', ',') . " | Diferencia: " . number_format($meta - $test2, 2, '.', ',') . "\n";

// Probar si es SUBTOTAL
if (abs($meta - $suma_subtotal) < 1) {
    echo "\n✓ MATCH ENCONTRADO: El total de Excel es SUBTOTAL\n";
}

?>
