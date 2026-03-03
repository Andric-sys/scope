<?php
// /areas.php (VISTA)
// CRUD Áreas (modal global) + búsqueda rápida

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('AREAS');

$page_title = 'Áreas';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Áreas</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <div style="flex:1; min-width:220px;">
        <input type="search" id="q" placeholder="Buscar área..." autocomplete="off">
      </div>
      <button type="button" id="btnNew" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">➕ Nueva área</button>
    </div>

    <div class="muted" style="margin-top:10px;">
      Administra el catálogo de áreas (estatus: activa / inactiva).
    </div>
  </div>

  <div class="widget" style="cursor:default; margin-top:16px;">
    <div class="widget-title">Listado</div>

    <div style="overflow:auto; margin-top:10px;">
      <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
        <thead>
          <tr class="muted" style="text-align:left;">
            <th style="padding:6px 10px;">ID</th>
            <th style="padding:6px 10px;">Nombre</th>
            <th style="padding:6px 10px;">Descripción</th>
            <th style="padding:6px 10px;">Estatus</th>
            <th style="padding:6px 10px; width:240px;">Acciones</th>
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
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");

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

  function render(list){
    $rows.innerHTML = '';

    if(!list.length){
      $empty.style.display = '';
      return;
    }
    $empty.style.display = 'none';

    for(const r of list){
      const id = r.id_area;
      const st = r.estatus;

      const btnEdit = `<button type="button" data-act="edit" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">✏️ Editar</button>`;
      const btnToggle = (String(st).toLowerCase()==='activa')
        ? `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Inactivar</button>`
        : `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Activar</button>`;

      const btnDel = `<button type="button" data-act="del" data-id="${id}" class="btn btn-secondary" style="border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.08); color:#ef4444; padding:8px 10px;">🗑️ Eliminar</button>`;

      $rows.insertAdjacentHTML('beforeend', `
        <tr style="${rowCardStyle}">
          <td style="padding:12px 10px;font-weight:900;">${esc(id)}</td>
          <td style="padding:12px 10px;font-weight:900;">${esc(r.nombre)}</td>
          <td style="padding:12px 10px;">${esc(r.descripcion || '')}</td>
          <td style="padding:12px 10px;">${badge(st)}</td>
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

  async function api(payload){
    const res = await fetch('areas_api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json().catch(()=>null);
    if(!j || j.ok !== true){
      const msg = (j && j.msg) ? j.msg : 'Error interno.';
      throw new Error(msg);
    }
    return j;
  }

  async function load(){
    try{
      const j = await api({ action:'list' });
      DATA = j.data || [];
      applyFilter();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  function applyFilter(){
    const q = ($q.value || '').toLowerCase().trim();
    if(!q) return render(DATA);

    const f = DATA.filter(x => {
      const t = `${x.id_area} ${x.nombre||''} ${x.descripcion||''} ${x.estatus||''}`.toLowerCase();
      return t.includes(q);
    });
    render(f);
  }

  function openForm({mode='new', row=null}){
    const isEdit = (mode==='edit');
    const title = isEdit ? `Editar área #${row.id_area}` : 'Nueva área';

    const nombre = isEdit ? (row.nombre||'') : '';
    const descripcion = isEdit ? (row.descripcion||'') : '';
    const estatus = isEdit ? (row.estatus||'activa') : 'activa';

    CGL.modal.open({
      title,
      body: `
        <form id="areaForm">
          <input type="text" name="nombre" placeholder="Nombre del área" required value="${esc(nombre)}">
          <textarea name="descripcion" placeholder="Descripción (opcional)">${esc(descripcion)}</textarea>
          <select name="estatus" required>
            <option value="activa" ${String(estatus).toLowerCase()==='activa'?'selected':''}>Activa</option>
            <option value="inactiva" ${String(estatus).toLowerCase()==='inactiva'?'selected':''}>Inactiva</option>
          </select>
        </form>
      `,
      footer: `
        <button type="button" onclick="CGL.modal.close()" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">❌ Cancelar</button>
        <button type="button" id="btnSaveArea" class="btn btn-primary" style="background:var(--core-primary);color:#fff; padding:8px 10px;">✅ Guardar</button>
      `
    });

    // “Guardar” del modal (no submit directo para controlar SweetAlert)
    setTimeout(() => {
      const btn = document.getElementById('btnSaveArea');
      const form = document.getElementById('areaForm');
      if(!btn || !form) return;

      btn.addEventListener('click', async () => {
        const fd = new FormData(form);
        const payload = {
          action: isEdit ? 'update' : 'create',
          id_area: isEdit ? row.id_area : undefined,
          nombre: String(fd.get('nombre')||'').trim(),
          descripcion: String(fd.get('descripcion')||'').trim(),
          estatus: String(fd.get('estatus')||'activa').trim()
        };

        // Validación específica
        if(!payload.nombre){
          Swal.fire({icon:'warning', title:'Faltan datos', text:'El nombre es obligatorio.'});
          return;
        }
        if(payload.nombre.length > 150){
          Swal.fire({icon:'warning', title:'Revisa', text:'El nombre es demasiado largo (máx 150).'});
          return;
        }
        if(!['activa','inactiva'].includes(payload.estatus)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Estatus inválido.'});
          return;
        }

        const ok = await CGL.confirm({
          title: 'Confirmar',
          text: isEdit ? '¿Guardar cambios del área?' : '¿Crear esta área?',
          icon: 'question',
          confirmButtonText: 'Sí, guardar',
          cancelButtonText: 'Cancelar'
        });

        if(!ok) return;

        try{
          await api(payload);
          CGL.toast('success', isEdit ? 'Área actualizada' : 'Área creada');
          CGL.modal.close();
          await load();
        }catch(err){
          Swal.fire({icon:'error', title:'Error', text: err.message});
        }
      });
    }, 0);
  }

  async function toggleArea(id){
    const row = DATA.find(x => String(x.id_area) === String(id));
    if(!row) return;

    const to = (String(row.estatus).toLowerCase()==='activa') ? 'inactiva' : 'activa';

    const ok = await CGL.confirm({
      title: 'Cambiar estatus',
      text: `¿Deseas poner el área como ${to}?`,
      icon: 'warning',
      confirmButtonText: 'Sí, cambiar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'toggle', id_area: row.id_area, estatus: to});
      CGL.toast('success', 'Estatus actualizado');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  async function deleteArea(id){
    const row = DATA.find(x => String(x.id_area) === String(id));
    if(!row) return;

    const ok = await CGL.confirm({
      title: 'Eliminar área',
      text: 'Si está asignada a usuarios/empleados, se bloqueará.',
      icon: 'warning',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'delete', id_area: row.id_area});
      CGL.toast('success', 'Área eliminada');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'No se pudo eliminar', text: err.message});
    }
  }

  // Eventos UI
  $q.addEventListener('input', applyFilter);

  $btnNew.addEventListener('click', () => openForm({mode:'new'}));

  $rows.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if(!btn) return;

    const act = btn.dataset.act;
    const id  = btn.dataset.id;

    if(act === 'edit'){
      const row = DATA.find(x => String(x.id_area) === String(id));
      if(row) openForm({mode:'edit', row});
    }
    if(act === 'toggle') toggleArea(id);
    if(act === 'del') deleteArea(id);
  });

  // Init
  load();

})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
