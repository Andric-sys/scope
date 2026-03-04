<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
$cssVars = core_brand_css_vars();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CORE SCOPE · Executive Dashboard</title>

  <style>
    <?= $cssVars ?>

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    .wrap{ max-width: 1550px; margin:0 auto; padding:18px 14px 26px; }

    .top-logo{
      display:flex;
      justify-content:center;
      margin-bottom: 12px;
    }
    .top-logo img{
      height: 52px;
      width: auto;
      object-fit: contain;
    }

    .topbar{
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 10px 26px rgba(2,6,23,.06);
      padding: 14px 16px;
      display:flex; gap:14px; flex-wrap:wrap; align-items:center; justify-content:space-between;
    }
    .brand{ display:flex; align-items:center; gap:12px; }
    .mark{
      width:44px;height:44px;border-radius:16px;
      background: linear-gradient(135deg, var(--core-blue), var(--core-navy));
      box-shadow: 0 14px 30px rgba(0,15,159,.18);
      position:relative; overflow:hidden;
    }
    .mark:after{
      content:""; position:absolute; inset:auto -22px -22px auto;
      width:46px;height:46px;border-radius:18px;
      background: rgba(156,193,247,.55); transform:rotate(18deg);
    }
    h1{ margin:0; font-size: 1.08rem; letter-spacing:.2px; }
    .muted{ color: var(--muted); font-weight:800; font-size:.9rem; }

    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid rgba(15,23,42,.12);
      background: rgba(2,6,23,.03);
      font-weight: 950;
      font-size:.82rem;
      color: var(--core-navy);
      white-space:nowrap;
    }

    .controls{
      display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    }
    .seg{
      display:inline-flex; border:1px solid rgba(15,23,42,.12);
      border-radius: 999px; overflow:hidden; background:#fff;
    }
    .seg button{
      border:0; background:transparent; cursor:pointer;
      padding:10px 12px; font-weight:950; color: var(--core-navy);
    }
    .seg button.active{
      background: var(--core-navy);
      color:#fff;
    }

    .select, .input{
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 10px 12px;
      font-weight: 850;
      background:#fff;
      outline:none;
    }

    .btn{
      border:1px solid rgba(15,23,42,.12);
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 950;
      cursor:pointer;
      display:inline-flex; align-items:center; gap:10px;
      background: #fff;
      color: var(--core-navy);
      text-decoration:none;
    }
    .btn-primary{
      background: var(--core-blue);
      color:#fff;
      border-color: rgba(1,113,226,.40);
    }

    .grid{
      margin-top: 14px;
      display:grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 14px;
    }
    .card{
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 10px 26px rgba(2,6,23,.06);
      padding: 14px 14px;
    }
    .kpi{ grid-column: span 3; }
    .kpi .label{ color: var(--muted); font-weight:950; font-size:.85rem; }
    .kpi .value{ font-weight:1000; font-size:1.55rem; margin-top:6px; color: var(--core-navy); }
    .kpi .sub{ color: var(--muted); font-weight:850; font-size:.82rem; margin-top:6px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .delta{
      font-weight:950;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(15,23,42,.12);
      background: rgba(2,6,23,.03);
      display:inline-flex; align-items:center; gap:8px;
    }
    .delta.up{ color:#065f46; background: rgba(156,193,247,.25); }
    .delta.down{ color:#b91c1c; background: rgba(255,0,0,.06); }

    .wide{ grid-column: span 8; }
    .side{ grid-column: span 4; }
    .half{ grid-column: span 6; }

    .title{
      display:flex; align-items:flex-end; justify-content:space-between; gap:10px; flex-wrap:wrap;
      margin-bottom: 8px;
    }
    .title .h{ font-weight:1000; }
    .title .s{ color: var(--muted); font-weight:850; font-size:.86rem; }

    .table{
      width:100%;
      border-collapse: collapse;
      font-size:.92rem;
    }
    .table th{
      text-align:left;
      background: var(--core-navy);
      color:#fff;
      padding: 10px 10px;
      font-weight: 950;
    }
    .table td{
      padding: 10px 10px;
      border-bottom: 1px solid rgba(15,23,42,.08);
      vertical-align: top;
    }
    .link{
      cursor:pointer;
      color: var(--core-blue-700);
      font-weight:950;
      text-decoration:none;
    }
    .link:hover{ text-decoration: underline; }

    .modal-backdrop{
      position: fixed; inset:0;
      background: rgba(2,6,23,.55);
      display:none;
      align-items:center;
      justify-content:center;
      padding: 18px;
      z-index: 9999;
    }
    .modal{
      width: min(1100px, 96vw);
      max-height: 86vh;
      overflow:auto;
      background: #fff;
      border-radius: 18px;
      border:1px solid rgba(255,255,255,.12);
      box-shadow: 0 30px 80px rgba(2,6,23,.35);
    }
    .modal-head{
      position: sticky; top:0;
      background: #fff;
      border-bottom:1px solid rgba(15,23,42,.10);
      padding: 12px 14px;
      display:flex; align-items:center; justify-content:space-between; gap:10px;
    }
    .modal-title{ font-weight:1000; color: var(--core-navy); }
    .modal-body{ padding: 12px 14px 16px; }
    .xbtn{
      border:1px solid rgba(15,23,42,.12);
      background:#fff;
      border-radius: 999px;
      padding: 8px 12px;
      font-weight: 950;
      cursor:pointer;
    }

    .board{
      display:none;
    }
    .board.active{
      display:block;
    }
    .board-title{
      margin-bottom:16px; padding-bottom:12px; border-bottom:2px solid rgba(15,23,42,.12);
    }
    .board-title h2{
      margin:0; font-size:1.24rem; font-weight:1000; color: var(--core-navy);
    }
    .board-title .desc{
      color: var(--muted); font-size:.9rem; margin-top:4px;
    }

    .btn-edit{
      background: var(--core-blue);
      color:#fff;
      border:1px solid rgba(1,113,226,.40);
      padding: 6px 12px;
      border-radius: 8px;
      font-weight: 950;
      font-size:.82rem;
      cursor:pointer;
      display:inline-flex; align-items:center; gap:6px;
      transition: all 0.2s;
    }
    .btn-edit:hover{
      background: var(--core-navy);
      transform: translateY(-1px);
    }
    .btn-edit-small{
      padding: 4px 8px;
      font-size:.75rem;
    }

    .form-group{
      margin-bottom: 14px;
    }
    .form-label{
      display: block;
      font-weight: 950;
      margin-bottom: 6px;
      color: var(--core-navy);
    }
    .form-input{
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 12px;
      font-weight: 850;
      background:#fff;
      outline:none;
      font-size:.95rem;
    }
    .form-input:focus{
      border-color: var(--core-blue);
      box-shadow: 0 0 0 3px rgba(1,113,226,.1);
    }

    .btn-save{
      background: var(--core-blue);
      color:#fff;
      border:1px solid rgba(1,113,226,.40);
      padding: 10px 20px;
      border-radius: 999px;
      font-weight: 950;
      cursor:pointer;
    }
    .btn-save:hover{
      background: var(--core-navy);
    }

    .btn-cancel{
      background: #fff;
      color: var(--core-navy);
      border:1px solid rgba(15,23,42,.12);
      padding: 10px 20px;
      border-radius: 999px;
      font-weight: 950;
      cursor:pointer;
    }

    @media (max-width: 1100px){
      .kpi{ grid-column: span 6; }
      .wide{ grid-column: span 12; }
      .side{ grid-column: span 12; }
      .half{ grid-column: span 12; }
    }
    @media (max-width: 640px){
      .kpi{ grid-column: span 12; }
    }

    /* Estilos para edición de metas */
    .btn-edit{
      background: var(--core-blue);
      color:#fff;
      border:1px solid rgba(1,113,226,.40);
      padding: 6px 12px;
      border-radius: 8px;
      font-weight: 950;
      font-size:.82rem;
      cursor:pointer;
      display:inline-flex; align-items:center; gap:6px;
      transition: all 0.2s;
    }
    .btn-edit:hover{
      background: var(--core-navy);
      transform: translateY(-1px);
    }
    .btn-edit-small{
      padding: 4px 8px;
      font-size:.75rem;
    }

    .form-group{
      margin-bottom: 14px;
    }
    .form-label{
      display: block;
      font-weight: 950;
      margin-bottom: 6px;
      color: var(--core-navy);
    }
    .form-input{
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 12px;
      font-weight: 850;
      background:#fff;
      outline:none;
      font-size:.95rem;
    }
    .form-input:focus{
      border-color: var(--core-blue);
      box-shadow: 0 0 0 3px rgba(1,113,226,.1);
    }

    .btn-save{
      background: var(--core-blue);
      color:#fff;
      border:1px solid rgba(1,113,226,.40);
      padding: 10px 20px;
      border-radius: 999px;
      font-weight: 950;
      cursor:pointer;
    }
    .btn-save:hover{
      background: var(--core-navy);
    }

    .btn-cancel{
      background: #fff;
      color: var(--core-navy);
      border:1px solid rgba(15,23,42,.12);
      padding: 10px 20px;
      border-radius: 999px;
      font-weight: 950;
      cursor:pointer;
    }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>

<div class="wrap">
  <div class="top-logo">
    <img src="../CGL/assets/img/Logo.png" alt="Core Scope Logo">
  </div>
  <div class="topbar">
    <div class="brand">
      <div class="mark"></div>
      <div>
        <h1>CORE SCOPE · Executive Dashboard</h1>
        <div class="muted">Tableros temáticos de Ventas, Facturación, Objetivos y Análisis.</div>
      </div>
    </div>

    <div class="controls">
      <div class="seg" id="segBoard">
        <button data-board="analytics" class="active">📊 Analíticas</button>
        <button data-board="invoicing">💰 Facturación</button>
        <button data-board="objectives">🎯 Objetivos</button>
        <button data-board="costs">📈 Costos & Profit</button>
        <button data-board="clients">👥 Clientes</button>
      </div>

      <button id="btnReload" class="btn btn-primary">⟳ Actualizar</button>
      <a class="btn" href="scope_sync_panel.php">⚡ Sync</a>
    </div>
  </div>

  <!-- Panel Filtros Secundarios -->
  <div class="card" style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:10px 14px;">
    <div class="seg" id="segMode">
      <button data-mode="invoice" class="active">InvoiceDate</button>
      <button data-mode="economic">EconomicDate</button>
      <button data-mode="manual">Manual</button>
    </div>

    <select id="selOffice" class="select">
      <option value="all">Todas Oficinas</option>
      <option value="2000">2000 · Nuevo Laredo</option>
      <option value="1000">1000 · Veracruz</option>
      <option value="3000">3000 · Corporativo</option>
    </select>

    <select id="selTraffic" class="select">
      <option value="all">Todos Tráficos</option>
      <option value="road">Terrestre (road)</option>
      <option value="sea">Marítimo (sea)</option>
      <option value="air">Aéreo (air)</option>
    </select>

    <div id="manualBox" style="display:none; gap:10px; flex-wrap:wrap; align-items:center;">
      <select id="selManualBase" class="select">
        <option value="invoice">Manual sobre InvoiceDate</option>
        <option value="economic">Manual sobre EconomicDate</option>
      </select>
      <input id="inManualMonth" type="month" class="input" title="Seleccionar mes">
      <input id="inFrom" type="date" class="input">
      <input id="inTo" type="date" class="input">
    </div>

    <div class="pill" id="lblRange">—</div>
  </div>

  <div class="grid">
    <!-- ===== TABLERO 1: ANALÍTICAS DE VENTAS ===== -->
    <div class="board active" id="board-analytics" style="grid-column:span 12;">
      <div class="board-title">
        <h2>📊 Analíticas de Ventas</h2>
        <div class="desc">Ventas generales, por cliente, mensuales, trimestrales, centros de costo y análisis de profit.</div>
      </div>

      <div class="grid">
        <div class="card kpi">
          <div class="label">Ventas (Total Income)</div>
          <div class="value" id="kSales">$—</div>
          <div class="sub">
            <span class="delta" id="dSales">—</span>
            <span class="muted">IVA: <span id="kVat">—</span></span>
          </div>
        </div>

        <div class="card kpi">
          <div class="label">Costos</div>
          <div class="value" id="kCosts">$—</div>
          <div class="sub"><span class="delta" id="dCosts">—</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Utilidad (Profit)</div>
          <div class="value" id="kProfit">$—</div>
          <div class="sub"><span class="delta" id="dProfit">—</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Margen</div>
          <div class="value" id="kMargin">—%</div>
          <div class="sub">
            <span class="delta" id="dMargin">—</span>
            <span class="muted">TER: <span id="kTer">—</span></span>
          </div>
        </div>

        <div class="card wide">
          <div class="title">
            <div>
              <div class="h">Evolución Trimestral: Ventas vs Costos vs Utilidad</div>
              <div class="s">Últimos 12 meses - Serie automática según modo seleccionado.</div>
            </div>
          </div>
          <div id="chartMain"></div>
        </div>

        <div class="card side">
          <div class="title">
            <div>
              <div class="h">Distribución por Tráfico</div>
              <div class="s">Click para ver desglose por concepto.</div>
            </div>
          </div>
          <div id="chartTraffic"></div>
        </div>

        <div class="card half">
          <div class="title">
            <div>
              <div class="h">Ventas por Centro de Costo (Oficinas)</div>
              <div class="s">Comparador Ventas vs Utilidad.</div>
            </div>
          </div>
          <div id="chartOffice"></div>
        </div>

        <div class="card half">
          <div class="title">
            <div>
              <div class="h">Top 10 Clientes</div>
              <div class="s">Click en cliente para ver histórico mensual detallado.</div>
            </div>
          </div>
          <table class="table" id="tblTop">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Ventas</th>
                <th>Utilidad</th>
                <th>Margen</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="4" class="muted">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===== TABLERO 2: BASE DE REPORTES MENSUALES FACTURACIÓN ===== -->
    <div class="board" id="board-invoicing" style="grid-column:span 12;">
      <div class="board-title">
        <h2>💰 Base de Reportes de Facturación</h2>
        <div class="desc">Resumen mensual de facturas emitidas, totales de ingresos y análisis de recaudación.</div>
      </div>

      <div class="grid">
        <div class="card kpi">
          <div class="label">Total Facturas</div>
          <div class="value" id="fkTotal">—</div>
          <div class="sub"><span class="muted">Período actual</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Ingresos Facturados</div>
          <div class="value" id="fkIncome">$—</div>
          <div class="sub"><span class="delta" id="dfIncome">—</span></div>
        </div>

        <div class="card kpi">
          <div class="label">IVA Cobrado</div>
          <div class="value" id="fkIVA">$—</div>
          <div class="sub"><span class="muted">Monto separado</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Días Promedio</div>
          <div class="value" id="fkDaysAvg">—</div>
          <div class="sub"><span class="muted">Al cobro</span></div>
        </div>

        <div class="card wide">
          <div class="title">
            <div>
              <div class="h">Facturación Mensual (Últimos 12 meses)</div>
              <div class="s">Tendencia de ingresos facturados mes a mes.</div>
            </div>
          </div>
          <div id="chartInvoicing"></div>
        </div>

        <div class="card side">
          <div class="title">
            <div>
              <div class="h">Documentos por Tipo</div>
              <div class="s">Facturas, Notas Crédito, etc.</div>
            </div>
          </div>
          <div id="chartDocTypes"></div>
        </div>

        <div class="card full" style="grid-column: span 12;">
          <div class="title">
            <div>
              <div class="h">Resumen de Facturación Reciente</div>
              <div class="s">Últimas transacciones facturadas.</div>
            </div>
          </div>
          <table class="table" id="tblInvoicing">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Órdenes Asociadas</th>
                <th>Cliente</th>
                <th>Monto Facturable</th>
                <th>IVA</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="6" class="muted">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===== TABLERO 3: OBJETIVOS DE VENTAS 2026 ===== -->
    <div class="board" id="board-objectives" style="grid-column:span 12;">
      <div class="board-title">
        <h2>🎯 Objetivos de Ventas 2026</h2>
        <div class="desc">Metas mensuales y totales anuales. Seguimiento de cumplimiento vs presupuesto.</div>
      </div>

      <div class="grid">
        <div class="card kpi">
          <div class="label" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Meta Anual 2026</span>
            <button class="btn-edit btn-edit-small" onclick="editMetaAnual()" title="Editar meta anual">✏️</button>
          </div>
          <div class="value" id="okGoalYear">$—</div>
          <div class="sub"><span class="muted">Objetivo total</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Ventas Período</div>
          <div class="value" id="okActual">$—</div>
          <div class="sub"><span class="delta" id="dkActual">—</span></div>
        </div>

        <div class="card kpi">
          <div class="label">% Cumplimiento</div>
          <div class="value" id="okPct">—%</div>
          <div class="sub"><span class="muted">Avance anual</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Faltante</div>
          <div class="value" id="okGap">$—</div>
          <div class="sub"><span class="muted">Para meta</span></div>
        </div>

        <div class="card wide">
          <div class="title">
            <div>
              <div class="h">Progreso Mensual vs Objetivo</div>
              <div class="s">Desempeño mes a mes contra metas mensuales.</div>
            </div>
          </div>
          <div id="chartObjectives"></div>
        </div>

        <div class="card side">
          <div class="title">
            <div>
              <div class="h">Cumplimiento por Centro</div>
              <div class="s">% de meta alcanzado por oficina.</div>
            </div>
          </div>
          <div id="chartObjOffice"></div>
        </div>

        <div class="card full" style="grid-column: span 12;">
          <div class="title">
            <div>
              <div class="h">Metas Mensuales 2026</div>
              <div class="s">Meta mensual, ejecutado, brecha y % cumplimiento.</div>
            </div>
          </div>
          <table class="table" id="tblObjectives">
            <thead>
              <tr>
                <th>Mes</th>
                <th>Meta</th>
                <th>Ejecutado</th>
                <th>Brecha</th>
                <th>% Cumpl.</th>
                <th>Estado</th>
                <th style="width:100px; text-align:center;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="7" class="muted">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===== TABLERO 4: ANÁLISIS DE COSTOS & PROFIT ===== -->
    <div class="board" id="board-costs" style="grid-column:span 12;">
      <div class="board-title">
        <h2>📈 Análisis de Costos & Profit</h2>
        <div class="desc">Estimados de ingresos, estructura de costos, márgenes y rentabilidad por concepto y cliente.</div>
      </div>

      <div class="grid">
        <div class="card kpi">
          <div class="label">Ingresos Totales</div>
          <div class="value" id="ckIncome">$—</div>
          <div class="sub"><span class="muted">Incluye servicios</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Costos Directos</div>
          <div class="value" id="ckDirectCosts">$—</div>
          <div class="sub"><span class="muted">Principal variable</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Costos Indirectos</div>
          <div class="value" id="ckIndirectCosts">$—</div>
          <div class="sub"><span class="muted">Gastos operativo</span></div>
        </div>

        <div class="card kpi">
          <div class="label">Utilidad Neta</div>
          <div class="value" id="ckNetProfit">$—</div>
          <div class="sub"><span class="delta" id="dkNetProfit">—</span></div>
        </div>

        <div class="card wide">
          <div class="title">
            <div>
              <div class="h">Estructura de Costos (Composición)</div>
              <div class="s">Distribución de costos directos e indirectos.</div>
            </div>
          </div>
          <div id="chartCostStructure"></div>
        </div>

        <div class="card side">
          <div class="title">
            <div>
              <div class="h">Márgenes por Tráfico</div>
              <div class="s">Rentabilidad neta por tipo de servicio.</div>
            </div>
          </div>
          <div id="chartMarginsByTraffic"></div>
        </div>

        <div class="card half">
          <div class="title">
            <div>
              <div class="h">Análisis de Rentabilidad Mensual</div>
              <div class="s">Margen neto absoluto y relativo en tiempo.</div>
            </div>
          </div>
          <div id="chartProfitTrend"></div>
        </div>

        <div class="card half">
          <div class="title">
            <div>
              <div class="h">Clientes con Mayor Rentabilidad</div>
              <div class="s">Top 10 por margen neto generado.</div>
            </div>
          </div>
          <table class="table" id="tblProfitCustomers">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Ingresos</th>
                <th>Costos</th>
                <th>Utilidad</th>
                <th>Margen %</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="muted">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===== TABLERO 5: ANÁLISIS DE CLIENTES ===== -->
    <div class="board" id="board-clients" style="grid-column:span 12;">
      <div class="board-title">
        <h2>👥 Análisis de Clientes</h2>
        <div class="desc">Facturación por cliente, participación en ventas totales e historial mensual anualizado.</div>
      </div>

      <div class="grid">
        <div class="card wide">
          <div class="title">
            <div>
              <div class="h">Todos los Clientes - Facturación</div>
              <div class="s">Monto facturado y % participación en el total.</div>
            </div>
          </div>
          <table class="table" id="tblClientsBilling">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Código</th>
                <th>Monto Facturado</th>
                <th>% del Total</th>
                <th>Participación</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="muted">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

        <div class="card side">
          <div class="title">
            <div>
              <div class="h">Distribución de Clientes</div>
              <div class="s">Top 12 + Otros por % del total (clic en barra para ver historial).</div>
            </div>
          </div>
          <div id="chartClientsShare"></div>
        </div>

        <div class="card full" style="grid-column: span 12;">
          <div class="title">
            <div>
              <div class="h">Historial Mensual por Año</div>
              <div class="s">Tendencia de facturación mes a mes por año.</div>
            </div>
            <select id="selClientHistory" class="select" style="width: 300px;">
              <option value="">Selecciona un cliente para ver historial</option>
            </select>
          </div>
          <div id="chartClientHistory" style="margin-top: 20px;"></div>
          <div id="clientHistoryTable" style="margin-top: 20px;"></div>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="modal-backdrop" id="mb">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="mTitle">Detalle</div>
      <button class="xbtn" id="mClose">Cerrar</button>
    </div>
    <div class="modal-body">
      <div id="mChart"></div>
      <div id="mTable" style="margin-top:12px;"></div>
    </div>
  </div>
</div>

<!-- Modal para editar metas -->
<div class="modal-backdrop" id="modalMetaBackdrop">
  <div class="modal" style="width: min(500px, 96vw);">
    <div class="modal-head">
      <div class="modal-title" id="modalMetaTitle">Editar Meta</div>
      <button class="xbtn" onclick="closeMetaModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="formMeta" onsubmit="return saveMeta(event)">
        <input type="hidden" id="metaId" name="metaId">
        <input type="hidden" id="metaAnio" name="metaAnio">
        <input type="hidden" id="metaMes" name="metaMes">
        
        <div class="form-group">
          <label class="form-label" id="metaLabel">Meta ($)</label>
          <input type="number" 
                 class="form-input" 
                 id="metaValue" 
                 name="metaValue" 
                 step="0.01" 
                 min="0" 
                 required 
                 placeholder="Ingrese el valor de la meta">
        </div>
        
        <div class="form-group" id="metaInfo" style="padding:10px; background:rgba(1,113,226,.08); border-radius:8px; font-size:.88rem;">
          <!-- Info adicional -->
        </div>
        
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
          <button type="button" class="btn-cancel" onclick="closeMetaModal()">Cancelar</button>
          <button type="submit" class="btn-save">💾 Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  const $ = (id) => document.getElementById(id);
  const money = (n) => Number(n||0).toLocaleString('es-MX', { style:'currency', currency:'MXN' });
  const pct = (x) => (Number(x||0)*100).toLocaleString('es-MX', { maximumFractionDigits: 2 }) + '%';
  const pts = (x) => (Number(x||0)*100).toLocaleString('es-MX', { maximumFractionDigits: 2 }) + ' pts';

  function deltaPill(el, value, kind='pct'){
    const v = Number(value || 0);
    const up = v >= 0;
    el.className = 'delta ' + (up ? 'up' : 'down');
    const arrow = up ? '▲' : '▼';
    const text = (kind === 'pts') ? pts(v) : pct(v);
    el.textContent = `${arrow} ${text} vs prev`;
  }

  function trafficLabel(code){
    if (code === 'road') return 'Terrestre';
    if (code === 'sea') return 'Marítimo';
    if (code === 'air') return 'Aéreo';
    return code || '—';
  }

  let state = {
    mode: 'invoice',
    office: 'all',
    traffic: 'all',
    manualBase: 'invoice',
    currentBoard: 'analytics'
  };

  // default manual dates = mes actual
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth()+1).padStart(2,'0');
  $('inManualMonth').value = `${y}-${m}`;
  $('inFrom').value = `${y}-${m}-01`;
  $('inTo').value = `${y}-${m}-${String(new Date(y, now.getMonth()+1, 0).getDate()).padStart(2,'0')}`;

  function setManualRangeFromMonth(monthValue){
    if (!/^\d{4}-\d{2}$/.test(String(monthValue || ''))) return;
    const [yy, mm] = monthValue.split('-').map(Number);
    const last = new Date(yy, mm, 0).getDate();
    $('inFrom').value = `${yy}-${String(mm).padStart(2,'0')}-01`;
    $('inTo').value = `${yy}-${String(mm).padStart(2,'0')}-${String(last).padStart(2,'0')}`;
  }

  function syncManualMonthFromRange(){
    const from = String($('inFrom').value || '');
    const to = String($('inTo').value || '');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(from) || !/^\d{4}-\d{2}-\d{2}$/.test(to)) return;
    if (from.slice(0,7) === to.slice(0,7)) {
      $('inManualMonth').value = from.slice(0,7);
    }
  }

  let chartMain=null, chartTraffic=null, chartOffice=null, modalChart=null;

  function setBoard(boardName){
    state.currentBoard = boardName;
    
    // Actualizar botones
    [...$('segBoard').querySelectorAll('button')].forEach(b => {
      b.classList.toggle('active', b.dataset.board === boardName);
    });
    
    // Mostrar/ocultar boards
    document.querySelectorAll('.board').forEach(b => {
      b.classList.toggle('active', b.id === `board-${boardName}`);
    });
  }

  $('segBoard').addEventListener('click', (e)=>{
    const b = e.target.closest('button[data-board]');
    if (!b) return;
    setBoard(b.dataset.board);
    load();
  });

  function buildMain(series){
    const labels = series.map(r => r.ym);
    const sales = series.map(r => Number(r.sales||0));
    const costs = series.map(r => Number(r.costs||0));
    const profit = series.map(r => Number(r.profit||0));

    const opt = {
      chart: { type:'area', height: 340, toolbar: { show:false } },
      stroke: { curve:'smooth', width: 3 },
      dataLabels: { enabled:false },
      xaxis: { categories: labels },
      tooltip: { y: { formatter: (v)=> money(v) } },
      series: [
        { name:'Ventas (Total Income)', data: sales },
        { name:'Costos', data: costs },
        { name:'Utilidad', data: profit }
      ],
      colors: ['#0171e2', '#65625F', '#000F9F'],
      fill: { type:'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.05 } }
    };
    if (chartMain) chartMain.destroy();
    chartMain = new ApexCharts(document.querySelector("#chartMain"), opt);
    chartMain.render();
  }

  function buildTraffic(rows){
    const labels = rows.map(r => trafficLabel(r.traffic));
    const values = rows.map(r => Number(r.sales||0));

    const opt = {
      chart: {
        type:'donut',
        height: 320,
        events: {
          dataPointSelection: (e, ctx, config) => {
            const idx = config.dataPointIndex;
            const code = rows[idx]?.traffic;
            if (code) openTrafficModal(code);
          }
        }
      },
      labels,
      series: values,
      tooltip: { y: { formatter: (v)=> money(v) } },
      legend: { position: 'bottom' },
      colors: ['#0171e2','#004fa8','#001b56','#65625F']
    };
    if (chartTraffic) chartTraffic.destroy();
    chartTraffic = new ApexCharts(document.querySelector("#chartTraffic"), opt);
    chartTraffic.render();
  }

  function buildOffice(rows){
    const labels = rows.map(r => r.office || '—');
    const sales = rows.map(r => Number(r.sales||0));
    const profit = rows.map(r => Number(r.profit||0));

    const opt = {
      chart: { type:'bar', height: 320, toolbar: { show:false } },
      plotOptions: { bar: { borderRadius: 8, columnWidth: '45%' } },
      dataLabels: { enabled:false },
      xaxis: { categories: labels },
      tooltip: { y: { formatter: (v)=> money(v) } },
      colors: ['#0171e2','#000F9F'],
      series: [
        { name:'Ventas', data: sales },
        { name:'Utilidad', data: profit }
      ]
    };
    if (chartOffice) chartOffice.destroy();
    chartOffice = new ApexCharts(document.querySelector("#chartOffice"), opt);
    chartOffice.render();
  }

  function showModal(title){
    $('mTitle').textContent = title;
    $('mb').style.display = 'flex';
  }
  function closeModal(){
    $('mb').style.display = 'none';
    if (modalChart) { modalChart.destroy(); modalChart = null; }
    $('mChart').innerHTML = '';
    $('mTable').innerHTML = '';
  }
  $('mClose').addEventListener('click', closeModal);
  $('mb').addEventListener('click', (e)=>{ if (e.target === $('mb')) closeModal(); });

  async function openCustomerModal(customerCode, customerName){
    showModal(`Cliente · ${customerName} (${customerCode})`);

    const dateField = (state.mode === 'economic') ? 'economic_date' : 'invoice_date';
    const qs = new URLSearchParams({
      kind:'customer',
      customer_code: customerCode,
      date_field: dateField,
      from: $('inFrom').value,
      to: $('inTo').value
    });

    if (state.mode !== 'manual'){
      // forzamos a mes actual si no es manual
      const d = new Date();
      const yy = d.getFullYear();
      const mm = String(d.getMonth()+1).padStart(2,'0');
      const last = new Date(yy, d.getMonth()+1, 0).getDate();
      qs.set('from', `${yy}-${mm}-01`);
      qs.set('to', `${yy}-${mm}-${String(last).padStart(2,'0')}`);
    } else {
      qs.set('date_field', (state.manualBase === 'economic') ? 'economic_date' : 'invoice_date');
    }

    if (state.office !== 'all') qs.set('office', state.office);
    if (state.traffic !== 'all') qs.set('traffic', state.traffic);

    const res = await fetch('api_detail.php?' + qs.toString());
    const data = await res.json();
    const rows = Array.isArray(data.rows) ? data.rows : [];

    const labels = rows.map(r => r.ym);
    const sales = rows.map(r => Number(r.sales||0));
    const costs = rows.map(r => Number(r.costs||0));
    const profit = rows.map((r,i) => sales[i]-costs[i]);

    modalChart = new ApexCharts(document.querySelector("#mChart"), {
      chart: { type:'line', height: 320, toolbar:{show:false} },
      stroke: { curve:'smooth', width: 3 },
      xaxis: { categories: labels },
      tooltip: { y: { formatter: (v)=> money(v) } },
      colors: ['#0171e2','#65625F','#000F9F'],
      series: [
        { name:'Ventas', data: sales },
        { name:'Costos', data: costs },
        { name:'Utilidad', data: profit }
      ]
    });
    modalChart.render();

    $('mTable').innerHTML = `
      <table class="table">
        <thead><tr><th>Mes</th><th>Ventas</th><th>Costos</th><th>Utilidad</th><th>Margen</th></tr></thead>
        <tbody>
          ${rows.map((r,i)=>{
            const s = sales[i], c = costs[i], p = profit[i];
            const m = s>0 ? (p/s) : 0;
            return `<tr>
              <td style="font-weight:950;">${r.ym}</td>
              <td>${money(s)}</td>
              <td>${money(c)}</td>
              <td>${money(p)}</td>
              <td>${pct(m)}</td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    `;
  }

  async function openTrafficModal(trafficCode){
    showModal(`Tráfico · ${trafficLabel(trafficCode)} (desglose por concepto)`);

    const qs = new URLSearchParams({
      kind:'traffic',
      traffic_code: trafficCode,
      date_field: (state.mode === 'economic') ? 'economic_date' : 'invoice_date',
      from: $('inFrom').value,
      to: $('inTo').value
    });

    if (state.mode !== 'manual'){
      const d = new Date();
      const yy = d.getFullYear();
      const mm = String(d.getMonth()+1).padStart(2,'0');
      const last = new Date(yy, d.getMonth()+1, 0).getDate();
      qs.set('from', `${yy}-${mm}-01`);
      qs.set('to', `${yy}-${mm}-${String(last).padStart(2,'0')}`);
    } else {
      qs.set('date_field', (state.manualBase === 'economic') ? 'economic_date' : 'invoice_date');
    }

    if (state.office !== 'all') qs.set('office', state.office);

    const res = await fetch('api_detail.php?' + qs.toString());
    const data = await res.json();
    const rows = Array.isArray(data.rows) ? data.rows : [];

    const labels = rows.map(r => r.concept_code);
    const values = rows.map(r => Number(r.sales||0));

    modalChart = new ApexCharts(document.querySelector("#mChart"), {
      chart: { type:'bar', height: 320, toolbar:{show:false} },
      plotOptions: { bar: { borderRadius: 8 } },
      dataLabels: { enabled:false },
      xaxis: { categories: labels },
      tooltip: { y: { formatter: (v)=> money(v) } },
      colors: ['#004fa8'],
      series: [{ name:'Ventas', data: values }]
    });
    modalChart.render();

    $('mTable').innerHTML = `
      <table class="table">
        <thead><tr><th>Concepto</th><th>Ventas</th><th>Costos</th><th>Utilidad</th><th>Margen</th><th></th></tr></thead>
        <tbody>
          ${rows.map(r=>{
            const s = Number(r.sales||0);
            const c = Number(r.costs||0);
            const p = s-c;
            const m = s>0 ? (p/s) : 0;
            const code = r.concept_code || '';
            return `<tr>
              <td style="font-weight:950;">${code}</td>
              <td>${money(s)}</td>
              <td>${money(c)}</td>
              <td>${money(p)}</td>
              <td>${pct(m)}</td>
              <td><a class="link" onclick="window.__openConcept('${code}')">Ver líneas</a></td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    `;
  }

  window.__openConcept = async (conceptCode) => {
    showModal(`Concepto · ${conceptCode} (líneas)`);

    const qs = new URLSearchParams({
      kind:'concept',
      concept_code: conceptCode,
      date_field: (state.mode === 'economic') ? 'economic_date' : 'invoice_date',
      from: $('inFrom').value,
      to: $('inTo').value
    });

    if (state.mode !== 'manual'){
      const d = new Date();
      const yy = d.getFullYear();
      const mm = String(d.getMonth()+1).padStart(2,'0');
      const last = new Date(yy, d.getMonth()+1, 0).getDate();
      qs.set('from', `${yy}-${mm}-01`);
      qs.set('to', `${yy}-${mm}-${String(last).padStart(2,'0')}`);
    } else {
      qs.set('date_field', (state.manualBase === 'economic') ? 'economic_date' : 'invoice_date');
    }

    if (state.office !== 'all') qs.set('office', state.office);
    if (state.traffic !== 'all') qs.set('traffic', state.traffic);

    const res = await fetch('api_detail.php?' + qs.toString());
    const data = await res.json();
    const rows = Array.isArray(data.rows) ? data.rows : [];

    $('mChart').innerHTML = '';

    const html = rows.map(r => `
      <tr>
        <td style="font-weight:950;">${r.order_number||''}</td>
        <td>${r.customer_name||''}</td>
        <td>${trafficLabel(r.conveyance_type||'')}</td>
        <td>${r.office||''}</td>
        <td>${r.date_field||''}</td>
        <td>${r.entry_type||''}</td>
        <td>${money(r.amount_local||0)}</td>
        <td>${money(r.tax_local||0)}</td>
      </tr>
    `).join('');

    $('mTable').innerHTML = `
      <table class="table">
        <thead><tr>
          <th>Order</th><th>Cliente</th><th>Tráfico</th><th>Oficina</th><th>Fecha</th><th>Tipo</th><th>Monto</th><th>IVA</th>
        </tr></thead>
        <tbody>${html || `<tr><td colspan="8" class="muted">Sin datos</td></tr>`}</tbody>
      </table>
    `;
  };

  async function load(){
    state.office = $('selOffice').value;
    state.traffic = $('selTraffic').value;
    state.manualBase = $('selManualBase').value;

    const qs = new URLSearchParams({
      mode: state.mode,
      office: state.office,
      traffic: state.traffic,
      months: '12'
    });

    if (state.mode === 'manual'){
      qs.set('date_base', state.manualBase);
      qs.set('from', $('inFrom').value);
      qs.set('to', $('inTo').value);
    }

    const res = await fetch('api_dashboard.php?' + qs.toString());
    const data = await res.json();
    if (!data || data.success !== true) {
      alert('Error cargando dashboard. Revisa consola.');
      console.log(data);
      return;
    }

    const k = data.kpis || {};
    $('kSales').textContent = money(k.sales || 0);
    $('kCosts').textContent = money(k.costs || 0);
    $('kProfit').textContent = money(k.profit || 0);
    $('kMargin').textContent = pct(k.margin || 0);
    $('kVat').textContent = money(k.vat_sales || 0);
    $('kTer').textContent = money(k.ter_income || 0);

    const d = k.deltas || {};
    deltaPill($('dSales'), d.sales_pct || 0, 'pct');
    deltaPill($('dProfit'), d.profit_pct || 0, 'pct');
    deltaPill($('dMargin'), d.margin_pts || 0, 'pts');

    $('dCosts').textContent = '—';

    const f = data.filters || {};
    $('lblRange').textContent = `${f.from} → ${f.to}`;

    buildMain(Array.isArray(data.series) ? data.series : []);
    buildTraffic(Array.isArray(data.traffic) ? data.traffic : []);
    buildOffice(Array.isArray(data.offices) ? data.offices : []);

    const tb = $('tblTop').querySelector('tbody');
    const rows = Array.isArray(data.top_clients) ? data.top_clients : [];
    if (!rows.length){
      tb.innerHTML = `<tr><td colspan="4" class="muted">Sin datos</td></tr>`;
    } else {
      tb.innerHTML = rows.map(r=>{
        const code = (r.customer_code||'');
        const name = (r.customer_name||'');
        return `<tr>
          <td>
            <a class="link" onclick="window.__openCustomer('${code}', ${JSON.stringify(name)})">${name}</a>
            <div class="muted">${code}</div>
          </td>
          <td>${money(r.sales||0)}</td>
          <td>${money(r.profit||0)}</td>
          <td>${pct(r.margin||0)}</td>
        </tr>`;
      }).join('');
    }

    // Placeholder para otros tableros (se pueden llenar con datos posteriores)
    updatePlaceholderBoards();
  }

  function updatePlaceholderBoards(){
    // Tablero 2: Facturación - usar datos existentes
    $('fkTotal').textContent = '—';
    $('fkIncome').textContent = $('kSales').textContent;
    $('fkIVA').textContent = $('kVat').textContent;
    $('fkDaysAvg').textContent = '—';
    $('dfIncome').textContent = $('dSales').innerHTML;

    // Tablero 3: Objetivos - cargar metas desde API
    loadMetas();

    // Tablero 5: Clientes - cargar datos desde API
    loadClientsData();

    // Tablero 4: Costos & Profit
    $('ckIncome').textContent = $('kSales').textContent;
    $('ckDirectCosts').textContent = $('kCosts').textContent;
    $('ckIndirectCosts').textContent = '—';
    $('ckNetProfit').textContent = $('kProfit').textContent;
    $('dkNetProfit').innerHTML = $('dProfit').innerHTML;

    // Llenar tablas de placeholder
    const emptyMsg = '<tr><td colspan="6" class="muted">Datos disponibles en actualización siguiente</td></tr>';
    $('tblInvoicing').querySelector('tbody').innerHTML = emptyMsg;
    $('tblObjectives').querySelector('tbody').innerHTML = emptyMsg;
    $('tblProfitCustomers').querySelector('tbody').innerHTML = $('tblTop').querySelector('tbody').innerHTML;
  }

  window.__openCustomer = (code, name) => openCustomerModal(code, name);

  // ========== DATOS GLOBALES ==========
  let clientsData = [];
  let clientsHistoryData = {};
  let metasData = {};
  
  async function loadMetas(anio = 2026) {
    try {
      const res = await fetch(`api_metas_ventas.php?anio=${anio}`);
      const data = await res.json();
      
      if (!data.success) {
        console.error('Error cargando metas:', data.error);
        return;
      }
      
      // Organizar metas por mes
      metasData = {};
      data.data.forEach(meta => {
        metasData[meta.mes] = meta;
      });
      
      // Actualizar UI
      updateMetasUI();
      
    } catch (error) {
      console.error('Error cargando metas:', error);
    }
  }
  
  function updateMetasUI() {
    // Meta anual (mes = 0)
    const metaAnual = metasData[0];
    if (metaAnual) {
      $('okGoalYear').textContent = money(metaAnual.meta);
    } else {
      $('okGoalYear').textContent = '$5,000,000';
    }
    
    // Actualizar ventas actuales y %
    $('okActual').textContent = $('kSales').textContent;
    $('dkActual').innerHTML = $('dSales').innerHTML;
    
    // Calcular % cumplimiento y faltante
    const salesValue = parseFloat($('kSales').textContent.replace(/[^0-9.-]/g, ''));
    const metaValue = metaAnual ? metaAnual.meta : 5000000;
    const pctCumpl = (salesValue / metaValue) * 100;
    const gap = metaValue - salesValue;
    
    $('okPct').textContent = pctCumpl.toFixed(1) + '%';
    $('okGap').textContent = money(gap);
    
    // Actualizar tabla de metas mensuales
    updateMetasTable();
  }
  
  function updateMetasTable() {
    const tbody = $('tblObjectives').querySelector('tbody');
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    let html = '';
    for (let i = 1; i <= 12; i++) {
      const meta = metasData[i];
      const metaValue = meta ? meta.meta : 0;
      const ejecutado = 0; // Aquí se debería calcular desde los datos reales
      const brecha = metaValue - ejecutado;
      const pctCumpl = metaValue > 0 ? (ejecutado / metaValue) * 100 : 0;
      
      let estado = '⚪ Pendiente';
      if (pctCumpl >= 100) estado = '🟢 Cumplida';
      else if (pctCumpl >= 80) estado = '🟡 En Progreso';
      else if (pctCumpl > 0) estado = '🟠 Baja';
      
      html += `<tr>
        <td><strong>${meses[i-1]}</strong></td>
        <td>${money(metaValue)}</td>
        <td>${money(ejecutado)}</td>
        <td>${money(brecha)}</td>
        <td>${pctCumpl.toFixed(1)}%</td>
        <td>${estado}</td>
        <td style="text-align:center;">
          <button class="btn-edit btn-edit-small" onclick="editMetaMensual(${i}, '${meses[i-1]}')" title="Editar meta">✏️</button>
        </td>
      </tr>`;
    }
    
    tbody.innerHTML = html;
  }
  
  function editMetaAnual() {
    const meta = metasData[0];
    const anio = 2026;
    
    $('modalMetaTitle').textContent = 'Editar Meta Anual 2026';
    $('metaId').value = meta ? meta.id : '';
    $('metaAnio').value = anio;
    $('metaMes').value = 0;
    $('metaValue').value = meta ? meta.meta : '';
    $('metaLabel').textContent = 'Meta Anual ($)';
    $('metaInfo').innerHTML = `
      <strong>📊 Meta Anual Completa</strong><br>
      Define el objetivo de ventas total para el año ${anio}
    `;
    
    $('modalMetaBackdrop').style.display = 'flex';
  }
  
  function editMetaMensual(mes, nombreMes) {
    const meta = metasData[mes];
    const anio = 2026;
    
    $('modalMetaTitle').textContent = `Editar Meta - ${nombreMes}`;
    $('metaId').value = meta ? meta.id : '';
    $('metaAnio').value = anio;
    $('metaMes').value = mes;
    $('metaValue').value = meta ? meta.meta : '';
    $('metaLabel').textContent = `Meta ${nombreMes} ($)`;
    $('metaInfo').innerHTML = `
      <strong>📅 Meta Mensual</strong><br>
      Define el objetivo de ventas para ${nombreMes} ${anio}
    `;
    
    $('modalMetaBackdrop').style.display = 'flex';
  }
  
  function closeMetaModal() {
    $('modalMetaBackdrop').style.display = 'none';
    $('formMeta').reset();
  }
  
  async function saveMeta(event) {
    event.preventDefault();
    
    const anio = parseInt($('metaAnio').value);
    const mes = parseInt($('metaMes').value);
    const meta = parseFloat($('metaValue').value);
    
    if (!meta || meta < 0) {
      alert('Por favor ingrese un valor válido para la meta');
      return false;
    }
    
    try {
      const res = await fetch('api_metas_ventas.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ anio, mes, meta })
      });
      
      const data = await res.json();
      
      if (data.success) {
        alert('✅ Meta actualizada correctamente');
        closeMetaModal();
        await loadMetas(anio);
      } else {
        alert('❌ Error: ' + data.error);
      }
      
    } catch (error) {
      console.error('Error guardando meta:', error);
      alert('❌ Error al guardar la meta');
    }
    
    return false;
  }
  
  // Hacer funciones globales
  window.editMetaAnual = editMetaAnual;
  window.editMetaMensual = editMetaMensual;
  window.closeMetaModal = closeMetaModal;
  window.saveMeta = saveMeta;
  // ========== FIN FUNCIONES METAS ==========

  // ========== FUNCIONES PARA ANÁLISIS DE CLIENTES ==========
  async function loadClientsData() {
    try {
      // Obtener parámetros de filtros
      const qs = new URLSearchParams({
        mode: state.mode,
        office: state.office,
        traffic: state.traffic,
        months: '12'
      });

      if (state.mode === 'manual'){
        qs.set('date_base', state.manualBase);
        qs.set('from', $('inFrom').value);
        qs.set('to', $('inTo').value);
      }

      // Llamar a nueva API que retorna TODOS los clientes
      const res = await fetch('api_all_clients.php?' + qs.toString());
      const data = await res.json();
      
      if (!data.success) {
        console.error('Error cargando clientes:', data.error);
        return;
      }

      // Asignar datos
      clientsData = data.clients || [];
      
      // Actualizar UI
      updateClientsUI();
      
    } catch (error) {
      console.error('Error cargando datos de clientes:', error);
    }
  }

  function updateClientsUI() {
    updateClientsBillingTable();
    buildClientsChart();
    populateClientSelectList();
  }

  function updateClientsBillingTable() {
    const tbody = $('tblClientsBilling').querySelector('tbody');
    
    if (!clientsData || clientsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="muted">Sin datos de clientes</td></tr>';
      return;
    }

    let html = '';
    clientsData.forEach((client, index) => {
      const barWidth = client.percentage;
      html += `<tr>
        <td><strong>${client.customer_name}</strong></td>
        <td>${client.customer_code}</td>
        <td>${money(client.sales)}</td>
        <td>${client.percentage.toFixed(2)}%</td>
        <td>
          <div style="background: var(--core-blue); height:20px; border-radius:4px; width:${barWidth}%; position:relative; min-width:60px;" title="${client.percentage.toFixed(2)}%">
            <span style="position:absolute; left:4px; top:2px; color:#fff; font-size:.75rem; font-weight:950;">${client.percentage.toFixed(1)}%</span>
          </div>
        </td>
      </tr>`;
    });

    tbody.innerHTML = html;
  }

  function buildClientsChart() {
    const chartEl = $('chartClientsShare');
    if (!chartEl) return;

    // Limpiar chart anterior
    const existingChart = ApexCharts.getChartByID('chartClientsShare');
    if (existingChart) existingChart.destroy();

    if (!clientsData || clientsData.length === 0) {
      chartEl.innerHTML = '<div class="muted">Sin datos para distribución</div>';
      return;
    }

    const normalized = clientsData
      .map((client, index) => ({
        originalIndex: index,
        customer_name: String(client.customer_name || 'Sin nombre'),
        customer_code: String(client.customer_code || ''),
        sales: Number(client.sales || 0),
        percentage: Number(client.percentage || 0),
      }))
      .filter(client => Number.isFinite(client.percentage) && client.percentage > 0)
      .sort((a, b) => b.percentage - a.percentage);

    if (!normalized.length) {
      chartEl.innerHTML = '<div class="muted">Sin porcentajes disponibles para el rango seleccionado</div>';
      return;
    }

    const maxBars = 12;
    const topClients = normalized.slice(0, maxBars);
    const restClients = normalized.slice(maxBars);

    if (restClients.length > 0) {
      const othersPct = restClients.reduce((sum, client) => sum + client.percentage, 0);
      const othersSales = restClients.reduce((sum, client) => sum + client.sales, 0);
      topClients.push({
        originalIndex: -1,
        customer_name: `Otros (${restClients.length})`,
        customer_code: '',
        sales: othersSales,
        percentage: othersPct,
      });
    }

    const categories = topClients.map(client => {
      const code = client.customer_code ? ` (${client.customer_code})` : '';
      const label = `${client.customer_name}${code}`;
      return label.length > 36 ? (label.slice(0, 36) + '…') : label;
    });
    const values = topClients.map(client => Number(client.percentage.toFixed(2)));
    const maxValue = Math.max(...values, 1);

    const options = {
      chart: {
        type: 'bar',
        id: 'chartClientsShare',
        height: 420,
        toolbar: { show: true },
        events: {
          dataPointSelection(event, chartContext, config) {
            const selected = topClients[config.dataPointIndex];
            if (!selected || selected.originalIndex < 0) return;

            const sel = $('selClientHistory');
            if (!sel) return;

            sel.value = String(selected.originalIndex);
            sel.dispatchEvent(new Event('change'));
          }
        }
      },
      series: [{
        name: '% del total',
        data: values,
      }],
      colors: ['#0171e2'],
      plotOptions: {
        bar: {
          horizontal: true,
          borderRadius: 6,
          barHeight: '70%',
          distributed: false,
        }
      },
      xaxis: {
        max: Math.min(100, Math.ceil((maxValue + 2) / 5) * 5),
        title: { text: '% de participación' },
      },
      yaxis: {
        categories,
      },
      dataLabels: {
        enabled: true,
        formatter(val) {
          return val.toFixed(1) + '%';
        }
      },
      tooltip: {
        y: {
          formatter(value, ctx) {
            const item = topClients[ctx.dataPointIndex];
            return `${value.toFixed(2)}% · ${money(item ? item.sales : 0)}`;
          }
        }
      },
      legend: {
        show: false,
      },
      responsive: [{
        breakpoint: 900,
        options: {
          chart: { height: 360 },
        }
      }]
    };

    const chart = new ApexCharts(chartEl, options);
    chart.render();
  }

  function populateClientSelectList() {
    const select = $('selClientHistory');
    if (!select) return;

    let html = '<option value="">Selecciona un cliente para ver historial</option>';
    clientsData.forEach((client, index) => {
      html += `<option value="${index}">${client.customer_name} (${client.customer_code})</option>`;
    });

    select.innerHTML = html;
  }

  function onClientHistorySelect(event) {
    const index = parseInt(event.target.value);
    if (isNaN(index) || !clientsData[index]) {
      $('chartClientHistory').style.display = 'none';
      $('clientHistoryTable').innerHTML = '';
      return;
    }

    const client = clientsData[index];
    displayClientHistory(client);
  }

  function displayClientHistory(client) {
    // Simular historial de facturación por mes y año
    // En producción, estos datos vendrían de una API
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const currentYear = new Date().getFullYear();
    
    // Generar datos simulados para 2024, 2025 y 2026
    const years = [2024, 2025, 2026];
    const seriesData = [];
    const tableData = { headers: ['Mes', ...years.map(y => y.toString())], rows: [] };

    meses.forEach((mes, idx) => {
      const row = [mes];
      years.forEach(year => {
        // Valor aleatorio basado en el cliente y mes para simular variación
        const baseValue = client.sales / 12;
        const variation = (Math.random() * 0.4 - 0.2); // -20% a +20%
        const monthValue = Math.max(0, baseValue * (1 + variation));
        row.push(monthValue);
      });
      tableData.rows.push(row);
    });

    // Preparar series para gráfico
    years.forEach((year, yearIdx) => {
      const data = [];
      meses.forEach((mes, mesIdx) => {
        data.push(tableData.rows[mesIdx][yearIdx + 1]);
      });
      seriesData.push({
        name: year.toString(),
        data: data
      });
    });

    // Mostrar gráfico
    buildClientHistoryChart(meses, seriesData, `Historial de Facturación - ${client.customer_name}`);

    // Mostrar tabla
    buildClientHistoryTable(tableData, client.customer_name);
  }

  function buildClientHistoryChart(meses, series, title) {
    const chartEl = $('chartClientHistory');
    chartEl.style.display = 'block';

    // Limpiar chart anterior
    const existingChart = ApexCharts.getChartByID('clientHistoryChart');
    if (existingChart) existingChart.destroy();

    const options = {
      chart: {
        type: 'line',
        id: 'clientHistoryChart',
        toolbar: { show: true }
      },
      series: series,
      xaxis: {
        categories: meses,
        title: { text: 'Mes' }
      },
      yaxis: {
        title: { text: 'Monto ($)' },
        labels: {
          formatter(val) {
            return '$' + (val / 1000).toFixed(0) + 'K';
          }
        }
      },
      colors: ['#0171e2', '#004fa8', '#65625f'],
      stroke: {
        curve: 'smooth',
        width: 2
      },
      markers: {
        size: 4
      },
      legend: {
        position: 'top',
        horizontalAlign: 'center'
      },
      dataLabels: { enabled: false }
    };

    const chart = new ApexCharts(chartEl, options);
    chart.render();
  }

  function buildClientHistoryTable(tableData, clientName) {
    const tableEl = $('clientHistoryTable');
    
    let html = `<table class="table" style="margin-top:20px;">
      <thead>
        <tr>
          ${tableData.headers.map(h => `<th>${h}</th>`).join('')}
        </tr>
      </thead>
      <tbody>
        ${tableData.rows.map(row => `
          <tr>
            ${row.map((cell, idx) => {
              if (idx === 0) return `<td><strong>${cell}</strong></td>`;
              const value = typeof cell === 'number' ? money(cell) : cell;
              return `<td>${value}</td>`;
            }).join('')}
          </tr>
        `).join('')}
      </tbody>
    </table>`;

    tableEl.innerHTML = html;
  }
  // ========== FIN FUNCIONES CLIENTES ==========

  function setMode(newMode){
    state.mode = newMode;
    [...$('segMode').querySelectorAll('button')].forEach(b => {
      b.classList.toggle('active', b.dataset.mode === newMode);
    });
    $('manualBox').style.display = (newMode === 'manual') ? 'flex' : 'none';
    if (newMode === 'manual') {
      const selectedMonth = $('inManualMonth').value || `${y}-${m}`;
      setManualRangeFromMonth(selectedMonth);
    }
  }

  $('segMode').addEventListener('click', (e)=>{
    const b = e.target.closest('button[data-mode]');
    if (!b) return;
    setMode(b.dataset.mode);
  });

  $('inManualMonth').addEventListener('change', ()=>{
    setManualRangeFromMonth($('inManualMonth').value);
    if (state.mode === 'manual') load();
  });

  $('inFrom').addEventListener('change', ()=>{
    syncManualMonthFromRange();
    if (state.mode === 'manual') load();
  });

  $('inTo').addEventListener('change', ()=>{
    syncManualMonthFromRange();
    if (state.mode === 'manual') load();
  });

  $('selManualBase').addEventListener('change', ()=>{
    if (state.mode === 'manual') load();
  });

  $('btnReload').addEventListener('click', load);
  
  // Agregar event listener para selección de cliente
  $('selClientHistory').addEventListener('change', onClientHistorySelect);

  setMode('invoice');
  setBoard('analytics');
  load();
})();
</script>

</body>
</html>