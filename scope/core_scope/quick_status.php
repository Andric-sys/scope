<?php
require 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

echo "\n=== ESTADO ACTUAL DE BD ===\n\n";

try {
  $st = $pdo->prepare("
    SELECT 
      COUNT(DISTINCT id) as total_orders,
      MIN(DATE(created_at)) as min_date,
      MAX(DATE(created_at)) as max_date
    FROM scope_orders
  ");
  $st->execute();
  $data = $st->fetch(PDO::FETCH_ASSOC);
  
  echo "Total Órdenes: " . ($data['total_orders'] ?? 0) . "\n";
  echo "Fecha Min: " . ($data['min_date'] ?? 'N/A') . "\n";
  echo "Fecha Max: " . ($data['max_date'] ?? 'N/A') . "\n\n";
  
  // Breakdown por financial status
  echo "=== BREAKDOWN POR FINANCIAL STATUS ===\n\n";
  
  $st = $pdo->prepare("
    SELECT 
      COALESCE(o.financial_status, 'NULL') as status,
      COUNT(DISTINCT o.id) as orders
    FROM scope_orders o
    GROUP BY COALESCE(o.financial_status, 'NULL')
    ORDER BY orders DESC
  ");
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($rows as $row) {
    echo $row['status'] . ": " . $row['orders'] . " órdenes\n";
  }
  
} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n";
