<?php
// Leer el archivo CSV nativo de Scope
$file = 'excel/facturas_tabla_2026-03-04-02-17-43 (1) 1(in).csv';
$lines = file($file);

echo "Total de líneas: " . count($lines) . "\n";
echo "======================================\n\n";

// Procesar el archivo
$row_num = 0;
$suma_neto_local = 0;
$suma_iva_local = 0;
$suma_total_local = 0;  // Neto + IVA

foreach ($lines as $line) {
    $row_num++;
    
    // Parsear CSV manualmente con semicolon
    $data = str_getcsv($line, ';');
    
    // Saltar encabezado (línea 1)
    if ($row_num == 1) {
        echo "Columnas encontradas: " . count($data) . "\n";
        for ($i = 0; $i < min(10, count($data)); $i++) {
            echo "  Col $i: " . trim($data[$i]) . "\n";
        }
        echo "  ...\n";
        echo "======================================\n\n";
        continue;
    }
    
    // Las columnas son:
    // 0:ID, 1:Orden, 2:Cliente, 3:Tipo, 4:Concepto, 5:Oficina, 6:Tráfico, 7:Partner, 
    // 8:Factura, 9:Fecha factura, 10:Moneda, 11:Neto, 12:IVA, 13:Neto (local), 14:IVA (local), 15:Fecha económica
    
    if (count($data) < 15) {
        continue;
    }
    
    // Extraer valores de moneda local (columnas 13 y 14)
    $neto_local = isset($data[13]) ? trim($data[13]) : '';
    $iva_local = isset($data[14]) ? trim($data[14]) : '';
    
    // Convertir desde formato europeo
    $neto_local_num = (float)str_replace(',', '.', str_replace('.', '', $neto_local));
    $iva_local_num = (float)str_replace(',', '.', str_replace('.', '', $iva_local));
    
    // Sumar
    $suma_neto_local += $neto_local_num;
    $suma_iva_local += $iva_local_num;
    $suma_total_local += ($neto_local_num + $iva_local_num);
    
    // Mostrar primeras 15 filas
    if ($row_num >= 2 && $row_num <= 16) {
        echo "Fila $row_num: NETO=" . number_format($neto_local_num, 2) . ", IVA=" . number_format($iva_local_num, 2) . ", TOT=" . number_format($neto_local_num + $iva_local_num, 2) . "\n";
    }
}

echo "\n======================================\n";
echo "TOTALES CALCULADOS (Moneda Local = MXN):\n";
echo "Suma NETO (local): " . number_format($suma_neto_local, 2) . "\n";
echo "Suma IVA (local): " . number_format($suma_iva_local, 2) . "\n";
echo "Suma TOTAL (NETO + IVA): " . number_format($suma_total_local, 2) . "\n";

$target = 3666487.68;
echo "\n======================================\n";
echo "COMPARACIÓN CON TARGET: 3,666,487.68 MXN\n";
echo "¿NETO = TARGET?: " . (abs($suma_neto_local - $target) < 0.01 ? "SÍ ✓" : "NO ✗") . " | Diff: " . number_format($target - $suma_neto_local, 2) . "\n";
echo "¿TOTAL (NETO+IVA) = TARGET?: " . (abs($suma_total_local - $target) < 0.01 ? "SÍ ✓" : "NO ✗") . " | Diff: " . number_format($target - $suma_total_local, 2) . "\n";
?>
