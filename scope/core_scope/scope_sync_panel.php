<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';

date_default_timezone_set('America/Mexico_City');
$cssVars = core_brand_css_vars();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Actualizar datos</title>
<style>
<?= $cssVars ?>
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
.wrap{max-width:900px;margin:40px auto;padding:20px}
.card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:25px;box-shadow:0 15px 40px rgba(2,6,23,.08)}
h1{margin:0 0 10px;color:var(--core-navy)}
.muted{color:var(--muted);margin-bottom:20px;font-weight:850}
.btn{border:none;padding:14px 22px;border-radius:999px;font-weight:900;cursor:pointer;background:var(--core-blue);color:#fff;font-size:1rem}
.btn:disabled{opacity:.6;cursor:not-allowed}
.progress-wrap{display:none;margin-top:14px}
.progress-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:.85rem;color:var(--muted);font-weight:700}
.progress-track{width:100%;height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden;border:1px solid var(--border)}
.progress-bar{height:100%;width:0%;background:var(--core-blue);transition:width .35s ease}
.result{margin-top:20px;background:#0b1220;color:#e5e7eb;border-radius:14px;padding:15px;font-size:.85rem;max-height:360px;overflow:auto;white-space:pre-wrap;word-break:break-word}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Actualizar datos</h1>
    <div class="muted">Pantalla de soporte para ver el resultado crudo.</div>
    <button id="btnSync" class="btn">⟳ Actualizar ahora</button>
    <div id="progressWrap" class="progress-wrap">
      <div class="progress-head">
        <span id="progressText">Iniciando...</span>
        <span id="progressPct">0%</span>
      </div>
      <div class="progress-track">
        <div id="progressBar" class="progress-bar"></div>
      </div>
    </div>
    <div id="result" class="result" style="display:none;"></div>
  </div>
</div>
<script>
const btn = document.getElementById('btnSync');
const result = document.getElementById('result');
const progressWrap = document.getElementById('progressWrap');
const progressBar = document.getElementById('progressBar');
const progressPct = document.getElementById('progressPct');
const progressText = document.getElementById('progressText');

let currentProgress = 0;
let statusPollTimer = null;
let baselineRunId = 0;

function buildAjaxOptions() {
  return {
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  };
}

function parsePageFromMessage(msg) {
  if (!msg) return 0;
  const m = String(msg).match(/página\s+(\d+)/i);
  return m ? Number(m[1]) : 0;
}

function setProgress(value, text) {
  currentProgress = Math.max(0, Math.min(100, value));
  progressBar.style.width = `${currentProgress}%`;
  progressPct.textContent = `${Math.round(currentProgress)}%`;
  if (text) progressText.textContent = text;
}

function estimateProgressFromRun(run) {
  if (!run || !run.is_running) return 100;

  const maxPages = 5;
  const pageHint = parsePageFromMessage(run.message || '');
  const byPage = pageHint > 0 ? Math.min(82, (pageHint / maxPages) * 82) : 0;
  const activity = Number(run.fetched_count || 0) + Number(run.write_count_total || 0);
  const byActivity = Math.min(90, 10 + Math.log10(activity + 1) * 24);

  return Math.max(8, byPage, byActivity);
}

function renderLiveStatus(payload) {
  if (!payload?.success || !payload?.has_run || !payload?.run) return;

  const run = payload.run;
  const readCount = Number(run.fetched_count || 0);
  const writeCount = Number(run.write_count_total || 0);
  const stateText = run.is_running
    ? (run.is_waiting ? `En espera (${run.seconds_since_update}s sin cambios)` : 'Procesando')
    : 'Finalizado';

  const msg = run.message || '-';
  const details = [
    `Run: ${run.run_uuid}`,
    `Estado: ${stateText}`,
    `Mensaje: ${msg}`,
    `Leídos (API): ${readCount}`,
    `Escritos (BD total): ${writeCount}`,
    `Órdenes upsert: ${run.upserted_orders}`,
    `Milestones upsert: ${run.upserted_milestones}`,
    `References upsert: ${run.upserted_references}`,
    `Transport Orders upsert: ${run.upserted_transport_orders}`,
    `Jobcosting upsert: ${run.upserted_jobcosting_entries}`,
    `Última actividad: hace ${run.seconds_since_update}s`,
    `Inicio: ${run.started_at}`,
    `Fin: ${run.finished_at || '-'}`,
  ];

  result.textContent = details.join('\n');
  setProgress(estimateProgressFromRun(run), run.is_waiting ? 'Esperando respuesta de API/BD...' : msg);
}

async function getLatestRunId() {
  try {
    const r = await fetch('scope_sync_status.php?since_id=0&_=' + Date.now(), buildAjaxOptions());
    const txt = await r.text();
    const data = JSON.parse(txt);
    if (data?.success && data?.has_run && data?.run?.id) return Number(data.run.id);
  } catch (_) {}
  return 0;
}

async function pollStatus() {
  try {
    const url = 'scope_sync_status.php?since_id=' + baselineRunId + '&_=' + Date.now();
    const r = await fetch(url, buildAjaxOptions());
    const txt = await r.text();

    let data = null;
    try {
      data = JSON.parse(txt);
    } catch (_) {
      setProgress(Math.max(currentProgress, 6), 'Polling activo (respuesta no JSON)');
      result.textContent = `Polling activo, pero el estado devolvió formato inesperado.\nHTTP ${r.status}\n\n${txt.slice(0, 1200)}`;
      return;
    }

    if (data?.success && data?.has_run && data?.run) {
      renderLiveStatus(data);
      if (!data.run.is_running) {
        stopStatusPolling();
      }
    } else {
      setProgress(Math.max(currentProgress, 6), 'Esperando inicio de corrida...');
      result.textContent = 'Polling activo: esperando que inicie la corrida de sincronización...';
    }
  } catch (e) {
    setProgress(Math.max(currentProgress, 6), 'Consultando estado...');
    result.textContent = 'Polling activo con error temporal:\n' + (e?.message || e);
  }
}

function startStatusPolling() {
  progressWrap.style.display = 'block';
  setProgress(4, 'Iniciando sincronización...');
  if (statusPollTimer) clearInterval(statusPollTimer);
  statusPollTimer = setInterval(pollStatus, 1500);
}

function stopStatusPolling() {
  if (statusPollTimer) {
    clearInterval(statusPollTimer);
    statusPollTimer = null;
  }
}

function finishProgress(finalText) {
  stopStatusPolling();
  setProgress(100, finalText || 'Completado');
}

btn.addEventListener('click', async () => {
  btn.disabled = true;
  btn.textContent = "⟳ Actualizando...";
  result.style.display = "block";
  result.textContent = "Preparando monitoreo en tiempo real...\n";

  baselineRunId = await getLatestRunId();
  startStatusPolling();
  await pollStatus();

  try{
    const url = 'scope_sync.php?mode=incremental&size=100&max_pages=5&days=7&throttle_ms=120&_=' + Date.now();
    const res = await fetch(url, buildAjaxOptions());
    const txt = await res.text();

    await pollStatus();

    let rawBlock = '';
    try { rawBlock = JSON.stringify(JSON.parse(txt), null, 2); }
    catch (_) { rawBlock = `HTTP ${res.status}\n\n${txt}`; }

    result.textContent += `\n\n--- Respuesta final ---\n${rawBlock}`;
    finishProgress(res.ok ? 'Actualización finalizada' : 'Finalizó con errores');
  }catch(e){
    result.textContent += "\n\nError:\n" + (e?.stack || e);
    finishProgress('Error en actualización');
  }
  btn.disabled = false;
  btn.textContent = "⟳ Actualizar ahora";
});
</script>
</body>
</html>