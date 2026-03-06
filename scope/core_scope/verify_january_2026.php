<?php
require 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

echo "\n═══════════════════════════════════════════════════════════\n";
echo "ANÁLISIS DE MONTOS - ENERO 2026\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$from = '2026-01-01';
$to = '2026-01-31';

try {
  // 1. Verificar rango disponible
  echo "PASO 1: Datos disponibles en BD\n";
  echo "─" . str_repeat("─", 58) . "\n\n";
  
  $st = $pdo->prepare("
    SELECT 
      MIN(DATE(j.invoice_date)) as min_date,
      MAX(DATE(j.invoice_date)) as max_date,
      COUNT(*) as total_entries
    FROM scope_jobcosting_entries j
    WHERE j.invoice_date IS NOT NULL
  ");
  $st->execute();
  $range = $st->fetch(PDO::FETCH_ASSOC);
  
  echo "Rango total en BD: " . ($range['min_date'] ?? 'N/A') . " a " . ($range['max_date'] ?? 'N/A') . "\n";
  echo "Total entries: " . ($range['total_entries'] ?? 0) . "\n\n";

  // 2. Datos de enero 2026 por financial_status
  echo "PASO 2: Breakdown ENERO 2026 por Financial Status\n";
  echo "─" . str_repeat("─", 58) . "\n\n";
  
  $sql = "
    SELECT 
      COALESCE(o.financial_status, 'NULL') AS status,
      COUNT(DISTINCT o.id) AS total_orders,
      COUNT(*) AS total_entries,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' 
        AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS income_amount,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' 
        AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.tax_value,0) ELSE 0 END),0) AS income_iva,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) AS total_amount,
      COALESCE(SUM(IFNULL(j.tax_value,0)),0) AS total_tax
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE DATE(j.invoice_date) BETWEEN ? AND ?
      AND j.amount_currency = 'MXN'
    GROUP BY COALESCE(o.financial_status, 'NULL')
    ORDER BY status
  ";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$from, $to]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  printf("%-15s | %10s | %15s | %15s | %15s\n", "Status", "Orders", "Income", "IVA", "Total");
  echo str_repeat("─", 85) . "\n";
  
  $grand_income = 0;
  $grand_iva = 0;
  $grand_total = 0;
  
  foreach ($rows as $row) {
    $income = (float)$row['income_amount'];
    $iva = (float)$row['income_iva'];
    $total = (float)$row['total_amount'];
    
    printf("%-15s | %10d | %15s | %15s | %15s\n",
      $row['status'],
      (int)$row['total_orders'],
      number_format($income, 2),
      number_format($iva, 2),
      number_format($total, 2)
    );
    
    $grand_income += $income;
    $grand_iva += $iva;
    $grand_total += $total;
  }
  
  echo str_repeat("─", 85) . "\n";
  printf("%-15s | %10s | %15s | %15s | %15s\n",
    "TOTAL",
    "",
    number_format($grand_income, 2),
    number_format($grand_iva, 2),
    number_format($grand_total, 2)
  );
  
  echo "\n";
  
  // 3. Comparación con Excel
  echo "PASO 3: Comparación con Excel\n";
  echo "─" . str_repeat("─", 58) . "\n\n";
  
  echo "Excel esperado (Enero 2026): 3,666,487.68 MXN\n";
  echo "BD Total Amount:             " . number_format($grand_total, 2) . " MXN\n";
  echo "BD Income Amount:            " . number_format($grand_income, 2) . " MXN\n\n";
  
  // 4. Verificar por qué podrían no coincidir
  echo "PASO 4: Análisis de discrepancia\n";
  echo "─" . str_repeat("─", 58) . "\n\n";
  
  // Solo income entries
  $st = $pdo->prepare("
    SELECT 
      COUNT(*) as entries,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE DATE(j.invoice_date) BETWEEN ? AND ?
      AND j.amount_currency = 'MXN'
      AND LOWER(COALESCE(j.entry_type,'')) LIKE '%income%'
      AND j.entry_number IS NOT NULL AND j.entry_number <> ''
  ");
  $st->execute([$from, $to]);
  $income_only = $st->fetch(PDO::FETCH_ASSOC);
  
  echo "Si SOLO contamos entries tipo 'income':\n";
  echo "  Entries: " . ($income_only['entries'] ?? 0) . "\n";
  echo "  Total: " . number_format((float)($income_only['total'] ?? 0), 2) . " MXN\n\n";
  
  // Sin filtro de entry_type
  $st = $pdo->prepare("
    SELECT 
      COUNT(*) as entries,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE DATE(j.invoice_date) BETWEEN ? AND ?
      AND j.amount_currency = 'MXN'
  ");
  $st->execute([$from, $to]);
  $all = $st->fetch(PDO::FETCH_ASSOC);
  
  echo "Si contamos TODOS los entries (sin filtro de tipo):\n";
  echo "  Entries: " . ($all['entries'] ?? 0) . "\n";
  echo "  Total: " . number_format((float)($all['total'] ?? 0), 2) . " MXN\n\n";
  
  // Mostrar primeros registros de enero para debug
  echo "PASO 5: Muestra de órdenes en Enero 2026\n";
  echo "─" . str_repeat("─", 58) . "\n\n";
  
  $st = $pdo->prepare("
    SELECT DISTINCT
      o.order_number,
      o.financial_status,
      COUNT(*) as entries,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE DATE(j.invoice_date) BETWEEN ? AND ?
      AND j.amount_currency = 'MXN'
    GROUP BY o.id
    ORDER BY j.invoice_date DESC
    LIMIT 10
  ");
  $st->execute([$from, $to]);
  $samples = $st->fetchAll(PDO::FETCH_ASSOC);
  
  printf("%-15s | %-10s | %10s | %15s\n", "Orden", "Status", "Entries", "Total");
  echo str_repeat("─", 60) . "\n";
  
  foreach ($samples as $sample) {
    printf("%-15s | %-10s | %10d | %15s\n",
      $sample['order_number'] ?? 'N/A',
      $sample['financial_status'] ?? 'NULL',
      (int)$sample['entries'],
      number_format((float)$sample['total'], 2)
    );
  }
  
  echo "\n═══════════════════════════════════════════════════════════\n\n";
  
} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
