<?php
declare(strict_types=1);

/**
 * vistas_crudas.php
 * - Funciona para cualquier mes (input type="month")
 * - 3 vistas:
 *    1) resumen: 1 fila por external_number + cliente + totales (general)
 *    2) detalle: líneas (entries) tal como vienen
 *    3) excel:   formato tipo “FEB MXN” (FACTURA = entry_number, solo INCOME, moneda filtrable, totales por factura)
 * - Modal Detalle por factura
 * - Exportación del MES COMPLETO (servidor, no depende paginación)
 */

require __DIR__ . '/core_scope/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* =========================
   Parámetros
========================= */
$mes = (string)($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

$inicio = $mes . '-01';
$fin = date('Y-m-t', strtotime($inicio));

$vista = (string)($_GET['vista'] ?? 'excel'); // default a excel porque es lo que pide el cliente
if (!in_array($vista, ['resumen','detalle','excel'], true)) $vista = 'excel';

// Moneda para vista excel (por defecto MXN)
$moneda = trim((string)($_GET['moneda'] ?? 'MXN'));
$moneda = $moneda !== '' ? strtoupper($moneda) : 'MXN';

/* =========================
   Helpers
========================= */
function is_income(?string $entryType): bool {
  $s = strtolower(trim((string)$entryType));
  return $s !== '' && strpos($s, 'income') !== false;
}

/* =========================
   AJAX: Detalle (modal)
   - resumen: key = external_number + fecha + moneda
   - excel:   key = entry_number  + fecha + moneda
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalle') {
  header('Content-Type: application/json; charset=utf-8');

  $keyType = trim((string)($_GET['keyType'] ?? 'external')); // external|entry
  $keyType = in_array($keyType, ['external','entry'], true) ? $keyType : 'external';

  $factura = trim((string)($_GET['factura'] ?? ''));
  $fecha   = trim((string)($_GET['fecha'] ?? ''));   // YYYY-MM-DD
  $mon     = trim((string)($_GET['moneda'] ?? ''));

  if ($factura === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $mon === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Parámetros inválidos.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // arma WHERE por tipo de llave
  $whereKey = $keyType === 'entry'
    ? "j.entry_number = :factura"
    : "j.external_number = :factura";

  $sql = "
    SELECT
      j.id,
      o.order_number,
      o.customer_name,
      j.entry_type,
      j.charge_type_code,
      COALESCE(j.cost_center_code, o.cost_center_code, '') AS office,
      o.conveyance_type,
      j.partner_code,
      j.partner_name,
      j.invoice_date,
      j.economic_date,
      j.booking_date,
      j.entry_number,
      j.external_number,
      j.amount_currency,
      j.amount_value,
      j.tax_value,
      j.local_amount_value,
      j.local_tax_value,
      j.updated_at
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND {$whereKey}
      AND DATE(j.invoice_date) = :fecha
      AND j.amount_currency = :moneda
    ORDER BY j.id ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute([
    ':factura' => $factura,
    ':fecha'   => $fecha,
    ':moneda'  => $mon,
  ]);

  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
  exit;
}

/* =========================
   EXPORT (MES COMPLETO) - servidor
========================= */
if (isset($_GET['download']) && $_GET['download'] === '1') {
  $downloadVista = $vista;

  // --------------------
  // 1) RESUMEN (external_number)
  // --------------------
  if ($downloadVista === 'resumen') {
    $sql = "
      SELECT
        j.external_number                           AS factura,
        DATE(j.invoice_date)                        AS fecha_factura,
        j.amount_currency                           AS moneda,
        o.customer_code                             AS cliente_codigo,
        o.customer_name                             AS cliente_nombre,
        COUNT(*)                                    AS lineas,
        SUM(IFNULL(j.amount_value,0))               AS total_neto,
        SUM(IFNULL(j.tax_value,0))                  AS total_iva,
        SUM(IFNULL(j.local_amount_value,0))         AS total_neto_local,
        SUM(IFNULL(j.local_tax_value,0))            AS total_iva_local
      FROM scope_jobcosting_entries j
      LEFT JOIN scope_orders o ON o.id = j.order_id
      WHERE j.invoice_date IS NOT NULL
        AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
      GROUP BY
        j.external_number, DATE(j.invoice_date), j.amount_currency,
        o.customer_code, o.customer_name
      ORDER BY fecha_factura DESC, factura DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':inicio'=>$inicio, ':fin'=>$fin]);
    $data = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $filename = "vistas_crudas_resumen_{$mes}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";

    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<table border='1'>";
    echo "<thead><tr>
      <th>Factura</th><th>Fecha factura</th><th>Moneda</th>
      <th>Cliente código</th><th>Cliente</th><th>Líneas</th>
      <th>Total neto</th><th>Total IVA</th><th>Total neto local</th><th>Total IVA local</th>
    </tr></thead><tbody>";

    foreach ($data as $r) {
      echo "<tr>";
      echo "<td>".h($r['factura'])."</td>";
      echo "<td>".h($r['fecha_factura'])."</td>";
      echo "<td>".h($r['moneda'])."</td>";
      echo "<td>".h($r['cliente_codigo'])."</td>";
      echo "<td>".h($r['cliente_nombre'])."</td>";
      echo "<td>".h((string)$r['lineas'])."</td>";
      echo "<td>".h(number_format((float)$r['total_neto'],2,'.',''))."</td>";
      echo "<td>".h(number_format((float)$r['total_iva'],2,'.',''))."</td>";
      echo "<td>".h(number_format((float)$r['total_neto_local'],2,'.',''))."</td>";
      echo "<td>".h(number_format((float)$r['total_iva_local'],2,'.',''))."</td>";
      echo "</tr>";
    }
    echo "</tbody></table></body></html>";
    exit;
  }

  // --------------------
  // 2) DETALLE (líneas)
  // --------------------
  if ($downloadVista === 'detalle') {
    $sql = "
      SELECT
        j.id,
        o.order_number,
        o.customer_name,
        j.entry_type,
        j.charge_type_code,
        COALESCE(j.cost_center_code, o.cost_center_code, '') AS office,
        o.conveyance_type,
        j.partner_code,
        j.partner_name,
        j.invoice_date,
        j.economic_date,
        j.booking_date,
        j.entry_number,
        j.external_number,
        j.amount_currency,
        j.amount_value,
        j.tax_value,
        j.local_amount_value,
        j.local_tax_value,
        j.updated_at
      FROM scope_jobcosting_entries j
      LEFT JOIN scope_orders o ON o.id = j.order_id
      WHERE j.invoice_date IS NOT NULL
        AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
      ORDER BY j.invoice_date DESC, j.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':inicio'=>$inicio, ':fin'=>$fin]);
    $data = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $filename = "vistas_crudas_detalle_{$mes}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo "\xEF\xBB\xBF";

    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<table border='1'>";
    echo "<thead><tr>
      <th>ID</th><th>Orden</th><th>Cliente</th><th>Tipo</th><th>Concepto</th><th>Oficina</th>
      <th>Tráfico</th><th>Partner</th><th>Factura (external)</th><th>Fecha factura</th><th>Moneda</th>
      <th>Neto</th><th>IVA</th><th>Neto (local)</th><th>IVA (local)</th>
      <th>Fecha económica</th><th>Fecha booking</th><th>No. asiento</th><th>Actualizado</th>
    </tr></thead><tbody>";

    foreach ($data as $r) {
      $partner = trim(((string)($r['partner_code'] ?? '')).' '.((string)($r['partner_name'] ?? '')));
      echo "<tr>";
      echo "<td>".h($r['id'])."</td>";
      echo "<td>".h($r['order_number'])."</td>";
      echo "<td>".h($r['customer_name'])."</td>";
      echo "<td>".h($r['entry_type'])."</td>";
      echo "<td>".h($r['charge_type_code'])."</td>";
      echo "<td>".h($r['office'])."</td>";
      echo "<td>".h($r['conveyance_type'])."</td>";
      echo "<td>".h($partner)."</td>";
      echo "<td>".h($r['external_number'])."</td>";
      echo "<td>".h((string)$r['invoice_date'])."</td>";
      echo "<td>".h($r['amount_currency'])."</td>";
      echo "<td>".h(number_format((float)$r['amount_value'],2,'.',''))."</td>";
      echo "<td>".h(number_format((float)$r['tax_value'],2,'.',''))."</td>";
      echo "<td>".h(number_format((float)$r['local_amount_value'],2,'.',''))."</td>";
      echo "<td>".h(number_format((float)$r['local_tax_value'],2,'.',''))."</td>";
      echo "<td>".h((string)$r['economic_date'])."</td>";
      echo "<td>".h((string)$r['booking_date'])."</td>";
      echo "<td>".h($r['entry_number'])."</td>";
      echo "<td>".h((string)$r['updated_at'])."</td>";
      echo "</tr>";
    }
    echo "</tbody></table></body></html>";
    exit;
  }

  // --------------------
  // 3) EXCEL (tipo FEB MXN)
  //   FACTURA = entry_number
  //   solo income
  //   moneda = $moneda
  //   agrupado por entry_number + fecha + moneda + cliente
  // --------------------
  $sql = "
    SELECT
      SUBSTRING_INDEX(j.entry_number, '-', 1)                      AS serie,
      j.entry_number                                               AS factura,
      o.order_number                                               AS referencia,
      DATE(j.invoice_date)                                         AS fecha,
      o.customer_code                                              AS cliente_codigo,
      o.customer_name                                              AS cliente_nombre,

      SUM(IFNULL(j.amount_value,0))                                AS complementarios,
      SUM(IFNULL(j.tax_value,0))                                   AS iva,
      SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0))        AS subtotal,
      0                                                            AS anticipo,
      SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0))        AS total,

      j.amount_currency                                            AS moneda,
      COUNT(*)                                                     AS lineas
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
      AND j.amount_currency = :moneda
      AND LOWER(j.entry_type) LIKE '%income%'
      AND j.entry_number IS NOT NULL
      AND j.entry_number <> ''
    GROUP BY
      j.entry_number, o.order_number, DATE(j.invoice_date),
      o.customer_code, o.customer_name, j.amount_currency
    ORDER BY fecha DESC, factura DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':inicio'=>$inicio, ':fin'=>$fin, ':moneda'=>$moneda]);
  $data = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $filename = "vistas_crudas_excel_{$mes}_{$moneda}.xls";
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header("Content-Disposition: attachment; filename=\"{$filename}\"");
  echo "\xEF\xBB\xBF";

  echo "<html><head><meta charset='UTF-8'></head><body>";
  echo "<table border='1'>";
  echo "<thead><tr>
    <th>SERIE</th><th>FACTURA</th><th>REFERENCIA</th><th>FECHA</th>
    <th>CLIENTE</th><th>NOMBRE</th>
    <th>COMPLEMENTARIOS</th><th>IVA</th><th>SUBTOTAL</th><th>ANTICIPO</th><th>TOTAL</th>
    <th>MONEDA</th><th>LÍNEAS</th>
  </tr></thead><tbody>";

  foreach ($data as $r) {
    echo "<tr>";
    echo "<td>".h($r['serie'])."</td>";
    echo "<td>".h($r['factura'])."</td>";
    echo "<td>".h($r['referencia'])."</td>";
    echo "<td>".h($r['fecha'])."</td>";
    echo "<td>".h($r['cliente_codigo'])."</td>";
    echo "<td>".h($r['cliente_nombre'])."</td>";
    echo "<td>".h(number_format((float)$r['complementarios'],2,'.',''))."</td>";
    echo "<td>".h(number_format((float)$r['iva'],2,'.',''))."</td>";
    echo "<td>".h(number_format((float)$r['subtotal'],2,'.',''))."</td>";
    echo "<td>0.00</td>";
    echo "<td>".h(number_format((float)$r['total'],2,'.',''))."</td>";
    echo "<td>".h($r['moneda'])."</td>";
    echo "<td>".h((string)$r['lineas'])."</td>";
    echo "</tr>";
  }
  echo "</tbody></table></body></html>";
  exit;
}

/* =========================
   Datos para UI (mes seleccionado)
========================= */
if ($vista === 'resumen') {
  $sql = "
    SELECT
      j.external_number                           AS factura,
      DATE(j.invoice_date)                        AS fecha_factura,
      j.amount_currency                           AS moneda,
      o.customer_code                             AS cliente_codigo,
      o.customer_name                             AS cliente_nombre,
      COUNT(*)                                    AS lineas,
      SUM(IFNULL(j.amount_value,0))               AS total_neto,
      SUM(IFNULL(j.tax_value,0))                  AS total_iva,
      SUM(IFNULL(j.local_amount_value,0))         AS total_neto_local,
      SUM(IFNULL(j.local_tax_value,0))            AS total_iva_local
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
    GROUP BY
      j.external_number, DATE(j.invoice_date), j.amount_currency,
      o.customer_code, o.customer_name
    ORDER BY fecha_factura DESC, factura DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':inicio'=>$inicio, ':fin'=>$fin]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

} elseif ($vista === 'excel') {
  $sql = "
    SELECT
      SUBSTRING_INDEX(j.entry_number, '-', 1)                      AS serie,
      j.entry_number                                               AS factura,
      o.order_number                                               AS referencia,
      DATE(j.invoice_date)                                         AS fecha,
      o.customer_code                                              AS cliente_codigo,
      o.customer_name                                              AS cliente_nombre,

      SUM(IFNULL(j.amount_value,0))                                AS complementarios,
      SUM(IFNULL(j.tax_value,0))                                   AS iva,
      SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0))        AS subtotal,
      0                                                            AS anticipo,
      SUM(IFNULL(j.amount_value,0) + IFNULL(j.tax_value,0))        AS total,

      j.amount_currency                                            AS moneda,
      COUNT(*)                                                     AS lineas
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
      AND j.amount_currency = :moneda
      AND LOWER(j.entry_type) LIKE '%income%'
      AND j.entry_number IS NOT NULL
      AND j.entry_number <> ''
    GROUP BY
      j.entry_number, o.order_number, DATE(j.invoice_date),
      o.customer_code, o.customer_name, j.amount_currency
    ORDER BY fecha DESC, factura DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':inicio'=>$inicio, ':fin'=>$fin, ':moneda'=>$moneda]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

} else { // detalle
  $sql = "
    SELECT
      j.id,
      o.order_number,
      o.customer_name,
      j.entry_type,
      j.charge_type_code,
      COALESCE(j.cost_center_code, o.cost_center_code, '') AS office,
      o.conveyance_type,
      j.partner_code,
      j.partner_name,
      j.invoice_date,
      j.economic_date,
      j.booking_date,
      j.entry_number,
      j.external_number,
      j.amount_currency,
      j.amount_value,
      j.tax_value,
      j.local_amount_value,
      j.local_tax_value,
      j.updated_at
    FROM scope_jobcosting_entries j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    WHERE j.invoice_date IS NOT NULL
      AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
    ORDER BY j.invoice_date DESC, j.id DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':inicio'=>$inicio, ':fin'=>$fin]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$totalRows = count($rows);
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Vistas crudas</title>

  <style>
    :root{
      --bg:#0b0c10; --text:#eaeaea; --muted:#a7adbd;
      --card:#12131a; --cardBorder:#242634;
      --th:#151826; --thText:#cbd3ea;
      --field:#0f1016; --fieldBorder:#2a2d3f;
      --btn:#ffd000; --btnText:#111;
      --btn2:#2a2d3f; --btn2Text:#fff;
      --shadow: rgba(0,0,0,.25);
      --bad:#ef4444;
    }
    html[data-theme="light"]{
      --bg:#f6f7fb; --text:#14161f; --muted:#5b6173;
      --card:#ffffff; --cardBorder:#e6e8f0;
      --th:#f2f4fa; --thText:#2a2f42;
      --field:#ffffff; --fieldBorder:#d6d9e6;
      --btn:#ffd000; --btnText:#111;
      --btn2:#111827; --btn2Text:#fff;
      --shadow: rgba(17,24,39,.10);
      --bad:#991b1b;
    }

    html, body { height:100%; }
    * { box-sizing:border-box; }
    body{
      margin:0;
      background:var(--bg);
      color:var(--text);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
      overflow:hidden;
    }
    .app{ height:100vh; display:flex; flex-direction:column; overflow:hidden; }
    .wrap{ flex:1; overflow:auto; padding:18px; }
    .container{max-width:1700px; margin:0 auto;}

    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      margin-bottom:12px;
    }
    .brand h1{font-size:18px; margin:0 0 4px;}
    .muted{color:var(--muted); font-size:13px;}

    .themeBtn{
      width:44px; height:44px; border-radius:14px;
      background:var(--field); border:1px solid var(--fieldBorder);
      display:inline-flex; align-items:center; justify-content:center;
      cursor:pointer;
    }
    .themeBtn svg{width:20px; height:20px; fill:currentColor; color:var(--text);}

    .card{
      background:var(--card);
      border:1px solid var(--cardBorder);
      border-radius:14px;
      padding:14px;
      box-shadow:0 10px 30px var(--shadow);
    }

    .actions{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;}
    button, a.btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding:10px 12px; border-radius:12px;
      font-weight:800; border:0; cursor:pointer; text-decoration:none;
    }
    button.primary, a.btn.primary{background:var(--btn); color:var(--btnText);}
    button.secondary, a.btn.secondary{background:var(--btn2); color:var(--btn2Text);}

    .pager{
      display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;
      margin:10px 0 0;
    }
    .pager .left{display:flex; gap:10px; align-items:center; flex-wrap:wrap;}
    .pager select, .pager input{
      background:var(--field);
      border:1px solid var(--fieldBorder);
      color:var(--text);
      border-radius:10px;
      padding:9px 10px;
      font-size:13px;
      outline:none;
    }
    .pager .meta{color:var(--muted); font-size:13px;}

    .tableWrap{
      overflow:auto;
      border-radius:12px;
      border:1px solid var(--cardBorder);
      max-height: calc(100vh - 310px);
    }

    table{width:100%; border-collapse:separate; border-spacing:0; min-width:1500px;}
    th, td{padding:9px 8px; border-bottom:1px solid var(--cardBorder); font-size:12px; white-space:nowrap;}
    th{background:var(--th); color:var(--thText); text-align:left; position:sticky; top:0; z-index:2;}
    .num{text-align:right; font-variant-numeric:tabular-nums;}
    .center{text-align:center;}

    /* Modal */
    .modalBackdrop{
      position:fixed; inset:0; background:rgba(0,0,0,.55);
      display:none; align-items:center; justify-content:center;
      padding:18px; z-index:9999;
    }
    .modal{
      width:min(1400px, 98vw);
      max-height:90vh;
      overflow:hidden;
      background:var(--card);
      border:1px solid var(--cardBorder);
      border-radius:16px;
      box-shadow:0 20px 50px rgba(0,0,0,.35);
      display:flex; flex-direction:column;
    }
    .modalHeader{
      padding:12px 14px;
      border-bottom:1px solid var(--cardBorder);
      display:flex; align-items:center; justify-content:space-between; gap:10px;
    }
    .modalHeader h2{margin:0; font-size:15px;}
    .modalBody{padding:12px 14px; overflow:auto;}
    .closeBtn{
      background:var(--btn2); color:var(--btn2Text);
      border:0; border-radius:10px; padding:8px 10px; font-weight:900; cursor:pointer;
    }
    .loading{color:var(--muted); font-size:13px;}
  </style>

  <script>
    (function(){
      try{
        const saved = localStorage.getItem('core_scope.theme');
        document.documentElement.setAttribute('data-theme', saved || 'dark');
      }catch(e){}
    })();
  </script>
</head>
<body>
  <div class="app">
    <div class="wrap">
      <div class="container">

        <div class="topbar">
          <div class="brand">
            <h1>Vistas crudas · <?= h(strtoupper($vista)) ?></h1>
            <div class="muted">
              Mes: <b><?= h($mes) ?></b> (<?= h($inicio) ?> a <?= h($fin) ?>) ·
              Filas: <b><?= number_format($totalRows) ?></b>
              <?php if ($vista === 'excel'): ?>
                · Moneda: <b><?= h($moneda) ?></b> · Solo INCOME
              <?php endif; ?>
            </div>
          </div>

          <button id="themeBtn" class="themeBtn" type="button" aria-label="Cambiar tema" title="Cambiar tema">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM4 11a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2h1Zm18 0a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2h1ZM5.64 4.22a1 1 0 0 1 1.41 0l.7.7A1 1 0 1 1 6.34 6.34l-.7-.7a1 1 0 0 1 0-1.42Zm11.31 13.44a1 1 0 0 1 1.41 0l.7.7a1 1 0 1 1-1.41 1.41l-.7-.7a1 1 0 0 1 0-1.41ZM19.78 5.64a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0ZM6.34 17.66a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0Z"/></svg>
          </button>
        </div>

        <div class="card">

          <div class="actions">
            <a class="btn secondary" href="?mes=<?= h($mes) ?>&vista=excel&moneda=<?= h($moneda) ?>">Excel</a>
            <a class="btn secondary" href="?mes=<?= h($mes) ?>&vista=resumen">Resumen</a>
            <a class="btn secondary" href="?mes=<?= h($mes) ?>&vista=detalle">Detalle</a>

            <a class="btn primary" href="?mes=<?= h($mes) ?>&vista=<?= h($vista) ?><?php if($vista==='excel'): ?>&moneda=<?= h($moneda) ?><?php endif; ?>&download=1">Exportar (mes)</a>

            <a class="btn secondary" href="comparar_excel.php?mes=<?= h($mes) ?>">Comparar Excel</a>
            <a class="btn secondary" href="core_scope/scope_menu.php">Volver al menú</a>
          </div>

          <div class="pager">
            <div class="left">

              <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0;">
                <input type="hidden" name="vista" value="<?= h($vista) ?>">
                <label class="meta">Mes</label>
                <input type="month" name="mes" value="<?= h($mes) ?>">

                <?php if ($vista === 'excel'): ?>
                  <label class="meta">Moneda</label>
                  <select name="moneda">
                    <?php foreach (['MXN','USD','EUR'] as $m): ?>
                      <option value="<?= h($m) ?>" <?= $moneda===$m?'selected':'' ?>><?= h($m) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>

                <button class="secondary" type="submit">Aplicar</button>
              </form>

              <span style="width:1px;height:26px;background:var(--cardBorder);display:inline-block;"></span>

              <label class="meta">Filas por página</label>
              <select id="pageSize">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="200">200</option>
                <option value="500">500</option>
              </select>

              <button class="secondary" type="button" id="prevPage">←</button>
              <button class="secondary" type="button" id="nextPage">→</button>

              <span class="meta" id="pageMeta"></span>
            </div>

            <div class="meta">
              <?php if ($vista==='excel'): ?>
                Formato “Excel” (FACTURA = No. asiento) · Solo INCOME
              <?php elseif ($vista==='resumen'): ?>
                Resumen general (external_number)
              <?php else: ?>
                Detalle por líneas (entries)
              <?php endif; ?>
            </div>
          </div>

          <div class="tableWrap">
            <table id="t">
              <thead>
              <?php if ($vista === 'excel'): ?>
                <tr>
                  <th>SERIE</th>
                  <th>FACTURA</th>
                  <th>REFERENCIA</th>
                  <th>FECHA</th>
                  <th>CLIENTE</th>
                  <th>NOMBRE</th>
                  <th class="num">COMPLEMENTARIOS</th>
                  <th class="num">IVA</th>
                  <th class="num">SUBTOTAL</th>
                  <th class="num">ANTICIPO</th>
                  <th class="num">TOTAL</th>
                  <th>MONEDA</th>
                  <th class="num">LÍNEAS</th>
                  <th class="center">DETALLE</th>
                </tr>
              <?php elseif ($vista === 'resumen'): ?>
                <tr>
                  <th>Factura (external)</th>
                  <th>Fecha factura</th>
                  <th>Moneda</th>
                  <th>Cliente</th>
                  <th class="num">Líneas</th>
                  <th class="num">Total neto</th>
                  <th class="num">Total IVA</th>
                  <th class="num">Total neto local</th>
                  <th class="num">Total IVA local</th>
                  <th class="center">Detalle</th>
                </tr>
              <?php else: ?>
                <tr>
                  <th>ID</th>
                  <th>Orden</th>
                  <th>Cliente</th>
                  <th>Tipo</th>
                  <th>Concepto</th>
                  <th>Oficina</th>
                  <th>Tráfico</th>
                  <th>Partner</th>
                  <th>Factura (external)</th>
                  <th>Fecha factura</th>
                  <th>Moneda</th>
                  <th class="num">Neto</th>
                  <th class="num">IVA</th>
                  <th class="num">Neto (local)</th>
                  <th class="num">IVA (local)</th>
                  <th>Fecha económica</th>
                  <th>Fecha booking</th>
                  <th>No. asiento</th>
                  <th>Actualizado</th>
                </tr>
              <?php endif; ?>
              </thead>

              <tbody>
              <?php if ($vista === 'excel'): ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= h($r['serie']) ?></td>
                    <td><?= h($r['factura']) ?></td>
                    <td><?= h($r['referencia']) ?></td>
                    <td><?= h($r['fecha']) ?></td>
                    <td><?= h($r['cliente_codigo']) ?></td>
                    <td><?= h($r['cliente_nombre']) ?></td>
                    <td class="num"><?= number_format((float)$r['complementarios'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['iva'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['subtotal'], 2) ?></td>
                    <td class="num">0.00</td>
                    <td class="num"><?= number_format((float)$r['total'], 2) ?></td>
                    <td><?= h($r['moneda']) ?></td>
                    <td class="num"><?= h((string)$r['lineas']) ?></td>
                    <td class="center">
                      <button
                        class="secondary"
                        type="button"
                        data-detalle="1"
                        data-keytype="entry"
                        data-factura="<?= h($r['factura']) ?>"
                        data-fecha="<?= h($r['fecha']) ?>"
                        data-moneda="<?= h($r['moneda']) ?>"
                      >Ver</button>
                    </td>
                  </tr>
                <?php endforeach; ?>

              <?php elseif ($vista === 'resumen'): ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?= h($r['factura']) ?></td>
                    <td><?= h($r['fecha_factura']) ?></td>
                    <td><?= h($r['moneda']) ?></td>
                    <td><?= h(trim((string)$r['cliente_nombre'])) ?></td>
                    <td class="num"><?= h((string)$r['lineas']) ?></td>
                    <td class="num"><?= number_format((float)$r['total_neto'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['total_iva'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['total_neto_local'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['total_iva_local'], 2) ?></td>
                    <td class="center">
                      <button
                        class="secondary"
                        type="button"
                        data-detalle="1"
                        data-keytype="external"
                        data-factura="<?= h($r['factura']) ?>"
                        data-fecha="<?= h($r['fecha_factura']) ?>"
                        data-moneda="<?= h($r['moneda']) ?>"
                      >Ver</button>
                    </td>
                  </tr>
                <?php endforeach; ?>

              <?php else: ?>
                <?php foreach ($rows as $r):
                  $partner = trim(((string)($r['partner_code'] ?? '')).' '.((string)($r['partner_name'] ?? '')));
                ?>
                  <tr>
                    <td><?= h($r['id']) ?></td>
                    <td><?= h($r['order_number']) ?></td>
                    <td><?= h($r['customer_name']) ?></td>
                    <td><?= h($r['entry_type']) ?></td>
                    <td><?= h($r['charge_type_code']) ?></td>
                    <td><?= h($r['office']) ?></td>
                    <td><?= h($r['conveyance_type']) ?></td>
                    <td><?= h($partner) ?></td>
                    <td><?= h($r['external_number']) ?></td>
                    <td><?= h((string)$r['invoice_date']) ?></td>
                    <td><?= h($r['amount_currency']) ?></td>
                    <td class="num"><?= number_format((float)$r['amount_value'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['tax_value'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['local_amount_value'], 2) ?></td>
                    <td class="num"><?= number_format((float)$r['local_tax_value'], 2) ?></td>
                    <td><?= h((string)$r['economic_date']) ?></td>
                    <td><?= h((string)$r['booking_date']) ?></td>
                    <td><?= h($r['entry_number']) ?></td>
                    <td><?= h((string)$r['updated_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>

      </div>
    </div>
  </div>

  <!-- Modal Detalle -->
  <div class="modalBackdrop" id="modalBackdrop" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-label="Detalle de factura">
      <div class="modalHeader">
        <div>
          <h2 id="modalTitle">Detalle</h2>
          <div class="muted" id="modalSubtitle"></div>
        </div>
        <button class="closeBtn" type="button" id="modalClose">Cerrar</button>
      </div>
      <div class="modalBody" id="modalBody">
        <div class="loading">Cargando…</div>
      </div>
    </div>
  </div>

<script>
  function initTheme(){
    const btn = document.getElementById('themeBtn');
    btn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try{ localStorage.setItem('core_scope.theme', next); }catch(e){}
    });
  }

  // Paginación visual (NO afecta export)
  const table = document.getElementById('t');
  const tbodyRows = Array.from(table.querySelectorAll('tbody tr'));
  const pageSizeEl = document.getElementById('pageSize');
  const meta = document.getElementById('pageMeta');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  let page = 1;
  function pageSize(){ return parseInt(pageSizeEl.value, 10) || 100; }
  function totalPages(){ return Math.max(1, Math.ceil(tbodyRows.length / pageSize())); }

  function render(){
    const ps = pageSize();
    const tp = totalPages();
    if (page > tp) page = tp;
    if (page < 1) page = 1;

    const start = (page - 1) * ps;
    const end = start + ps;

    tbodyRows.forEach((tr, i) => tr.style.display = (i >= start && i < end) ? '' : 'none');

    const showing = Math.max(0, Math.min(ps, tbodyRows.length - start));
    meta.textContent = `Página ${page} de ${tp} · Mostrando ${showing} filas`;
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= tp;
  }

  prevBtn.addEventListener('click', () => { page--; render(); });
  nextBtn.addEventListener('click', () => { page++; render(); });
  pageSizeEl.addEventListener('change', () => { page = 1; render(); });

  // Modal
  const backdrop = document.getElementById('modalBackdrop');
  const modalBody = document.getElementById('modalBody');
  const modalTitle = document.getElementById('modalTitle');
  const modalSubtitle = document.getElementById('modalSubtitle');
  const modalClose = document.getElementById('modalClose');

  function closeModal(){
    backdrop.style.display = 'none';
    backdrop.setAttribute('aria-hidden', 'true');
  }
  modalClose.addEventListener('click', closeModal);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

  function esc(s){
    return (s ?? '').toString()
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  async function openDetalle(btn){
    const factura = btn.dataset.factura || '';
    const fecha   = btn.dataset.fecha || '';
    const moneda  = btn.dataset.moneda || '';
    const keyType = btn.dataset.keytype || 'external';

    modalTitle.textContent = `Detalle ${factura}`;
    modalSubtitle.textContent = `Fecha: ${fecha} · Moneda: ${moneda}`;
    modalBody.innerHTML = `<div class="loading">Cargando…</div>`;

    backdrop.style.display = 'flex';
    backdrop.setAttribute('aria-hidden', 'false');

    const url = new URL(window.location.href);
    url.searchParams.set('ajax','detalle');
    url.searchParams.set('keyType', keyType);
    url.searchParams.set('factura', factura);
    url.searchParams.set('fecha', fecha);
    url.searchParams.set('moneda', moneda);

    try{
      const res = await fetch(url.toString(), { headers: { 'Accept':'application/json' } });
      const js = await res.json();
      if (!js.ok) throw new Error(js.message || 'Error');

      const rows = js.rows || [];
      if (!rows.length){
        modalBody.innerHTML = `<div class="muted">No hay líneas para esta factura.</div>`;
        return;
      }

      const html = `
        <div class="tableWrap" style="max-height:70vh;">
          <table style="min-width:1500px;">
            <thead>
              <tr>
                <th>ID</th><th>Orden</th><th>Cliente</th><th>Tipo</th><th>Concepto</th><th>Oficina</th>
                <th>Tráfico</th><th>Partner</th><th>Factura (external)</th><th>No. asiento</th><th>Fecha factura</th><th>Moneda</th>
                <th class="num">Neto</th><th class="num">IVA</th><th class="num">Neto (local)</th><th class="num">IVA (local)</th>
                <th>Actualizado</th>
              </tr>
            </thead>
            <tbody>
              ${rows.map(r => {
                const partner = (r.partner_code ? (r.partner_code + ' ') : '') + (r.partner_name || '');
                return `<tr>
                  <td>${esc(r.id)}</td>
                  <td>${esc(r.order_number)}</td>
                  <td>${esc(r.customer_name)}</td>
                  <td>${esc(r.entry_type)}</td>
                  <td>${esc(r.charge_type_code)}</td>
                  <td>${esc(r.office)}</td>
                  <td>${esc(r.conveyance_type)}</td>
                  <td>${esc(partner.trim())}</td>
                  <td>${esc(r.external_number)}</td>
                  <td>${esc(r.entry_number)}</td>
                  <td>${esc(r.invoice_date)}</td>
                  <td>${esc(r.amount_currency)}</td>
                  <td class="num">${Number(r.amount_value || 0).toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                  <td class="num">${Number(r.tax_value || 0).toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                  <td class="num">${Number(r.local_amount_value || 0).toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                  <td class="num">${Number(r.local_tax_value || 0).toLocaleString('es-MX',{minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                  <td>${esc(r.updated_at)}</td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>
      `;
      modalBody.innerHTML = html;

    }catch(err){
      modalBody.innerHTML = `<div style="color:var(--bad);font-weight:900;">Error</div><div class="muted" style="margin-top:6px;">${esc(err.message || String(err))}</div>`;
    }
  }

  document.querySelectorAll('[data-detalle="1"]').forEach(btn => {
    btn.addEventListener('click', () => openDetalle(btn));
  });

  initTheme();
  render();
</script>
</body>
</html>