<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
require __DIR__ . '/scope_api.php';

date_default_timezone_set('America/Mexico_City');

$pdo = db();
$cfg = require __DIR__ . '/config.php';
$cssVars = core_brand_css_vars();

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function json_out(int $code, array $payload): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function fmt_mx_date(?string $ymd): string {
  if (!$ymd) return '—';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return $ymd;
  [$y,$m,$d] = explode('-', $ymd);
  return "{$d}/{$m}/{$y}";
}
function fmt_mx_dt(?string $dt): string {
  if (!$dt) return '—';
  try {
    $d = new DateTimeImmutable($dt);
    $mx = $d->setTimezone(new DateTimeZone('America/Mexico_City'));
    return $mx->format('d/m/Y H:i');
  } catch(Throwable $e) {
    return $dt;
  }
}

$scope = $cfg['scope'] ?? [];
$org = (string)($scope['organizationCode'] ?? '');
$le  = (string)($scope['legalEntityCode'] ?? '');
$br  = (string)($scope['branchCode'] ?? '');
$tenantLbl = trim(($org!==''?$org:'—').' / '.($le!==''?$le:'—').' / '.($br!==''?$br:'—'));

$lockName = 'core_scope_sync_' . $org . '_' . $le . '_' . $br;

/* =========================================================
   AJAX
========================================================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  $action = (string)($_GET['action'] ?? 'status');

  try {
    if ($action === 'status') {
      // ¿hay actualización en proceso?
      $isFree = null;
      try {
        $st = $pdo->prepare("SELECT IS_FREE_LOCK(?) AS v");
        $st->execute([$lockName]);
        $v = $st->fetchColumn();
        $isFree = ($v === null) ? null : (int)$v; // 1 libre, 0 ocupado
      } catch (Throwable $ignored) {}

      // Resumen BD
      $db = $pdo->query("
        SELECT
          (SELECT COUNT(*) FROM scope_orders) AS orders_total,
          (SELECT COUNT(*) FROM scope_jobcosting_entries) AS entries_total,
          (SELECT DATE(MAX(last_modified_utc)) FROM scope_orders) AS max_last_modified,
          (SELECT DATE(MAX(COALESCE(invoice_date, economic_date))) FROM scope_jobcosting_entries) AS max_effective
      ")->fetch(PDO::FETCH_ASSOC) ?: [];

      // Último registro guardado
      $last = $pdo->query("
        SELECT order_number, customer_name, last_modified_utc, updated_at
        FROM scope_orders
        WHERE last_modified_utc IS NOT NULL
        ORDER BY last_modified_utc DESC
        LIMIT 1
      ")->fetch(PDO::FETCH_ASSOC) ?: null;

      // Última actualización (último run terminado)
      $run = $pdo->query("
        SELECT run_uuid, started_at, finished_at, fetched_count, upserted_orders,
               upserted_jobcosting_entries, http_status, mensaje
        FROM scope_sync_runs
        WHERE finished_at IS NOT NULL
        ORDER BY finished_at DESC
        LIMIT 1
      ")->fetch(PDO::FETCH_ASSOC) ?: null;

      // Run activo (para progreso en vivo)
      $runActive = $pdo->query("
        SELECT run_uuid, started_at, fetched_count, upserted_orders, upserted_jobcosting_entries, mensaje
        FROM scope_sync_runs
        WHERE finished_at IS NULL
        ORDER BY started_at DESC
        LIMIT 1
      ")->fetch(PDO::FETCH_ASSOC) ?: null;

      json_out(200, [
        'success' => true,
        'tenant' => $tenantLbl,
        'updating' => ($isFree === 0),
        'db' => [
          'orders_total' => (int)($db['orders_total'] ?? 0),
          'entries_total' => (int)($db['entries_total'] ?? 0),
          'max_last_modified' => $db['max_last_modified'] ?? null,
          'max_effective' => $db['max_effective'] ?? null,
        ],
        'last_record' => $last ? [
          'order_number' => $last['order_number'] ?? null,
          'customer_name' => $last['customer_name'] ?? null,
          'last_modified_utc' => $last['last_modified_utc'] ?? null,
          'updated_at' => $last['updated_at'] ?? null,
        ] : null,
        'last_run' => $run ?: null,
        'active_run' => $runActive ?: null,
        'server_time' => date('Y-m-d H:i:s'),
      ]);
    }

    if ($action === 'ping') {
      $t0 = microtime(true);
      $ok = false;
      $ms = null;
      $label = 'Sin conexión';

      try {
        scope_list_orders(['page'=>0,'size'=>1,'orderBy'=>'-lastModified']);
        $ok = true;
        $ms = (int)round((microtime(true)-$t0)*1000);
        if ($ms <= 2500) $label = 'Conectado';
        elseif ($ms <= 8000) $label = 'Conectado (lento)';
        else $label = 'Conectado (muy lento)';
      } catch (Throwable $e) {
        $ms = (int)round((microtime(true)-$t0)*1000);
        $ok = false;
        $label = 'Sin conexión';
      }

      json_out(200, ['success'=>true,'connected'=>$ok,'label'=>$label,'ms'=>$ms,'server_time'=>date('Y-m-d H:i:s')]);
    }

    json_out(400, ['success'=>false,'message'=>'Acción inválida.']);
  } catch (Throwable $e) {
    json_out(500, ['success'=>false,'message'=>'Error','error'=>$e->getMessage()]);
  }
}

/* =========================================================
   UI
========================================================= */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0171E2">
  <title>Inicio</title>
  <style>
    <?= $cssVars ?>
    *{box-sizing:border-box}
    :root{
      --glass:rgba(255,255,255,.80);
      --shadow:0 18px 48px rgba(2,6,23,.10);
      --shadow2:0 10px 22px rgba(2,6,23,.08);
      --ok:#16a34a; --warn:#f59e0b; --bad:#ef4444;
    }
    body{
      margin:0;
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(156,193,247,.35), transparent 60%),
        radial-gradient(900px 500px at 110% 10%, rgba(1,113,226,.18), transparent 55%),
        var(--bg);
      color:var(--text);
    }
    .wrap{max-width:1400px;margin:0 auto;padding:14px 12px 28px}
    .appbar{
      position:sticky;top:10px;z-index:50;
      backdrop-filter:blur(10px);
      background:var(--glass);
      border:1px solid rgba(15,23,42,.08);
      border-radius:18px;
      box-shadow:var(--shadow);
      padding:12px 14px;
      display:flex;gap:12px;align-items:center;justify-content:space-between;
      margin-bottom:12px;
    }
    .brand{display:flex;align-items:center;gap:12px}
    .mark{
      width:46px;height:46px;border-radius:16px;
      background:linear-gradient(135deg, var(--core-blue), var(--core-navy));
      box-shadow:0 16px 34px rgba(0,15,159,.18);
      position:relative;overflow:hidden;
    }
    .mark:after{
      content:"";position:absolute;inset:auto -22px -22px auto;
      width:48px;height:48px;border-radius:18px;background:rgba(156,193,247,.60);transform:rotate(18deg);
    }
    h1{margin:0;font-size:1.08rem;letter-spacing:.2px}
    .muted{color:var(--muted);font-weight:800;font-size:.88rem}

    .chips{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:flex-end}
    .chip{
      display:inline-flex;align-items:center;gap:8px;
      padding:7px 10px;border-radius:999px;
      border:1px solid rgba(15,23,42,.12);
      background:rgba(255,255,255,.88);
      font-weight:950;font-size:.80rem;color:var(--core-navy);white-space:nowrap;
    }
    .dot{width:10px;height:10px;border-radius:999px;background:#94a3b8}
    .dot.ok{background:var(--ok)} .dot.warn{background:var(--warn)} .dot.bad{background:var(--bad)}

    .grid{display:grid;grid-template-columns:1.1fr .9fr;gap:12px;align-items:start}
    .panel{
      background:rgba(255,255,255,.86);
      border:1px solid rgba(15,23,42,.10);
      border-radius:18px;
      box-shadow:var(--shadow2);
      padding:12px;
      overflow:hidden;
    }
    .panelHead{
      display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
      padding-bottom:10px;border-bottom:1px solid rgba(15,23,42,.08);margin-bottom:10px
    }
    .title{font-weight:1100;color:var(--core-navy)}
    .subtitle{color:var(--muted);font-weight:850;font-size:.88rem;margin-top:4px;line-height:1.25}

    .cards{display:grid;grid-template-columns:1fr;gap:10px}
    .card{
      border:1px solid rgba(15,23,42,.10);
      border-radius:18px;background:#fff;padding:12px;
      box-shadow:0 10px 22px rgba(2,6,23,.06);
      display:flex;gap:12px;align-items:flex-start;justify-content:space-between;
    }
    .cardL{min-width:0}
    .cardT{font-weight:1100;color:var(--core-navy)}
    .cardS{color:var(--muted);font-weight:850;font-size:.86rem;margin-top:4px;line-height:1.25}
    .btn{
      border:1px solid rgba(15,23,42,.12);
      padding:10px 12px;border-radius:999px;
      font-weight:950;cursor:pointer;background:#fff;color:var(--core-navy);
      text-decoration:none;display:inline-flex;align-items:center;gap:10px;white-space:nowrap;
    }
    .btn-primary{background:var(--core-blue);color:#fff;border-color:rgba(1,113,226,.40)}
    .btn:disabled{opacity:.6;cursor:not-allowed}

    .kv{
      margin-top:10px;display:grid;grid-template-columns:240px 1fr;
      gap:8px 12px;font-size:.92rem
    }
    .kv b{color:var(--muted);font-weight:950}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}

    /* Overlay */
    .overlay{position:fixed;inset:0;background:rgba(2,6,23,.55);display:none;align-items:center;justify-content:center;padding:18px;z-index:9999}
    .modal{
      width:min(760px,96vw);
      background:rgba(255,255,255,.92);
      border:1px solid rgba(255,255,255,.18);
      border-radius:18px;
      box-shadow:0 30px 80px rgba(2,6,23,.35);
      overflow:hidden;
    }
    .modalHead{padding:12px 14px;border-bottom:1px solid rgba(15,23,42,.10);display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(255,255,255,.92)}
    .modalTitle{font-weight:1100;color:var(--core-navy)}
    .modalBody{padding:12px 14px 14px}
    .progressRow{
      display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;
      padding:10px 12px;border:1px solid rgba(15,23,42,.10);border-radius:16px;background:#fff;
    }
    .spin{
      width:18px;height:18px;border-radius:999px;
      border:3px solid rgba(1,113,226,.18);
      border-top-color:rgba(1,113,226,1);
      animation:spin .9s linear infinite;
    }
    @keyframes spin{to{transform:rotate(360deg)}}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,.10);font-weight:950;font-size:.82rem;background:rgba(2,6,23,.03);color:var(--core-navy)}
    .pill .dot{background:var(--warn)}
    .pill.ok .dot{background:var(--ok)}
    .pill.bad .dot{background:var(--bad)}
    pre{
      margin:12px 0 0;white-space:pre-wrap;word-break:break-word;
      background:#0b1220;color:#e5e7eb;border-radius:14px;padding:12px;
      border:1px solid rgba(255,255,255,.08);max-height:45vh;overflow:auto;font-size:.82rem;
    }

    @media (max-width:1100px){
      .grid{grid-template-columns:1fr}
      .kv{grid-template-columns:1fr}
      .card{flex-direction:column;align-items:stretch}
      .btn{justify-content:center}
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="appbar">
    <div class="brand">
      <div class="mark"></div>
      <div>
        <h1>Inicio</h1>
        <div class="muted">Consulta y actualización de información</div>
      </div>
    </div>
    <div class="chips">
      <span class="chip"><span class="dot" id="dotConn"></span><span id="chipConn">Conexión: —</span></span>
      <span class="chip"><span class="dot" id="dotUpd"></span><span id="chipUpd">Actualización: —</span></span>
      <span class="chip">Cliente: <span class="muted"><?= h($tenantLbl) ?></span></span>
      <span class="chip" id="chipTime">—</span>
    </div>
  </div>

  <div class="grid">
    <div class="panel">
      <div class="panelHead">
        <div>
          <div class="title">Panel principal</div>
          <div class="subtitle">Consulta la información y actualízala cuando lo necesites.</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <button class="btn btn-primary" id="btnSync">⟳ Actualizar datos</button>
          <button class="btn" id="btnRefresh">↻ Actualizar pantalla</button>
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <div class="cardL">
            <div class="cardT">📋 Reportes</div>
            <div class="cardS">Ver registros guardados, filtrar y revisar detalle.</div>
          </div>
          <a class="btn btn-primary" href="scope_audit.php">Abrir</a>
        </div>
        <div class="card">
          <div class="cardL">
            <div class="cardT">📊 Indicadores</div>
            <div class="cardS">Vista general de resultados.</div>
          </div>
          <a class="btn" href="dashboard_pro.php">Abrir</a>
        </div>
      </div>

      <div class="kv" style="margin-top:14px">
        <b>Última actualización realizada</b><div id="kvLastRun">—</div>
        <b>Registros guardados</b><div id="kvCounts">—</div>
        <b>Último registro guardado</b><div id="kvNewest">—</div>
      </div>
    </div>

    <div class="panel">
      <div class="panelHead">
        <div>
          <div class="title">Estado</div>
          <div class="subtitle">Conexión y resumen, en idioma claro.</div>
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <div class="cardL">
            <div class="cardT">🌐 Conexión</div>
            <div class="cardS">Estado y tiempo de respuesta.</div>
          </div>
          <button class="btn" id="btnPing">Probar</button>
        </div>
        <div class="card">
          <div class="cardL">
            <div class="cardT">📅 Datos guardados hasta</div>
            <div class="cardS">Último día con cambios y último día financiero.</div>
          </div>
          <button class="btn" id="btnOpenAudit">Ver en reportes</button>
        </div>
      </div>

      <div class="kv" style="margin-top:14px">
        <b>Último día con cambios</b><div id="kvMaxLM">—</div>
        <b>Último día financiero</b><div id="kvMaxEff">—</div>
        <b>Servidor</b><div class="mono" id="kvServerTime">—</div>
      </div>
    </div>
  </div>
</div>

<div class="overlay" id="ov">
  <div class="modal">
    <div class="modalHead">
      <div class="modalTitle" id="ovTitle">Actualizando datos</div>
      <button class="btn" id="ovClose" style="padding:8px 12px;">Cerrar</button>
    </div>
    <div class="modalBody">
      <div class="progressRow">
        <div style="display:flex;gap:10px;align-items:center;">
          <div class="spin" id="ovSpin"></div>
          <div>
            <div style="font-weight:1100;color:var(--core-navy);" id="ovStep">Iniciando…</div>
            <div class="muted" id="ovTime">Tiempo: 0s</div>
          </div>
        </div>
        <div id="ovBadge" class="pill" style="display:none;"><span class="dot"></span><span id="ovBadgeTxt">—</span></div>
      </div>
      <div class="muted" id="ovProg" style="margin-top:10px;font-weight:850;">—</div>
      <pre id="ovOut" style="display:none;">—</pre>
    </div>
  </div>
</div>

<script>
(() => {
  const $ = (id) => document.getElementById(id);

  function setDot(id, state){ // ok|warn|bad|''
    $(id).className = 'dot' + (state ? ' ' + state : '');
  }
  function fmtDateMX(ymd){
    if(!ymd) return '—';
    const s = String(ymd);
    if(!/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    const [y,m,d] = s.split('-');
    return `${d}/${m}/${y}`;
  }

  async function getJson(url){
    const res = await fetch(url, { credentials:'same-origin' });
    const txt = await res.text();
    try { return { ok:true, res, json: JSON.parse(txt), raw: txt }; }
    catch(_) { return { ok:false, res, raw: txt }; }
  }

  async function loadStatus(){
    const r = await getJson('scope_menu.php?ajax=1&action=status&_=' + Date.now());
    if(!r.ok || !r.json.success) return;

    const j = r.json;
    $('chipTime').textContent = j.server_time || '—';
    $('kvServerTime').textContent = j.server_time || '—';

    if (j.updating){
      $('chipUpd').textContent = 'Actualización: en proceso';
      setDot('dotUpd','warn');
    } else {
      $('chipUpd').textContent = 'Actualización: disponible';
      setDot('dotUpd','ok');
    }

    const orders = Number(j.db.orders_total||0).toLocaleString('es-MX');
    const entries = Number(j.db.entries_total||0).toLocaleString('es-MX');
    $('kvCounts').textContent = `${orders} registros · ${entries} movimientos`;

    $('kvMaxLM').textContent = fmtDateMX(j.db.max_last_modified);
    $('kvMaxEff').textContent = fmtDateMX(j.db.max_effective);

    // Último registro guardado
    if (j.last_record){
      const lr = j.last_record;
      const o = lr.order_number || '—';
      const c = lr.customer_name || '—';
      const when = lr.last_modified_utc || lr.updated_at || '—';
      $('kvNewest').textContent = `${o} · ${c} · ${when}`;
    } else {
      $('kvNewest').textContent = '—';
    }

    // Última actualización “legible”
    if (j.last_run){
      const ok = Number(j.last_run.http_status || 0) === 200;
      const when = j.last_run.finished_at || j.last_run.started_at || '—';
      const up = Number(j.last_run.upserted_orders||0);
      const fetch = Number(j.last_run.fetched_count||0);
      $('kvLastRun').textContent = `${ok ? 'Completada' : 'Con incidencias'} · ${when} · Actualizados: ${up} · Revisados: ${fetch}`;
    } else {
      $('kvLastRun').textContent = '—';
    }

    // Si hay run activo, el overlay lo usa para progreso
    return j;
  }

  async function ping(){
    $('chipConn').textContent = 'Conexión: probando…';
    setDot('dotConn','warn');
    const r = await getJson('scope_menu.php?ajax=1&action=ping&_=' + Date.now());
    if(!r.ok || !r.json.success){
      $('chipConn').textContent = 'Conexión: sin conexión';
      setDot('dotConn','bad');
      return;
    }
    const j = r.json;
    $('chipConn').textContent = `Conexión: ${j.label} · ${j.ms} ms`;
    setDot('dotConn', j.connected ? (j.ms>8000?'warn':'ok') : 'bad');
  }

  // Overlay
  let tStart = 0, timer=null, poll=null;

  function ovOpen(){
    $('ov').style.display='flex';
    $('ovOut').style.display='none';
    $('ovOut').textContent='—';
    $('ovSpin').style.display='block';
    $('ovStep').textContent='Iniciando…';
    $('ovProg').textContent='Preparando actualización…';
    $('ovBadge').style.display='none';
    tStart = Date.now();
    clearInterval(timer);
    timer = setInterval(() => {
      const s = Math.floor((Date.now()-tStart)/1000);
      $('ovTime').textContent = `Tiempo: ${s}s`;
    }, 250);
  }
  function ovClose(){
    $('ov').style.display='none';
    clearInterval(timer); timer=null;
    clearInterval(poll); poll=null;
  }
  function ovBadge(kind, text){
    $('ovBadge').style.display='inline-flex';
    $('ovBadge').className = 'pill' + (kind==='ok'?' ok':kind==='bad'?' bad':'');
    $('ovBadgeTxt').textContent=text;
    const dot = $('ovBadge').querySelector('.dot');
    if(dot){
      dot.className='dot ' + (kind==='ok'?'ok':kind==='bad'?'bad':'warn');
    }
  }

  async function pollProgress(){
    const st = await getJson('scope_menu.php?ajax=1&action=status&_=' + Date.now());
    if(!st.ok || !st.json.success) return;

    const j = st.json;
    if (j.active_run){
      const ar = j.active_run;
      const f = Number(ar.fetched_count||0);
      const u = Number(ar.upserted_orders||0);
      const e = Number(ar.upserted_jobcosting_entries||0);
      $('ovProg').textContent = `Progreso: Revisados ${f} · Actualizados ${u} · Movimientos ${e}`;
      $('ovStep').textContent = 'Actualizando…';
    } else {
      // ya no hay run activo -> terminó
      clearInterval(poll); poll=null;
    }
  }

  async function runSync(){
    ovOpen();

    // Si ya hay actualización corriendo
    const st0 = await loadStatus();
    if (st0 && st0.updating){
      $('ovSpin').style.display='none';
      $('ovStep').textContent='Ya se está actualizando';
      ovBadge('warn','En proceso');
      $('ovOut').style.display='block';
      $('ovOut').textContent='Ya hay una actualización en proceso. Espera un momento y vuelve a intentar.';
      return;
    }

    // arrancar polling de progreso
    clearInterval(poll);
    poll = setInterval(() => pollProgress().catch(()=>{}), 1500);

    // ejecutar sync (parámetros sanos)
    const url = 'scope_sync.php?mode=incremental&size=100&max_pages=5&days=7&throttle_ms=120&_=' + Date.now();
    const res = await fetch(url, { credentials:'same-origin' });
    const txt = await res.text();
    let data=null;
    try{ data=JSON.parse(txt);}catch(_){}

    $('ovOut').style.display='block';

    if (!res.ok || !data || !data.success){
      $('ovSpin').style.display='none';
      $('ovStep').textContent='No se pudo actualizar';
      ovBadge('bad','Error');
      $('ovOut').textContent = (data && (data.error||data.message))
        ? (data.error||data.message)
        : ('Respuesta inesperada (HTTP '+res.status+'):\n\n'+txt.slice(0,1800));
      await loadStatus();
      return;
    }

    $('ovSpin').style.display='none';
    const up = Number(data.upserted_orders||0);
    const fetched = Number(data.fetched||0);
    const errs = Number(data.errors_total||0);
    const failed = Number(data.failed_details||0);

    if (up>0){
      $('ovStep').textContent='Actualización completada';
      ovBadge('ok','Listo');
    } else {
      $('ovStep').textContent='Sin cambios recientes';
      ovBadge('ok','Listo');
    }

    $('ovOut').textContent =
      `Resumen:\n`+
      `• Revisados: ${fetched}\n`+
      `• Actualizados: ${up}\n`+
      `• Incidencias: ${errs} (sin respuesta: ${failed})\n`+
      `• Hora: ${data.server_time || '—'}\n`;

    await loadStatus();
  }

  $('btnSync').addEventListener('click', async () => {
    $('btnSync').disabled=true;
    try{ await runSync(); }
    finally{ $('btnSync').disabled=false; }
  });
  $('btnRefresh').addEventListener('click', () => loadStatus());
  $('btnPing').addEventListener('click', () => ping());
  $('btnOpenAudit').addEventListener('click', () => window.location.href='scope_audit.php');

  $('ovClose').addEventListener('click', ovClose);
  $('ov').addEventListener('click', (e)=>{ if(e.target === $('ov')) ovClose(); });

  // boot
  loadStatus();
  ping();
  setInterval(() => loadStatus().catch(()=>{}), 15000);
})();
</script>
</body>
</html>