<?php
/**
 * jobcosting_totals_graficas.php — Gráficas Job Costing Totals (POPUP)
 * - Misma “piel” que graficas.php
 * - Recibe los mismos filtros que jobcosting_totals.php
 * - NO exporta aquí (solo visual)
 * - Protegido con autenticación de CGL
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
$q    = trim((string)($_GET['q'] ?? ''));
$cur  = trim((string)($_GET['cur'] ?? ''));
$minp = trim((string)($_GET['minp'] ?? ''));
$maxp = trim((string)($_GET['maxp'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$minpVal = ($minp !== '' && is_numeric($minp)) ? (float)$minp : null;
$maxpVal = ($maxp !== '' && is_numeric($maxp)) ? (float)$maxp : null;

// =======================
// WHERE (misma lógica)
// =======================
$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(j.order_id LIKE :q OR o.order_number LIKE :q OR o.customer_name LIKE :q)";
  $params[':q'] = build_like($q);
}
if ($cur !== '') {
  if ($cur === '__EMPTY__') $where[] = "(j.local_currency IS NULL OR TRIM(j.local_currency)='')";
  else { $where[] = "(j.local_currency = :cur)"; $params[':cur'] = $cur; }
}
if ($minpVal !== null) { $where[] = "(j.local_profit >= :minp)"; $params[':minp'] = $minpVal; }
if ($maxpVal !== null) { $where[] = "(j.local_profit <= :maxp)"; $params[':maxp'] = $maxpVal; }
if ($from !== '') { $where[] = "(DATE(j.updated_at) >= :from)"; $params[':from'] = $from; }
if ($to !== '') { $where[] = "(DATE(j.updated_at) <= :to)"; $params[':to'] = $to; }

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// =======================
// KPIs
// =======================
$stmt = $pdo->prepare("
  SELECT
    COUNT(*) AS n,
    COALESCE(SUM(j.local_total_income),0) AS inc,
    COALESCE(SUM(j.local_total_cost),0) AS cost,
    COALESCE(SUM(j.local_profit),0) AS prof,
    COALESCE(AVG(j.local_gross_margin),0) AS avg_margin
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $whereSql
");
$stmt->execute($params);
$k = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$kpi_n    = (int)($k['n'] ?? 0);
$kpi_inc  = (float)($k['inc'] ?? 0);
$kpi_cost = (float)($k['cost'] ?? 0);
$kpi_prof = (float)($k['prof'] ?? 0);
$kpi_avgm = (float)($k['avg_margin'] ?? 0);

// =======================
// Serie por día (updated_at) últimos N días
// =======================
$days = 30;
$serie_from = (new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City')))
  ->sub(new DateInterval('P' . max(1, $days - 1) . 'D'))
  ->format('Y-m-d');

$serieWhere = $where;
$serieParams = $params;
$serieWhere[] = "(DATE(j.updated_at) >= :serie_from)";
$serieParams[':serie_from'] = $serie_from;

$serieSql = " WHERE " . implode(" AND ", $serieWhere);

$stmt = $pdo->prepare("
  SELECT DATE(j.updated_at) AS d, COUNT(*) AS c
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $serieSql
  GROUP BY DATE(j.updated_at)
  ORDER BY DATE(j.updated_at) ASC
");
$stmt->execute($serieParams);
$dayRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dayMap = [];
foreach ($dayRows as $r) {
  $d = (string)$r['d'];
  if ($d !== '') $dayMap[$d] = (int)$r['c'];
}

$labelsDays = [];
$valuesDays = [];
$dt0 = new DateTimeImmutable($serie_from, new DateTimeZone('America/Mexico_City'));
for ($i=0; $i<$days; $i++) {
  $d = $dt0->add(new DateInterval("P{$i}D"))->format('Y-m-d');
  $labelsDays[] = $d;
  $valuesDays[] = $dayMap[$d] ?? 0;
}

// =======================
// Distribución por moneda local
// =======================
$stmt = $pdo->prepare("
  SELECT COALESCE(NULLIF(TRIM(j.local_currency),''),'') AS cur, COUNT(*) AS c
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $whereSql
  GROUP BY COALESCE(NULLIF(TRIM(j.local_currency),''),'')
  ORDER BY c DESC
  LIMIT 12
");
$stmt->execute($params);
$curRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labelsCur = [];
$valuesCur = [];
foreach ($curRows as $r) {
  $raw = (string)$r['cur'];
  $labelsCur[] = ($raw === '') ? 'Sin moneda' : $raw;
  $valuesCur[] = (int)$r['c'];
}

// =======================
// Top clientes (por volumen)
// =======================
$stmt = $pdo->prepare("
  SELECT COALESCE(NULLIF(TRIM(o.customer_name),''),'(Sin cliente)') AS cliente, COUNT(*) AS c
  FROM scope_jobcosting_totals j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  $whereSql
  GROUP BY COALESCE(NULLIF(TRIM(o.customer_name),''),'(Sin cliente)')
  ORDER BY c DESC
  LIMIT 10
");
$stmt->execute($params);
$topRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labelsTop = [];
$valuesTop = [];
foreach ($topRows as $r) {
  $labelsTop[] = (string)$r['cliente'];
  $valuesTop[] = (int)$r['c'];
}

// =======================
// Tabla rápida por “bandas” de margen (local_gross_margin)
// =======================
$bands = [
  'Negativo'   => [null, -0.00001],
  '0% - 5%'    => [0.0, 0.05],
  '5% - 10%'   => [0.05, 0.10],
  '10% - 20%'  => [0.10, 0.20],
  '20% - 35%'  => [0.20, 0.35],
  '35%+'       => [0.35, null],
];

$bandLabels = [];
$bandValues = [];
foreach ($bands as $lbl => [$a, $b]) {
  $w = $where;
  $p = $params;

  if ($a === null && $b !== null) { $w[] = "(j.local_gross_margin <= :b)"; $p[':b'] = $b; }
  elseif ($a !== null && $b === null) { $w[] = "(j.local_gross_margin >= :a)"; $p[':a'] = $a; }
  elseif ($a !== null && $b !== null) { $w[] = "(j.local_gross_margin >= :a AND j.local_gross_margin < :b)"; $p[':a'] = $a; $p[':b'] = $b; }

  $ws = $w ? (" WHERE " . implode(" AND ", $w)) : "";
  $stt = $pdo->prepare("
    SELECT COUNT(*)
    FROM scope_jobcosting_totals j
    LEFT JOIN scope_orders o ON o.id = j.order_id
    $ws
  ");
  $stt->execute($p);
  $bandLabels[] = $lbl;
  $bandValues[] = (int)$stt->fetchColumn();
}

// =======================
// Back URL (regresar a tabla)
// =======================
$backUrl = 'jobcosting_totals.php?' . http_build_query([
  'q' => $q,
  'cur' => $cur,
  'minp' => $minp,
  'maxp' => $maxp,
  'from' => $from,
  'to' => $to,
]);

?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Gráficas Job Costing</title>

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
      margin:0; background:var(--bg); color:var(--text);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
      overflow:hidden;
    }

    .app{ height:100vh; display:flex; flex-direction:column; overflow:hidden; }
    .wrap{ flex:1; overflow:auto; -webkit-overflow-scrolling:touch; padding:18px; }
    .container{ max-width:1200px; margin:0 auto; }

    .topbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }

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

    .kpi{display:grid; grid-template-columns:repeat(5, minmax(0,1fr)); gap:10px; margin-top:12px;}
    .pill{background:var(--field); border:1px solid var(--cardBorder); border-radius:12px; padding:10px;}
    .pill b{display:block; font-size:12px; color:var(--muted); margin-bottom:4px;}
    .pill span{font-size:16px; font-weight:800; word-break:break-word;}

    button, a.btn, a.reset{
      display:inline-block; padding:12px 14px; border-radius:12px;
      font-weight:800; text-decoration:none; cursor:pointer; border:0; text-align:center;
    }
    button, a.btn{background:var(--btn); color:var(--btnText);}
    a.btn.secondary{background:var(--btn2); color:var(--btn2Text);}
    a.reset{background:var(--linkBg); border:1px solid var(--fieldBorder); color:var(--text); font-weight:700;}

    .grid{display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; margin-top:12px;}
    .chart{
      background:var(--field);
      border:1px solid var(--cardBorder);
      border-radius:14px;
      padding:14px;
      height: 420px;
      overflow:hidden;
      display:flex;
      flex-direction:column;
    }
    .chart h2{font-size:14px; margin:0 0 10px; color:var(--thText); flex:0 0 auto;}
    .chart .canvasWrap{flex:1 1 auto; min-height:0;}
    .chart canvas{width:100% !important; height:100% !important; display:block;}

    .tableWrap{flex:1 1 auto; min-height:0; overflow:auto; border-radius:12px; border:1px solid var(--cardBorder);}
    table{width:100%; border-collapse:separate; border-spacing:0;}
    th,td{padding:10px; border-bottom:1px solid var(--cardBorder); font-size:13px;}
    th{background:var(--th); color:var(--thText); text-align:left;}
    tr:last-child td{border-bottom:0;}
    td.num{text-align:right;}

    @media(max-width:980px){
      .wrap{padding:12px;}
      .grid{grid-template-columns:1fr;}
      .kpi{grid-template-columns:repeat(2, minmax(0,1fr));}
      .chart{height: 380px;}
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
    document.addEventListener('DOMContentLoaded', updateThemeIcon);
  </script>
</head>

<body>
  <div class="app">
    <div class="wrap">
      <div class="container">

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
          <h1>CORE_SCOPE · Gráficas · Job Costing Totals</h1>
          <div class="muted">Popup de análisis (misma piel que graficas.php). Usa los filtros de la tabla.</div>

          <div class="muted" style="margin-top:10px; font-weight:700; letter-spacing:.2px;">Acciones</div>
          <div class="row" style="margin-top:10px;">
            <a class="btn secondary" href="<?=h($backUrl)?>" onclick="try{window.close();}catch(e){}">Volver a la tabla</a>
            <a class="reset" href="<?=h('jobcosting_totals_graficas.php')?>" title="Quitar filtros">Restablecer (sin filtros)</a>
          </div>

          <div class="kpi">
            <div class="pill"><b>Registros</b><span><?=h((string)$kpi_n)?></span></div>
            <div class="pill"><b>Ingreso total (local)</b><span><?=h(money((string)$kpi_inc))?></span></div>
            <div class="pill"><b>Costo total (local)</b><span><?=h(money((string)$kpi_cost))?></span></div>
            <div class="pill"><b>Utilidad total (local)</b><span><?=h(money((string)$kpi_prof))?></span></div>
            <div class="pill"><b>Margen prom. (local)</b><span><?=h(pct((string)$kpi_avgm))?></span></div>
          </div>
        </div>

        <div class="grid">
          <div class="chart">
            <h2>Registros por día (updated_at · últimos <?=h((string)$days)?> días)</h2>
            <div class="canvasWrap"><canvas id="chDays"></canvas></div>
          </div>

          <div class="chart">
            <h2>Distribución por moneda (local)</h2>
            <div class="canvasWrap"><canvas id="chCur"></canvas></div>
          </div>

          <div class="chart">
            <h2>Top 10 clientes (volumen)</h2>
            <div class="canvasWrap"><canvas id="chTop"></canvas></div>
          </div>

          <div class="chart">
            <h2>Bandas de margen (local_gross_margin)</h2>
            <div class="canvasWrap"><canvas id="chBands"></canvas></div>
          </div>

          <div class="chart" style="grid-column:1 / -1;">
            <h2>Tabla rápida · Bandas de margen</h2>
            <div class="muted" style="margin-bottom:10px;">Conteo por rango de margen, con los mismos filtros.</div>

            <div class="tableWrap">
              <table>
                <thead>
                  <tr>
                    <th>Banda</th>
                    <th style="text-align:right;">Registros</th>
                  </tr>
                </thead>
                <tbody>
                  <?php for ($i=0; $i<count($bandLabels); $i++): ?>
                    <tr>
                      <td><?=h($bandLabels[$i])?></td>
                      <td class="num"><?=h((string)$bandValues[$i])?></td>
                    </tr>
                  <?php endfor; ?>
                  <?php if (!$bandLabels): ?>
                    <tr><td colspan="2" class="muted">Sin datos.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    const labelsDays = <?=json_encode($labelsDays, JSON_UNESCAPED_UNICODE)?>;
    const valuesDays = <?=json_encode($valuesDays, JSON_UNESCAPED_UNICODE)?>;

    const labelsCur = <?=json_encode($labelsCur, JSON_UNESCAPED_UNICODE)?>;
    const valuesCur = <?=json_encode($valuesCur, JSON_UNESCAPED_UNICODE)?>;

    const labelsTop = <?=json_encode($labelsTop, JSON_UNESCAPED_UNICODE)?>;
    const valuesTop = <?=json_encode($valuesTop, JSON_UNESCAPED_UNICODE)?>;

    const labelsBands = <?=json_encode($bandLabels, JSON_UNESCAPED_UNICODE)?>;
    const valuesBands = <?=json_encode($bandValues, JSON_UNESCAPED_UNICODE)?>;

    function mkLine() {
      return new Chart(document.getElementById('chDays'), {
        type: 'line',
        data: {
          labels: labelsDays,
          datasets: [{
            label: 'Registros',
            data: valuesDays,
            tension: 0.25,
            pointRadius: 2,
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: {
            x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
          }
        }
      });
    }

    function mkDoughnut(elId, labels, values, legendPos='bottom') {
      return new Chart(document.getElementById(elId), {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            label: 'Registros',
            data: values,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: legendPos } }
        }
      });
    }

    function mkBar(elId, labels, values) {
      return new Chart(document.getElementById(elId), {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Registros',
            data: values,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: {
            x: { ticks: { autoSkip: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
          }
        }
      });
    }

    mkLine();
    mkDoughnut('chCur', labelsCur, valuesCur, 'bottom');
    mkBar('chTop', labelsTop, valuesTop);
    mkBar('chBands', labelsBands, valuesBands);
  </script>
</body>
</html>
