<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
  require __DIR__ . '/conexion.php';
  date_default_timezone_set('America/Mexico_City');
  
  $pdo = db();

  echo "\n=== ALL DATA BREAKDOWN BY FINANCIAL STATUS (October 2025 - March 2026) ===\n";
  echo "Looking at ALL data regardless of entry_type filter\n\n";
  echo str_repeat("=", 130) . "\n";

  $sql = "
    SELECT 
      COALESCE(o.financial_status, 'NULL') AS status,
      COUNT(DISTINCT o.id) AS total_orders,
      COUNT(DISTINCT j.id) AS total_entries,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) AS sum_amount_value,
      COALESCE(SUM(IFNULL(j.tax_value,0)),0) AS sum_tax_value,
      COUNT(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' THEN 1 END) AS income_count,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS income_amount
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND j.amount_currency = 'MXN'
    GROUP BY COALESCE(o.financial_status, 'NULL')
    ORDER BY status
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  printf("%-15s | %12s | %14s | %18s | %18s | %14s | %18s\n", 
    "Financial St.", "# Orders", "# Entries", "Sum Amount", "Sum Tax", "Income Count", "Income Amount");
  echo str_repeat("-", 130) . "\n";

  $grand_orders = 0;
  $grand_amount = 0;
  $grand_tax = 0;
  $grand_income = 0;

  foreach ($rows as $row) {
    printf("%-15s | %12d | %14d | %18s | %18s | %14d | %18s\n", 
      trim($row['status'] ?? 'NULL'),
      (int)$row['total_orders'],
      (int)$row['total_entries'],
      number_format((float)$row['sum_amount_value'], 2),
      number_format((float)$row['sum_tax_value'], 2),
      (int)$row['income_count'],
      number_format((float)$row['income_amount'], 2)
    );
    
    $grand_orders += (int)$row['total_orders'];
    $grand_amount += (float)$row['sum_amount_value'];
    $grand_tax += (float)$row['sum_tax_value'];
    $grand_income += (float)$row['income_amount'];
  }

  echo str_repeat("-", 130) . "\n";
  printf("%-15s | %12d | %14d | %18s | %18s | %14s | %18s\n", 
    "GRAND TOTAL",
    $grand_orders,
    "N/A",
    number_format($grand_amount, 2),
    number_format($grand_tax, 2),
    "N/A",
    number_format($grand_income, 2)
  );

  echo "\n✓ Expected Excel figure: 3,666,487.68 MXN\n";
  echo "Note: If the grand total of 'Sum Amount Value' doesn't match Excel, it could be:\n";
  echo "  - Different date range (user is looking at Jan 2025 or different month)\n";
  echo "  - Different financial_status filtering\n";
  echo "  - Different entry_type filtering\n\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo $e->getTraceAsString() . "\n";
}
?>
