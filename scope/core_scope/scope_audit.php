<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();
$cfg = require __DIR__ . '/config.php';

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function json_out(int $code, array $payload): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* =========================
   PWA (sin archivos extra)
========================= */
if (isset($_GET['pwa'])) {
  $pwa = (string)$_GET['pwa'];

  if ($pwa === 'manifest') {
    header('Content-Type: application/manifest+json; charset=utf-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
      <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
        <stop stop-color="#0171E2"/><stop offset="1" stop-color="#0B1B4A"/>
      </linearGradient></defs>
      <rect width="256" height="256" rx="54" fill="url(#g)"/>
      <path d="M70 158c0-36 26-64 58-64h58v26h-58c-17 0-32 16-32 38 0 21 15 36 32 36h58v26h-58c-32 0-58-26-58-62z"
        fill="rgba(255,255,255,.92)"/>
      <circle cx="70" cy="126" r="12" fill="rgba(255,255,255,.92)"/>
    </svg>';
    $iconData = 'data:image/svg+xml;charset=utf-8,'.rawurlencode($svg);

    echo json_encode([
      'name' => 'CORE SCOPE · Auditoría',
      'short_name' => 'SCOPE',
      'start_url' => 'scope_audit.php',
      'display' => 'standalone',
      'background_color' => '#0B1B4A',
      'theme_color' => '#0171E2',
      'icons' => [
        ['src' => $iconData, 'sizes' => '256x256', 'type' => 'image/svg+xml', 'purpose' => 'any']
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($pwa === 'sw') {
    header('Content-Type: application/javascript; charset=utf-8');
    ?>
/* CORE SCOPE SW (scope_audit.php?pwa=sw) */
const CACHE = 'scope-audit-v2';
const SHELL = ['./scope_audit.php'];

self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    const c = await caches.open(CACHE);
    await c.addAll(SHELL);
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => (k === CACHE ? null : caches.delete(k))));
    self.clients.claim();
  })());
});

function isAjax(req){
  try{
    const u = new URL(req.url);
    return u.pathname.endsWith('/scope_audit.php') && u.searchParams.get('ajax') === '1';
  }catch(_){ return false; }
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  if (isAjax(req)) {
    e.respondWith((async () => {
      try{
        const fresh = await fetch(req);
        const c = await caches.open(CACHE);
        c.put(req, fresh.clone());
        return fresh;
      }catch(_){
        const cached = await caches.match(req);
        return cached || new Response(JSON.stringify({ success:false, message:'Sin conexión' }), {
          status: 200,
          headers: { 'Content-Type':'application/json; charset=utf-8' }
        });
      }
    })());
    return;
  }

  e.respondWith((async () => {
    const cached = await caches.match(req);
    if (cached) return cached;
    const fresh = await fetch(req);
    const c = await caches.open(CACHE);
    c.put(req, fresh.clone());
    return fresh;
  })());
});
    <?php
    exit;
  }
}

/* =========================
   AJAX
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  $action = (string)($_GET['action'] ?? 'list');

  try {
    if ($action === 'health') {
      $r = $pdo->query("
        SELECT
          (SELECT COUNT(*) FROM scope_jobcosting_entries) AS total_entries,
          DATE(MIN(COALESCE(invoice_date, economic_date))) AS min_effective,
          DATE(MAX(COALESCE(invoice_date, economic_date))) AS max_effective,
          DATE(MIN(invoice_date)) AS min_invoice,
          DATE(MAX(invoice_date)) AS max_invoice,
          DATE(MIN(economic_date)) AS min_economic,
          DATE(MAX(economic_date)) AS max_economic,
          (SELECT DATE(MAX(last_modified_utc)) FROM scope_orders) AS max_last_modified,
          (SELECT DATE(MIN(last_modified_utc)) FROM scope_orders) AS min_last_modified,
          (SELECT SUM(invoice_date IS NULL) FROM scope_jobcosting_entries) AS invoice_null
      ")->fetch(PDO::FETCH_ASSOC) ?: [];

      $st = $pdo->query("
        SELECT organization_code, legal_entity_code, branch_code,
               last_modified_max_utc, last_run_uuid, updated_at
        FROM scope_sync_state
        ORDER BY updated_at DESC
        LIMIT 1
      ")->fetch(PDO::FETCH_ASSOC) ?: null;

      json_out(200, [
        'success' => true,
        'db' => $r,
        'sync' => $st,
        'server_time' => date('Y-m-d H:i:s'),
      ]);
    }

    if ($action === 'order_json') {
      $uuid = trim((string)($_GET['uuid'] ?? ''));
      if ($uuid === '') json_out(400, ['success'=>false,'message'=>'uuid requerido']);

      $st = $pdo->prepare("
        SELECT scope_uuid, order_number, raw_json
        FROM scope_orders
        WHERE scope_uuid = ?
        ORDER BY id DESC
        LIMIT 1
      ");
      $st->execute([$uuid]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if (!$row) json_out(404, ['success'=>false,'message'=>'No existe en BD']);

      $raw = (string)($row['raw_json'] ?? '');
      if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $raw = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      json_out(200, [
        'success' => true,
        'uuid' => $row['scope_uuid'],
        'order_number' => $row['order_number'],
        'raw' => $raw !== '' ? $raw : '—',
      ]);
    }

    /* ===== LIST ===== */

    // ✅ NUEVO: sort
    // updated = por lastModified (DEFAULT)
    // financial = por fecha financiera (coalesce invoice/economic)
    $sort = (string)($_GET['sort'] ?? 'updated');
    if (!in_array($sort, ['updated','financial'], true)) $sort = 'updated';

    $mode = (string)($_GET['mode'] ?? 'fallback'); // invoice|economic|fallback
    if (!in_array($mode, ['invoice','economic','fallback'], true)) $mode = 'fallback';

    $from = (string)($_GET['from'] ?? '');
    $to   = (string)($_GET['to'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';

    $office  = (string)($_GET['office'] ?? 'all');
    $traffic = (string)($_GET['traffic'] ?? 'all');

    $etypeUi = (string)($_GET['etype'] ?? 'all');
    $etype = 'all';
    if ($etypeUi === 'ingresos') $etype = 'income';
    if ($etypeUi === 'egresos')  $etype = 'payable';

    $ter = (string)($_GET['ter'] ?? 'all'); // all|exclude|only

    $q = trim((string)($_GET['q'] ?? ''));
    $customer = trim((string)($_GET['customer'] ?? ''));
    $concept  = trim((string)($_GET['concept'] ?? ''));
    $orderNo  = trim((string)($_GET['order'] ?? ''));
    $uuid     = trim((string)($_GET['uuid'] ?? ''));

    $page = max(1, (int)($_GET['page'] ?? 1));
    $size = (int)($_GET['size'] ?? 25);
    if ($size < 10) $size = 10;
    if ($size > 100) $size = 100;
    $offset = ($page - 1) * $size;

    $dateExpr = "COALESCE(j.invoice_date, j.economic_date)";
    if ($mode === 'invoice') $dateExpr = "j.invoice_date";
    if ($mode === 'economic') $dateExpr = "j.economic_date";

    // Default range según sort:
    // - updated: últimos 30 días desde MAX(last_modified_utc) de scope_orders
    // - financial: últimos 30 días desde MAX(coalesce(invoice/economic)) en entries
    if ($from === '' || $to === '') {
      if ($sort === 'updated') {
        $max = $pdo->query("SELECT DATE(MAX(last_modified_utc)) AS d FROM scope_orders")->fetch(PDO::FETCH_ASSOC);
        $maxD = (string)($max['d'] ?? '');
      } else {
        $max = $pdo->query("SELECT DATE(MAX(COALESCE(invoice_date,economic_date))) AS d FROM scope_jobcosting_entries")->fetch(PDO::FETCH_ASSOC);
        $maxD = (string)($max['d'] ?? '');
      }

      if ($maxD === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $maxD)) {
        $maxD = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
      }

      $to = $maxD;
      $from = (new DateTimeImmutable($to, new DateTimeZone('America/Mexico_City')))
        ->sub(new DateInterval('P30D'))
        ->format('Y-m-d');
    }

    // WHERE base
    $w = [];
    $p = [];

    // ✅ Si sort=financial, necesitamos entries; si sort=updated, mostramos órdenes aunque no tengan entries.
    // Para no rearmar todo, usamos LEFT JOIN a entries y filtramos inteligente.
    // Cuando sort=financial: exigimos al menos una fecha financiera (fallback/invoice/economic).
    if ($sort === 'financial') {
      if ($mode === 'invoice')   $w[] = "j.invoice_date IS NOT NULL";
      if ($mode === 'economic')  $w[] = "j.economic_date IS NOT NULL";
      if ($mode === 'fallback')  $w[] = "(j.invoice_date IS NOT NULL OR j.economic_date IS NOT NULL)";
      $w[] = "DATE($dateExpr) BETWEEN :from AND :to";
    } else {
      // sort=updated: rango por lastModified de la orden
      $w[] = "o.last_modified_utc IS NOT NULL";
      $w[] = "DATE(o.last_modified_utc) BETWEEN :from AND :to";
    }
    $p[':from'] = $from;
    $p[':to'] = $to;

    if ($office !== 'all') { $w[] = "COALESCE(j.cost_center_code, o.cost_center_code) = :office"; $p[':office']=$office; }
    if ($traffic !== 'all') { $w[] = "o.conveyance_type = :traffic"; $p[':traffic']=$traffic; }
    if ($etype !== 'all') { $w[] = "j.entry_type = :etype"; $p[':etype']=$etype; }

    if ($ter === 'only') $w[] = "j.charge_type_code LIKE 'PT%'";
    if ($ter === 'exclude') $w[] = "(j.charge_type_code IS NULL OR j.charge_type_code NOT LIKE 'PT%')";

    if ($customer !== '') { $w[] = "(o.customer_code LIKE :cust OR o.customer_name LIKE :cust)"; $p[':cust'] = '%'.$customer.'%'; }
    if ($concept !== '')  { $w[] = "j.charge_type_code LIKE :concept"; $p[':concept'] = '%'.$concept.'%'; }
    if ($orderNo !== '')  { $w[] = "o.order_number LIKE :ord"; $p[':ord'] = '%'.$orderNo.'%'; }
    if ($uuid !== '')     { $w[] = "o.scope_uuid = :uuid"; $p[':uuid'] = $uuid; }

    if ($q !== '') {
      $w[] = "(
        o.order_number LIKE :q OR
        o.customer_code LIKE :q OR o.customer_name LIKE :q OR
        j.charge_type_code LIKE :q OR
        COALESCE(j.cost_center_code, o.cost_center_code) LIKE :q OR
        o.conveyance_type LIKE :q OR
        j.partner_code LIKE :q OR j.partner_name LIKE :q OR
        j.entry_number LIKE :q OR j.external_number LIKE :q
      )";
      $p[':q'] = '%'.$q.'%';
    }

    $where = implode(' AND ', $w);

    // COUNT total (DISTINCT order para sort=updated; para financial contamos rows)
    if ($sort === 'updated') {
      $sqlCount = "
        SELECT COUNT(DISTINCT o.id) AS c
        FROM scope_orders o
        LEFT JOIN scope_jobcosting_entries j ON j.order_id = o.id
        WHERE $where
      ";
    } else {
      $sqlCount = "
        SELECT COUNT(*) AS c
        FROM scope_jobcosting_entries j
        JOIN scope_orders o ON o.id = j.order_id
        WHERE $where
      ";
    }

    $st = $pdo->prepare($sqlCount);
    $st->execute($p);
    $totalRows = (int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    // LIST
    // Para sort=updated:
    // - sacamos 1 fila por orden y agregamos mini-resumen de montos (sum) como preview.
    // Para sort=financial:
    // - se queda por entry (como antes).
    if ($sort === 'updated') {
      $sql = "
        SELECT
          o.scope_uuid,
          o.order_number,
          o.conveyance_type,
          o.cost_center_code AS office,
          o.customer_code,
          o.customer_name,
          o.last_modified_utc AS last_modified_utc,
          DATE_FORMAT(DATE(o.last_modified_utc), '%d/%m/%Y') AS last_modified_mx,

          COALESCE(SUM(CASE WHEN j.entry_type='income' THEN j.local_amount_value ELSE 0 END),0) AS sum_income,
          COALESCE(SUM(CASE WHEN j.entry_type='payable' THEN j.local_amount_value ELSE 0 END),0) AS sum_cost,

          COALESCE(MAX($dateExpr), NULL) AS last_financial_raw,
          DATE_FORMAT(DATE(MAX($dateExpr)), '%d/%m/%Y') AS last_financial_mx

        FROM scope_orders o
        LEFT JOIN scope_jobcosting_entries j ON j.order_id = o.id
        WHERE $where
        GROUP BY o.id
        ORDER BY o.last_modified_utc DESC
        LIMIT $size OFFSET $offset
      ";
      $st = $pdo->prepare($sql);
      $st->execute($p);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $sql = "
        SELECT
          o.scope_uuid,
          o.order_number,
          o.conveyance_type,
          COALESCE(j.cost_center_code, o.cost_center_code) AS office,
          o.customer_code,
          o.customer_name,
          $dateExpr AS date_effective_raw,
          DATE_FORMAT(DATE($dateExpr), '%d/%m/%Y') AS date_effective_mx,
          j.entry_type,
          j.charge_type_code,
          j.local_amount_value,
          j.local_tax_value,
          j.partner_code,
          j.partner_name,
          j.entry_number,
          j.external_number,
          o.last_modified_utc,
          DATE_FORMAT(DATE(o.last_modified_utc), '%d/%m/%Y') AS last_modified_mx
        FROM scope_jobcosting_entries j
        JOIN scope_orders o ON o.id = j.order_id
        WHERE $where
        ORDER BY DATE($dateExpr) DESC, o.order_number DESC
        LIMIT $size OFFSET $offset
      ";
      $st = $pdo->prepare($sql);
      $st->execute($p);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Summary:
    // - financial: igual que antes, con entries
    // - updated: lo calculamos en entries pero respetando el mismo where (LEFT JOIN)
    $sqlSum = "
      SELECT
        COALESCE(SUM(CASE WHEN j.entry_type='income' AND j.charge_type_code NOT LIKE 'PT%' THEN j.local_amount_value ELSE 0 END),0) AS sales,
        COALESCE(SUM(CASE WHEN j.entry_type='payable' THEN j.local_amount_value ELSE 0 END),0) AS costs,
        COALESCE(SUM(CASE WHEN j.entry_type='income' AND j.charge_type_code LIKE 'PT%' THEN j.local_amount_value ELSE 0 END),0) AS ter
      FROM scope_orders o
      LEFT JOIN scope_jobcosting_entries j ON j.order_id = o.id
      WHERE $where
    ";
    $st = $pdo->prepare($sqlSum);
    $st->execute($p);
    $sum = $st->fetch(PDO::FETCH_ASSOC) ?: ['sales'=>0,'costs'=>0,'ter'=>0];

    json_out(200, [
      'success' => true,
      'filters' => [
        'sort' => $sort,
        'mode' => $mode,
        'from' => $from,
        'to' => $to,
        'office' => $office,
        'traffic' => $traffic,
        'etype' => $etypeUi,
        'ter' => $ter,
        'q' => $q,
        'customer' => $customer,
        'concept' => $concept,
        'order' => $orderNo,
        'uuid' => $uuid,
        'page' => $page,
        'size' => $size,
      ],
      'summary' => [
        'total_rows' => $totalRows,
        'sales_local' => (float)$sum['sales'],
        'costs_local' => (float)$sum['costs'],
        'ter_income_local' => (float)$sum['ter'],
        'profit_local' => (float)$sum['sales'] - (float)$sum['costs'],
      ],
      'rows' => $rows,
      'server_time' => date('Y-m-d H:i:s'),
    ]);

  } catch (Throwable $e) {
    json_out(500, ['success'=>false,'message'=>'Error','error'=>$e->getMessage()]);
  }
}

/* =========================
   UI
========================= */
$cssVars = core_brand_css_vars();
$tenantLbl = h(($cfg['scope']['organizationCode'] ?? '').' / '.($cfg['scope']['legalEntityCode'] ?? '').' / '.($cfg['scope']['branchCode'] ?? ''));

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0171E2">

  <link rel="manifest" href="scope_audit.php?pwa=manifest">

  <title>CORE SCOPE · Auditoría</title>

  <style>
    <?= $cssVars ?>
    *{ box-sizing:border-box; }
    :root{
      --glass: rgba(255,255,255,.78);
      --shadow: 0 18px 48px rgba(2,6,23,.10);
      --shadow2: 0 10px 22px rgba(2,6,23,.08);
    }
    body{
      margin:0;
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(156,193,247,.35), transparent 60%),
        radial-gradient(900px 500px at 110% 10%, rgba(1,113,226,.18), transparent 55%),
        var(--bg);
      color: var(--text);
    }
    .wrap{ max-width: 1550px; margin:0 auto; padding: 14px 12px 90px; }
    .appbar{
      position: sticky; top: 10px; z-index: 50;
      backdrop-filter: blur(10px);
      background: var(--glass);
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 18px;
      box-shadow: var(--shadow);
      padding: 12px 14px;
      display:flex; gap:12px; align-items:center; justify-content:space-between;
      margin-bottom: 12px;
    }
    .brand{ display:flex; align-items:center; gap:12px; min-width: 260px; }
    .mark{ width:46px;height:46px;border-radius:16px;background: linear-gradient(135deg, var(--core-blue), var(--core-navy)); box-shadow: 0 16px 34px rgba(0,15,159,.18); position:relative; overflow:hidden; }
    .mark:after{ content:""; position:absolute; inset:auto -22px -22px auto; width:48px;height:48px;border-radius:18px; background: rgba(156,193,247,.60); transform: rotate(18deg); }
    h1{ margin:0; font-size: 1.05rem; letter-spacing:.2px; }
    .muted{ color: var(--muted); font-weight:800; font-size:.88rem; }
    .chip{ display:inline-flex; align-items:center; gap:8px; padding:7px 10px; border-radius:999px; border:1px solid rgba(15,23,42,.12); background: rgba(255,255,255,.85); font-weight: 950; font-size:.80rem; color: var(--core-navy); white-space:nowrap; }
    .btn{ border:1px solid rgba(15,23,42,.12); padding: 10px 12px; border-radius: 999px; font-weight: 950; cursor:pointer; background:#fff; color: var(--core-navy); display:inline-flex; align-items:center; gap:10px; text-decoration:none; }
    .btn-primary{ background: var(--core-blue); color:#fff; border-color: rgba(1,113,226,.40); }
    .btn:disabled{ opacity:.55; cursor:not-allowed; }

    .grid{ display:grid; grid-template-columns: 420px 1fr; gap: 12px; align-items:start; }
    .panel, .list{ background: rgba(255,255,255,.86); border: 1px solid rgba(15,23,42,.10); border-radius: 18px; box-shadow: var(--shadow2); padding: 12px; }

    label{ display:block; font-weight: 950; color: var(--muted); font-size:.80rem; margin-bottom:6px; }
    .input, .select{ width:100%; border: 1px solid rgba(15,23,42,.10); border-radius: 14px; padding: 10px 12px; font-weight: 850; background:#fff; outline:none; }

    .filters{ display:grid; grid-template-columns: repeat(12, 1fr); gap: 10px; }
    .f6{ grid-column: span 6; }
    .f12{ grid-column: span 12; }

    .kpis{ display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-top:10px; }
    .kpi{ border-radius: 16px; border: 1px solid rgba(15,23,42,.10); background: linear-gradient(135deg, rgba(1,113,226,.10), rgba(255,255,255,.90)); padding: 10px 10px; }
    .kpi .t{ color: var(--muted); font-weight: 950; font-size:.80rem; }
    .kpi .v{ font-weight: 1100; font-size: 1.12rem; color: var(--core-navy); margin-top:4px; }
    .kpi .s{ color: var(--muted); font-weight: 850; font-size:.78rem; margin-top:2px; }

    .listHead{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; padding-bottom: 10px; border-bottom: 1px solid rgba(15,23,42,.08); margin-bottom: 10px; }
    .search{ flex:1; min-width: 220px; border: 1px solid rgba(15,23,42,.10); border-radius: 14px; padding: 10px 12px; font-weight: 850; outline:none; background:#fff; }

    .cards{ display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .card{ border: 1px solid rgba(15,23,42,.10); border-radius: 18px; background: #fff; padding: 12px; box-shadow: 0 10px 22px rgba(2,6,23,.06); overflow:hidden; }
    .cardTop{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .title{ font-weight: 1100; color: var(--core-navy); line-height: 1.15; font-size: 1.00rem; word-break: break-word; }
    .sub{ color: var(--muted); font-weight: 850; font-size:.86rem; margin-top: 4px; line-height:1.2; word-break: break-word; }

    .badge{ display:inline-flex; align-items:center; gap:8px; padding: 6px 10px; border-radius: 999px; font-weight: 1000; font-size:.78rem; border: 1px solid rgba(15,23,42,.10); background: rgba(2,6,23,.03); color: var(--core-navy); white-space:nowrap; }
    .b-in{ background: rgba(1,113,226,.12); color: var(--core-navy); }
    .b-out{ background: rgba(101,98,95,.12); color: #111827; }
    .b-ter{ background: rgba(255,193,7,.18); color: #7c2d12; }

    .moneyBox{ text-align:right; min-width: 140px; }
    .amt{ font-weight: 1200; font-size: 1.05rem; color: var(--core-navy); margin-top: 2px; white-space:nowrap; }
    .tax{ color: var(--muted); font-weight: 850; font-size: .80rem; margin-top: 2px; white-space:nowrap; }

    .more{ margin-top: 10px; border-top: 1px dashed rgba(15,23,42,.12); padding-top: 10px; display:none; }
    .more .line{ color: var(--muted); font-weight: 850; font-size:.85rem; margin-top:6px; }
    .more b{ color: var(--text); font-weight: 1100; }

    .cardBtns{ margin-top: 10px; display:flex; gap:10px; flex-wrap:wrap; }

    .paginator{
      position: sticky; bottom: 10px;
      margin-top: 12px;
      display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;
      padding: 10px 12px;
      border: 1px solid rgba(15,23,42,.10);
      border-radius: 18px;
      background: rgba(255,255,255,.90);
      box-shadow: var(--shadow2);
    }
    .small{ font-size:.86rem; font-weight: 900; color: var(--muted); }
    .pagerBtns{ display:flex; gap:10px; flex-wrap:wrap; }

    .modal-backdrop{ position: fixed; inset:0; background: rgba(2,6,23,.55); display:none; align-items:center; justify-content:center; padding: 18px; z-index: 9999; }
    .modal{ width: min(1100px, 96vw); max-height: 88vh; overflow:auto; background: #fff; border-radius: 18px; border:1px solid rgba(255,255,255,.12); box-shadow: 0 30px 80px rgba(2,6,23,.35); }
    .modal-head{ position: sticky; top:0; background: #fff; border-bottom:1px solid rgba(15,23,42,.10); padding: 12px 14px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .modal-title{ font-weight:1100; color: var(--core-navy); }
    .modal-body{ padding: 12px 14px 16px; }
    pre{ white-space: pre-wrap; word-break: break-word; background: #0b1220; color: #e5e7eb; border-radius: 14px; padding: 12px; border:1px solid rgba(255,255,255,.08); max-height: 60vh; overflow:auto; font-size: .82rem; }
    .xbtn{ border:1px solid rgba(15,23,42,.12); background:#fff; border-radius: 999px; padding: 8px 12px; font-weight: 950; cursor:pointer; }

    @media (max-width: 1100px){ .grid{ grid-template-columns: 1fr; } .cards{ grid-template-columns: 1fr; } }
    @media (max-width: 520px){ .wrap{ padding-bottom: 110px; } .kpis{ grid-template-columns: 1fr; } .btn{ width:100%; justify-content:center; } }
  </style>
</head>

<body>
<div class="wrap">
  <div class="appbar">
    <div class="brand">
      <div class="mark"></div>
      <div>
        <h1>Auditoría</h1>
        <div class="muted" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
          <span class="chip">Tenant: <span class="muted"><?= $tenantLbl ?></span></span>
          <span class="chip" id="chipHealth">Cargando estado…</span>
        </div>
      </div>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <a class="btn" href="dashboard_pro.php">📊 Dashboard</a>
      <a class="btn" href="scope_console.php">🧪 Console</a>
      <button class="btn btn-primary" id="btnSync">⟳ Sincronizar</button>
    </div>
  </div>

  <div class="grid">
    <div class="panel">
      <div style="font-weight:1100; color:var(--core-navy);">Filtros</div>
      <div class="muted" id="rangeHint">Rango según el orden seleccionado.</div>

      <div class="filters" style="margin-top:10px;">
        <div class="f6">
          <label>Ordenar por</label>
          <select id="sort" class="select">
            <option value="updated" selected>🕒 Última actualización (recomendado)</option>
            <option value="financial">💰 Fecha financiera (Factura/Operación)</option>
          </select>
        </div>

        <div class="f6">
          <label>Base de fecha (financiera)</label>
          <select id="mode" class="select">
            <option value="fallback">Factura → Operación (recomendado)</option>
            <option value="invoice">Solo Factura</option>
            <option value="economic">Solo Operación</option>
          </select>
        </div>

        <div class="f6">
          <label>Mostrar</label>
          <select id="etype" class="select">
            <option value="all">Todo</option>
            <option value="ingresos">Ingresos</option>
            <option value="egresos">Egresos</option>
          </select>
        </div>

        <div class="f6">
          <label>TER</label>
          <select id="ter" class="select">
            <option value="all">Incluir TER</option>
            <option value="exclude">Excluir TER</option>
            <option value="only">Solo TER</option>
          </select>
        </div>

        <div class="f6">
          <label>Desde</label>
          <input id="from" type="date" class="input">
        </div>
        <div class="f6">
          <label>Hasta</label>
          <input id="to" type="date" class="input">
        </div>

        <div class="f6">
          <label>Oficina</label>
          <select id="office" class="select">
            <option value="all">Todas</option>
            <option value="2000">Nuevo Laredo</option>
            <option value="1000">Veracruz</option>
            <option value="3000">Corporativo</option>
          </select>
        </div>

        <div class="f6">
          <label>Tráfico</label>
          <select id="traffic" class="select">
            <option value="all">Todos</option>
            <option value="road">🚚 Terrestre</option>
            <option value="sea">🚢 Marítimo</option>
            <option value="air">✈️ Aéreo</option>
          </select>
        </div>

        <div class="f6">
          <label>Tarjetas por página</label>
          <select id="size" class="select">
            <option value="20">20</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>

        <div class="f12">
          <label>Cliente</label>
          <input id="customer" class="input" placeholder="Ej. ARMOR / C0001">
        </div>

        <div class="f12">
          <label>Concepto (código)</label>
          <input id="concept" class="input" placeholder="Ej. IAD02 / CAD02 / PT01">
        </div>

        <div class="f12">
          <label>Embarque</label>
          <input id="order" class="input" placeholder="Ej. CGL26...">
        </div>

        <div class="f12">
          <label>UUID (opcional)</label>
          <input id="uuid" class="input" placeholder="03a3a6c1-...">
        </div>

        <div class="f12" style="display:flex; gap:10px; flex-wrap:wrap;">
          <button id="btnLoad" class="btn btn-primary">✅ Aplicar</button>
          <button id="btnClear" class="btn">🧹 Limpiar</button>
        </div>
      </div>

      <div class="kpis" id="kpis"></div>
    </div>

    <div class="list">
      <div class="listHead">
        <div>
          <div style="font-weight:1100; color:var(--core-navy);">Resultados</div>
          <div class="muted">Modo A: “Última actualización” por defecto.</div>
        </div>
        <input id="q" class="search" placeholder="Buscar dentro de esta página…">
      </div>

      <div class="cards" id="cards"></div>

      <div class="paginator">
        <div class="small" id="pageInfo">—</div>
        <div class="pagerBtns">
          <button class="btn" id="btnPrev">← Anterior</button>
          <button class="btn" id="btnNext">Siguiente →</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal JSON -->
<div class="modal-backdrop" id="mb">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="mTitle">Detalle</div>
      <button class="xbtn" id="mClose">Cerrar</button>
    </div>
    <div class="modal-body">
      <pre id="mJson">—</pre>
    </div>
  </div>
</div>

<script>
(() => {
  const $ = (id) => document.getElementById(id);

  const money = (n) => Number(n||0).toLocaleString('es-MX', { style:'currency', currency:'MXN' });

  function trafficLabel(code){
    if (code === 'road') return '🚚 Terrestre';
    if (code === 'sea') return '🚢 Marítimo';
    if (code === 'air') return '✈️ Aéreo';
    return code || '—';
  }
  function officeLabel(code){
    if (code === '2000') return 'Nuevo Laredo';
    if (code === '1000') return 'Veracruz';
    if (code === '3000') return 'Corporativo';
    return code || '—';
  }
  function isPT(code){
    return String(code||'').toUpperCase().startsWith('PT');
  }

  function setRangeHint(sort, from, to){
    const a = from ? from.split('-').reverse().join('/') : '—';
    const b = to ? to.split('-').reverse().join('/') : '—';
    $('rangeHint').textContent = (sort === 'updated')
      ? `Rango por última actualización: ${a} al ${b}.`
      : `Rango por fecha financiera: ${a} al ${b}.`;
  }

  let state = { page: 1, size: 25, total: 0, rows: [] };

  const mb = $('mb');
  $('mClose').addEventListener('click', ()=> mb.style.display='none');
  mb.addEventListener('click', (e)=>{ if (e.target === mb) mb.style.display='none'; });

  function setKpis(s){
    const total = Number(s.total_rows||0);
    const sales = Number(s.sales_local||0);
    const costs = Number(s.costs_local||0);
    const ter = Number(s.ter_income_local||0);
    const profit = Number(s.profit_local||0);
    const margin = (sales > 0) ? (profit/sales) : 0;

    $('kpis').innerHTML = `
      <div class="kpi"><div class="t">Registros</div><div class="v">${total.toLocaleString('es-MX')}</div><div class="s">en este filtro</div></div>
      <div class="kpi"><div class="t">Ventas (sin TER)</div><div class="v">${money(sales)}</div><div class="s">ingresos sin PT</div></div>
      <div class="kpi"><div class="t">Costos</div><div class="v">${money(costs)}</div><div class="s">egresos</div></div>
      <div class="kpi"><div class="t">Utilidad / Margen</div><div class="v">${money(profit)}</div><div class="s">margen ${(margin*100).toFixed(2)}% · TER ${money(ter)}</div></div>
    `;
  }

  function toggleMore(id){
    const el = document.querySelector(`[data-more="${id}"]`);
    const btn = document.querySelector(`[data-morebtn="${id}"]`);
    if (!el || !btn) return;
    const open = (el.style.display === 'block');
    el.style.display = open ? 'none' : 'block';
    btn.textContent = open ? '➕ Ver más' : '➖ Ver menos';
  }

  function safe(s){
    return String(s ?? '').replaceAll('<','&lt;').replaceAll('>','&gt;');
  }

  function render(){
    const q = ($('q').value || '').trim().toLowerCase();
    const rows = q ? state.rows.filter(r => JSON.stringify(r).toLowerCase().includes(q)) : state.rows;

    const sort = $('sort').value;

    if (!rows.length){
      $('cards').innerHTML = `<div class="muted">Sin resultados en esta página.</div>`;
    } else {
      $('cards').innerHTML = rows.map((r, idx) => {
        const id = `${state.page}-${idx}`;

        const uuid = r.scope_uuid || '';
        const order = r.order_number || '—';
        const customer = `${r.customer_code||''} · ${r.customer_name||''}`.trim();

        // Modo updated: sumas + last_modified
        if (sort === 'updated') {
          const updated = r.last_modified_mx || '—';
          const lastFin = r.last_financial_mx || '—';
          const inc = money(r.sum_income || 0);
          const cost = money(r.sum_cost || 0);
          const profit = money((Number(r.sum_income||0) - Number(r.sum_cost||0)));

          return `
            <div class="card">
              <div class="cardTop">
                <div style="min-width:0;">
                  <div class="title">${safe(order)}</div>
                  <div class="sub">${safe(customer || '—')}</div>
                  <div class="sub">Actualizado: <b>${updated}</b> · ${trafficLabel(r.conveyance_type||'')} · ${officeLabel(r.office||'')}</div>
                </div>

                <div class="moneyBox">
                  <div class="amt">${profit}</div>
                  <div class="tax">Ingresos: ${inc}</div>
                  <div class="tax">Costos: ${cost}</div>
                </div>
              </div>

              <div class="cardBtns">
                <button class="btn" data-morebtn="${id}" onclick="window.__toggleMore('${id}')">➕ Ver más</button>
                <button class="btn" onclick="window.__openJson('${uuid}','${safe(order).replaceAll("'","")}')">👁️ JSON</button>
              </div>

              <div class="more" data-more="${id}">
                <div class="line">Última fecha financiera detectada: <b>${lastFin}</b></div>
                <div class="line">UUID: <b>${safe(uuid || '—')}</b></div>
              </div>
            </div>
          `;
        }

        // Modo financial: entries (como antes)
        const entryType = r.entry_type || '';
        const badgeType = entryType === 'income' ? 'b-in' : 'b-out';
        const code = r.charge_type_code || '';
        const terBadge = isPT(code) ? `<span class="badge b-ter">🧾 TER</span>` : '';

        const dmx = r.date_effective_mx || '—';
        const updated = r.last_modified_mx || '—';
        const partner = `${r.partner_code||''} · ${r.partner_name||''}`.trim();
        const inv = (r.entry_number || '—') + (r.external_number ? ` / ${r.external_number}` : '');

        return `
          <div class="card">
            <div class="cardTop">
              <div style="min-width:0;">
                <div class="title">${safe(order)}</div>
                <div class="sub">${safe(customer || '—')}</div>
                <div class="sub">Fecha: <b>${dmx}</b> · Actualizado: <b>${updated}</b></div>
                <div class="sub">${trafficLabel(r.conveyance_type||'')} · ${officeLabel(r.office||'')}</div>
              </div>

              <div class="moneyBox">
                <span class="badge ${badgeType}">${entryType==='income'?'⬆️ Ingreso':'⬇️ Egreso'}</span>
                ${terBadge}
                <div class="amt">${money(r.local_amount_value||0)}</div>
                <div class="tax">IVA: ${money(r.local_tax_value||0)}</div>
              </div>
            </div>

            <div class="cardBtns">
              <button class="btn" data-morebtn="${id}" onclick="window.__toggleMore('${id}')">➕ Ver más</button>
              <button class="btn" onclick="window.__openJson('${uuid}','${safe(order).replaceAll("'","")}')">👁️ JSON</button>
            </div>

            <div class="more" data-more="${id}">
              <div class="line">Concepto: <b>${safe(code || '—')}</b></div>
              <div class="line">Factura/Referencia: <b>${safe(inv || '—')}</b></div>
              <div class="line">Partner: <b>${safe(partner || '—')}</b></div>
              <div class="line">UUID: <b>${safe(uuid || '—')}</b></div>
            </div>
          </div>
        `;
      }).join('');
    }

    const totalPages = Math.max(1, Math.ceil(state.total / state.size));
    $('pageInfo').textContent = `Página ${state.page} de ${totalPages} · ${state.total.toLocaleString('es-MX')} registros`;
    $('btnPrev').disabled = (state.page <= 1);
    $('btnNext').disabled = (state.page >= totalPages);
  }

  async function load(page){
    state.page = page || state.page;
    state.size = Number($('size').value || 25);

    const qs = new URLSearchParams({
      ajax:'1',
      action:'list',
      sort: $('sort').value,
      mode: $('mode').value,
      from: $('from').value,
      to: $('to').value,
      office: $('office').value,
      traffic: $('traffic').value,
      etype: $('etype').value,
      ter: $('ter').value,
      customer: $('customer').value.trim(),
      concept: $('concept').value.trim(),
      order: $('order').value.trim(),
      uuid: $('uuid').value.trim(),
      q: '',
      page: String(state.page),
      size: String(state.size),
      _: String(Date.now())
    });

    const res = await fetch('scope_audit.php?' + qs.toString(), { credentials:'same-origin' });
    const data = await res.json();

    if (!res.ok || !data.success) {
      console.log(data);
      alert(data.error || data.message || 'Error');
      return;
    }

    if (!$('from').value) $('from').value = data.filters.from;
    if (!$('to').value) $('to').value = data.filters.to;

    setRangeHint(data.filters.sort, data.filters.from, data.filters.to);
    setKpis(data.summary);

    state.total = Number(data.summary.total_rows || 0);
    state.rows = Array.isArray(data.rows) ? data.rows : [];
    render();
  }

  async function health(){
    const res = await fetch('scope_audit.php?ajax=1&action=health&_=' + Date.now(), { credentials:'same-origin' });
    const data = await res.json();
    if (!res.ok || !data.success) return;

    const db = data.db || {};
    const maxLM = db.max_last_modified ? String(db.max_last_modified).split('-').reverse().join('/') : '—';
    const maxEff = db.max_effective ? String(db.max_effective).split('-').reverse().join('/') : '—';

    $('chipHealth').textContent = `BD: Actualizaciones hasta ${maxLM} · Financiero hasta ${maxEff} · Entries ${Number(db.total_entries||0).toLocaleString('es-MX')}`;
  }

  window.__openJson = async (uuid, orderNo) => {
    if (!uuid) return;
    $('mTitle').textContent = `JSON · ${orderNo}`;
    $('mJson').textContent = 'Cargando…';
    mb.style.display = 'flex';

    const q = new URLSearchParams({ ajax:'1', action:'order_json', uuid, _: String(Date.now()) });
    const r = await fetch('scope_audit.php?' + q.toString());
    const j = await r.json();
    if (!r.ok || !j.success) { $('mJson').textContent = (j.error||j.message||'Error'); return; }
    $('mJson').textContent = j.raw || '—';
  };

  window.__toggleMore = (id) => toggleMore(id);

  $('btnLoad').addEventListener('click', ()=> load(1));
  $('btnPrev').addEventListener('click', ()=> load(state.page - 1));
  $('btnNext').addEventListener('click', ()=> load(state.page + 1));
  $('q').addEventListener('input', render);

  $('btnClear').addEventListener('click', () => {
    $('customer').value = '';
    $('concept').value = '';
    $('order').value = '';
    $('uuid').value = '';
    $('q').value = '';
    $('from').value = '';
    $('to').value = '';
    $('sort').value = 'updated';
    load(1);
  });

  $('sort').addEventListener('change', () => {
    $('from').value = '';
    $('to').value = '';
    load(1);
  });

  $('btnSync').addEventListener('click', async () => {
    $('btnSync').disabled = true;
    $('btnSync').textContent = '⟳ Sincronizando…';
    try{
      const r = await fetch('scope_sync.php?mode=incremental&size=200&max_pages=10&days=7&_=' + Date.now(), { credentials:'same-origin' });
      const j = await r.json();
      if (!r.ok || !j.success) alert(j.error || j.message || 'Error en sync');
    } finally {
      $('btnSync').disabled = false;
      $('btnSync').textContent = '⟳ Sincronizar';
      await health();
      await load(1);
    }
  });

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('scope_audit.php?pwa=sw').catch(()=>{});
  }

  // boot: DEFAULT A
  $('sort').value = 'updated';
  health();
  load(1);

})();
</script>
</body>
</html>