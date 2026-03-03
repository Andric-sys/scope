<?php
// /vistas.php (VISTA)
// CRUD Vistas (modal global) + búsqueda rápida

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('VISTAS_ADMIN');

$page_title = 'Vistas';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Vistas</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <div style="flex:1; min-width:220px;">
        <input type="search" id="q" placeholder="Buscar vista (path/clave/título)..." autocomplete="off">
      </div>
      <button type="button" id="btnNew" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">➕ Nueva vista</button>
    </div>

    <div class="muted" style="margin-top:10px;">
      Catálogo de rutas/módulos. Estatus: activa / inactiva.
    </div>
  </div>

  <div class="widget" style="cursor:default; margin-top:16px;">
    <div class="widget-title">Listado</div>

    <div style="overflow:auto; margin-top:10px;">
      <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
        <thead>
          <tr class="muted" style="text-align:left;">
            <th style="padding:6px 10px;">ID</th>
            <th style="padding:6px 10px;">Path</th>
            <th style="padding:6px 10px;">Clave</th>
            <th style="padding:6px 10px;">Título</th>
            <th style="padding:6px 10px;">Estatus</th>
            <th style="padding:6px 10px; width:260px;">Acciones</th>
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
  const $btnNew= document.getElementById('btnNew');

  let DATA = [];

  const esc = (s) => String(s ?? '')
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");

  const badge = (st) => {
    const ok = (String(st||'').toLowerCase() === 'activa');
    const bg = ok ? 'rgba(34,197,94,.12)' : 'rgba(148,163,184,.14)';
    const fg = ok ? '#16a34a' : 'var(--muted)';
    const tx = ok ? 'Activa' : 'Inactiva';
    return `<span style="display:inline-block;padding:6px 10px;border-radius:999px;font-weight:900;background:${bg};color:${fg};border:1px solid var(--border);">${tx}</span>`;
  };

  const rowCardStyle = `
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow-soft);
  `;

  async function api(payload){
    const res = await fetch('vistas_api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json().catch(()=>null);
    if(!j || j.ok !== true){
      throw new Error((j && j.msg) ? j.msg : 'Error interno.');
    }
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
      const id = r.id_vista;

      const btnEdit = `<button type="button" data-act="edit" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">✏️ Editar</button>`;
      const btnToggle = (String(r.estatus).toLowerCase()==='activa')
        ? `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Inactivar</button>`
        : `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Activar</button>`;
      const btnDel  = `<button type="button" data-act="del" data-id="${id}" class="btn btn-secondary" style="border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.08); color:#ef4444; padding:8px 10px;">🗑️ Eliminar</button>`;

      $rows.insertAdjacentHTML('beforeend', `
        <tr style="${rowCardStyle}">
          <td style="padding:12px 10px;font-weight:900;">${esc(id)}</td>
          <td style="padding:12px 10px;font-weight:900;">${esc(r.path)}</td>
          <td style="padding:12px 10px;">${esc(r.clave)}</td>
          <td style="padding:12px 10px;">${esc(r.titulo)}</td>
          <td style="padding:12px 10px;">${badge(r.estatus)}</td>
          <td style="padding:12px 10px;">
            <div style="display:flex; gap:8px; flex-wrap:nowrap; justify-content:flex-end;">
              ${btnEdit}
              ${btnToggle}
              ${btnDel}
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
      const t = `${x.id_vista} ${x.path||''} ${x.clave||''} ${x.titulo||''} ${x.estatus||''}`.toLowerCase();
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

  function openForm({mode='new', row=null}){
    const isEdit = mode === 'edit';
    const title = isEdit ? `Editar vista #${row.id_vista}` : 'Nueva vista';

    const path   = isEdit ? (row.path||'') : '';
    const clave  = isEdit ? (row.clave||'') : '';
    const titulo = isEdit ? (row.titulo||'') : '';
    const estatus= isEdit ? (row.estatus||'activa') : 'activa';

    CGL.modal.open({
      title,
      body: `
        <form id="vistaForm">
          <input type="text" name="path" placeholder="Path (ej: usuarios.php)" required value="${esc(path)}">
          <input type="text" name="clave" placeholder="Clave (ej: USUARIOS)" required value="${esc(clave)}">
          <input type="text" name="titulo" placeholder="Título visible" required value="${esc(titulo)}">
          <select name="estatus" required>
            <option value="activa" ${String(estatus).toLowerCase()==='activa'?'selected':''}>Activa</option>
            <option value="inactiva" ${String(estatus).toLowerCase()==='inactiva'?'selected':''}>Inactiva</option>
          </select>
          <div class="muted" style="margin-top:6px;">
            Sugerencia: mantén el path relativo (sin /) para XAMPP/PWA.
          </div>
        </form>
      `,
      footer: `
        <button type="button" onclick="CGL.modal.close()" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">❌ Cancelar</button>
        <button type="button" id="btnSaveVista" class="btn btn-primary" style="background:var(--core-primary);color:#fff; padding:8px 10px;">✅ Guardar</button>
      `
    });

    setTimeout(() => {
      const btn  = document.getElementById('btnSaveVista');
      const form = document.getElementById('vistaForm');
      if(!btn || !form) return;

      btn.addEventListener('click', async () => {
        const fd = new FormData(form);
        const payload = {
          action: isEdit ? 'update' : 'create',
          id_vista: isEdit ? row.id_vista : undefined,
          path:   String(fd.get('path')||'').trim(),
          clave:  String(fd.get('clave')||'').trim(),
          titulo: String(fd.get('titulo')||'').trim(),
          estatus:String(fd.get('estatus')||'activa').trim()
        };

        if(!payload.path || !payload.clave || !payload.titulo){
          Swal.fire({icon:'warning', title:'Faltan datos', text:'Path, clave y título son obligatorios.'});
          return;
        }
        if(payload.path.length > 200)  return Swal.fire({icon:'warning', title:'Revisa', text:'Path demasiado largo (máx 200).'});
        if(payload.clave.length > 80)  return Swal.fire({icon:'warning', title:'Revisa', text:'Clave demasiado larga (máx 80).'});
        if(payload.titulo.length > 120)return Swal.fire({icon:'warning', title:'Revisa', text:'Título demasiado largo (máx 120).'});
        if(!['activa','inactiva'].includes(payload.estatus)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Estatus inválido.'});
          return;
        }

        const ok = await CGL.confirm({
          title:'Confirmar',
          text: isEdit ? '¿Guardar cambios de la vista?' : '¿Crear esta vista?',
          icon:'question',
          confirmButtonText:'Sí, guardar',
          cancelButtonText:'Cancelar'
        });
        if(!ok) return;

        try{
          await api(payload);
          CGL.toast('success', isEdit ? 'Vista actualizada' : 'Vista creada');
          CGL.modal.close();
          await load();
        }catch(err){
          Swal.fire({icon:'error', title:'Error', text: err.message});
        }
      });
    }, 0);
  }

  async function toggleVista(id){
    const row = DATA.find(x => String(x.id_vista) === String(id));
    if(!row) return;

    const to = (String(row.estatus).toLowerCase()==='activa') ? 'inactiva' : 'activa';

    const ok = await CGL.confirm({
      title:'Cambiar estatus',
      text:`¿Deseas poner la vista como ${to}?`,
      icon:'warning',
      confirmButtonText:'Sí, cambiar',
      cancelButtonText:'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'toggle', id_vista: row.id_vista, estatus: to});
      CGL.toast('success', 'Estatus actualizado');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  async function deleteVista(id){
    const row = DATA.find(x => String(x.id_vista) === String(id));
    if(!row) return;

    const ok = await CGL.confirm({
      title:'Eliminar vista',
      text:'Si está asignada a permisos (usuarios_vistas), se bloqueará.',
      icon:'warning',
      confirmButtonText:'Sí, eliminar',
      cancelButtonText:'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'delete', id_vista: row.id_vista});
      CGL.toast('success','Vista eliminada');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'No se pudo eliminar', text: err.message});
    }
  }

  // UI
  $q.addEventListener('input', applyFilter);
  $btnNew.addEventListener('click', () => openForm({mode:'new'}));

  $rows.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if(!btn) return;

    const act = btn.dataset.act;
    const id  = btn.dataset.id;

    if(act === 'edit'){
      const row = DATA.find(x => String(x.id_vista) === String(id));
      if(row) openForm({mode:'edit', row});
    }
    if(act === 'toggle') toggleVista(id);
    if(act === 'del') deleteVista(id);
  });

  load();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
