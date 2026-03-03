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
.result{margin-top:20px;background:#0b1220;color:#e5e7eb;border-radius:14px;padding:15px;font-size:.85rem;max-height:360px;overflow:auto;white-space:pre-wrap;word-break:break-word}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Actualizar datos</h1>
    <div class="muted">Pantalla de soporte para ver el resultado crudo.</div>
    <button id="btnSync" class="btn">⟳ Actualizar ahora</button>
    <div id="result" class="result" style="display:none;"></div>
  </div>
</div>
<script>
const btn = document.getElementById('btnSync');
const result = document.getElementById('result');

btn.addEventListener('click', async () => {
  btn.disabled = true;
  btn.textContent = "⟳ Actualizando...";
  result.style.display = "block";
  result.textContent = "Procesando...\n";

  try{
    const url = 'scope_sync.php?mode=incremental&size=100&max_pages=5&days=7&throttle_ms=120&_=' + Date.now();
    const res = await fetch(url, { credentials:'same-origin' });
    const txt = await res.text();
    try{ result.textContent = JSON.stringify(JSON.parse(txt), null, 2); }
    catch(_){ result.textContent = `HTTP ${res.status}\n\n` + txt; }
  }catch(e){
    result.textContent = "Error:\n" + (e?.stack || e);
  }
  btn.disabled = false;
  btn.textContent = "⟳ Actualizar ahora";
});
</script>
</body>
</html>