<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function json_out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $pdo = db();

  $orderId = (int)($_GET['order_id'] ?? 0);
  if ($orderId <= 0) {
    json_out(400, ['success' => false, 'error' => 'order_id requerido']);
  }

  // Obtener información de la orden
  $stOrder = $pdo->prepare("
    SELECT 
      id,
      order_number,
      customer_name,
      order_date,
      economic_date,
      conveyance_type,
      cost_center_code
    FROM scope_orders
    WHERE id = :order_id
  ");
  $stOrder->execute([':order_id' => $orderId]);
  $order = $stOrder->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    json_out(404, ['success' => false, 'error' => "Factura no encontrada (ID: $orderId)"]);
  }

  // Obtener SOLO entries POSITIVOS (excluyendo anticipos/deducibles negativos)
  $stEntries = $pdo->prepare("
    SELECT 
      id,
      entry_type,
      charge_type_code,
      booking_date,
      economic_date,
      invoice_date,
      local_amount_value,
      local_amount_currency,
      local_tax_value,
      local_tax_currency
    FROM scope_jobcosting_entries
    WHERE order_id = :order_id
      AND CAST(local_amount_value AS DECIMAL(15,2)) >= 0
    ORDER BY id ASC
  ");
  $stEntries->execute([':order_id' => $orderId]);
  $entries = $stEntries->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Calcular totales (solo positivos)
  $subtotal = 0;
  $iva_total = 0;
  foreach ($entries as $e) {
    $subtotal += (float)($e['local_amount_value'] ?? 0);
    $iva_total += (float)($e['local_tax_value'] ?? 0);
  }

  json_out(200, [
    'success' => true,
    'order' => $order,
    'entries' => $entries,
    'summary' => [
      'subtotal' => abs($subtotal),
      'iva' => abs($iva_total),
      'total' => abs($subtotal + $iva_total),
      'entry_count' => count($entries)
    ]
  ]);

} catch (Exception $e) {
  json_out(500, [
    'success' => false,
    'error' => 'Error: ' . $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine()
  ]);
}
?>
