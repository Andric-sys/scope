<?php
// Leer el archivo CSV
$file = 'excel/Facturacion Febrero  2026 (3)(ENE USD ).csv';
$lines = file($file);

echo "Total de líneas: " . count($lines) . "\n";
echo "======================================\n\n";

// Procesar el archivo
$row_num = 0;
$suma_complementarios_mxn = 0;
$suma_subtotal_mxn = 0;
$suma_total_mxn = 0;
$suma_anticipo_mxn = 0;

foreach ($lines as $line) {
    $row_num++;
    
    // Parsear CSV manualmente con semicolon
    $data = str_getcsv($line, ';');
    
    // Saltar líneas vacías o muy cortas
    if (empty(trim($data[0])) && count($data) < 15) {
        continue;
    }
    
    // Saltar el header (primera línea tiene "PROFIT")
    if (strpos($data[0], 'PROFIT') !== false) {
        continue;
    }
    
    // Las columnas en MXN están después de TIPOCAMBIO
    // Estructura esperada:
    // PROFIT(0);EJECUTIVO(1);T OPERACIÓN(2);SERIE(3);FACTURA(4);REFERENCIA(5);FECHA(6);CLIENTE(7);CLIENTENOMBRE(8);
    // + USD : COMPROBADOS(9);COMPLEMENTARIOS(10);IVA(11);SUBTOTAL(12);ANTICIPO(13);TOTAL(14);TIPOCAMBIO(15);
    // + MXN : COMPLEMENTARIOS(16);IVA(17);SUBTOTAL(18);ANTICIPO(19);TOTAL(20);
    
    // Si la fila tiene menos de 15 elementos o no parece tener datos numéricos en posiciones clave, tratarla especialmente
    if (count($data) < 17) {
        // Línea de resumen o especial
        if (count($data) > 16 && !empty($data[16])) {
            $val = trim($data[16]);
            $val_clean = str_replace(',', '.', $val);
            if (is_numeric($val_clean)) {
                echo "Fila $row_num (RESUMEN): COMP_MXN=" . $val_clean . "\n";
                $suma_complementarios_mxn += (float)$val_clean;
            }
        }
        continue;
    }
    
    // Extraer valores MXN (columnas 16-20)
    $complementarios_mxn = isset($data[16]) ? trim($data[16]) : '';
    $iva_mxn = isset($data[17]) ? trim($data[17]) : '';
    $subtotal_mxn = isset($data[18]) ? trim($data[18]) : '';
    $anticipo_mxn = isset($data[19]) ? trim($data[19]) : '';
    $total_mxn = isset($data[20]) ? trim($data[20]) : '';
    
    // Convertir desde formato europeo (1.234,56) a número PHP
    // Paso 1: Eliminar separadores de mil (punto)
    // Paso 2: Reemplazar coma decimal por punto
    $complementarios_mxn_num = (float)str_replace(',', '.', str_replace('.', '', $complementarios_mxn));
    $subtotal_mxn_num = (float)str_replace(',', '.', str_replace('.', '', $subtotal_mxn));
    $anticipo_mxn_num = (float)str_replace(',', '.', str_replace('.', '', $anticipo_mxn));
    $total_mxn_num = (float)str_replace(',', '.', str_replace('.', '', $total_mxn));
    
    // Sumar si son números válidos
    if ($complementarios_mxn_num > 0 || strpos($complementarios_mxn, 'USD') !== false) {
        $suma_complementarios_mxn += $complementarios_mxn_num;
    }
    if ($subtotal_mxn_num > 0 || strpos($subtotal_mxn, 'USD') !== false) {
        $suma_subtotal_mxn += $subtotal_mxn_num;
    }
    if ($anticipo_mxn_num > 0 || strpos($anticipo_mxn, 'USD') !== false) {
        $suma_anticipo_mxn += $anticipo_mxn_num;
    }
    if ($total_mxn_num > 0 || strpos($total_mxn, 'USD') !== false) {
        $suma_total_mxn += $total_mxn_num;
    }
    
    // Mostrar datos de las primeras 15 filas con datos
    if ($row_num <= 15 && $complementarios_mxn_num > 0) {
        echo "Fila $row_num: COMP=" . number_format($complementarios_mxn_num, 2) . ", SUB=" . number_format($subtotal_mxn_num, 2) . ", ANT=" . number_format($anticipo_mxn_num, 2) . ", TOT=" . number_format($total_mxn_num, 2) . "\n";
    }
}

echo "\n======================================\n";
echo "TOTALES CALCULADOS:\n";
echo "Suma COMPLEMENTARIOS: " . number_format($suma_complementarios_mxn, 2) . "\n";
echo "Suma SUBTOTAL: " . number_format($suma_subtotal_mxn, 2) . "\n";
echo "Suma ANTICIPO: " . number_format($suma_anticipo_mxn, 2) . "\n";
echo "Suma TOTAL: " . number_format($suma_total_mxn, 2) . "\n";

$target = 3666487.68;
echo "\n======================================\n";
echo "COMPARACIÓN CON TARGET: 3,666,487.68\n";
echo "¿COMPLEMENTARIOS = 3,666,487.68?: " . (abs($suma_complementarios_mxn - $target) < 0.01 ? "SÍ ✓" : "NO ✗") . " Diff: " . number_format($target - $suma_complementarios_mxn, 2) . "\n";
echo "¿SUBTOTAL = 3,666,487.68?: " . (abs($suma_subtotal_mxn - $target) < 0.01 ? "SÍ ✓" : "NO ✗") . " Diff: " . number_format($target - $suma_subtotal_mxn, 2) . "\n";
echo "¿TOTAL = 3,666,487.68?: " . (abs($suma_total_mxn - $target) < 0.01 ? "SÍ ✓" : "NO ✗") . " Diff: " . number_format($target - $suma_total_mxn, 2) . "\n";
echo "¿SUBTOTAL-ANTICIPO = 3,666,487.68?: " . (abs(($suma_subtotal_mxn - $suma_anticipo_mxn) - $target) < 0.01 ? "SÍ ✓" : "NO ✗") . " Diff: " . number_format($target - ($suma_subtotal_mxn - $suma_anticipo_mxn), 2) . "\n";
?>
