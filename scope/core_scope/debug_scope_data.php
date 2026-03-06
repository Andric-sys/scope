<?php
declare(strict_types=1);

/**
 * debug_scope_data.php
 * Muestra exactamente qué datos está trayendo Scope para una orden o factura específica
 */

require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
require __DIR__ . '/scope_api.php';

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

$invoice = (string)($_GET['invoice'] ?? '');
if ($invoice === '') {
  echo "Uso: ?invoice=2-003624\n";
  exit;
}

echo "=== DIAGNÓSTICO: Buscando factura $invoice ===\n\n";

$pdo = db();

// Buscar la orden que contiene esta factura
$sql = "
  SELECT DISTINCT o.id, o.scope_uuid, o.order_number, o.customer_name
  FROM scope_orders o
  LEFT JOIN scope_jobcosting_entries j ON j.order_id = o.id
  WHERE j.external_number = ? OR j.entry_number = ?
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$invoice, $invoice]);
$order = $st->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  echo "❌ No se encontró factura $invoice en la BD local.\n";
  exit;
}

echo "✅ Orden encontrada:\n";
echo "   ID: {$order['id']}\n";
echo "   Scope UUID: {$order['scope_uuid']}\n";
echo "   Order Number: {$order['order_number']}\n";
echo "   Customer: {$order['customer_name']}\n\n";

// Mostrar los jobcosting_entries en la BD local para esta factura
$sql = "
  SELECT
    id, entry_type, charge_type_code, invoice_date,
    amount_value, amount_currency, tax_value, tax_currency,
    local_amount_value, local_amount_currency,
    local_tax_value, local_tax_currency,
    org_amount_value, org_amount_currency,
    entry_number, external_number, tax_key
  FROM scope_jobcosting_entries
  WHERE (external_number = ? OR entry_number = ?)
  ORDER BY id
";
$st = $pdo->prepare($sql);
$st->execute([$invoice, $invoice]);
$entries = $st->fetchAll(PDO::FETCH_ASSOC);

echo "📊 Entries en BD local para $invoice:\n";
echo str_repeat('-', 150) . "\n";

$totalMonto = 0;
$totalIva = 0;

foreach ($entries as $e) {
  $monto = (float)($e['local_amount_value'] ?? 0);
  $iva = (float)($e['local_tax_value'] ?? 0);
  $totalMonto += $monto;
  $totalIva += $iva;

  printf(
    "ID:%d | Tipo:%s | Cargo:%s | Monto:%10.2f %s | IVA:%10.2f %s | Monto Local:%10.2f %s | IVA Local:%10.2f %s\n",
    $e['id'],
    $e['entry_type'],
    $e['charge_type_code'],
    (float)($e['amount_value'] ?? 0),
    $e['amount_currency'] ?? 'N/A',
    (float)($e['tax_value'] ?? 0),
    $e['tax_currency'] ?? 'N/A',
    $monto,
    $e['local_amount_currency'] ?? 'N/A',
    $iva,
    $e['local_tax_currency'] ?? 'N/A'
  );
}

echo str_repeat('-', 150) . "\n";
echo "TOTAL MONTO: $totalMonto | TOTAL IVA: $totalIva\n\n";

// Ahora traer los datos de Scope API para comparar
echo "🔄 Obteniendo datos desde Scope API...\n\n";

try {
  $cfg = require __DIR__ . '/config.php';
  $scopeCfg = $cfg['scope'] ?? [];
  
  $org = $scopeCfg['organizationCode'] ?? '';
  $le = $scopeCfg['legalEntityCode'] ?? '';
  $br = $scopeCfg['branchCode'] ?? '';
  
  $scopeOrder = scope_get_order($order['scope_uuid']);

  if (!is_array($scopeOrder) || !isset($scopeOrder['identifier'])) {
    echo "❌ No se encontró orden en Scope API con UUID: {$order['scope_uuid']}\n";
    exit;
  }

  echo "✅ Orden en Scope:\n";
  echo "   Identifier: {$scopeOrder['identifier']}\n";
  echo "   Number: {$scopeOrder['number']}\n\n";

  // Mostrar jobcosting entries de Scope
  $jobcostingList = $scopeOrder['jobcostingEntries']['jobcostingEntry'] ?? [];
  if (!is_array($jobcostingList)) $jobcostingList = [];
  if (isset($jobcostingList['type'])) $jobcostingList = [$jobcostingList]; // objeto singular

  echo "📊 Jobcosting Entries en Scope:\n";
  echo str_repeat('-', 180) . "\n";

  $scopeTotalMonto = 0;
  $scopeTotalIva = 0;

  foreach ($jobcostingList as $entry) {
    $type = $entry['type'] ?? 'N/A';
    $chargeTypeCode = $entry['chargeType']['code'] ?? 'N/A';
    $entryNumber = $entry['number'] ?? 'N/A';
    $externalNumber = $entry['externalNumber'] ?? 'N/A';
    
    $amount = $entry['amount']['value'] ?? 0;
    $amountCur = $entry['amount']['currency'] ?? 'N/A';
    $taxAmount = $entry['taxAmount']['value'] ?? 0;
    $taxCur = $entry['taxAmount']['currency'] ?? 'N/A';
    
    $localAmount = $entry['localAmount']['value'] ?? 0;
    $localAmountCur = $entry['localAmount']['currency'] ?? 'N/A';
    $localTaxAmount = $entry['localTaxAmount']['value'] ?? 0;
    $localTaxCur = $entry['localTaxAmount']['currency'] ?? 'N/A';

    $scopeTotalMonto += (float)$localAmount;
    $scopeTotalIva += (float)$localTaxAmount;

    printf(
      "Entry:%s | Ext:%s | Tipo:%s | Cargo:%s | Monto:%10.2f %s | IVA:%10.2f %s | Monto Local:%10.2f %s | IVA Local:%10.2f %s\n",
      $entryNumber,
      $externalNumber,
      $type,
      $chargeTypeCode,
      (float)$amount,
      $amountCur,
      (float)$taxAmount,
      $taxCur,
      (float)$localAmount,
      $localAmountCur,
      (float)$localTaxAmount,
      $localTaxCur
    );
  }

  echo str_repeat('-', 180) . "\n";
  echo "TOTAL SCOPE - MONTO: $scopeTotalMonto | IVA: $scopeTotalIva\n\n";

  echo "📋 COMPARACIÓN:\n";
  echo "   BD Local  - Monto: $totalMonto | IVA: $totalIva\n";
  echo "   Scope API - Monto: $scopeTotalMonto | IVA: $scopeTotalIva\n";
  echo "   Diferencia - Monto: " . ($totalMonto - $scopeTotalMonto) . " | IVA: " . ($totalIva - $scopeTotalIva) . "\n\n";

  echo "📝 JSON raw de Scope (primeras 2000 caracteres):\n";
  echo json_encode($scopeOrder, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (Throwable $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo $e->getTraceAsString();
}
?>
