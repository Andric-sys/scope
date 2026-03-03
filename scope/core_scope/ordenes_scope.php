<?php
/**
 * ordenes_scope.php — Órdenes (tabla + KPIs + tarjetas + filtros)
 * AJUSTES APLICADOS:
 * 1) “Piel” unificada con graficas.php / jobcosting_totals.php:
 *    - Topbar dentro de .wrap (CORE_SCOPE + themeBtn)
 *    - Tema persistente en localStorage('core_scope.theme') + iconos sol/luna
 *    - Body con overflow:hidden y scroll interno
 *    - Bloques “Filtros” y “Acciones y navegación”
 * 2) Botón “Gráficas” que abre popup con los mismos filtros. * 3) Protegido con autenticación de CGL *    - Archivo destino sugerido: ordenes_scope_graficas.php
 */

declare(strict_types=1);

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
function build_like(string $q): string { return '%' . trim($q) . '%'; }
function valid_ymd(string $s): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }

// =======================
// GET filtros
// =======================
$q = trim((string)($_GET['q'] ?? ''));
$st = trim((string)($_GET['st'] ?? '')); // estatus exact o __EMPTY__
$dateField = trim((string)($_GET['df'] ?? 'transport_date')); // transport_date|order_date|economic_date
if (!in_array($dateField, ['transport_date','order_date','economic_date'], true)) $dateField = 'transport_date';

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';

$cancelled = trim((string)($_GET['cancelled'] ?? '')); // ''|0|1
$blocked   = trim((string)($_GET['blocked'] ?? ''));   // ''|0|1

$limit = (int)($_GET['limit'] ?? 25);
if ($limit <= 0) $limit = 25;
if ($limit > 200) $limit = 200;

// Dropdown estatus
$statusRows = $pdo->query("
  SELECT COALESCE(NULLIF(TRIM(financial_status),''),'') AS st, COUNT(*) c
  FROM scope_orders
  GROUP BY COALESCE(NULLIF(TRIM(financial_status),''),'')
  ORDER BY c DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Cards estatus
$cards = [];
foreach ($statusRows as $r) {
  $raw = (string)$r['st'];
  $cards[] = [
    'raw' => $raw,
    'label' => $raw==='' ? 'Sin estatus' : es_financial_status($raw),
    'count' => (int)$r['c'],
  ];
}

function is_active_card(string $selected, string $raw): bool {
  if ($selected === '__EMPTY__' && $raw === '') return true;
  return $selected !== '' && $selected === $raw;
}

// =======================
// WHERE
// =======================
$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(order_number LIKE :q OR customer_name LIKE :q OR scope_uuid LIKE :q OR legacy_identifier LIKE :q)";
  $params[':q'] = build_like($q);
}
if ($st !== '') {
  if ($st === '__EMPTY__') $where[] = "(financial_status IS NULL OR TRIM(financial_status)='')";
  else { $where[] = "(financial_status = :st)"; $params[':st'] = $st; }
}
if ($from !== '') { $where[] = "($dateField >= :from)"; $params[':from'] = $from; }
if ($to !== '') { $where[] = "($dateField <= :to)"; $params[':to'] = $to; }
if ($cancelled !== '' && ($cancelled==='0' || $cancelled==='1')) {
  $where[] = "(cancelled = :c)"; $params[':c'] = (int)$cancelled;
}
if ($blocked !== '' && ($blocked==='0' || $blocked==='1')) {
  $where[] = "(blocked = :b)"; $params[':b'] = (int)$blocked;
}

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// =======================
// KPIs
// =======================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM scope_orders $whereSql");
$stmt->execute($params);
$kpi_total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scope_orders $whereSql" . ($where ? " AND " : " WHERE ") . "(financial_status IS NULL OR TRIM(financial_status)='')");
$stmt->execute($params);
$kpi_sin_estatus = (int)$stmt->fetchColumn();

// =======================
// Tabla
// =======================
$sql = "
  SELECT
    id, order_number, customer_name, customer_country,
    $dateField AS fecha_ref,
    financial_status, cancelled, blocked, module, conveyance_type,
    last_modified_utc, updated_at
  FROM scope_orders
  $whereSql
  ORDER BY updated_at DESC
  LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export URL
$exportUrl = 'export_scope_orders_full.php?' . http_build_query([
  'q'=>$q,'st'=>$st,'df'=>$dateField,'from'=>$from,'to'=>$to,'cancelled'=>$cancelled,'blocked'=>$blocked,'limit'=>$limit
]);

// Popup URL (mismos filtros)
$graphsUrl = 'ordenes_scope_graficas.php?' . http_build_query([
  'q'=>$q,'st'=>$st,'df'=>$dateField,'from'=>$from,'to'=>$to,'cancelled'=>$cancelled,'blocked'=>$blocked,'limit'=>$limit
]);
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Órdenes</title>

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

    /* tarjetas de estatus */
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

    .tag{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:999px;
      border:1px solid var(--cardBorder); background:var(--field);
      font-weight:900; font-size:12px;
    }
    .tag.ok{border-color:rgba(0,180,90,.35);}
    .tag.bad{border-color:rgba(220,50,70,.45);}

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
      window.open(url, 'core_scope_graficas_ordenes', `width=${w},height=${h},left=${left},top=${top},scrollbars=yes,resizable=yes`);
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
        <h1>CORE_SCOPE · Órdenes</h1>
        <div class="muted">Filtros operativos + clasificación por estatus financiero.</div>

        <h1 style="margin-top:14px;">Clasificación por estatus financiero</h1>
        <div class="muted">Haz clic en una tarjeta para filtrar.</div>

        <div class="cards">
          <?php foreach ($cards as $c): ?>
            <?php
              $raw = $c['raw'];
              $hrefSt = ($raw === '') ? '__EMPTY__' : $raw;
              $href = 'ordenes_scope.php?' . http_build_query([
                'st'=>$hrefSt,'q'=>$q,'df'=>$dateField,'from'=>$from,'to'=>$to,'cancelled'=>$cancelled,'blocked'=>$blocked,'limit'=>$limit
              ]);
              $active = is_active_card($st, $raw);
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
            <input type="text" name="q" value="<?=h($q)?>" placeholder="Orden / UUID / cliente...">
          </div>

          <div>
            <label>Estatus financiero</label>
            <select name="st">
              <option value="">Todos</option>
              <option value="__EMPTY__" <?= $st==='__EMPTY__' ? 'selected' : '' ?>>Sin estatus</option>
              <?php foreach ($statusRows as $r): ?>
                <?php $raw=(string)$r['st']; if ($raw==='') continue; ?>
                <option value="<?=h($raw)?>" <?= $st===$raw ? 'selected' : '' ?>><?=h(es_financial_status($raw))?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label>Fecha para rango</label>
            <select name="df">
              <option value="transport_date" <?= $dateField==='transport_date'?'selected':'' ?>>Fecha de transporte</option>
              <option value="order_date" <?= $dateField==='order_date'?'selected':'' ?>>Fecha de orden</option>
              <option value="economic_date" <?= $dateField==='economic_date'?'selected':'' ?>>Fecha económica</option>
            </select>
          </div>

          <div>
            <label>Desde</label>
            <input type="date" name="from" value="<?=h($from)?>">
          </div>

          <div>
            <label>Hasta</label>
            <input type="date" name="to" value="<?=h($to)?>">
          </div>

          <div>
            <label>Cancelada</label>
            <select name="cancelled">
              <option value="" <?= $cancelled===''?'selected':'' ?>>Todas</option>
              <option value="0" <?= $cancelled==='0'?'selected':'' ?>>No</option>
              <option value="1" <?= $cancelled==='1'?'selected':'' ?>>Sí</option>
            </select>
          </div>

          <div>
            <label>Bloqueada</label>
            <select name="blocked">
              <option value="" <?= $blocked===''?'selected':'' ?>>Todas</option>
              <option value="0" <?= $blocked==='0'?'selected':'' ?>>No</option>
              <option value="1" <?= $blocked==='1'?'selected':'' ?>>Sí</option>
            </select>
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
            <a class="reset" href="<?=h('ordenes_scope.php')?>">Restablecer</a>
            <a class="btn secondary" href="<?=h('graficas.php')?>">Volver</a>
            <a class="btn" href="<?=h($exportUrl)?>">Exportar CSV</a>

            <!-- NUEVO: Gráficas (popup) -->
            <a class="btn secondary" href="<?=h($graphsUrl)?>" id="btnGraficas" data-url="<?=h($graphsUrl)?>">Gráficas</a>
          </div>
        </form>

        <div class="kpi">
          <div class="pill"><b>Órdenes (filtro)</b><span><?=h((string)$kpi_total)?></span></div>
          <div class="pill"><b>Sin estatus</b><span><?=h((string)$kpi_sin_estatus)?></span></div>
          <div class="pill"><b>Campo fecha</b><span><?=h($dateField)?></span></div>
          <div class="pill"><b>Mostrando</b><span><?=h((string)count($rows))?></span></div>
        </div>

        <h1 style="margin-top:16px;">Órdenes</h1>
        <div class="muted">Vista rápida con datos clave.</div>

        <div class="tableWrap">
          <table>
            <thead>
              <tr>
                <th>Orden</th>
                <th>Cliente</th>
                <th>País</th>
                <th><?=h($dateField)?></th>
                <th>Estatus financiero</th>
                <th>Flags</th>
                <th>Módulo</th>
                <th>Conveyance</th>
                <th>Last Modified (UTC)</th>
                <th>Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <?php
                  $flags = [];
                  if ((int)$r['cancelled'] === 1) $flags[] = '<span class="tag bad">CANCELADA</span>';
                  if ((int)$r['blocked'] === 1) $flags[] = '<span class="tag bad">BLOQUEADA</span>';
                  if (!$flags) $flags[] = '<span class="tag ok">OK</span>';
                ?>
                <tr>
                  <td><?=h((string)$r['order_number'])?></td>
                  <td><?=h((string)($r['customer_name'] ?? ''))?></td>
                  <td><?=h((string)($r['customer_country'] ?? ''))?></td>
                  <td><?=h((string)($r['fecha_ref'] ?? ''))?></td>
                  <td><?=h(es_financial_status((string)($r['financial_status'] ?? '')))?></td>
                  <td><?=implode(' ', $flags)?></td>
                  <td><?=h((string)($r['module'] ?? ''))?></td>
                  <td><?=h((string)($r['conveyance_type'] ?? ''))?></td>
                  <td><?=h((string)($r['last_modified_utc'] ?? ''))?></td>
                  <td><?=h((string)($r['updated_at'] ?? ''))?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="10" class="muted">Sin datos.</td></tr>
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
