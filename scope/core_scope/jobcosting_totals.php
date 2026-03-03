<?php
/**
 * jobcosting_totals.php — Job Costing Totals (tabla + KPIs + filtros)
 * AJUSTES:
 * 1) Topbar y botón de tema igual al diseño de "graficas.php" (themeBtn dentro de wrap).
 * 2) Botón "Gráficas" que abre popup (ventana) con los mismos filtros.
 * 3) Protegido con autenticación de CGL
 */

declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';

require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function money(?string $v): string {
  if ($v === null || $v === '') return '';
  return number_format((float)$v, 2, '.', ',');
}
function pct(?string $v): string {
  if ($v === null || $v === '') return '';
  return rtrim(rtrim(number_format((float)$v, 3, '.', ','), '0'), '.') . '%';
}
function valid_ymd(string $s): bool {
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}
function build_like(string $q): string { return '%' . trim($q) . '%'; }

// =======================
// Filtros (GET)
// =======================
$q = trim((string)($_GET['q'] ?? ''));          // order_id u order_number / cliente
$cur = trim((string)($_GET['cur'] ?? ''));      // currency
$minp = trim((string)($_GET['minp'] ?? ''));    // min profit local
$maxp = trim((string)($_GET['maxp'] ?? ''));    // max profit local
$from = trim((string)($_GET['from'] ?? ''));    // updated_at desde
$to   = trim((string)($_GET['to'] ?? ''));      // updated_at hasta
$limit = (int)($_GET['limit'] ?? 25);
if ($limit <= 0) $limit = 25;
if ($limit > 200) $limit = 200;

if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$minpVal = ($minp !== '' && is_numeric($minp)) ? (float)$minp : null;
$maxpVal = ($maxp !== '' && is_numeric($maxp)) ? (float)$maxp : null;

// Dropdown currency
$curRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(local_currency),''),'') AS cur, COUNT(*) c
  FROM scope_jobcosting_totals
  GROUP BY COALESCE(NULLIF(TRIM(local_currency),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// WHERE
// =======================
$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(j.order_id LIKE :q OR o.order_number LIKE :q OR o.customer_name LIKE :q)";
  $params[':q'] = build_like($q);
}
if ($cur !== '') {
  if ($cur === '__EMPTY__') {
    $where[] = "(j.local_currency IS NULL OR TRIM(j.local_currency)='')";
  } else {
    $where[] = "(j.local_currency = :cur)";
    $params[':cur'] = $cur;
  }
}
if ($minpVal !== null) {
  $where[] = "(j.local_profit >= :minp)";
  $params[':minp'] = $minpVal;
}
if ($maxpVal !== null) {
  $where[] = "(j.local_profit <= :maxp)";
  $params[':maxp'] = $maxpVal;
}
if ($from !== '') {
  $where[] = "(DATE(j.updated_at) >= :from)";
  $params[':from'] = $from;
}
if ($to !== '') {
  $where[] = "(DATE(j.updated_at) <= :to)";
  $params[':to'] = $to;
}
$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// =======================
// KPIs (mismo filtro)
// =======================
$kpi = [
  'Registros' => 0,
  'Ingreso total (local)' => 0.0,
  'Costo total (local)' => 0.0,
  'Utilidad total (local)' => 0.0,
];

$stmt = $pdo->prepare("
  SELECT
    COUNT(*) AS n,
    COALESCE(SUM(j.local_total_income),0) AS inc,
    COALESCE(SUM(j.local_total_cost),0) AS cost,
    COALESCE(SUM(j.local_profit),0) AS prof
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $whereSql
");
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$kpi['Registros'] = (int)($row['n'] ?? 0);
$kpi['Ingreso total (local)'] = (float)($row['inc'] ?? 0);
$kpi['Costo total (local)'] = (float)($row['cost'] ?? 0);
$kpi['Utilidad total (local)'] = (float)($row['prof'] ?? 0);

// =======================
// Tabla
// =======================
$sql = "
  SELECT
    j.order_id,
    o.order_number,
    o.customer_name,
    j.local_currency,
    j.local_total_income,
    j.local_total_cost,
    j.local_profit,
    j.local_gross_margin,
    j.org_currency,
    j.org_total_income,
    j.org_total_cost,
    j.org_profit,
    j.org_gross_margin,
    j.updated_at
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $whereSql
  ORDER BY j.updated_at DESC
  LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =======================
// Export URL
// =======================
$exportUrl = 'export_jobcosting_totals.php?' . http_build_query([
  'q' => $q,
  'cur' => $cur,
  'minp' => $minp,
  'maxp' => $maxp,
  'from' => $from,
  'to' => $to,
]);

// =======================
// URL para popup de gráficas (mismos filtros)
// Nota: ajusta el archivo destino si usas otro para graficar jobcosting
// =======================
$graphsUrl = 'jobcosting_totals_graficas.php?' . http_build_query([
  'q' => $q,
  'cur' => $cur,
  'minp' => $minp,
  'maxp' => $maxp,
  'from' => $from,
  'to' => $to,
  'limit' => $limit,
]);
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Job Costing Totals</title>

  <style>
    :root{
      --bg:#0b0c10; --text:#eaeaea; --muted:#a7adbd;
      --card:#12131a; --cardBorder:#242634;
      --field:#0f1016; --fieldBorder:#2a2d3f;
      --th:#151826; --thText:#cbd3ea;
      --btn:#ffd000; --btnText:#111;
      --btn2:#2a2d3f; --btn2Text:#fff;
      --linkBg:#1b1d29;
      --shadow: rgba(0,0,0,.25);
    }

    html[data-theme="light"]{
      --bg:#f6f7fb; --text:#14161f; --muted:#5b6173;
      --card:#ffffff; --cardBorder:#e6e8f0;
      --field:#ffffff; --fieldBorder:#d6d9e6;
      --th:#f2f4fa; --thText:#2a2f42;
      --btn:#ffd000; --btnText:#111;
      --btn2:#111827; --btn2Text:#fff;
      --linkBg:#ffffff;
      --shadow: rgba(17,24,39,.10);
    }

    html, body { height: 100%; }
    body{
      margin:0;
      background:var(--bg);
      color:var(--text);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
      overflow:hidden;
    }

    .app{ height:100vh; display:flex; flex-direction:column; overflow:hidden; }
    .wrap{
      flex:1;
      overflow:auto;
      -webkit-overflow-scrolling:touch;
      padding:18px;
    }
    .container{max-width:1200px; margin:0 auto;}

    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      margin-bottom:12px;
    }

    .themeBtn{
      width:44px; height:44px; border-radius:14px;
      background:var(--field); border:1px solid var(--fieldBorder);
      display:inline-flex; align-items:center; justify-content:center;
      cursor:pointer; user-select:none;
    }
    .themeBtn:hover{border-color:rgba(255,208,0,.35);}
    .themeBtn svg{width:20px; height:20px; fill:currentColor; color:var(--text);}
    .themeBtn:active{transform:translateY(1px);}

    .card{
      background:var(--card);
      border:1px solid var(--cardBorder);
      border-radius:14px;
      padding:16px;
      box-shadow:0 10px 30px var(--shadow);
    }

    h1{font-size:18px; margin:0 0 8px;}
    .muted{color:var(--muted); font-size:13px; line-height:1.35;}

    .row{display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;}
    label{display:block; font-size:12px; color:var(--muted); margin-bottom:6px;}

    input[type=text], input[type=number], input[type=date], select{
      padding:12px 12px;
      border-radius:12px;
      border:1px solid var(--fieldBorder);
      background:var(--field);
      color:var(--text);
      outline:none;
    }
    input[type=text]{width:360px; max-width:100%;}
    input[type=number]{width:180px; max-width:100%;}
    select{min-width:230px; max-width:100%;}

    button, a.btn, a.reset{
      display:inline-block;
      padding:12px 14px;
      border-radius:12px;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
      border:0;
      text-align:center;
      white-space:nowrap;
    }
    button, a.btn{background:var(--btn); color:var(--btnText);}
    a.btn.secondary{background:var(--btn2); color:var(--btn2Text);}
    a.reset{
      background:var(--linkBg);
      border:1px solid var(--fieldBorder);
      color:var(--text);
      font-weight:700;
    }

    .kpi{display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; margin-top:12px;}
    .pill{background:var(--field); border:1px solid var(--cardBorder); border-radius:12px; padding:10px;}
    .pill b{display:block; font-size:12px; color:var(--muted); margin-bottom:4px;}
    .pill span{font-size:16px; font-weight:800; word-break:break-word;}

    .tableWrap{
      overflow:auto;
      border-radius:12px;
      border:1px solid var(--cardBorder);
      margin-top:12px;
    }
    table{width:100%; border-collapse:separate; border-spacing:0;}
    th,td{padding:10px; border-bottom:1px solid var(--cardBorder); font-size:13px; white-space:nowrap;}
    th{background:var(--th); color:var(--thText); text-align:left;}
    tr:last-child td{border-bottom:0;}
    td.num{text-align:right;}

    @media(max-width:980px){
      .wrap{padding:12px;}
      .kpi{grid-template-columns:repeat(2, minmax(0,1fr));}
      input[type=text]{width:100%;}
      select{width:100%; min-width:unset;}
      .row{align-items:stretch;}
      button, a.btn, a.reset{width:100%;}
    }
  </style>

  <script>
    (function(){
      try{
        const saved = localStorage.getItem('core_scope.theme');
        const theme = saved ? saved : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
      }catch(e){}
    })();

    function setTheme(t){
      document.documentElement.setAttribute('data-theme', t);
      try{ localStorage.setItem('core_scope.theme', t); }catch(e){}
      updateThemeIcon();
    }
    function toggleTheme(){
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      setTheme(cur === 'dark' ? 'light' : 'dark');
    }
    function updateThemeIcon(){
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      const sun = document.getElementById('icoSun');
      const moon = document.getElementById('icoMoon');
      if (!sun || !moon) return;
      if (cur === 'dark'){ sun.style.display='block'; moon.style.display='none'; }
      else { sun.style.display='none'; moon.style.display='block'; }
    }

    function openPopup(url){
      const w = Math.min(1200, window.screen.width - 40);
      const h = Math.min(820, window.screen.height - 80);
      const left = Math.max(10, (window.screen.width - w) / 2);
      const top  = Math.max(10, (window.screen.height - h) / 2);
      window.open(url, 'core_scope_graficas', `width=${w},height=${h},left=${left},top=${top},scrollbars=yes,resizable=yes`);
    }

    document.addEventListener('DOMContentLoaded', () => {
      updateThemeIcon();
      const btn = document.getElementById('btnGraficas');
      if (btn) {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          openPopup(btn.getAttribute('data-url'));
        });
      }
    });
  </script>
</head>
<body>
  <div class="app">
    <div class="wrap">
      <div class="container">

        <!-- TOPBAR (igual al diseño de graficas.php) -->
        <div class="topbar">
          <div class="muted">CORE_SCOPE</div>

          <button type="button" class="themeBtn" onclick="toggleTheme()" aria-label="Cambiar tema" title="Cambiar tema">
            <svg id="icoSun" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM4.22 5.64a1 1 0 0 1 1.41 0l.71.7a1 1 0 1 1-1.42 1.42l-.7-.71a1 1 0 0 1 0-1.41Zm13.44 13.44a1 1 0 0 1 1.41 0l.71.7a1 1 0 1 1-1.42 1.42l-.7-.71a1 1 0 0 1 0-1.41ZM2 13a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1Zm18 0a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2h-1a1 1 0 0 1-1-1ZM4.22 20.36a1 1 0 0 1 0-1.41l.7-.71a1 1 0 1 1 1.42 1.42l-.71.7a1 1 0 0 1-1.41 0Zm13.44-13.44a1 1 0 0 1 0-1.41l.7-.71a1 1 0 1 1 1.42 1.42l-.71.7a1 1 0 0 1-1.41 0Z"/>
            </svg>
            <svg id="icoMoon" viewBox="0 0 24 24" aria-hidden="true" style="display:none">
              <path d="M21 14.6A8.5 8.5 0 0 1 9.4 3a7 7 0 1 0 11.6 11.6Z"/>
            </svg>
          </button>
        </div>

        <div class="card">
          <h1>CORE_SCOPE · Job Costing Totals</h1>
          <div class="muted">Utilidad, margen e importes por orden (join con scope_orders).</div>

          <div class="muted" style="margin-top:10px; font-weight:700; letter-spacing:.2px;">
            Filtros
          </div>

          <form method="get" class="row" style="margin-top:12px;">
            <div>
              <label>Buscar</label>
              <input type="text" name="q" value="<?=h($q)?>" placeholder="Ej: 12345 / ORD-001 / cliente...">
            </div>

            <div>
              <label>Moneda (local)</label>
              <select name="cur">
                <option value="">Todas</option>
                <option value="__EMPTY__" <?= $cur==='__EMPTY__' ? 'selected' : '' ?>>Sin moneda</option>
                <?php foreach ($curRows as $r): ?>
                  <?php $cc = (string)$r['cur']; if ($cc==='') continue; ?>
                  <option value="<?=h($cc)?>" <?= $cur===$cc ? 'selected' : '' ?>><?=h($cc)?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label>Utilidad mín (local)</label>
              <input type="number" step="0.01" name="minp" value="<?=h($minp)?>" placeholder="Ej: 0">
            </div>

            <div>
              <label>Utilidad máx (local)</label>
              <input type="number" step="0.01" name="maxp" value="<?=h($maxp)?>" placeholder="Ej: 50000">
            </div>

            <div>
              <label>Actualizado desde</label>
              <input type="date" name="from" value="<?=h($from)?>">
            </div>

            <div>
              <label>Actualizado hasta</label>
              <input type="date" name="to" value="<?=h($to)?>">
            </div>

            <div>
              <label>Registros</label>
              <select name="limit">
                <?php foreach ([10,25,50,100,200] as $n): ?>
                  <option value="<?=h((string)$n)?>" <?= $limit===$n ? 'selected' : '' ?>><?=h((string)$n)?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="flex-basis:100%; height:0;"></div>
            <div class="muted" style="margin-top:2px; font-weight:700; letter-spacing:.2px;">
              Acciones y navegación
            </div>

            <div class="row" style="align-items:center; width:100%; margin-top:10px;">
              <button type="submit">Aplicar</button>
              <a class="reset" href="<?=h('jobcosting_totals.php')?>">Restablecer</a>
              <a class="btn secondary" href="<?=h('graficas.php')?>">Volver</a>
              <a class="btn" href="<?=h($exportUrl)?>">Exportar CSV</a>

              <!-- NUEVO: Graficas (popup) -->
              <a class="btn secondary" href="<?=h($graphsUrl)?>" id="btnGraficas" data-url="<?=h($graphsUrl)?>">Gráficas</a>
            </div>
          </form>

          <div class="kpi">
            <div class="pill"><b>Registros</b><span><?=h((string)$kpi['Registros'])?></span></div>
            <div class="pill"><b>Ingreso total (local)</b><span><?=h(money((string)$kpi['Ingreso total (local)']))?></span></div>
            <div class="pill"><b>Costo total (local)</b><span><?=h(money((string)$kpi['Costo total (local)']))?></span></div>
            <div class="pill"><b>Utilidad total (local)</b><span><?=h(money((string)$kpi['Utilidad total (local)']))?></span></div>
          </div>

          <h1 style="margin-top:16px;">Resultados</h1>
          <div class="muted">Mostrando <?=h((string)count($rows))?> registro(s).</div>

          <div class="tableWrap">
            <table>
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Orden</th>
                  <th>Cliente</th>
                  <th>Moneda</th>
                  <th class="num">Ingreso</th>
                  <th class="num">Costo</th>
                  <th class="num">Utilidad</th>
                  <th class="num">Margen</th>
                  <th>Moneda (org)</th>
                  <th class="num">Ingreso (org)</th>
                  <th class="num">Costo (org)</th>
                  <th class="num">Utilidad (org)</th>
                  <th class="num">Margen (org)</th>
                  <th>Actualizado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><?=h((string)$r['order_id'])?></td>
                    <td><?=h((string)($r['order_number'] ?? ''))?></td>
                    <td><?=h((string)($r['customer_name'] ?? ''))?></td>
                    <td><?=h((string)($r['local_currency'] ?? ''))?></td>
                    <td class="num"><?=h(money((string)($r['local_total_income'] ?? '')))?></td>
                    <td class="num"><?=h(money((string)($r['local_total_cost'] ?? '')))?></td>
                    <td class="num"><?=h(money((string)($r['local_profit'] ?? '')))?></td>
                    <td class="num"><?=h(pct((string)($r['local_gross_margin'] ?? '')))?></td>
                    <td><?=h((string)($r['org_currency'] ?? ''))?></td>
                    <td class="num"><?=h(money((string)($r['org_total_income'] ?? '')))?></td>
                    <td class="num"><?=h(money((string)($r['org_total_cost'] ?? '')))?></td>
                    <td class="num"><?=h(money((string)($r['org_profit'] ?? '')))?></td>
                    <td class="num"><?=h(pct((string)($r['org_gross_margin'] ?? '')))?></td>
                    <td><?=h((string)($r['updated_at'] ?? ''))?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                  <tr><td colspan="14" class="muted">Sin datos con esos filtros.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>

      </div>
    </div>
  </div>
</body>
</html>
