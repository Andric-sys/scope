<?php
declare(strict_types=1);

require __DIR__ . '/core_scope/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

if (!function_exists('h')) {
  function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* =========================
   Filtro por mes (YYYY-MM)
========================= */
$mes = (string)($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

$inicio = $mes . '-01';
$fin = date('Y-m-t', strtotime($inicio));

$sql = "
  SELECT
    j.id,
    o.order_number,
    o.customer_name,
    j.entry_type,
    j.charge_type_code,
    COALESCE(j.cost_center_code, o.cost_center_code, '') AS office,
    o.conveyance_type,
    j.partner_code,
    j.partner_name,

    j.invoice_date,
    j.economic_date,
    j.booking_date,

    j.entry_number,
    j.external_number,

    j.amount_currency,
    j.amount_value,
    j.tax_value,
    j.local_amount_value,
    j.local_tax_value,

    j.updated_at
  FROM scope_jobcosting_entries j
  LEFT JOIN scope_orders o ON o.id = j.order_id
  WHERE j.invoice_date IS NOT NULL
    AND DATE(j.invoice_date) BETWEEN :inicio AND :fin
  ORDER BY j.invoice_date DESC, j.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':inicio' => $inicio, ':fin' => $fin]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>CORE_SCOPE · Vistas crudas</title>

  <style>
    :root{
      --bg:#0b0c10; --text:#eaeaea; --muted:#a7adbd;
      --card:#12131a; --cardBorder:#242634;
      --th:#151826; --thText:#cbd3ea;
      --field:#0f1016; --fieldBorder:#2a2d3f;
      --btn:#ffd000; --btnText:#111;
      --btn2:#2a2d3f; --btn2Text:#fff;
      --shadow: rgba(0,0,0,.25);
    }
    html[data-theme="light"]{
      --bg:#f6f7fb; --text:#14161f; --muted:#5b6173;
      --card:#ffffff; --cardBorder:#e6e8f0;
      --th:#f2f4fa; --thText:#2a2f42;
      --field:#ffffff; --fieldBorder:#d6d9e6;
      --btn:#ffd000; --btnText:#111;
      --btn2:#111827; --btn2Text:#fff;
      --shadow: rgba(17,24,39,.10);
    }
    html, body { height:100%; }
    * { box-sizing:border-box; }
    body{
      margin:0; background:var(--bg); color:var(--text);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
      overflow:hidden;
    }
    .app{ height:100vh; display:flex; flex-direction:column; overflow:hidden; }
    .wrap{ flex:1; overflow:auto; padding:18px; }
    .container{max-width:1700px; margin:0 auto;}

    .topbar{display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px;}
    .brand h1{font-size:18px; margin:0 0 4px;}
    .muted{color:var(--muted); font-size:13px;}

    .themeBtn{
      width:44px; height:44px; border-radius:14px;
      background:var(--field); border:1px solid var(--fieldBorder);
      display:inline-flex; align-items:center; justify-content:center;
      cursor:pointer;
    }
    .themeBtn svg{width:20px; height:20px; fill:currentColor; color:var(--text);}

    .card{
      background:var(--card);
      border:1px solid var(--cardBorder);
      border-radius:14px;
      padding:14px;
      box-shadow:0 10px 30px var(--shadow);
    }
    .actions{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;}
    button, a.btn{
      display:inline-flex; align-items:center; justify-content:center;
      padding:10px 12px; border-radius:12px;
      font-weight:800; border:0; cursor:pointer; text-decoration:none;
    }
    button.primary, a.btn.primary{background:var(--btn); color:var(--btnText);}
    button.secondary, a.btn.secondary{background:var(--btn2); color:var(--btn2Text);}

    .pager{
      display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;
      margin:10px 0 0;
    }
    .pager .left{display:flex; gap:10px; align-items:center; flex-wrap:wrap;}
    .pager select, .pager input{
      background:var(--field);
      border:1px solid var(--fieldBorder);
      color:var(--text);
      border-radius:10px;
      padding:9px 10px;
      font-size:13px;
      outline:none;
    }
    .pager .meta{color:var(--muted); font-size:13px;}

    .tableWrap{
      overflow:auto;
      border-radius:12px;
      border:1px solid var(--cardBorder);
      max-height: calc(100vh - 240px);
    }
    table{width:100%; border-collapse:separate; border-spacing:0; min-width:1600px;}
    th, td{padding:9px 8px; border-bottom:1px solid var(--cardBorder); font-size:12px; white-space:nowrap;}
    th{background:var(--th); color:var(--thText); text-align:left; position:sticky; top:0; z-index:2;}
    .num{text-align:right; font-variant-numeric:tabular-nums;}
  </style>

  <script>
    (function(){
      try{
        const saved = localStorage.getItem('core_scope.theme');
        document.documentElement.setAttribute('data-theme', saved || 'dark');
      }catch(e){}
    })();
  </script>
</head>
<body>
  <div class="app">
    <div class="wrap">
      <div class="container">

        <div class="topbar">
          <div class="brand">
            <h1>Vistas crudas</h1>
            <div class="muted">
              Mes: <b><?= h($mes) ?></b> (<?= h($inicio) ?> a <?= h($fin) ?>) ·
              Filas: <b><?= number_format(count($rows)) ?></b>
            </div>
          </div>
          <button id="themeBtn" class="themeBtn" type="button" aria-label="Cambiar tema" title="Cambiar tema">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM4 11a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2h1Zm18 0a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2h1ZM5.64 4.22a1 1 0 0 1 1.41 0l.7.7A1 1 0 1 1 6.34 6.34l-.7-.7a1 1 0 0 1 0-1.42Zm11.31 13.44a1 1 0 0 1 1.41 0l.7.7a1 1 0 1 1-1.41 1.41l-.7-.7a1 1 0 0 1 0-1.41ZM19.78 5.64a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0ZM6.34 17.66a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0Z"/></svg>
          </button>
        </div>

        <div class="card">
          <div class="actions">
            <button class="primary" type="button" id="exportExcel">Exportar (mes)</button>
            <a class="btn secondary" href="comparar_excel.php?mes=<?= h($mes) ?>">Comparar Excel</a>
            <a class="btn secondary" href="core_scope/scope_menu.php">Volver al menú</a>
          </div>

          <div class="pager">
            <div class="left">

              <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0;">
                <label class="meta">Mes</label>
                <input type="month" name="mes" value="<?= h($mes) ?>">
                <button class="secondary" type="submit">Aplicar</button>
              </form>

              <span style="width:1px;height:26px;background:var(--cardBorder);display:inline-block;"></span>

              <label class="meta">Filas por página</label>
              <select id="pageSize">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="200">200</option>
                <option value="500">500</option>
              </select>

              <button class="secondary" type="button" id="prevPage">←</button>
              <button class="secondary" type="button" id="nextPage">→</button>

              <span class="meta" id="pageMeta"></span>
            </div>

            <div class="meta">Total filas mes: <?= number_format(count($rows)) ?></div>
          </div>

          <div class="tableWrap">
            <table id="t">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Orden</th>
                  <th>Cliente</th>
                  <th>Tipo</th>
                  <th>Concepto</th>
                  <th>Oficina</th>
                  <th>Tráfico</th>
                  <th>Partner</th>
                  <th>Factura</th>
                  <th>Fecha factura</th>
                  <th>Moneda</th>
                  <th class="num">Neto</th>
                  <th class="num">IVA</th>
                  <th class="num">Neto (local)</th>
                  <th class="num">IVA (local)</th>
                  <th>Fecha económica</th>
                  <th>Fecha booking</th>
                  <th>No. asiento</th>
                  <th>Actualizado</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r):
                $partner = trim(((string)($r['partner_code'] ?? '')).' '.((string)($r['partner_name'] ?? '')));
              ?>
                <tr>
                  <td><?= h($r['id']) ?></td>
                  <td><?= h($r['order_number']) ?></td>
                  <td><?= h($r['customer_name']) ?></td>
                  <td><?= h($r['entry_type']) ?></td>
                  <td><?= h($r['charge_type_code']) ?></td>
                  <td><?= h($r['office']) ?></td>
                  <td><?= h($r['conveyance_type']) ?></td>
                  <td><?= h($partner) ?></td>
                  <td><?= h($r['external_number']) ?></td>
                  <td><?= h((string)$r['invoice_date']) ?></td>
                  <td><?= h($r['amount_currency']) ?></td>
                  <td class="num"><?= number_format((float)$r['amount_value'], 2) ?></td>
                  <td class="num"><?= number_format((float)$r['tax_value'], 2) ?></td>
                  <td class="num"><?= number_format((float)$r['local_amount_value'], 2) ?></td>
                  <td class="num"><?= number_format((float)$r['local_tax_value'], 2) ?></td>
                  <td><?= h((string)$r['economic_date']) ?></td>
                  <td><?= h((string)$r['booking_date']) ?></td>
                  <td><?= h($r['entry_number']) ?></td>
                  <td><?= h((string)$r['updated_at']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>

<script>
  function initTheme(){
    const btn = document.getElementById('themeBtn');
    btn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try{ localStorage.setItem('core_scope.theme', next); }catch(e){}
    });
  }

  // paginación simple (solo visual)
  const table = document.getElementById('t');
  const allRows = Array.from(table.querySelectorAll('tbody tr'));
  const pageSizeEl = document.getElementById('pageSize');
  const meta = document.getElementById('pageMeta');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  let page = 1;
  function pageSize(){ return parseInt(pageSizeEl.value, 10) || 100; }
  function totalPages(){ return Math.max(1, Math.ceil(allRows.length / pageSize())); }

  function render(){
    const ps = pageSize();
    const tp = totalPages();
    if (page > tp) page = tp;
    if (page < 1) page = 1;

    const start = (page - 1) * ps;
    const end = start + ps;

    allRows.forEach((tr, i) => tr.style.display = (i >= start && i < end) ? '' : 'none');

    const showing = Math.max(0, Math.min(ps, allRows.length - start));
    meta.textContent = `Página ${page} de ${tp} · Mostrando ${showing} filas`;
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= tp;
  }

  prevBtn.addEventListener('click', () => { page--; render(); });
  nextBtn.addEventListener('click', () => { page++; render(); });
  pageSizeEl.addEventListener('change', () => { page = 1; render(); });

  // EXPORTAR TODO EL MES (todas las filas del tbody, no la página)
  function exportMes(){
    const headCells = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());

    const escapeHtml = (value) => (value ?? '').toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

    const bodyHtml = allRows.map(row => {
      const cells = Array.from(row.querySelectorAll('td'))
        .map(td => `<td>${escapeHtml(td.textContent.trim())}</td>`)
        .join('');
      return `<tr>${cells}</tr>`;
    }).join('');

    const html = `
      <html>
        <head><meta charset="UTF-8"></head>
        <body>
          <table border="1">
            <thead>
              <tr>${headCells.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr>
            </thead>
            <tbody>${bodyHtml}</tbody>
          </table>
        </body>
      </html>
    `;

    const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    const stamp = new Date().toISOString().slice(0,19).replace(/[T:]/g, '-');
    a.href = url;
    a.download = `vistas_crudas_<?= h($mes) ?>_${stamp}.xls`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  document.getElementById('exportExcel')?.addEventListener('click', exportMes);

  initTheme();
  render();
</script>
</body>
</html>