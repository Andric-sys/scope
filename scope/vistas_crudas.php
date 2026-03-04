<?php
declare(strict_types=1);

require __DIR__ . '/core_scope/conexion.php';
date_default_timezone_set('America/Mexico_City');

$pdo = db();

if (!function_exists('h')) {
	function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$sql = "
	SELECT
		j.id,
		j.order_id,
		j.entry_type,
		j.charge_type_code,
		COALESCE(j.cost_center_code, o.cost_center_code, '') AS office,
		j.booking_date,
		j.economic_date,
		j.invoice_date,
		j.amount_value,
		j.amount_currency,
		j.tax_value,
		j.local_amount_value,
		j.local_tax_value,
		j.partner_code,
		j.partner_name,
		j.entry_number,
		j.external_number,
		o.order_number,
		o.customer_name,
		o.conveyance_type,
		j.updated_at
	FROM scope_jobcosting_entries j
	LEFT JOIN scope_orders o ON o.id = j.order_id
	WHERE j.invoice_date IS NOT NULL
	ORDER BY j.invoice_date DESC, j.id DESC
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<title>CORE_SCOPE · Vistas Crudas (Facturas)</title>

	<style>
		:root{
			--bg:#0b0c10; --text:#eaeaea; --muted:#a7adbd;
			--card:#12131a; --cardBorder:#242634;
			--field:#0f1016; --fieldBorder:#2a2d3f;
			--th:#151826; --thText:#cbd3ea;
			--btn:#ffd000; --btnText:#111;
			--btn2:#2a2d3f; --btn2Text:#fff;
			--ok:#16a34a;
			--bad:#ef4444;
			--shadow: rgba(0,0,0,.25);
		}
		html[data-theme="light"]{
			--bg:#f6f7fb; --text:#14161f; --muted:#5b6173;
			--card:#ffffff; --cardBorder:#e6e8f0;
			--field:#ffffff; --fieldBorder:#d6d9e6;
			--th:#f2f4fa; --thText:#2a2f42;
			--btn:#ffd000; --btnText:#111;
			--btn2:#111827; --btn2Text:#fff;
			--ok:#166534;
			--bad:#991b1b;
			--shadow: rgba(17,24,39,.10);
		}

		html, body { height: 100%; }
		* { box-sizing: border-box; }
		body{
			margin:0;
			background:var(--bg);
			color:var(--text);
			font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
			overflow:hidden;
		}

		.app{ height:100vh; display:flex; flex-direction:column; overflow:hidden; }
		.wrap{ flex:1; overflow:auto; padding:18px; }
		.container{max-width:1700px; margin:0 auto;}

		.topbar{
			display:flex; align-items:center; justify-content:space-between; gap:10px;
			margin-bottom:12px;
		}
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

		.summary{display:grid; grid-template-columns:repeat(5, minmax(0,1fr)); gap:10px; margin-bottom:12px;}
		.pill{background:var(--field); border:1px solid var(--cardBorder); border-radius:12px; padding:10px;}
		.pill b{display:block; font-size:12px; color:var(--muted); margin-bottom:4px;}
		.pill span{font-size:16px; font-weight:900;}

		.tableWrap{
			overflow:auto;
			border-radius:12px;
			border:1px solid var(--cardBorder);
			max-height: calc(100vh - 280px);
		}

		table{width:100%; border-collapse:separate; border-spacing:0; min-width:1700px;}
		th, td{padding:9px 8px; border-bottom:1px solid var(--cardBorder); font-size:12px; white-space:nowrap;}
		th{background:var(--th); color:var(--thText); text-align:left; position:sticky; top:0; z-index:2;}
		thead tr.filters th{top:34px; z-index:3; padding:6px 8px;}
		thead tr.filters input, thead tr.filters select{
			width:100%;
			background:var(--field);
			border:1px solid var(--fieldBorder);
			color:var(--text);
			border-radius:8px;
			padding:7px 8px;
			font-size:12px;
			outline:none;
		}
		.dateRange{display:grid; grid-template-columns:1fr 1fr; gap:6px;}
		.num{text-align:right; font-variant-numeric:tabular-nums;}
		.center{text-align:center;}

		tfoot td{
			position:sticky;
			bottom:0;
			z-index:2;
			background:var(--th);
			color:var(--thText);
			font-weight:900;
		}

		.tag{display:inline-block; border:1px solid var(--cardBorder); border-radius:999px; padding:3px 8px; font-weight:700;}
		.tag.income{border-color:rgba(22,163,74,.5); color:var(--ok);}
		.tag.payable{border-color:rgba(239,68,68,.5); color:var(--bad);}

		@media(max-width:1200px){
			.summary{grid-template-columns:repeat(2, minmax(0,1fr));}
		}
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
						<h1>Vistas Crudas · Facturas</h1>
						<div class="muted">Consulta completa de registros facturados con filtros por columna y totales en tiempo real.</div>
					</div>

					<button id="themeBtn" class="themeBtn" type="button" aria-label="Cambiar tema" title="Cambiar tema">
						<svg id="iconSun" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.76 4.84 5.34 3.42 3.92 4.84l1.42 1.42 1.42-1.42Zm10.48 0 1.42-1.42 1.42 1.42-1.42 1.42-1.42-1.42ZM12 5a1 1 0 0 1-1-1V2a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1Zm0 17a1 1 0 0 1-1-1v-2a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1ZM5 13H3a1 1 0 1 1 0-2h2a1 1 0 1 1 0 2Zm18 0h-2a1 1 0 1 1 0-2h2a1 1 0 1 1 0 2ZM6.34 18.66l-1.42 1.42-1.42-1.42 1.42-1.42 1.42 1.42Zm13.16 1.42-1.42-1.42 1.42-1.42 1.42 1.42-1.42 1.42ZM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z"/></svg>
					</button>
				</div>

				<div class="card">
					<div class="actions">
						<button class="primary" type="button" id="clearFilters">Limpiar filtros</button>
						<a class="btn secondary" href="core_scope/scope_menu.php">Volver al menú</a>
					</div>

					<div class="summary" id="summaryTop">
						<div class="pill"><b>Filas visibles</b><span id="sumRows">0</span></div>
						<div class="pill"><b>Importe (orig.)</b><span id="sumAmount">$0.00</span></div>
						<div class="pill"><b>IVA (orig.)</b><span id="sumTax">$0.00</span></div>
						<div class="pill"><b>Importe local</b><span id="sumLocalAmount">$0.00</span></div>
						<div class="pill"><b>IVA local</b><span id="sumLocalTax">$0.00</span></div>
					</div>

					<div class="tableWrap">
						<table id="invoiceTable">
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
									<th>Fecha factura</th>
									<th>Fecha económica</th>
									<th>Fecha booking</th>
									<th>No. asiento</th>
									<th>No. externo</th>
									<th>Moneda</th>
									<th class="num">Importe</th>
									<th class="num">IVA</th>
									<th class="num">Importe local</th>
									<th class="num">IVA local</th>
									<th>Actualizado</th>
								</tr>
								<tr class="filters">
									<th><input type="text" data-filter="id" placeholder="ID"></th>
									<th><input type="text" data-filter="order" placeholder="Orden"></th>
									<th><input type="text" data-filter="customer" placeholder="Cliente"></th>
									<th>
										<select data-filter="entry_type">
											<option value="">Todos</option>
										</select>
									</th>
									<th><input type="text" data-filter="concept" placeholder="Concepto"></th>
									<th><input type="text" data-filter="office" placeholder="Oficina"></th>
									<th><input type="text" data-filter="traffic" placeholder="Tráfico"></th>
									<th><input type="text" data-filter="partner" placeholder="Partner"></th>
									<th>
										<div class="dateRange">
											<input type="date" data-filter="invoice_from" title="Desde">
											<input type="date" data-filter="invoice_to" title="Hasta">
										</div>
									</th>
									<th>
										<div class="dateRange">
											<input type="date" data-filter="economic_from" title="Desde">
											<input type="date" data-filter="economic_to" title="Hasta">
										</div>
									</th>
									<th>
										<div class="dateRange">
											<input type="date" data-filter="booking_from" title="Desde">
											<input type="date" data-filter="booking_to" title="Hasta">
										</div>
									</th>
									<th><input type="text" data-filter="entry_number" placeholder="Asiento"></th>
									<th><input type="text" data-filter="external_number" placeholder="Externo"></th>
									<th><input type="text" data-filter="currency" placeholder="Moneda"></th>
									<th><input type="number" step="0.01" data-filter="amount_min" placeholder="Min"></th>
									<th><input type="number" step="0.01" data-filter="tax_min" placeholder="Min"></th>
									<th><input type="number" step="0.01" data-filter="local_amount_min" placeholder="Min"></th>
									<th><input type="number" step="0.01" data-filter="local_tax_min" placeholder="Min"></th>
									<th><input type="text" data-filter="updated" placeholder="YYYY-MM-DD"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($rows as $r):
									$entryType = trim((string)$r['entry_type']);
									$isIncome = stripos($entryType, 'income') !== false;
									$typeClass = $isIncome ? 'income' : 'payable';
									$partner = trim(((string)($r['partner_code'] ?? '')) . ' ' . ((string)($r['partner_name'] ?? '')));
								?>
								<tr
									data-id="<?= h($r['id']) ?>"
									data-order="<?= h($r['order_number']) ?>"
									data-customer="<?= h($r['customer_name']) ?>"
									data-entry_type="<?= h($entryType) ?>"
									data-concept="<?= h($r['charge_type_code']) ?>"
									data-office="<?= h($r['office']) ?>"
									data-traffic="<?= h($r['conveyance_type']) ?>"
									data-partner="<?= h($partner) ?>"
									data-invoice="<?= h((string)$r['invoice_date']) ?>"
									data-economic="<?= h((string)$r['economic_date']) ?>"
									data-booking="<?= h((string)$r['booking_date']) ?>"
									data-entry_number="<?= h($r['entry_number']) ?>"
									data-external_number="<?= h($r['external_number']) ?>"
									data-currency="<?= h($r['amount_currency']) ?>"
									data-amount="<?= h((string)($r['amount_value'] ?? '0')) ?>"
									data-tax="<?= h((string)($r['tax_value'] ?? '0')) ?>"
									data-local_amount="<?= h((string)($r['local_amount_value'] ?? '0')) ?>"
									data-local_tax="<?= h((string)($r['local_tax_value'] ?? '0')) ?>"
									data-updated="<?= h((string)$r['updated_at']) ?>"
								>
									<td><?= h($r['id']) ?></td>
									<td><?= h($r['order_number']) ?></td>
									<td><?= h($r['customer_name']) ?></td>
									<td><span class="tag <?= $typeClass ?>"><?= h($entryType) ?></span></td>
									<td><?= h($r['charge_type_code']) ?></td>
									<td><?= h($r['office']) ?></td>
									<td><?= h($r['conveyance_type']) ?></td>
									<td><?= h($partner) ?></td>
									<td><?= h((string)$r['invoice_date']) ?></td>
									<td><?= h((string)$r['economic_date']) ?></td>
									<td><?= h((string)$r['booking_date']) ?></td>
									<td><?= h($r['entry_number']) ?></td>
									<td><?= h($r['external_number']) ?></td>
									<td><?= h($r['amount_currency']) ?></td>
									<td class="num"><?= number_format((float)$r['amount_value'], 2) ?></td>
									<td class="num"><?= number_format((float)$r['tax_value'], 2) ?></td>
									<td class="num"><?= number_format((float)$r['local_amount_value'], 2) ?></td>
									<td class="num"><?= number_format((float)$r['local_tax_value'], 2) ?></td>
									<td><?= h((string)$r['updated_at']) ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<td colspan="14">Totales de filas visibles</td>
									<td class="num" id="footAmount">0.00</td>
									<td class="num" id="footTax">0.00</td>
									<td class="num" id="footLocalAmount">0.00</td>
									<td class="num" id="footLocalTax">0.00</td>
									<td class="center" id="footRows">0 filas</td>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>

			</div>
		</div>
	</div>

	<script>
		const moneyFmt = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

		function norm(v){ return (v || '').toString().trim().toLowerCase(); }
		function toNum(v){
			const n = Number.parseFloat((v ?? '').toString().replace(/,/g, ''));
			return Number.isFinite(n) ? n : 0;
		}
		function inDateRange(raw, from, to){
			if (!raw) return false;
			const d = raw.slice(0, 10);
			if (from && d < from) return false;
			if (to && d > to) return false;
			return true;
		}

		const table = document.getElementById('invoiceTable');
		const tbodyRows = Array.from(table.querySelectorAll('tbody tr'));
		const filterEls = Array.from(table.querySelectorAll('[data-filter]'));

		const summary = {
			rows: document.getElementById('sumRows'),
			amount: document.getElementById('sumAmount'),
			tax: document.getElementById('sumTax'),
			localAmount: document.getElementById('sumLocalAmount'),
			localTax: document.getElementById('sumLocalTax'),
			footAmount: document.getElementById('footAmount'),
			footTax: document.getElementById('footTax'),
			footLocalAmount: document.getElementById('footLocalAmount'),
			footLocalTax: document.getElementById('footLocalTax'),
			footRows: document.getElementById('footRows')
		};

		function getFilterValues(){
			const values = {};
			filterEls.forEach(el => values[el.dataset.filter] = (el.value || '').trim());
			return values;
		}

		function rowPasses(row, f){
			if (f.id && !norm(row.dataset.id).includes(norm(f.id))) return false;
			if (f.order && !norm(row.dataset.order).includes(norm(f.order))) return false;
			if (f.customer && !norm(row.dataset.customer).includes(norm(f.customer))) return false;
			if (f.entry_type && norm(row.dataset.entry_type) !== norm(f.entry_type)) return false;
			if (f.concept && !norm(row.dataset.concept).includes(norm(f.concept))) return false;
			if (f.office && !norm(row.dataset.office).includes(norm(f.office))) return false;
			if (f.traffic && !norm(row.dataset.traffic).includes(norm(f.traffic))) return false;
			if (f.partner && !norm(row.dataset.partner).includes(norm(f.partner))) return false;
			if (f.entry_number && !norm(row.dataset.entry_number).includes(norm(f.entry_number))) return false;
			if (f.external_number && !norm(row.dataset.external_number).includes(norm(f.external_number))) return false;
			if (f.currency && !norm(row.dataset.currency).includes(norm(f.currency))) return false;
			if (f.updated && !norm(row.dataset.updated).includes(norm(f.updated))) return false;

			if (f.invoice_from || f.invoice_to) {
				if (!inDateRange(row.dataset.invoice, f.invoice_from, f.invoice_to)) return false;
			}
			if (f.economic_from || f.economic_to) {
				if (!inDateRange(row.dataset.economic, f.economic_from, f.economic_to)) return false;
			}
			if (f.booking_from || f.booking_to) {
				if (!inDateRange(row.dataset.booking, f.booking_from, f.booking_to)) return false;
			}

			if (f.amount_min && toNum(row.dataset.amount) < toNum(f.amount_min)) return false;
			if (f.tax_min && toNum(row.dataset.tax) < toNum(f.tax_min)) return false;
			if (f.local_amount_min && toNum(row.dataset.local_amount) < toNum(f.local_amount_min)) return false;
			if (f.local_tax_min && toNum(row.dataset.local_tax) < toNum(f.local_tax_min)) return false;

			return true;
		}

		function refresh(){
			const f = getFilterValues();
			let count = 0;
			let amount = 0;
			let tax = 0;
			let localAmount = 0;
			let localTax = 0;

			tbodyRows.forEach(row => {
				const visible = rowPasses(row, f);
				row.style.display = visible ? '' : 'none';
				if (!visible) return;

				count += 1;
				amount += toNum(row.dataset.amount);
				tax += toNum(row.dataset.tax);
				localAmount += toNum(row.dataset.local_amount);
				localTax += toNum(row.dataset.local_tax);
			});

			summary.rows.textContent = String(count);
			summary.amount.textContent = '$' + moneyFmt.format(amount);
			summary.tax.textContent = '$' + moneyFmt.format(tax);
			summary.localAmount.textContent = '$' + moneyFmt.format(localAmount);
			summary.localTax.textContent = '$' + moneyFmt.format(localTax);

			summary.footAmount.textContent = moneyFmt.format(amount);
			summary.footTax.textContent = moneyFmt.format(tax);
			summary.footLocalAmount.textContent = moneyFmt.format(localAmount);
			summary.footLocalTax.textContent = moneyFmt.format(localTax);
			summary.footRows.textContent = count + ' filas';
		}

		function fillEntryTypeOptions(){
			const sel = table.querySelector('select[data-filter="entry_type"]');
			const values = Array.from(new Set(tbodyRows.map(r => (r.dataset.entry_type || '').trim()).filter(Boolean))).sort((a,b)=>a.localeCompare(b));
			values.forEach(v => {
				const opt = document.createElement('option');
				opt.value = v;
				opt.textContent = v;
				sel.appendChild(opt);
			});
		}

		function initThemeButton(){
			const btn = document.getElementById('themeBtn');
			btn?.addEventListener('click', () => {
				const current = document.documentElement.getAttribute('data-theme') || 'dark';
				const next = current === 'dark' ? 'light' : 'dark';
				document.documentElement.setAttribute('data-theme', next);
				try { localStorage.setItem('core_scope.theme', next); } catch(e){}
			});
		}

		function initFilters(){
			filterEls.forEach(el => el.addEventListener('input', refresh));
			document.getElementById('clearFilters')?.addEventListener('click', () => {
				filterEls.forEach(el => { el.value = ''; });
				refresh();
			});
		}

		fillEntryTypeOptions();
		initThemeButton();
		initFilters();
		refresh();
	</script>
</body>
</html>
