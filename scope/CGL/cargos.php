<?php
// /cargos.php (VISTA)
// CRUD Cargos (modal global) + búsqueda rápida

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('CARGOS');

$page_title = 'Cargos';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Cargos</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <div style="flex:1; min-width:220px;">
        <input type="search" id="q" placeholder="Buscar cargo..." autocomplete="off">
      </div>
      <button type="button" id="btnNew" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">➕ Nuevo cargo</button>
    </div>

    <div class="muted" style="margin-top:10px;">
      Administra el catálogo de cargos (estatus: activo / inactivo).
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
    const ok = (String(st||'').toLowerCase() === 'activo');
    const bg = ok ? 'rgba(34,197,94,.12)' : 'rgba(148,163,184,.14)';
    const fg = ok ? '#16a34a' : 'var(--muted)';
    const tx = ok ? 'Activo' : 'Inactivo';
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
      const id = r.id_cargo;
      const st = r.estatus;

      const btnEdit = `<button type="button" data-act="edit" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">✏️ Editar</button>`;
      const btnToggle = (String(st).toLowerCase()==='activo')
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
    const res = await fetch('cargos_api.php', {
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
      const t = `${x.id_cargo} ${x.nombre||''} ${x.descripcion||''} ${x.estatus||''}`.toLowerCase();
      return t.includes(q);
    });
    render(f);
  }

  function openForm({mode='new', row=null}){
    const isEdit = (mode==='edit');
    const title = isEdit ? `Editar cargo #${row.id_cargo}` : 'Nuevo cargo';

    const nombre = isEdit ? (row.nombre||'') : '';
    const descripcion = isEdit ? (row.descripcion||'') : '';
    const estatus = isEdit ? (row.estatus||'activo') : 'activo';

    CGL.modal.open({
      title,
      body: `
        <form id="cargoForm">
          <input type="text" name="nombre" placeholder="Nombre del cargo" required value="${esc(nombre)}">
          <textarea name="descripcion" placeholder="Descripción (opcional)">${esc(descripcion)}</textarea>
          <select name="estatus" required>
            <option value="activo" ${String(estatus).toLowerCase()==='activo'?'selected':''}>Activo</option>
            <option value="inactivo" ${String(estatus).toLowerCase()==='inactivo'?'selected':''}>Inactivo</option>
          </select>
        </form>
      `,
      footer: `
        <button type="button" onclick="CGL.modal.close()" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">❌ Cancelar</button>
        <button type="button" id="btnSaveCargo" class="btn btn-primary" style="background:var(--core-primary);color:#fff; padding:8px 10px;">✅ Guardar</button>
      `
    });

    setTimeout(() => {
      const btn = document.getElementById('btnSaveCargo');
      const form = document.getElementById('cargoForm');
      if(!btn || !form) return;

      btn.addEventListener('click', async () => {
        const fd = new FormData(form);
        const payload = {
          action: isEdit ? 'update' : 'create',
          id_cargo: isEdit ? row.id_cargo : undefined,
          nombre: String(fd.get('nombre')||'').trim(),
          descripcion: String(fd.get('descripcion')||'').trim(),
          estatus: String(fd.get('estatus')||'activo').trim()
        };

        if(!payload.nombre){
          Swal.fire({icon:'warning', title:'Faltan datos', text:'El nombre es obligatorio.'});
          return;
        }
        if(payload.nombre.length > 150){
          Swal.fire({icon:'warning', title:'Revisa', text:'El nombre es demasiado largo (máx 150).'});
          return;
        }
        if(!['activo','inactivo'].includes(payload.estatus)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Estatus inválido.'});
          return;
        }

        const ok = await CGL.confirm({
          title: 'Confirmar',
          text: isEdit ? '¿Guardar cambios del cargo?' : '¿Crear este cargo?',
          icon: 'question',
          confirmButtonText: 'Sí, guardar',
          cancelButtonText: 'Cancelar'
        });
        if(!ok) return;

        try{
          await api(payload);
          CGL.toast('success', isEdit ? 'Cargo actualizado' : 'Cargo creado');
          CGL.modal.close();
          await load();
        }catch(err){
          Swal.fire({icon:'error', title:'Error', text: err.message});
        }
      });
    }, 0);
  }

  async function toggleCargo(id){
    const row = DATA.find(x => String(x.id_cargo) === String(id));
    if(!row) return;

    const to = (String(row.estatus).toLowerCase()==='activo') ? 'inactivo' : 'activo';

    const ok = await CGL.confirm({
      title: 'Cambiar estatus',
      text: `¿Deseas poner el cargo como ${to}?`,
      icon: 'warning',
      confirmButtonText: 'Sí, cambiar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'toggle', id_cargo: row.id_cargo, estatus: to});
      CGL.toast('success', 'Estatus actualizado');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  async function deleteCargo(id){
    const row = DATA.find(x => String(x.id_cargo) === String(id));
    if(!row) return;

    const ok = await CGL.confirm({
      title: 'Eliminar cargo',
      text: 'Si está asignado a empleados, se bloqueará.',
      icon: 'warning',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'delete', id_cargo: row.id_cargo});
      CGL.toast('success', 'Cargo eliminado');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'No se pudo eliminar', text: err.message});
    }
  }

  $q.addEventListener('input', applyFilter);
  $btnNew.addEventListener('click', () => openForm({mode:'new'}));

  $rows.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if(!btn) return;
    const act = btn.dataset.act;
    const id  = btn.dataset.id;

    if(act === 'edit'){
      const row = DATA.find(x => String(x.id_cargo) === String(id));
      if(row) openForm({mode:'edit', row});
    }
    if(act === 'toggle') toggleCargo(id);
    if(act === 'del') deleteCargo(id);
  });

  load();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
