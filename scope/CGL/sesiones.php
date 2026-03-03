<?php
// /sesiones.php (VISTA)
// Sesiones activas + revocación (sesiones_usuarios)

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('SESIONES');

$page_title = 'Sesiones';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Sesiones</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <div style="flex:1; min-width:220px;">
        <input type="search" id="q" placeholder="Buscar (correo/nombre/ip/sesión)..." autocomplete="off">
      </div>
      <button type="button" id="btnReload">Recargar</button>
    </div>

    <div class="muted" style="margin-top:10px;">
      Lista de sesiones (activas y revocadas). Puedes revocar una sesión específica.
    </div>
  </div>

  <div class="widget" style="cursor:default; margin-top:16px;">
    <div class="widget-title">Listado</div>

    <div style="overflow:auto; margin-top:10px;">
      <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
        <thead>
          <tr class="muted" style="text-align:left;">
            <th style="padding:6px 10px;">ID</th>
            <th style="padding:6px 10px;">Usuario</th>
            <th style="padding:6px 10px;">Correo</th>
            <th style="padding:6px 10px;">IP</th>
            <th style="padding:6px 10px;">Últ. visto</th>
            <th style="padding:6px 10px;">Revocado</th>
            <th style="padding:6px 10px; width:220px;">Acciones</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
    </div>

    <div class="muted" id="empty" style="margin-top:10px; display:none;">
      Sin registros.
    </div>
  </div>

</div>

<script>
(() => {
  const $rows  = document.getElementById('rows');
  const $q     = document.getElementById('q');
  const $empty = document.getElementById('empty');
  const $btnReload = document.getElementById('btnReload');

  let DATA = [];

  const esc = (s) => String(s ?? '')
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");

  const rowCardStyle = `
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow-soft);
  `;

  const pill = (yes) => {
    const ok = !!yes;
    const bg = ok ? 'rgba(239,68,68,.10)' : 'rgba(34,197,94,.10)';
    const fg = ok ? '#ef4444' : '#16a34a';
    const tx = ok ? 'Sí' : 'No';
    return `<span style="display:inline-block;padding:6px 10px;border-radius:999px;font-weight:900;background:${bg};color:${fg};border:1px solid var(--border);">${tx}</span>`;
  };

  async function api(payload){
    const res = await fetch('sesiones_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json().catch(()=>null);
    if(!j || j.ok !== true) throw new Error((j && j.msg) ? j.msg : 'Error interno.');
    return j;
  }

  function render(list){
    $rows.innerHTML = '';
    if(!list.length){
      $empty.style.display = '';
      return;
    }
    $empty.style.display = 'none';

    for(const r of list){
      const revoked = !!r.revocado_en;
      const btnRevoke = revoked
        ? `<button type="button" disabled>Revocada</button>`
        : `<button type="button" data-act="revoke" data-id="${esc(r.id)}">Revocar</button>`;

      const btnDetails = `<button type="button" data-act="details" data-id="${esc(r.id)}">Detalles</button>`;

      $rows.insertAdjacentHTML('beforeend', `
        <tr style="${rowCardStyle}">
          <td style="padding:12px 10px;font-weight:900;">${esc(r.id)}</td>
          <td style="padding:12px 10px;font-weight:900;">${esc(r.usuario || '')}</td>
          <td style="padding:12px 10px;">${esc(r.correo || '')}</td>
          <td style="padding:12px 10px;">${esc(r.ip || '')}</td>
          <td style="padding:12px 10px;">${esc(r.visto_en || '')}</td>
          <td style="padding:12px 10px;">${pill(revoked)}</td>
          <td style="padding:12px 10px;">
            <div style="display:flex; gap:8px; flex-wrap:nowrap; justify-content:flex-end;">
              ${btnDetails}
              ${btnRevoke}
            </div>
          </td>
        </tr>
      `);
    }
  }

  function applyFilter(){
    const q = ($q.value || '').toLowerCase().trim();
    if(!q) return render(DATA);

    const f = DATA.filter(x => {
      const t = `${x.id} ${x.usuario||''} ${x.correo||''} ${x.ip||''} ${x.id_sesion||''} ${x.agente_usuario||''}`.toLowerCase();
      return t.includes(q);
    });
    render(f);
  }

  async function load(){
    try{
      const j = await api({action:'list'});
      DATA = j.data || [];
      applyFilter();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  async function showDetails(id){
    const row = DATA.find(x => String(x.id) === String(id));
    if(!row) return;

    CGL.modal.open({
      title: `Sesión #${esc(row.id)}`,
      body: `
        <div style="display:grid; gap:10px;">
          <div><b>Usuario:</b> ${esc(row.usuario || '')}</div>
          <div><b>Correo:</b> ${esc(row.correo || '')}</div>
          <div><b>ID Sesión:</b> <span class="muted">${esc(row.id_sesion || '')}</span></div>
          <div><b>Huella:</b> <span class="muted">${esc(row.huella_dispositivo || '')}</span></div>
          <div><b>IP:</b> ${esc(row.ip || '')}</div>
          <div><b>Agente:</b> <span class="muted">${esc(row.agente_usuario || '')}</span></div>
          <div><b>Creado:</b> ${esc(row.creado_en || '')}</div>
          <div><b>Visto:</b> ${esc(row.visto_en || '')}</div>
          <div><b>Revocado:</b> ${esc(row.revocado_en || '')}</div>
        </div>
      `,
      footer: `<button type="button" onclick="CGL.modal.close()">Cerrar</button>`
    });
  }

  async function revoke(id){
    const row = DATA.find(x => String(x.id) === String(id));
    if(!row) return;
    if(row.revocado_en) return;

    const ok = await CGL.confirm({
      title:'Revocar sesión',
      text:'El usuario tendrá que iniciar sesión de nuevo.',
      icon:'warning',
      confirmButtonText:'Sí, revocar',
      cancelButtonText:'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'revoke', id: row.id});
      CGL.toast('success','Sesión revocada');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  $q.addEventListener('input', applyFilter);
  $btnReload.addEventListener('click', load);

  $rows.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if(!btn) return;

    const act = btn.dataset.act;
    const id  = btn.dataset.id;

    if(act === 'details') showDetails(id);
    if(act === 'revoke') revoke(id);
  });

  load();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
