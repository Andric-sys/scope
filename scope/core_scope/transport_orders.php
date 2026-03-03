<?php
/**
 * transport_orders.php — Transport Orders (tabla + KPIs + tarjetas + filtros)
 * PIEL: igual a ordenes_scope.php
 * - Topbar dentro de .wrap (CORE_SCOPE + themeBtn)
 * - Tema persistente en localStorage('core_scope.theme') + iconos sol/luna
 * - Body con overflow:hidden y scroll interno
 * - Bloques “Filtros” y “Acciones y navegación”
 * - Botón “Gráficas” que abre popup con los mismos filtros (transport_orders_graficas.php) * - Protegido con autenticación de CGL */

declare(strict_types=1);

require __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function build_like(string $q): string { return '%' . trim($q) . '%'; }
function valid_ymd(string $s): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }

// =======================
// GET filtros
// =======================
$q  = trim((string)($_GET['q'] ?? ''));
$tt = trim((string)($_GET['tt'] ?? ''));     // transport_type exact o __EMPTY__
$cv = trim((string)($_GET['cv'] ?? ''));     // conveyance_type exact o __EMPTY__
$pc = trim((string)($_GET['pc'] ?? ''));     // pickup_country exact o __EMPTY__
$uc = trim((string)($_GET['uc'] ?? ''));     // unloading_country exact o __EMPTY__

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$limit = (int)($_GET['limit'] ?? 25);
if ($limit <= 0) $limit = 25;
if ($limit > 200) $limit = 200;

// =======================
// Dropdowns (SIN filtros para poblar opciones)
// =======================
$typeRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(transport_type),''),'') AS v, COUNT(*) c
  FROM scope_transport_orders
  GROUP BY COALESCE(NULLIF(TRIM(transport_type),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

$conRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(conveyance_type),''),'') AS v, COUNT(*) c
  FROM scope_transport_orders
  GROUP BY COALESCE(NULLIF(TRIM(conveyance_type),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

$countryPickRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(pickup_country),''),'') AS v, COUNT(*) c
  FROM scope_transport_orders
  GROUP BY COALESCE(NULLIF(TRIM(pickup_country),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

$countryUnlRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(unloading_country),''),'') AS v, COUNT(*) c
  FROM scope_transport_orders
  GROUP BY COALESCE(NULLIF(TRIM(unloading_country),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

// =======================
// Cards por transport_type (conteo global, como en ordenes_scope.php)
// =======================
$cards = [];
foreach ($typeRows as $r) {
  $raw = (string)$r['v'];
  $cards[] = [
    'raw'   => $raw,
    'label' => $raw === '' ? 'Sin tipo' : $raw,
    'count' => (int)$r['c'],
  ];
}

function is_active_card(string $selected, string $raw): bool {
  if ($selected === '__EMPTY__' && $raw === '') return true;
  return $selected !== '' && $selected === $raw;
}

// =======================
// WHERE (JOIN t + o)
// =======================
$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(
    t.transport_order_number LIKE :q OR
    t.transport_order_uuid LIKE :q OR
    o.order_number LIKE :q OR
    o.customer_name LIKE :q OR
    t.pickup_partner_name LIKE :q OR
    t.unloading_partner_name LIKE :q
  )";
  $params[':q'] = build_like($q);
}

if ($tt !== '') {
  if ($tt === '__EMPTY__') $where[] = "(t.transport_type IS NULL OR TRIM(t.transport_type)='')";
  else { $where[] = "(t.transport_type = :tt)"; $params[':tt'] = $tt; }
}
if ($cv !== '') {
  if ($cv === '__EMPTY__') $where[] = "(t.conveyance_type IS NULL OR TRIM(t.conveyance_type)='')";
  else { $where[] = "(t.conveyance_type = :cv)"; $params[':cv'] = $cv; }
}
if ($pc !== '') {
  if ($pc === '__EMPTY__') $where[] = "(t.pickup_country IS NULL OR TRIM(t.pickup_country)='')";
  else { $where[] = "(t.pickup_country = :pc)"; $params[':pc'] = $pc; }
}
if ($uc !== '') {
  if ($uc === '__EMPTY__') $where[] = "(t.unloading_country IS NULL OR TRIM(t.unloading_country)='')";
  else { $where[] = "(t.unloading_country = :uc)"; $params[':uc'] = $uc; }
}
if ($from !== '') { $where[] = "(t.transport_date >= :from)"; $params[':from'] = $from; }
if ($to !== '')   { $where[] = "(t.transport_date <= :to)";   $params[':to'] = $to;   }

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// =======================
// KPIs
// =======================
$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM scope_transport_orders t
  LEFT JOIN scope_orders o ON o.id = t.order_id
  $whereSql
");
$stmt->execute($params);
$kpi_total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM scope_transport_orders t
  LEFT JOIN scope_orders o ON o.id = t.order_id
  $whereSql
  " . ($where ? " AND " : " WHERE ") . "(t.transport_date IS NULL OR t.transport_date='')"
);
$stmt->execute($params);
$kpi_sin_fecha = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM scope_transport_orders t
  LEFT JOIN scope_orders o ON o.id = t.order_id
  $whereSql
  " . ($where ? " AND " : " WHERE ") . "(o.order_number IS NULL OR TRIM(o.order_number)='')"
);
$stmt->execute($params);
$kpi_sin_orden = (int)$stmt->fetchColumn();

// =======================
// Tabla
// =======================
$sql = "
  SELECT
    t.transport_order_number, t.transport_order_uuid,
    t.transport_type, t.conveyance_type, t.transport_date,
    o.order_number, o.customer_name,
    t.pickup_partner_name, t.pickup_country, t.pickup_city,
    t.unloading_partner_name, t.unloading_country, t.unloading_city,
    t.pieces, t.gross_weight_kg, t.volume_m3,
    t.updated_at
  FROM scope_transport_orders t
  LEFT JOIN scope_orders o ON o.id = t.order_id
  $whereSql
  ORDER BY t.updated_at DESC
  LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export URL
$exportUrl = 'export_transport_orders.php?' . http_build_query([
  'q'=>$q,'tt'=>$tt,'cv'=>$cv,'pc'=>$pc,'uc'=>$uc,'from'=>$from,'to'=>$to,'limit'=>$limit
]);

// Popup URL (mismos filtros)
$graphsUrl = 'transport_orders_graficas.php?' . http_build_query([
  'q'=>$q,'tt'=>$tt,'cv'=>$cv,'pc'=>$pc,'uc'=>$uc,'from'=>$from,'to'=>$to,'limit'=>$limit
]);
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Transport Orders</title>

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

    input[type=text], input[type=date], select{
      padding:12px 12px;
      border-radius:12px;
      border:1px solid var(--fieldBorder);
      background:var(--field);
      color:var(--text);
      outline:none;
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

    .cards{display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:10px; margin-top:12px;}
    .stat{
      background:var(--field);
      border:1px solid var(--cardBorder);
      border-radius:14px;
      padding:12px;
      color:var(--text);
      text-decoration:none;
      display:block;
    }
    .stat:hover{border-color:rgba(255,208,0,.25);}
    .stat.active{border-color:var(--btn); box-shadow:0 0 0 2px rgba(255,208,0,.12) inset;}
    .stat .t{font-size:12px; color:var(--muted); margin-bottom:8px; line-height:1.2;}
    .stat .n{font-size:18px; font-weight:900;}

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

    @media(max-width:1100px){ .cards{grid-template-columns:repeat(3, minmax(0,1fr));} }
    @media(max-width:980px){
      .wrap{padding:12px;}
      .kpi{grid-template-columns:repeat(2, minmax(0,1fr));}
      .cards{grid-template-columns:repeat(2, minmax(0,1fr));}
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
      window.open(url, 'core_scope_graficas_transport', `width=${w},height=${h},left=${left},top=${top},scrollbars=yes,resizable=yes`);
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
        <h1>CORE_SCOPE · Transport Orders</h1>
        <div class="muted">Órdenes de transporte (pickup → unloading) con filtros y clasificación por tipo.</div>

        <h1 style="margin-top:14px;">Clasificación por tipo de transporte</h1>
        <div class="muted">Haz clic en una tarjeta para filtrar.</div>

        <div class="cards">
          <?php foreach ($cards as $c): ?>
            <?php
              $raw = $c['raw'];
              $hrefTt = ($raw === '') ? '__EMPTY__' : $raw;
              $href = 'transport_orders.php?' . http_build_query([
                'tt'=>$hrefTt,'q'=>$q,'cv'=>$cv,'pc'=>$pc,'uc'=>$uc,'from'=>$from,'to'=>$to,'limit'=>$limit
              ]);
              $active = is_active_card($tt, $raw);
            ?>
            <a class="stat <?= $active ? 'active' : '' ?>" href="<?=h($href)?>">
              <div class="t"><?=h($c['label'])?></div>
              <div class="n"><?=h((string)$c['count'])?></div>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- ===== FILTROS ===== -->
        <div class="muted" style="margin-top:16px; font-weight:700; letter-spacing:.2px;">
          Filtros
        </div>

        <form method="get" class="row" style="margin-top:12px;">
          <div>
            <label>Buscar</label>
            <input type="text" name="q" value="<?=h($q)?>" placeholder="Transport order / UUID / orden / cliente / partner...">
          </div>

          <div>
            <label>Transport type</label>
            <select name="tt">
              <option value="">Todos</option>
              <option value="__EMPTY__" <?= $tt==='__EMPTY__' ? 'selected' : '' ?>>Sin tipo</option>
              <?php foreach ($typeRows as $r): ?>
                <?php $v=(string)$r['v']; if ($v==='') continue; ?>
                <option value="<?=h($v)?>" <?= $tt===$v ? 'selected' : '' ?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Conveyance type</label>
            <select name="cv">
              <option value="">Todos</option>
              <option value="__EMPTY__" <?= $cv==='__EMPTY__' ? 'selected' : '' ?>>Sin conveyance</option>
              <?php foreach ($conRows as $r): ?>
                <?php $v=(string)$r['v']; if ($v==='') continue; ?>
                <option value="<?=h($v)?>" <?= $cv===$v ? 'selected' : '' ?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Pickup country</label>
            <select name="pc">
              <option value="">Todos</option>
              <option value="__EMPTY__" <?= $pc==='__EMPTY__' ? 'selected' : '' ?>>Sin país</option>
              <?php foreach ($countryPickRows as $r): ?>
                <?php $v=(string)$r['v']; if ($v==='') continue; ?>
                <option value="<?=h($v)?>" <?= $pc===$v ? 'selected' : '' ?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Unloading country</label>
            <select name="uc">
              <option value="">Todos</option>
              <option value="__EMPTY__" <?= $uc==='__EMPTY__' ? 'selected' : '' ?>>Sin país</option>
              <?php foreach ($countryUnlRows as $r): ?>
                <?php $v=(string)$r['v']; if ($v==='') continue; ?>
                <option value="<?=h($v)?>" <?= $uc===$v ? 'selected' : '' ?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Desde (transport_date)</label>
            <input type="date" name="from" value="<?=h($from)?>">
          </div>

          <div>
            <label>Hasta (transport_date)</label>
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

          <!-- ===== BOTONES ===== -->
          <div style="flex-basis:100%; height:0;"></div>
          <div class="muted" style="margin-top:2px; font-weight:700; letter-spacing:.2px;">
            Acciones y navegación
          </div>

          <div class="row" style="align-items:center; width:100%; margin-top:10px;">
            <button type="submit">Aplicar</button>
            <a class="reset" href="<?=h('transport_orders.php')?>">Restablecer</a>
            <a class="btn secondary" href="<?=h('graficas.php')?>">Volver</a>
            <a class="btn" href="<?=h($exportUrl)?>">Exportar CSV</a>

            <!-- Gráficas (popup) -->
            <a class="btn secondary" href="<?=h($graphsUrl)?>" id="btnGraficas" data-url="<?=h($graphsUrl)?>">Gráficas</a>
          </div>
        </form>

        <div class="kpi">
          <div class="pill"><b>Transport orders (filtro)</b><span><?=h((string)$kpi_total)?></span></div>
          <div class="pill"><b>Sin transport_date</b><span><?=h((string)$kpi_sin_fecha)?></span></div>
          <div class="pill"><b>Sin orden ligada</b><span><?=h((string)$kpi_sin_orden)?></span></div>
          <div class="pill"><b>Mostrando</b><span><?=h((string)count($rows))?></span></div>
        </div>

        <h1 style="margin-top:16px;">Transport Orders</h1>
        <div class="muted">Vista rápida con datos clave (pickup → unloading).</div>

        <div class="tableWrap">
          <table>
            <thead>
              <tr>
                <th>Transport Order</th>
                <th>Tipo</th>
                <th>Conveyance</th>
                <th>Fecha</th>
                <th>Orden</th>
                <th>Cliente</th>
                <th>Pickup</th>
                <th>Unloading</th>
                <th class="num">Piezas</th>
                <th class="num">Peso (kg)</th>
                <th class="num">Volumen (m³)</th>
                <th>Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <?php
                  $pickup = trim((string)($r['pickup_partner_name'] ?? ''));
                  $pc2 = trim((string)($r['pickup_country'] ?? ''));
                  $pcity = trim((string)($r['pickup_city'] ?? ''));
                  $pickupLine = $pickup;
                  if ($pc2 !== '' || $pcity !== '') {
                    $pickupLine .= ($pickupLine !== '' ? ' · ' : '');
                    $pickupLine .= trim($pc2 . ' ' . $pcity);
                  }

                  $unl = trim((string)($r['unloading_partner_name'] ?? ''));
                  $uc2 = trim((string)($r['unloading_country'] ?? ''));
                  $ucity = trim((string)($r['unloading_city'] ?? ''));
                  $unlLine = $unl;
                  if ($uc2 !== '' || $ucity !== '') {
                    $unlLine .= ($unlLine !== '' ? ' · ' : '');
                    $unlLine .= trim($uc2 . ' ' . $ucity);
                  }
                ?>
                <tr>
                  <td><?=h((string)($r['transport_order_number'] ?? ''))?></td>
                  <td><?=h((string)($r['transport_type'] ?? ''))?></td>
                  <td><?=h((string)($r['conveyance_type'] ?? ''))?></td>
                  <td><?=h((string)($r['transport_date'] ?? ''))?></td>
                  <td><?=h((string)($r['order_number'] ?? ''))?></td>
                  <td><?=h((string)($r['customer_name'] ?? ''))?></td>
                  <td><?=h($pickupLine)?></td>
                  <td><?=h($unlLine)?></td>
                  <td class="num"><?=h((string)($r['pieces'] ?? ''))?></td>
                  <td class="num"><?=h((string)($r['gross_weight_kg'] ?? ''))?></td>
                  <td class="num"><?=h((string)($r['volume_m3'] ?? ''))?></td>
                  <td><?=h((string)($r['updated_at'] ?? ''))?></td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$rows): ?>
                <tr><td colspan="12" class="muted">Sin datos.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /card -->

    </div><!-- /container -->
  </div><!-- /wrap -->
</div><!-- /app -->
</body>
</html>
