<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function es_financial_status(?string $v): string {
  $v = trim((string)$v);
  if ($v === '') return 'Sin estatus';

  $key = mb_strtolower($v);
  $map = [
    'open' => 'Abierto',
    'closed' => 'Cerrado',
    'invoiced' => 'Facturado',
    'notinvoiced' => 'No facturado',
    'not invoiced' => 'No facturado',
    'partlyinvoiced' => 'Parcialmente facturado',
    'partly invoiced' => 'Parcialmente facturado',
    'readyforinvoice' => 'Listo para facturar',
    'ready for invoice' => 'Listo para facturar',
    'unknown' => 'Desconocido',
  ];
  return $map[$key] ?? $v;
}

function build_like(string $q): string {
  return '%' . trim($q) . '%';
}

function valid_ymd(string $s): bool {
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

// =======================
// Filtros (GET)
// =======================
$q     = trim((string)($_GET['q'] ?? ''));
$st    = trim((string)($_GET['st'] ?? ''));        // financial_status exact o "__EMPTY__"
$from  = trim((string)($_GET['from'] ?? ''));      // YYYY-MM-DD
$to    = trim((string)($_GET['to'] ?? ''));        // YYYY-MM-DD
$days  = (int)($_GET['days'] ?? 30);
if ($days <= 0) $days = 30;
if ($days > 180) $days = 180;

if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

// =======================
// WHERE común
// =======================
$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(order_number LIKE :q OR customer_name LIKE :q)";
  $params[':q'] = build_like($q);
}
if ($st !== '') {
  if ($st === '__EMPTY__') {
    $where[] = "(financial_status IS NULL OR TRIM(financial_status) = '')";
  } else {
    $where[] = "(financial_status = :st)";
    $params[':st'] = $st;
  }
}
if ($from !== '') {
  $where[] = "(transport_date >= :from)";
  $params[':from'] = $from;
}
if ($to !== '') {
  $where[] = "(transport_date <= :to)";
  $params[':to'] = $to;
}

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// =======================
// KPIs
// =======================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM scope_orders" . $whereSql);
$stmt->execute($params);
$kpi_total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM scope_orders
  " . ($where ? ($whereSql . " AND ") : " WHERE ") . "
  (transport_date IS NULL OR transport_date = '')
");
$stmt->execute($params);
$kpi_sin_fecha_transporte = (int)$stmt->fetchColumn();

// =======================
// Serie por día (últimos N días) usando transport_date
// =======================
$serie_from = (new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City')))
  ->sub(new DateInterval('P' . max(1, $days - 1) . 'D'))
  ->format('Y-m-d');

$serieWhere = $where;
$serieParams = $params;
$serieWhere[] = "(transport_date >= :serie_from)";
$serieParams[':serie_from'] = $serie_from;

$serieSql = " WHERE " . implode(" AND ", $serieWhere);

$stmt = $pdo->prepare("
  SELECT transport_date AS d, COUNT(*) AS c
  FROM scope_orders
  $serieSql
  GROUP BY transport_date
  ORDER BY transport_date ASC
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
// Estatus financiero
// =======================
$stStmt = $pdo->prepare("
  SELECT COALESCE(NULLIF(TRIM(financial_status),''),'') AS st, COUNT(*) AS c
  FROM scope_orders
  $whereSql
  GROUP BY COALESCE(NULLIF(TRIM(financial_status),''),'')
  ORDER BY c DESC
");
$stStmt->execute($params);
$stRows = $stStmt->fetchAll(PDO::FETCH_ASSOC);

$labelsSt = [];
$valuesSt = [];
foreach ($stRows as $r) {
  $raw = (string)$r['st'];
  $labelsSt[] = ($raw === '') ? 'Sin estatus' : es_financial_status($raw);
  $valuesSt[] = (int)$r['c'];
}

// =======================
// Top clientes
// =======================
$topStmt = $pdo->prepare("
  SELECT COALESCE(NULLIF(TRIM(customer_name),''),'(Sin cliente)') AS cliente, COUNT(*) AS c
  FROM scope_orders
  $whereSql
  GROUP BY COALESCE(NULLIF(TRIM(customer_name),''),'(Sin cliente)')
  ORDER BY c DESC
  LIMIT 10
");
$topStmt->execute($params);
$topRows = $topStmt->fetchAll(PDO::FETCH_ASSOC);

$labelsTop = [];
$valuesTop = [];
foreach ($topRows as $r) {
  $labelsTop[] = (string)$r['cliente'];
  $valuesTop[] = (int)$r['c'];
}

// =======================
// Dropdown estatus disponibles
// =======================
$allStRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(financial_status),''),'') AS st, COUNT(*) AS c
  FROM scope_orders
  GROUP BY COALESCE(NULLIF(TRIM(financial_status),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// Export URL
// =======================
$exportUrl = 'export_scope_orders.php?' . http_build_query([
  'q' => $q,
  'st' => $st,
  'from' => $from,
  'to' => $to,
]);

?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Gráficas</title>

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

    /* ====== BLOQUEO DE CRECIMIENTO VERTICAL DEL BODY ====== */
    html, body { height: 100%; }
    body{
      margin:0;
      background:var(--bg);
      color:var(--text);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
      overflow:hidden; /* clave: evita que el body siga creciendo */
    }

    /* ====== CONTENEDOR TIPO APP (SCROLL INTERNO) ====== */
    .app{
      height:100vh;
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }
    .wrap{
      flex:1;
      overflow:auto; /* scroll aquí, NO en body */
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

    input[type=text], input[type=date], select{
      padding:12px 12px;
      border-radius:12px;
      border:1px solid var(--fieldBorder);
      background:var(--field);
      color:var(--text);
    }
    input[type=text]{width:360px; max-width:100%;}
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

    /* ====== GRID Y TARJETAS DE GRÁFICAS (ALTURA FIJA) ====== */
    .grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0,1fr));
      gap:12px;
      margin-top:12px;
    }
    .chart{
      background:var(--field);
      border:1px solid var(--cardBorder);
      border-radius:14px;
      padding:14px;
      height: 420px;        /* fijo: evita que “crezca” */
      overflow:hidden;      /* evita expansión por canvas */
      display:flex;
      flex-direction:column;
    }
    .chart h2{
      font-size:14px;
      margin:0 0 10px;
      color:var(--thText);
      flex:0 0 auto;
    }
    .chart .canvasWrap{
      flex:1 1 auto;
      min-height:0;         /* importante en flex */
    }
    .chart canvas{
      width:100% !important;
      height:100% !important;
      display:block;
    }

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
      input[type=text]{width:100%;}
      select{width:100%; min-width:unset;}
      .chart{height: 380px;}
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
            <!-- Sol (se muestra en modo oscuro) -->
            <svg id="icoSun" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM4.22 5.64a1 1 0 0 1 1.41 0l.71.7a1 1 0 1 1-1.42 1.42l-.7-.71a1 1 0 0 1 0-1.41Zm13.44 13.44a1 1 0 0 1 1.41 0l.71.7a1 1 0 1 1-1.42 1.42l-.7-.71a1 1 0 0 1 0-1.41ZM2 13a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1Zm18 0a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2h-1a1 1 0 0 1-1-1ZM4.22 20.36a1 1 0 0 1 0-1.41l.7-.71a1 1 0 1 1 1.42 1.42l-.71.7a1 1 0 0 1-1.41 0Zm13.44-13.44a1 1 0 0 1 0-1.41l.7-.71a1 1 0 1 1 1.42 1.42l-.71.7a1 1 0 0 1-1.41 0Z"/>
            </svg>
            <!-- Luna (se muestra en modo claro) -->
            <svg id="icoMoon" viewBox="0 0 24 24" aria-hidden="true" style="display:none">
              <path d="M21 14.6A8.5 8.5 0 0 1 9.4 3a7 7 0 1 0 11.6 11.6Z"/>
            </svg>
          </button>
        </div>

        <div class="card">
  <h1>CORE_SCOPE · Gráficas</h1>
  <div class="muted">Dashboard con filtros y exportación en CSV.</div>

  <!-- ===== FILTROS ===== -->
  <div class="muted" style="margin-top:10px; font-weight:700; letter-spacing:.2px;">
    Filtros
  </div>

  <form method="get" class="row" style="margin-top:12px;">
    <div>
      <label>Buscar (Orden o Cliente)</label>
      <input type="text" name="q" value="<?=h($q)?>" placeholder="Ej: order / cliente...">
    </div>

    <div>
      <label>Estatus financiero</label>
      <select name="st">
        <option value="">Todos</option>
        <option value="__EMPTY__" <?= $st==='__EMPTY__' ? 'selected' : '' ?>>Sin estatus</option>
        <?php foreach ($allStRows as $r): ?>
          <?php $raw = (string)$r['st']; if ($raw==='') continue; ?>
          <option value="<?=h($raw)?>" <?= $st===$raw ? 'selected' : '' ?>>
            <?=h(es_financial_status($raw))?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label>Desde (Fecha de transporte)</label>
      <input type="date" name="from" value="<?=h($from)?>">
    </div>

    <div>
      <label>Hasta (Fecha de transporte)</label>
      <input type="date" name="to" value="<?=h($to)?>">
    </div>

    <div>
      <label>Ventana serie (días)</label>
      <select name="days">
        <?php foreach ([7,14,30,60,90,120,180] as $n): ?>
          <option value="<?=h((string)$n)?>" <?= $days===$n ? 'selected' : '' ?>><?=h((string)$n)?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- ===== BOTONES (acciones + navegación) ===== -->
    <div style="flex-basis:100%; height:0;"></div>
    <div class="muted" style="margin-top:2px; font-weight:700; letter-spacing:.2px;">
      Acciones y navegación
    </div>

    <div class="row" style="align-items:center; width:100%; margin-top:10px;">
      <!-- Acciones del filtro -->
      <button type="submit">Aplicar</button>
      <a class="reset" href="<?=h('graficas.php')?>">Restablecer</a>

      <!-- Navegación -->
      <a class="btn secondary" href="<?=h('index.php')?>">Volver a sincronización</a>

      <!-- Export (se queda tal cual, solo texto en español) -->
      <a class="btn" href="<?=h($exportUrl)?>">Exportar CSV</a>

      <!-- Acceso a las 3 interfaces creadas (NO cambio nombres de archivos) -->
      <a class="btn secondary" href="<?=h('jobcosting_totals.php')?>">Costos y utilidad</a>
      <a class="btn secondary" href="<?=h('transport_orders.php')?>">Órdenes de transporte</a>
      <a class="btn secondary" href="<?=h('ordenes_scope.php')?>">Análisis por periodo</a>
    </div>
  </form>

  <div class="kpi">
    <div class="pill">
      <b>Órdenes (según filtros)</b>
      <span><?=h((string)$kpi_total)?></span>
    </div>
    <div class="pill">
      <b>Sin fecha de transporte</b>
      <span><?=h((string)$kpi_sin_fecha_transporte)?></span>
    </div>
    <div class="pill">
      <b>Serie desde</b>
      <span><?=h($serie_from)?></span>
    </div>
    <div class="pill">
      <b>Ventana</b>
      <span><?=h((string)$days)?> días</span>
    </div>
  </div>
</div>


        <div class="grid">
          <div class="chart">
            <h2>Órdenes por día (según fecha de transporte)</h2>
            <div class="canvasWrap"><canvas id="chDays"></canvas></div>
          </div>

          <div class="chart">
            <h2>Distribución por estatus financiero</h2>
            <div class="canvasWrap"><canvas id="chStatus"></canvas></div>
          </div>

          <div class="chart">
            <h2>Top 10 clientes por volumen de órdenes</h2>
            <div class="canvasWrap"><canvas id="chTop"></canvas></div>
          </div>

          <div class="chart">
            <h2>Tabla rápida (estatus)</h2>
            <div class="muted" style="margin-bottom:10px;">Conteo por estatus con el mismo filtro.</div>

            <div class="tableWrap">
              <table>
                <thead>
                  <tr>
                    <th>Estatus</th>
                    <th style="text-align:right;">Órdenes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($stRows as $r): ?>
                    <?php
                      $raw = (string)$r['st'];
                      $lbl = ($raw==='') ? 'Sin estatus' : es_financial_status($raw);
                    ?>
                    <tr>
                      <td><?=h($lbl)?></td>
                      <td class="num"><?=h((string)$r['c'])?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$stRows): ?>
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

    const labelsSt = <?=json_encode($labelsSt, JSON_UNESCAPED_UNICODE)?>;
    const valuesSt = <?=json_encode($valuesSt, JSON_UNESCAPED_UNICODE)?>;

    const labelsTop = <?=json_encode($labelsTop, JSON_UNESCAPED_UNICODE)?>;
    const valuesTop = <?=json_encode($valuesTop, JSON_UNESCAPED_UNICODE)?>;

    function mkLine() {
      return new Chart(document.getElementById('chDays'), {
        type: 'line',
        data: {
          labels: labelsDays,
          datasets: [{
            label: 'Órdenes',
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

    function mkDoughnut() {
      return new Chart(document.getElementById('chStatus'), {
        type: 'doughnut',
        data: {
          labels: labelsSt,
          datasets: [{
            label: 'Órdenes',
            data: valuesSt,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom' } }
        }
      });
    }

    function mkBar() {
      return new Chart(document.getElementById('chTop'), {
        type: 'bar',
        data: {
          labels: labelsTop,
          datasets: [{
            label: 'Órdenes',
            data: valuesTop,
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
    mkDoughnut();
    mkBar();
  </script>
</body>
</html>
