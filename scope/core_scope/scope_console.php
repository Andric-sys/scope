<?php
declare(strict_types=1);

/**
 * scope_console.php — CORE SCOPE (DIAGNÓSTICO)
 * - UI + endpoint AJAX en el mismo archivo (?ajax=1)
 * - Protegido con autenticación de CGL
 */

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
require __DIR__ . '/scope_api.php';
require __DIR__ . '/scope_upsert.php';

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

/** helpers locales (prefijo sc_) para NO chocar con scope_upsert.php */
function sc_arr_get(array $a, array $path, $default=null) {
  $cur = $a;
  foreach ($path as $k) {
    if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
    $cur = $cur[$k];
  }
  return $cur;
}

function sc_normalize_list($maybeList, string $childKey): array {
  if (!is_array($maybeList)) return [];
  if (!isset($maybeList[$childKey]) || !is_array($maybeList[$childKey])) return [];
  $x = $maybeList[$childKey];
  if (array_keys($x) !== range(0, count($x)-1)) return [$x];
  return $x;
}

function sc_count_block($obj, string $containerKey): int {
  if (!is_array($obj)) return 0;
  $items = sc_normalize_list($obj, $containerKey);
  return count($items);
}

function sc_counts_from_order(array $o): array {
  return [
    'milestones'        => sc_count_block(($o['milestones'] ?? null), 'milestone'),
    'references'        => sc_count_block(($o['references'] ?? null), 'reference'),
    'transportOrders'   => sc_count_block(($o['transportOrders'] ?? null), 'transportOrder'),
    'jobcostingEntries' => sc_count_block(($o['jobcostingEntries'] ?? null), 'jobcostingEntry'),
    'partnerRelatedData'=> is_array($o['partnerRelatedData'] ?? null) ? count($o['partnerRelatedData']) : 0,
  ];
}

function sc_pick_partner_related(array $o): ?array {
  $prd = $o['partnerRelatedData'] ?? null;
  if (!is_array($prd)) return null;
  if (array_keys($prd) === range(0, count($prd)-1)) return $prd[0] ?? null;
  return $prd;
}

function sc_db_snapshot(PDO $pdo, string $uuid): array {
  $o = $pdo->prepare("
    SELECT *
    FROM scope_orders
    WHERE scope_uuid = ?
    ORDER BY id DESC
    LIMIT 1
  ");
  $o->execute([$uuid]);
  $order = $o->fetch(PDO::FETCH_ASSOC) ?: null;

  if (!$order) {
    return [
      'exists' => false,
      'order' => null,
      'counts' => ['milestones'=>0,'references'=>0,'transportOrders'=>0,'jobcostingEntries'=>0,'totals'=>0],
    ];
  }

  $orderId = (int)$order['id'];

  $q = function(string $sql) use ($pdo, $orderId): int {
    $st = $pdo->prepare($sql);
    $st->execute([$orderId]);
    return (int)($st->fetchColumn() ?: 0);
  };

  return [
    'exists' => true,
    'order' => [
      'id' => $orderId,
      'order_number' => $order['order_number'] ?? null,
      'last_modified_utc' => $order['last_modified_utc'] ?? null,
      'conveyance_type' => $order['conveyance_type'] ?? null,
      'usi' => $order['usi'] ?? null,
      'customer_code' => $order['customer_code'] ?? null,
      'customer_name' => $order['customer_name'] ?? null,
      'cost_center_code' => $order['cost_center_code'] ?? null,
      'financial_status' => $order['financial_status'] ?? null,
      'status_to_closed_date' => $order['status_to_closed_date'] ?? null,
      'economic_date' => $order['economic_date'] ?? null,
      'order_date' => $order['order_date'] ?? null,
      'updated_at' => $order['updated_at'] ?? null,
    ],
    'counts' => [
      'milestones' => $q("SELECT COUNT(*) FROM scope_order_milestones WHERE order_id=?"),
      'references' => $q("SELECT COUNT(*) FROM scope_order_references WHERE order_id=?"),
      'transportOrders' => $q("SELECT COUNT(*) FROM scope_transport_orders WHERE order_id=?"),
      'jobcostingEntries' => $q("SELECT COUNT(*) FROM scope_jobcosting_entries WHERE order_id=?"),
      'totals' => $q("SELECT COUNT(*) FROM scope_jobcosting_totals WHERE order_id=?"),
    ],
    'jobcosting_dates' => [
      'invoice_min' => (string)($pdo->query("SELECT MIN(invoice_date) AS d FROM scope_jobcosting_entries WHERE order_id=".$orderId)->fetch()['d'] ?? ''),
      'invoice_max' => (string)($pdo->query("SELECT MAX(invoice_date) AS d FROM scope_jobcosting_entries WHERE order_id=".$orderId)->fetch()['d'] ?? ''),
      'economic_min' => (string)($pdo->query("SELECT MIN(economic_date) AS d FROM scope_jobcosting_entries WHERE order_id=".$orderId)->fetch()['d'] ?? ''),
      'economic_max' => (string)($pdo->query("SELECT MAX(economic_date) AS d FROM scope_jobcosting_entries WHERE order_id=".$orderId)->fetch()['d'] ?? ''),
    ],
  ];
}

/* =========================================================
   AJAX ENDPOINT
========================================================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  $action = (string)($_GET['action'] ?? '');

  try {
    if ($action === 'ping') {
      $t0 = microtime(true);

      $from = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->sub(new DateInterval('P365D'));
      $list = scope_list_orders([
        'page' => 0,
        'size' => 1,
        'orderBy' => '-lastModified',
        'lastModified' => scope_lastModified_filter($from, 'ge', 'UTC'),
      ]);

      $ms = (int)round((microtime(true)-$t0)*1000);

      $orders = $list['orders'] ?? $list['data'] ?? $list['content'] ?? [];
      if (!is_array($orders)) $orders = [];

      $sample = $orders[0] ?? null;

      json_out(200, [
        'success' => true,
        'ms' => $ms,
        'tenant' => [
          'org' => (string)($cfg['scope']['organizationCode'] ?? ''),
          'le'  => (string)($cfg['scope']['legalEntityCode'] ?? ''),
          'br'  => (string)($cfg['scope']['branchCode'] ?? ''),
        ],
        'expand' => (string)($cfg['scope']['expand'] ?? ''),
        'sample' => is_array($sample) ? [
          'identifier' => $sample['identifier'] ?? null,
          'number' => $sample['number'] ?? null,
          'lastModified' => $sample['lastModified'] ?? null,
          'conveyanceType' => $sample['conveyanceType'] ?? null,
        ] : null,
        'server_time' => date('Y-m-d H:i:s'),
      ]);
    }

    if ($action === 'fetch') {
      $uuid = trim((string)($_GET['uuid'] ?? ''));
      if ($uuid === '') json_out(400, ['success'=>false,'message'=>'UUID requerido.']);

      $t0 = microtime(true);
      $order = scope_get_order($uuid);
      $ms = (int)round((microtime(true)-$t0)*1000);

      $prd0 = sc_pick_partner_related($order);
      $totLoc = is_array($prd0) ? ($prd0['jobcostingTotalsLocalCurrency'] ?? null) : null;

      $counts = sc_counts_from_order($order);

      $summary = [
        'identifier' => $order['identifier'] ?? null,
        'number' => $order['number'] ?? null,
        'lastModified' => $order['lastModified'] ?? null,
        'module' => $order['module'] ?? null,
        'conveyanceType' => $order['conveyanceType'] ?? null,
        'usi' => $order['usi'] ?? null,
        'orderDate' => $order['orderDate'] ?? null,
        'economicDate' => $order['economicDate'] ?? null,
        'customer_code' => sc_arr_get($order, ['customer','partner','code'], null),
        'customer_name' => sc_arr_get($order, ['customer','partner','name'], null),
        'costCenter' => is_array($prd0) ? sc_arr_get($prd0, ['costCenter','code'], null) : null,
        'financialStatus' => is_array($prd0) ? ($prd0['financialStatus'] ?? null) : null,
        'statusToClosedDate' => is_array($prd0) ? ($prd0['statusToClosedDate'] ?? null) : null,
        'localTotals' => is_array($totLoc) ? [
          'totalIncome' => sc_arr_get($totLoc, ['totalIncome','value'], null),
          'totalCost' => sc_arr_get($totLoc, ['totalCost','value'], null),
          'profit' => sc_arr_get($totLoc, ['profit','value'], null),
          'grossMargin' => $totLoc['grossMargin'] ?? null,
          'currency' => sc_arr_get($totLoc, ['totalIncome','currency'], null),
        ] : null,
      ];

      $raw = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $rawHead = is_string($raw) ? mb_substr($raw, 0, 60000) : null;

      json_out(200, [
        'success' => true,
        'ms' => $ms,
        'summary' => $summary,
        'counts' => $counts,
        'raw_head' => $rawHead,
      ]);
    }

    if ($action === 'compare') {
      $uuid = trim((string)($_GET['uuid'] ?? ''));
      if ($uuid === '') json_out(400, ['success'=>false,'message'=>'UUID requerido.']);

      $t0 = microtime(true);
      $order = scope_get_order($uuid);
      $ms = (int)round((microtime(true)-$t0)*1000);

      $prd0 = sc_pick_partner_related($order);
      $totLoc = is_array($prd0) ? ($prd0['jobcostingTotalsLocalCurrency'] ?? null) : null;

      $api = [
        'number' => $order['number'] ?? null,
        'lastModified' => $order['lastModified'] ?? null,
        'conveyanceType' => $order['conveyanceType'] ?? null,
        'usi' => $order['usi'] ?? null,
        'customer_code' => sc_arr_get($order, ['customer','partner','code'], null),
        'customer_name' => sc_arr_get($order, ['customer','partner','name'], null),
        'cost_center_code' => is_array($prd0) ? sc_arr_get($prd0, ['costCenter','code'], null) : null,
        'financial_status' => is_array($prd0) ? ($prd0['financialStatus'] ?? null) : null,
        'status_to_closed_date' => is_array($prd0) ? ($prd0['statusToClosedDate'] ?? null) : null,
        'economic_date' => $order['economicDate'] ?? null,
        'order_date' => $order['orderDate'] ?? null,
        'totals_local' => is_array($totLoc) ? [
          'totalIncome' => sc_arr_get($totLoc, ['totalIncome','value'], null),
          'totalCost' => sc_arr_get($totLoc, ['totalCost','value'], null),
          'profit' => sc_arr_get($totLoc, ['profit','value'], null),
          'grossMargin' => $totLoc['grossMargin'] ?? null,
        ] : null,
        'counts' => sc_counts_from_order($order),
      ];

      $db = sc_db_snapshot($pdo, $uuid);

      json_out(200, [
        'success' => true,
        'ms' => $ms,
        'api' => $api,
        'db' => $db,
      ]);
    }

    if ($action === 'upsert') {
      $uuid = trim((string)($_GET['uuid'] ?? ''));
      if ($uuid === '') json_out(400, ['success'=>false,'message'=>'UUID requerido.']);

      $t0 = microtime(true);
      $order = scope_get_order($uuid);
      $fetchMs = (int)round((microtime(true)-$t0)*1000);

      $t1 = microtime(true);
      $r = scope_upsert_order($pdo, $order);
      $upsertMs = (int)round((microtime(true)-$t1)*1000);

      $db = sc_db_snapshot($pdo, $uuid);

      json_out(200, [
        'success' => true,
        'fetch_ms' => $fetchMs,
        'upsert_ms' => $upsertMs,
        'result' => $r,
        'db' => $db,
      ]);
    }

    json_out(400, ['success'=>false,'message'=>'Acción inválida.']);
  } catch (Throwable $e) {
    json_out(500, [
      'success' => false,
      'message' => 'Error',
      'error' => $e->getMessage(),
    ]);
  }
}

/* =========================================================
   UI
========================================================= */
$cssVars = core_brand_css_vars();
$tenantLbl = h(($cfg['scope']['organizationCode'] ?? '').' / '.($cfg['scope']['legalEntityCode'] ?? '').' / '.($cfg['scope']['branchCode'] ?? ''));
$expandLbl = h((string)($cfg['scope']['expand'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CORE SCOPE · Scope Console</title>
  <style>
    <?= $cssVars ?>
    *{ box-sizing:border-box; }
    body{ margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background: var(--bg); color: var(--text); }
    .wrap{ max-width: 1500px; margin:0 auto; padding:18px 14px 26px; }
    .topbar{
      background: var(--card); border: 1px solid var(--border); border-radius: 18px;
      box-shadow: 0 10px 26px rgba(2,6,23,.06);
      padding: 14px 16px;
      display:flex; gap:14px; flex-wrap:wrap; align-items:center; justify-content:space-between;
    }
    .brand{ display:flex; align-items:center; gap:12px; }
    .mark{
      width:44px;height:44px;border-radius:16px;
      background: linear-gradient(135deg, var(--core-blue), var(--core-navy));
      box-shadow: 0 14px 30px rgba(0,15,159,.18);
      position:relative; overflow:hidden;
    }
    .mark:after{ content:""; position:absolute; inset:auto -22px -22px auto; width:46px;height:46px;border-radius:18px; background: rgba(156,193,247,.55); transform:rotate(18deg); }
    h1{ margin:0; font-size: 1.08rem; letter-spacing:.2px; }
    .muted{ color: var(--muted); font-weight:800; font-size:.9rem; }

    .grid{ margin-top: 14px; display:grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
    .card{ background: var(--card); border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 10px 26px rgba(2,6,23,.06); padding: 14px 14px; }
    .full{ grid-column: span 12; }
    .third{ grid-column: span 4; }

    .btn{
      border:1px solid rgba(15,23,42,.12);
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 950;
      cursor:pointer;
      display:inline-flex; align-items:center; gap:10px;
      background: #fff;
      color: var(--core-navy);
    }
    .btn-primary{ background: var(--core-blue); color:#fff; border-color: rgba(1,113,226,.40); }

    .input{ border: 1px solid var(--border); border-radius: 14px; padding: 10px 12px; font-weight: 850; width: 100%; outline:none; }
    .row{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top: 10px; }

    .kv{ display:grid; grid-template-columns: 240px 1fr; gap: 8px 12px; font-size: .93rem; margin-top: 8px; }
    .kv b{ color: var(--muted); font-weight: 950; }

    .pill{ display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; border:1px solid rgba(15,23,42,.12); background: rgba(2,6,23,.03); font-weight: 950; font-size:.82rem; color: var(--core-navy); }

    pre{ white-space: pre-wrap; word-break: break-word; background: #0b1220; color: #e5e7eb; border-radius: 14px; padding: 12px; border:1px solid rgba(255,255,255,.08); max-height: 52vh; overflow:auto; font-size: .82rem; }

    .status{ font-weight: 950; }
    .ok{ color:#065f46; }
    .bad{ color:#b91c1c; }

    @media (max-width: 1100px){
      .third { grid-column: span 12; }
      .kv{ grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <div class="brand">
      <div class="mark"></div>
      <div>
        <h1>CORE SCOPE · Scope Console</h1>
        <div class="muted">Diagnóstico de comunicación, payload, conteos y mapeo a BD.</div>
      </div>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <span class="pill">Tenant: <span class="muted"><?= $tenantLbl ?></span></span>
      <span class="pill">Expand: <span class="muted"><?= $expandLbl ?></span></span>
      <a class="btn" href="dashboard_pro.php">↩ Dashboard</a>
    </div>
  </div>

  <div class="grid">

    <div class="card third">
      <div style="font-weight:1000;">1) Ping Scope</div>
      <div class="muted">Valida tenant + expand + tiempo de respuesta.</div>
      <div class="row">
        <button class="btn btn-primary" id="btnPing">Ping</button>
      </div>
      <div id="pingOut" class="kv"></div>
    </div>

    <div class="card third">
      <div style="font-weight:1000;">2) Fetch por UUID</div>
      <div class="muted">Trae la orden completa desde Scope.</div>
      <div class="row">
        <input class="input" id="uuidFetch" value="03a3a6c1-1e11-4eb9-b91b-ec1f6d9250bf">
      </div>
      <div class="row">
        <button class="btn btn-primary" id="btnFetch">Fetch</button>
      </div>
      <div id="fetchOut" class="kv"></div>
    </div>

    <div class="card third">
      <div style="font-weight:1000;">3) Compare API vs BD</div>
      <div class="muted">Verifica si tu mapping llenó bien las tablas.</div>
      <div class="row">
        <input class="input" id="uuidCompare" value="03a3a6c1-1e11-4eb9-b91b-ec1f6d9250bf">
      </div>
      <div class="row">
        <button class="btn btn-primary" id="btnCompare">Compare</button>
        <button class="btn" id="btnUpsert">Upsert</button>
      </div>
      <div id="compareOut" class="kv"></div>
    </div>

    <div class="card full">
      <div class="row" style="align-items:flex-end; justify-content:space-between;">
        <div>
          <div style="font-weight:1000;">JSON crudo (recortado)</div>
          <div class="muted">Aquí vemos exactamente lo que Scope manda (para ajustar mapping).</div>
        </div>
        <span class="pill">Estado: <span id="status" class="status muted">—</span></span>
      </div>
      <pre id="raw">—</pre>
    </div>

  </div>
</div>

<script>
(() => {
  const $ = (id) => document.getElementById(id);

  function setStatus(txt, ok=true){
    const el = $('status');
    el.textContent = txt;
    el.className = 'status ' + (ok ? 'ok' : 'bad');
  }

  function kv(el, obj){
    if (!obj) { el.innerHTML = ''; return; }
    const rows = [];
    for (const [k,v] of Object.entries(obj)) {
      rows.push(`<b>${escapeHtml(k)}</b><div>${escapeHtml(String(v ?? '—'))}</div>`);
    }
    el.innerHTML = rows.join('');
  }

  function escapeHtml(s){
    return String(s ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  async function call(action, params){
    const qs = new URLSearchParams({ ajax:'1', action, ...params, _: String(Date.now()) });
    const res = await fetch('<?= h(basename(__FILE__)) ?>?' + qs.toString(), { credentials:'same-origin' });
    const data = await res.json();
    if (!res.ok || !data.success) throw data;
    return data;
  }

  $('btnPing').addEventListener('click', async () => {
    setStatus('Pinging…', true);
    try{
      const data = await call('ping', {});
      kv($('pingOut'), {
        'ms': data.ms,
        'tenant': `${data.tenant.org}/${data.tenant.le}/${data.tenant.br}`,
        'expand': data.expand,
        'sample.number': data.sample?.number ?? '—',
        'sample.lastModified': data.sample?.lastModified ?? '—',
        'sample.conveyanceType': data.sample?.conveyanceType ?? '—',
        'server_time': data.server_time ?? '—',
      });
      setStatus('OK (Ping)', true);
    }catch(e){
      console.log(e);
      setStatus(e.error || e.message || 'Error', false);
    }
  });

  $('btnFetch').addEventListener('click', async () => {
    const uuid = $('uuidFetch').value.trim();
    if (!uuid) return;
    setStatus('Fetching…', true);
    try{
      const data = await call('fetch', { uuid });
      kv($('fetchOut'), {
        'ms': data.ms,
        'number': data.summary?.number,
        'lastModified': data.summary?.lastModified,
        'conveyanceType': data.summary?.conveyanceType,
        'usi': data.summary?.usi,
        'economicDate': data.summary?.economicDate,
        'customer': `${data.summary?.customer_code ?? ''} · ${data.summary?.customer_name ?? ''}`,
        'costCenter': data.summary?.costCenter,
        'financialStatus': data.summary?.financialStatus,
        'statusToClosedDate': data.summary?.statusToClosedDate,
        'counts.milestones': data.counts?.milestones,
        'counts.references': data.counts?.references,
        'counts.transportOrders': data.counts?.transportOrders,
        'counts.jobcostingEntries': data.counts?.jobcostingEntries,
      });

      $('raw').textContent = data.raw_head || '—';
      setStatus('OK (Fetch)', true);
    }catch(e){
      console.log(e);
      setStatus(e.error || e.message || 'Error', false);
    }
  });

  $('btnCompare').addEventListener('click', async () => {
    const uuid = $('uuidCompare').value.trim();
    if (!uuid) return;
    setStatus('Comparing…', true);
    try{
      const data = await call('compare', { uuid });
      const api = data.api || {};
      const db = data.db || {};

      kv($('compareOut'), {
        'ms': data.ms,
        'API.number': api.number ?? '—',
        'BD.exists': db.exists ? 'YES' : 'NO',
        'API.conveyanceType': api.conveyanceType ?? '—',
        'BD.conveyance_type': db.order?.conveyance_type ?? '—',
        'API.cost_center': api.cost_center_code ?? '—',
        'BD.cost_center_code': db.order?.cost_center_code ?? '—',
        'API.financial_status': api.financial_status ?? '—',
        'BD.financial_status': db.order?.financial_status ?? '—',
        'API.count.jobcostingEntries': api.counts?.jobcostingEntries ?? 0,
        'BD.count.jobcostingEntries': db.counts?.jobcostingEntries ?? 0,
        'BD.totals_rows': db.counts?.totals ?? 0,
        'BD.invoice_range': `${db.jobcosting_dates?.invoice_min ?? ''} → ${db.jobcosting_dates?.invoice_max ?? ''}`,
        'BD.economic_range': `${db.jobcosting_dates?.economic_min ?? ''} → ${db.jobcosting_dates?.economic_max ?? ''}`,
      });

      setStatus('OK (Compare)', true);
    }catch(e){
      console.log(e);
      setStatus(e.error || e.message || 'Error', false);
    }
  });

  $('btnUpsert').addEventListener('click', async () => {
    const uuid = $('uuidCompare').value.trim();
    if (!uuid) return;
    setStatus('Upserting…', true);
    try{
      const data = await call('upsert', { uuid });

      kv($('compareOut'), {
        'fetch_ms': data.fetch_ms,
        'upsert_ms': data.upsert_ms,
        'result.order_id': data.result?.order_id ?? '—',
        'skipped_children': data.result?.skipped_children ? 'YES' : 'NO',
        'upserted_milestones': data.result?.upserted_milestones ?? 0,
        'upserted_references': data.result?.upserted_references ?? 0,
        'upserted_transport_orders': data.result?.upserted_transport_orders ?? 0,
        'upserted_jobcosting_entries': data.result?.upserted_jobcosting_entries ?? 0,
        'upserted_jobcosting_totals': data.result?.upserted_jobcosting_totals ?? 0,
        'DB.exists': data.db?.exists ? 'YES' : 'NO',
        'DB.count.entries': data.db?.counts?.jobcostingEntries ?? 0,
        'DB.count.totals': data.db?.counts?.totals ?? 0,
      });

      setStatus('OK (Upsert)', true);
    }catch(e){
      console.log(e);
      setStatus(e.error || e.message || 'Error', false);
    }
  });

})();
</script>
</body>
</html>