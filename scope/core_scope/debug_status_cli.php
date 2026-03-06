<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
  require __DIR__ . '/auth_guard.php';
  require __DIR__ . '/conexion.php';
  date_default_timezone_set('America/Mexico_City');

  $from = '2025-01-01';
  $to = '2025-01-31';

  echo "Testing database connection...\n";
  $test = $pdo->query("SELECT COUNT(*) as cnt FROM scope_jobcosting_entries WHERE invoice_date IS NOT NULL");
  $res = $test->fetch(PDO::FETCH_ASSOC);
  echo "Total entries with invoice_date: " . $res['cnt'] . "\n\n";

  $sql = "
    SELECT 
      COALESCE(o.financial_status, 'NULL') AS status,
      COUNT(DISTINCT o.id) AS total_orders,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS income_amount,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) AS total_amount
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :from AND :to
      AND j.amount_currency = 'MXN'
    GROUP BY COALESCE(o.financial_status, 'NULL')
    ORDER BY status
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':from' => $from, ':to' => $to]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "=== FINANCIAL STATUS BREAKDOWN FOR JANUARY 2025 (MXN) ===\n";
  echo str_repeat("-", 110) . "\n";
  printf("%-20s | %15s | %20s | %20s\n", "Financial Status", "# Orders", "Income Amount", "Total Amount");
  echo str_repeat("-", 110) . "\n";

  if (empty($rows)) {
    echo "No data found for the period.\n";
  } else {
    foreach ($rows as $row) {
      printf("%-20s | %15d | %20s | %20s\n", 
        trim($row['status'] ?? 'NULL'),
        (int)$row['total_orders'],
        number_format((float)$row['income_amount'], 2),
        number_format((float)$row['total_amount'], 2)
      );
    }
  }

  echo str_repeat("-", 110) . "\n";

  // Grand total
  $sqlTotal = "
    SELECT 
      COUNT(DISTINCT o.id) AS total_orders,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS income_amount,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) AS total_amount
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :from AND :to
      AND j.amount_currency = 'MXN'
  ";

  $stmt = $pdo->prepare($sqlTotal);
  $stmt->execute([':from' => $from, ':to' => $to]);
  $total = $stmt->fetch(PDO::FETCH_ASSOC);

  printf("%-20s | %15d | %20s | %20s\n", 
    "GRAND TOTAL",
    (int)$total['total_orders'],
    number_format((float)$total['income_amount'], 2),
    number_format((float)$total['total_amount'], 2)
  );

  echo "\n✓ Expected Excel figure: 3,666,487.68 MXN (income only)\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo $e->getTraceAsString() . "\n";
}
