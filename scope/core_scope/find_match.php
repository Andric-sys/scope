<?php
require 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

echo "\n═══════════════════════════════════════════════════════════\n";
echo "BÚSQUEDA DE MONTO EXCEL: 3,666,487.68 MXN\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$target = 3666487.68;
$tolerance = 0.01;

// Prueba 1
echo "OPCIÓN 1: Solo 'closed' + 'income' entries\n";
echo "─" . str_repeat("─", 58) . "\n";

$st = $pdo->prepare("
  SELECT COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND o.financial_status = 'closed'
    AND LOWER(COALESCE(j.entry_type,'')) LIKE '%income%'
    AND j.entry_number IS NOT NULL AND j.entry_number <> ''
");
$st->execute();
$result = $st->fetch();
$amount = (float)($result['total'] ?? 0);
$diff = $amount - $target;
echo "Resultado: " . number_format($amount, 2) . " MXN\n";
echo "Diferencia: " . number_format($diff, 2) . " MXN\n";
echo "Match: " . ($diff >= -$tolerance && $diff <= $tolerance ? "✓ YES" : "✗ NO") . "\n\n";

// Prueba 2
echo "OPCIÓN 2: Solo 'billed' + 'income' entries\n";
echo "─" . str_repeat("─", 58) . "\n";

$st = $pdo->prepare("
  SELECT COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND o.financial_status = 'billed'
    AND LOWER(COALESCE(j.entry_type,'')) LIKE '%income%'
    AND j.entry_number IS NOT NULL AND j.entry_number <> ''
");
$st->execute();
$result = $st->fetch();
$amount = (float)($result['total'] ?? 0);
$diff = $amount - $target;
echo "Resultado: " . number_format($amount, 2) . " MXN\n";
echo "Diferencia: " . number_format($diff, 2) . " MXN\n";
echo "Match: " . ($diff >= -$tolerance && $diff <= $tolerance ? "✓ YES" : "✗ NO") . "\n\n";

// Prueba 3
echo "OPCIÓN 3: 'closed' + 'billed' (combinados) + 'income' entries\n";
echo "─" . str_repeat("─", 58) . "\n";

$st = $pdo->prepare("
  SELECT COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND o.financial_status IN ('closed', 'billed')
    AND LOWER(COALESCE(j.entry_type,'')) LIKE '%income%'
    AND j.entry_number IS NOT NULL AND j.entry_number <> ''
");
$st->execute();
$result = $st->fetch();
$amount = (float)($result['total'] ?? 0);
$diff = $amount - $target;
echo "Resultado: " . number_format($amount, 2) . " MXN\n";
echo "Diferencia: " . number_format($diff, 2) . " MXN\n";
echo "Match: " . ($diff >= -$tolerance && $diff <= $tolerance ? "✓ YES" : "✗ NO") . "\n\n";

// Prueba 4
echo "OPCIÓN 4: Todos los 'financial_status' + 'income' entries\n";
echo "─" . str_repeat("─", 58) . "\n";

$st = $pdo->prepare("
  SELECT COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND LOWER(COALESCE(j.entry_type,'')) LIKE '%income%'
    AND j.entry_number IS NOT NULL AND j.entry_number <> ''
");
$st->execute();
$result = $st->fetch();
$amount = (float)($result['total'] ?? 0);
$diff = $amount - $target;
echo "Resultado: " . number_format($amount, 2) . " MXN\n";
echo "Diferencia: " . number_format($diff, 2) . " MXN\n";
echo "Match: " . ($diff >= -$tolerance && $diff <= $tolerance ? "✓ YES" : "✗ NO") . "\n\n";

// Prueba 5
echo "OPCIÓN 5: Solo 'closed' (todas las entries, sin filtro tipo)\n";
echo "─" . str_repeat("─", 58) . "\n";

$st = $pdo->prepare("
  SELECT COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.amount_currency = 'MXN'
    AND o.financial_status = 'closed'
");
$st->execute();
$result = $st->fetch();
$amount = (float)($result['total'] ?? 0);
$diff = $amount - $target;
echo "Resultado: " . number_format($amount, 2) . " MXN\n";
echo "Diferencia: " . number_format($diff, 2) . " MXN\n";
echo "Match: " . ($diff >= -$tolerance && $diff <= $tolerance ? "✓ YES" : "✗ NO") . "\n\n";

// Prueba 6
echo "OPCIÓN 6: FEBRERO 2026 - Solo 'income' entries\n";
echo "─" . str_repeat("─", 58) . "\n";

$st = $pdo->prepare("
  SELECT COALESCE(SUM(IFNULL(j.amount_value,0)),0) as total
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE DATE(j.invoice_date) BETWEEN '2026-02-01' AND '2026-02-28'
    AND j.amount_currency = 'MXN'
    AND LOWER(COALESCE(j.entry_type,'')) LIKE '%income%'
    AND j.entry_number IS NOT NULL AND j.entry_number <> ''
");
$st->execute();
$result = $st->fetch();
$amount = (float)($result['total'] ?? 0);
$diff = $amount - $target;
echo "Resultado: " . number_format($amount, 2) . " MXN\n";
echo "Diferencia: " . number_format($diff, 2) . " MXN\n";
echo "Match: " . ($diff >= -$tolerance && $diff <= $tolerance ? "✓ YES" : "✗ NO") . "\n\n";

echo "═══════════════════════════════════════════════════════════\n\n";
