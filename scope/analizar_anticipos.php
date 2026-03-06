<?php
declare(strict_types=1);

require __DIR__ . '/core_scope/auth_guard.php';
require __DIR__ . '/core_scope/conexion.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = db();

// Obtener filtros
$filter_order = $_GET['order'] ?? 'CGL25123863';
$filter_customer = $_GET['customer'] ?? '';
$filter_date_start = $_GET['date_start'] ?? '';
$filter_date_end = $_GET['date_end'] ?? '';
$filter_entry_type = $_GET['entry_type'] ?? '';
$filter_charge_type = $_GET['charge_type'] ?? '';

// Obtener tipos únicos de entry para dropdown
$sql_types = "SELECT DISTINCT entry_type FROM scope_jobcosting_entries ORDER BY entry_type";
$available_types = $pdo->query($sql_types)->fetchAll(PDO::FETCH_COLUMN);

// Obtener conceptos únicos de la orden actual
$sql_concepts = "
  SELECT DISTINCT j.charge_type_code 
  FROM scope_jobcosting_entries j
  INNER JOIN scope_orders o ON o.id = j.order_id
  WHERE o.order_number = :order
  ORDER BY j.charge_type_code
";
$st = $pdo->prepare($sql_concepts);
$st->execute([':order' => $filter_order]);
$available_concepts = $st->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Análisis Anticipos - BD</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
    h1, h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
    .filters-form { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #007bff; }
    .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
    .filter-group { display: flex; flex-direction: column; }
    .filter-group label { font-weight: bold; margin-bottom: 5px; font-size: 14px; }
    .filter-group input, .filter-group select { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
    .button-group { display: flex; gap: 10px; }
    button { padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .btn-filter { background: #007bff; color: white; }
    .btn-filter:hover { background: #0056b3; }
    .btn-reset { background: #6c757d; color: white; }
    .btn-reset:hover { background: #545b62; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #007bff; color: white; font-weight: bold; }
    th:first-child, td:first-child { text-align: center; padding: 12px 8px; width: 50px; }
    tr:hover { background: #f5f5f5; }
    .amount { text-align: right; font-family: monospace; }
    .error-box { background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828; margin: 20px 0; }
    .check-row { cursor: pointer; width: 18px; height: 18px; vertical-align: middle; }
  </style>
</head>
<body>
<div class="container">
<h1>📊 Análisis Anticipos - Base de Datos</h1>

<div class="filters-form">
  <form method="GET">
    <div class="filter-row">
      <div class="filter-group">
        <label>Número de Orden (CGL...):</label>
        <input type="text" name="order" value="<?php echo htmlspecialchars($filter_order); ?>">
      </div>
      <div class="filter-group">
        <label>Cliente (contiene):</label>
        <input type="text" name="customer" value="<?php echo htmlspecialchars($filter_customer); ?>">
      </div>
      <div class="filter-group">
        <label>Tipo de Entry:</label>
        <select name="entry_type">
          <option value="">-- Todos --</option>
          <?php foreach ($available_types as $type): ?>
            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filter_entry_type === $type ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($type); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label>Concepto (charge_type_code):</label>
        <select name="charge_type">
          <option value="">-- Todos --</option>
          <?php foreach ($available_concepts as $concept): ?>
            <option value="<?php echo htmlspecialchars($concept); ?>" <?php echo $filter_charge_type === $concept ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($concept); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="filter-row">
      <div class="filter-group">
        <label>Fecha Inicio:</label>
        <input type="date" name="date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>">
      </div>
      <div class="filter-group">
        <label>Fecha Fin:</label>
        <input type="date" name="date_end" value="<?php echo htmlspecialchars($filter_date_end); ?>">
      </div>
    </div>
    <div class="button-group">
      <button type="submit" class="btn-filter">🔍 Filtrar</button>
      <button type="reset" class="btn-reset" onclick="window.location.href='?order=CGL25123863'">🔄 Reset</button>
    </div>
  </form>
</div>

<?php

// Construir consulta dinámica
$where_clauses = [];
$params = [];

if (!empty($filter_order)) {
  $where_clauses[] = "o.order_number = :order";
  $params[':order'] = $filter_order;
}

if (!empty($filter_customer)) {
  $where_clauses[] = "o.customer_name LIKE :customer";
  $params[':customer'] = "%{$filter_customer}%";
}

if (!empty($filter_entry_type)) {
  $where_clauses[] = "j.entry_type = :entry_type";
  $params[':entry_type'] = $filter_entry_type;
}

if (!empty($filter_charge_type)) {
  $where_clauses[] = "j.charge_type_code = :charge_type";
  $params[':charge_type'] = $filter_charge_type;
}

if (!empty($filter_date_start)) {
  $where_clauses[] = "DATE(o.economic_date) >= :date_start";
  $params[':date_start'] = $filter_date_start;
}

if (!empty($filter_date_end)) {
  $where_clauses[] = "DATE(o.economic_date) <= :date_end";
  $params[':date_end'] = $filter_date_end;
}

$where = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

try {
  // Obtener órdenes
  $sql = "
    SELECT 
      o.id,
      o.order_number,
      o.customer_name,
      o.order_date,
      o.economic_date,
      COUNT(j.id) as entry_count,
      ROUND(SUM(j.local_amount_value), 2) as total_neto,
      ROUND(SUM(j.local_tax_value), 2) as total_iva
    FROM scope_orders o
    LEFT JOIN scope_jobcosting_entries j ON o.id = j.order_id
    $where
    GROUP BY o.id
    ORDER BY o.economic_date DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $orders = $st->fetchAll(PDO::FETCH_ASSOC);

  if (empty($orders)) {
    echo '<div class="error-box">❌ No se encontraron órdenes con los filtros aplicados</div>';
  } else {
    echo '<h2>Órdenes Encontradas: ' . count($orders) . '</h2>';
    
    foreach ($orders as $order) {
      echo '<h3>Orden: ' . htmlspecialchars($order['order_number']) . ' - ' . htmlspecialchars($order['customer_name']) . '</h3>';
      echo '<table>';
      echo '<tr><td><strong>Fecha Económica:</strong> ' . htmlspecialchars($order['economic_date']) . '</td>';
      echo '<td><strong># Entries:</strong> ' . $order['entry_count'] . '</td>';
      echo '<td><strong>Total Neto:</strong> $' . number_format((float)$order['total_neto'], 2) . '</td>';
      echo '<td><strong>Total IVA:</strong> $' . number_format((float)$order['total_iva'], 2) . '</td></tr>';
      echo '</table>';

      // Obtener entries de la orden
      $sql2 = "
        SELECT 
          j.id,
          j.entry_type,
          j.charge_type_code,
          j.booking_date,
          j.economic_date,
          j.invoice_date,
          ROUND(j.local_amount_value, 2) as monto,
          ROUND(j.local_tax_value, 2) as iva
        FROM scope_jobcosting_entries j
        WHERE j.order_id = :order_id
      ";
      
      $sql2_params = [':order_id' => $order['id']];
      
      if (!empty($filter_entry_type)) {
        $sql2 .= " AND j.entry_type = :entry_type";
        $sql2_params[':entry_type'] = $filter_entry_type;
      }
      
      if (!empty($filter_charge_type)) {
        $sql2 .= " AND j.charge_type_code = :charge_type";
        $sql2_params[':charge_type'] = $filter_charge_type;
      }
      
      $sql2 .= " ORDER BY j.charge_type_code, j.entry_type, j.id";
      
      $st2 = $pdo->prepare($sql2);
      $st2->execute($sql2_params);
      $entries = $st2->fetchAll(PDO::FETCH_ASSOC);

      if (!empty($entries)) {
        $table_id = 'order_' . $order['id'];
        echo '<table id="' . $table_id . '">';
        echo '<tr><th style="width: 40px;">✓</th><th>ID</th><th>Concepto</th><th>Tipo</th><th class="amount">Monto</th><th class="amount">IVA</th><th class="amount">Total</th><th>Fecha Factura</th></tr>';
        
        $order_neto = 0.0;
        $order_iva = 0.0;
        
        foreach ($entries as $e) {
          $monto = (float)($e['monto'] ?? 0);
          $iva = (float)($e['iva'] ?? 0);
          $order_neto += $monto;
          $order_iva += $iva;
          $row_total = $monto + $iva;
          
          echo '<tr data-row-id="' . $e['id'] . '" data-monto="' . $monto . '" data-iva="' . $iva . '">';
          echo '<td><input type="checkbox" class="check-row" data-table="' . $table_id . '" data-monto="' . $monto . '" data-iva="' . $iva . '" checked></td>';
          echo '<td>' . $e['id'] . '</td>';
          echo '<td><strong>' . htmlspecialchars($e['charge_type_code'] ?? '-') . '</strong></td>';
          echo '<td>' . htmlspecialchars($e['entry_type'] ?? '-') . '</td>';
          echo '<td class="amount">' . number_format((float)$monto, 2) . '</td>';
          echo '<td class="amount">' . number_format((float)$iva, 2) . '</td>';
          echo '<td class="amount">' . number_format((float)($monto + $iva), 2) . '</td>';
          echo '<td>' . htmlspecialchars($e['invoice_date'] ?? '-') . '</td>';
          echo '</tr>';
        }
        
        echo '<tr style="background: #f0f0f0; font-weight: bold;" class="total-row" data-table="' . $table_id . '">';
        echo '<td></td>';
        echo '<td>TOTAL ORDEN</td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td class="amount total-monto">' . number_format((float)$order_neto, 2) . '</td>';
        echo '<td class="amount total-iva">' . number_format((float)$order_iva, 2) . '</td>';
        echo '<td class="amount total-sum">' . number_format((float)($order_neto + $order_iva), 2) . '</td>';
        echo '<td></td></tr>';
        echo '</table>';
      }
      
      echo '<hr>';
    }
  }

} catch (Exception $e) {
  echo '<div class="error-box"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

</div>
</body>
<script>
document.querySelectorAll('.check-row').forEach(checkbox => {
  checkbox.addEventListener('change', function() {
    const tableId = this.getAttribute('data-table');
    const table = document.getElementById(tableId);
    const totalRow = document.querySelector('.total-row[data-table="' + tableId + '"]');
    
    if (!table || !totalRow) return;
    
    let sumMonto = 0;
    let sumIva = 0;
    
    // Iterar sobre todas las filas con checkboxes
    table.querySelectorAll('tr[data-row-id]').forEach(row => {
      const checkbox = row.querySelector('.check-row');
      if (checkbox && checkbox.checked) {
        sumMonto += parseFloat(checkbox.getAttribute('data-monto')) || 0;
        sumIva += parseFloat(checkbox.getAttribute('data-iva')) || 0;
      }
    });
    
    // Actualizar totales
    const totalMontoCell = totalRow.querySelector('.total-monto');
    const totalIvaCell = totalRow.querySelector('.total-iva');
    const totalSumCell = totalRow.querySelector('.total-sum');
    
    if (totalMontoCell) totalMontoCell.textContent = sumMonto.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (totalIvaCell) totalIvaCell.textContent = sumIva.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (totalSumCell) totalSumCell.textContent = (sumMonto + sumIva).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  });
});
</script>
</html>
