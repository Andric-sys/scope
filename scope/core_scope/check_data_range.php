<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
  // Direct DB connection without auth
  require __DIR__ . '/conexion.php';
  date_default_timezone_set('America/Mexico_City');
  
  $pdo = db();

  echo "\n=== CHECKING AVAILABLE DATA ===\n";

  // Check date range
  $sql = "
    SELECT 
      MIN(DATE(j.invoice_date)) as min_date,
      MAX(DATE(j.invoice_date)) as max_date,
      COUNT(*) as total_entries,
      COUNT(DISTINCT o.id) as unique_orders
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND j.amount_currency = 'MXN'
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $info = $stmt->fetch(PDO::FETCH_ASSOC);

  echo "Min Date: " . ($info['min_date'] ?? 'NULL') . "\n";
  echo "Max Date: " . ($info['max_date'] ?? 'NULL') . "\n";
  echo "Total Entries: " . ($info['total_entries'] ?? 0) . "\n";
  echo "Unique Orders: " . ($info['unique_orders'] ?? 0) . "\n\n";

  // If we have data, let's use the actual date range
  if ($info['max_date']) {
    $date_parts = explode('-', $info['max_date']);
    $year = $date_parts[0];
    $month = $date_parts[1];
    
    $from = "$year-$month-01";
    $to = $info['max_date'];
    
    echo "Using date range: $from to $to\n\n";

    echo "=== FINANCIAL STATUS BREAKDOWN ===\n";
    echo str_repeat("-", 110) . "\n";

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

    printf("%-20s | %15s | %20s | %20s\n", "Financial Status", "# Orders", "Income Amount", "Total Amount");
    echo str_repeat("-", 110) . "\n";

    foreach ($rows as $row) {
      printf("%-20s | %15d | %20s | %20s\n", 
        trim($row['status'] ?? 'NULL'),
        (int)$row['total_orders'],
        number_format((float)$row['income_amount'], 2),
        number_format((float)$row['total_amount'], 2)
      );
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

    echo "\n✓ Expected Excel figure: 3,666,487.68 MXN (for January or target month)\n";
  }

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo $e->getTraceAsString() . "\n";
}
?>
