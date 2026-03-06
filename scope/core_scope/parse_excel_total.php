<?php
// Analizar CSV de Excel y sumar columna TOTAL
$archivo = 'c:\xampp\htdocs\scope\scope\excel\Facturacion Febrero  2026 (3)(ENE MXN).csv';
$handle = fopen($archivo, 'r');

// Leer encabezados
$header = fgetcsv($handle, 0, ';');
echo "═══════════════════════════════════════════════════════════\n";
echo "Analizando CSV - Enero 2026\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Encontrar índice de columna TOTAL
$idx_total = null;
foreach ($header as $i => $col) {
    if (trim($col) === 'TOTAL') {
        $idx_total = $i;
        echo "Columna TOTAL encontrada en índice: $idx_total\n";
        break;
    }
}

if ($idx_total === null) {
    echo "ERROR: No encontré columna TOTAL\n";
    echo "Encabezados encontrados:\n";
    foreach ($header as $i => $col) {
        echo "[$i] $col\n";
    }
    exit;
}

// Procesar filas
$suma_total = 0;
$fila_num = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $fila_num++;
    
    // Saltar líneas vacías
    if (empty($row[0]) && empty($row[1])) continue;
    
    // Saltar líneas que contengan "TOTAL" u otros cálculos
    $contenido = implode(';', $row);
    if (strpos($contenido, 'TOTAL') !== false || strpos($contenido, '(') !== false) {
        continue;
    }
    
    // Obtener valor TOTAL
    if (isset($row[$idx_total]) && !empty(trim($row[$idx_total]))) {
        $valor_str = trim($row[$idx_total]);
        // Convertir formato: 48.720,00 -> 48720.00
        $valor = (float) str_replace(['.', ','], ['', '.'], $valor_str);
        
        if ($valor != 0) {
            echo "Fila $fila_num: $valor_str => $valor MXN\n";
            $suma_total += $valor;
        }
    }
}

fclose($handle);

echo "\n═══════════════════════════════════════════════════════════\n";
echo "RESULTADO:\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Suma de columna TOTAL: " . number_format($suma_total, 2, '.', ',') . " MXN\n\n";

$meta = 3666487.68;
echo "EXCEL TOTAL FACTURADOS: 3.666.487,68 MXN\n";
echo "Diferencia: " . number_format($suma_total - $meta, 2, '.', ',') . "\n";

if (abs($suma_total - $meta) < 0.01) {
    echo "\n✓ ¡MATCH EXACTO ENCONTRADO! ✓\n";
} else {
    echo "\nℹ No es un match exacto, pero cercano.\n";
}
?>
