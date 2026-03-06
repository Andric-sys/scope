<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
  // Get January 2025 data (or current month if different)
  $from = $_GET['from'] ?? '2025-01-01';
  $to = $_GET['to'] ?? '2025-01-31';
  
  // Validate dates
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $from = '2025-01-01';
    $to = '2025-01-31';
  }

  $sql = "
    SELECT 
      COALESCE(o.financial_status, 'NULL') AS status,
      COUNT(DISTINCT o.id) AS total_orders,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS income_amount,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.tax_value,0) ELSE 0 END),0) AS income_iva,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS payable_amount,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.tax_value,0) ELSE 0 END),0) AS payable_iva,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) AS total_amount,
      COALESCE(SUM(IFNULL(j.tax_value,0)),0) AS total_iva
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

  // Also get GRAND TOTAL
  $sqlTotal = "
    SELECT 
      'GRAND_TOTAL' AS status,
      COUNT(DISTINCT o.id) AS total_orders,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS income_amount,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%income%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.tax_value,0) ELSE 0 END),0) AS income_iva,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.amount_value,0) ELSE 0 END),0) AS payable_amount,
      COALESCE(SUM(CASE WHEN LOWER(COALESCE(j.entry_type,'')) LIKE '%payable%' AND j.entry_number IS NOT NULL AND j.entry_number <> ''
        THEN IFNULL(j.tax_value,0) ELSE 0 END),0) AS payable_iva,
      COALESCE(SUM(IFNULL(j.amount_value,0)),0) AS total_amount,
      COALESCE(SUM(IFNULL(j.tax_value,0)),0) AS total_iva
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :from AND :to
      AND j.amount_currency = 'MXN'
  ";

  $stmt = $pdo->prepare($sqlTotal);
  $stmt->execute([':from' => $from, ':to' => $to]);
  $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);

  $response = [
    'success' => true,
    'period' => ['from' => $from, 'to' => $to],
    'by_status' => $rows,
    'total' => $totalRow,
  ];

  http_response_code(200);
  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
