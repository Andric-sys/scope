<?php
// Leer archivo Excel XML/CSV y extraer anticipos por factura
$archivo_mxn = 'c:\xampp\htdocs\scope\scope\excel\Facturacion Febrero  2026 (3)(ENE MXN).csv';

echo "═══════════════════════════════════════════════════════════\n";
echo "EXTRAYENDO ANTICIPOS DEL ARCHIVO EXCEL - ENE MXN\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$handle = fopen($archivo_mxn, 'r');
$header = fgetcsv($handle, 0, ';');

echo "ENCABEZADOS (primeros 20):\n";
for ($i = 0; $i < 20 && $i < count($header); $i++) {
    echo "[$i] {$header[$i]}\n";
}

// Buscar índice de ANTICIPO y FACTURA
$idx_factura = array_search('FACTURA', $header);
$idx_anticipo = array_search('ANTICIPO', $header);

echo "\nÍndices:\n";
echo "FACTURA: $idx_factura\n";
echo "ANTICIPO: $idx_anticipo\n\n";

// Procesar filas
$anticipos = [];
$linea_num = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $linea_num++;
    
    // Saltar líneas vacías o de totales
    if (empty($row[0]) && empty($row[$idx_factura ?? 0])) continue;
    if (strpos(implode(';', $row), 'TOTAL') !== false) continue;
    
    if (isset($row[$idx_factura]) && isset($row[$idx_anticipo])) {
        $factura = trim($row[$idx_factura] ?? '');
        $anticipo_str = trim($row[$idx_anticipo] ?? '');
        
        if ($factura !== '' && $anticipo_str !== '') {
            // Convertir formato: 1.234,56 -> 1234.56
            $anticipo = (float) str_replace(['.', ','], ['', '.'], $anticipo_str);
            
            if ($anticipo > 0) {
                $anticipos[$factura] = $anticipo;
                if (count($anticipos) <= 20) {
                    echo "Factura: $factura | Anticipo: " . number_format($anticipo, 2, '.', ',') . "\n";
                }
            }
        }
    }
}

fclose($handle);

echo "\n═══════════════════════════════════════════════════════════\n";
echo "RESUMEN EXTRAÍDO DEL EXCEL:\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Total de facturas con ANTICIPO > 0: " . count($anticipos) . "\n";
echo "Suma total de ANTICIPOS: " . number_format(array_sum($anticipos), 2, '.', ',') . " MXN\n";

?>
