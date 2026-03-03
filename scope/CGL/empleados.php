<?php
// /empleados.php (VISTA)
// CRUD Empleados (modal global) + búsqueda rápida + selects áreas/cargos

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('EMPLEADOS');

$page_title = 'Empleados';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Empleados</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <div style="flex:1; min-width:220px;">
        <input type="search" id="q" placeholder="Buscar (No. empleado, nombre, correo, área, cargo)..." autocomplete="off">
      </div>
      <button type="button" id="btnNew" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">➕ Nuevo empleado</button>
    </div>

    <div class="muted" style="margin-top:10px;">
      Administra empleados (estatus: activo / baja). Áreas y cargos son opcionales (FK NULL).
    </div>
  </div>

  <div class="widget" style="cursor:default; margin-top:16px;">
    <div class="widget-title">Listado</div>

    <div style="overflow:auto; margin-top:10px;">
      <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
        <thead>
          <tr class="muted" style="text-align:left;">
            <th style="padding:6px 10px;">ID</th>
            <th style="padding:6px 10px;">No. Empleado</th>
            <th style="padding:6px 10px;">Nombre</th>
            <th style="padding:6px 10px;">Área</th>
            <th style="padding:6px 10px;">Cargo</th>
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
  let META = { areas: [], cargos: [] };

  const esc = (s) => String(s ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");

  const badge = (st) => {
    const ok = (String(st||'').toLowerCase() === 'activo');
    const bg = ok ? 'rgba(34,197,94,.12)' : 'rgba(239,68,68,.10)';
    const fg = ok ? '#16a34a' : '#ef4444';
    const tx = ok ? 'Activo' : 'Baja';
    return `<span style="display:inline-block;padding:6px 10px;border-radius:999px;font-weight:900;background:${bg};color:${fg};border:1px solid var(--border);">${tx}</span>`;
  };

  const rowCardStyle = `
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow-soft);
  `;

  function optList(list, selectedId){
    const sel = String(selectedId ?? '');
    return ['<option value="">— Sin asignar —</option>']
      .concat((list||[]).map(x => {
        const id = String(x.id);
        const nm = String(x.nombre ?? '');
        const s = (id === sel) ? 'selected' : '';
        return `<option value="${esc(id)}" ${s}>${esc(nm)}</option>`;
      }))
      .join('');
  }

  function render(list){
    $rows.innerHTML = '';
    if(!list.length){
      $empty.style.display = '';
      return;
    }
    $empty.style.display = 'none';

    for(const r of list){
      const id = r.id_empleado;

      const btnEdit = `<button type="button" data-act="edit" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">✏️ Editar</button>`;
      const btnToggle = (String(r.estatus).toLowerCase()==='activo')
        ? `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Dar de baja</button>`
        : `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Reactivar</button>`;
      const btnDel = `<button type="button" data-act="del" data-id="${id}" class="btn btn-secondary" style="border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.08); color:#ef4444; padding:8px 10px;">🗑️ Eliminar</button>`;

      const fullName = `${r.nombre||''} ${r.apellido||''}`.trim();

      $rows.insertAdjacentHTML('beforeend', `
        <tr style="${rowCardStyle}">
          <td style="padding:12px 10px;font-weight:900;">${esc(r.id_empleado)}</td>
          <td style="padding:12px 10px;font-weight:900;">${esc(r.no_empleado)}</td>
          <td style="padding:12px 10px;">${esc(fullName)}</td>
          <td style="padding:12px 10px;">${esc(r.area_nombre || '—')}</td>
          <td style="padding:12px 10px;">${esc(r.cargo_nombre || '—')}</td>
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

  async function api(payload){
    const res = await fetch('empleados_api.php', {
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

  async function loadMeta(){
    const j = await api({ action:'meta' });
    META.areas = j.areas || [];
    META.cargos = j.cargos || [];
  }

  async function load(){
    try{
      await loadMeta();
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
      const t = `${x.id_empleado} ${x.no_empleado||''} ${x.nombre||''} ${x.apellido||''} ${x.correo||''} ${x.telefono||''} ${x.area_nombre||''} ${x.cargo_nombre||''} ${x.estatus||''}`.toLowerCase();
      return t.includes(q);
    });
    render(f);
  }

  function openForm({mode='new', row=null}){
    const isEdit = (mode==='edit');
    const title = isEdit ? `Editar empleado #${row.id_empleado}` : 'Nuevo empleado';

    const v = (k, d='') => isEdit ? (row[k] ?? d) : d;

    CGL.modal.open({
      title,
      body: `
        <form id="empForm">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">No. Empleado</label>
              <input type="text" name="no_empleado" required value="${esc(v('no_empleado'))}" style="width:100%;">
            </div>
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Estatus</label>
              <select name="estatus" required style="width:100%;">
                <option value="activo" ${String(v('estatus','activo')).toLowerCase()==='activo'?'selected':''}>Activo</option>
                <option value="baja" ${String(v('estatus','activo')).toLowerCase()==='baja'?'selected':''}>Baja</option>
              </select>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Nombre(s)</label>
              <input type="text" name="nombre" required value="${esc(v('nombre'))}" style="width:100%;">
            </div>
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Apellidos</label>
              <input type="text" name="apellido" required value="${esc(v('apellido'))}" style="width:100%;">
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Área</label>
              <select name="id_area" style="width:100%;">
                ${optList(META.areas, v('id_area',''))}
              </select>
            </div>
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Cargo</label>
              <select name="id_cargo" style="width:100%;">
                ${optList(META.cargos, v('id_cargo',''))}
              </select>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Teléfono</label>
              <input type="text" name="telefono" value="${esc(v('telefono',''))}" style="width:100%;">
            </div>
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Correo</label>
              <input type="email" name="correo" value="${esc(v('correo',''))}" style="width:100%;">
            </div>
          </div>

          <div style="margin-top:14px;">
            <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Dirección</label>
            <textarea name="direccion" style="width:100%;">${esc(v('direccion',''))}</textarea>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Fecha Ingreso</label>
              <input type="date" name="fecha_ingreso" value="${esc(v('fecha_ingreso',''))}" style="width:100%;">
            </div>
            <div>
              <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Fecha Salida</label>
              <input type="date" name="fecha_salida" value="${esc(v('fecha_salida',''))}" style="width:100%;">
            </div>
          </div>

          <div style="margin-top:14px;">
            <label style="display:block; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; color:var(--muted);">Foto de Perfil</label>
            <input type="file" name="foto_file" accept="image/jpeg,image/png,image/webp" style="width:100%;">
            <div style="font-size:11px; color:var(--muted); margin-top:4px;">Formatos: JPG, PNG, WEBP (máx 2MB)</div>
          </div>
        </form>
      `,
      footer: `
        <button type="button" onclick="CGL.modal.close()" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">❌ Cancelar</button>
        <button type="button" id="btnSaveEmp" class="btn btn-primary" style="background:var(--core-primary);color:#fff; padding:8px 10px;">✅ Guardar</button>
      `
    });

    setTimeout(() => {
      const btn = document.getElementById('btnSaveEmp');
      const form = document.getElementById('empForm');
      if(!btn || !form) return;

      btn.addEventListener('click', async () => {
        const fd = new FormData(form);

        const payload = new FormData();
        payload.append('action', isEdit ? 'update' : 'create');
        payload.append('id_empleado', isEdit ? row.id_empleado : '');

        payload.append('no_empleado', String(fd.get('no_empleado')||'').trim());
        payload.append('nombre', String(fd.get('nombre')||'').trim());
        payload.append('apellido', String(fd.get('apellido')||'').trim());

        payload.append('id_area', String(fd.get('id_area')||'').trim());
        payload.append('id_cargo', String(fd.get('id_cargo')||'').trim());

        payload.append('telefono', String(fd.get('telefono')||'').trim());
        payload.append('correo', String(fd.get('correo')||'').trim());
        payload.append('direccion', String(fd.get('direccion')||'').trim());

        payload.append('estatus', String(fd.get('estatus')||'activo').trim());

        payload.append('fecha_ingreso', String(fd.get('fecha_ingreso')||'').trim());
        payload.append('fecha_salida', String(fd.get('fecha_salida')||'').trim());

        // Agregar archivo de foto si existe
        const fotoFile = fd.get('foto_file');
        if(fotoFile && fotoFile.size > 0){
          payload.append('foto_file', fotoFile);
        }

        // Validaciones básicas
        const no_empl = String(payload.get('no_empleado')||'').trim();
        const nombre_e = String(payload.get('nombre')||'').trim();
        const apellido_e = String(payload.get('apellido')||'').trim();
        const correo_e = String(payload.get('correo')||'').trim();
        const estatus_e = String(payload.get('estatus')||'activo').trim();

        if(!no_empl || !nombre_e || !apellido_e){
          Swal.fire({icon:'warning', title:'Faltan datos', text:'No. empleado, nombre y apellido son obligatorios.'});
          return;
        }
        if(no_empl.length > 50){
          Swal.fire({icon:'warning', title:'Revisa', text:'No. empleado demasiado largo (máx 50).'});
          return;
        }
        if(nombre_e.length > 100 || apellido_e.length > 100){
          Swal.fire({icon:'warning', title:'Revisa', text:'Nombre/apellido demasiado largos.'});
          return;
        }
        if(correo_e && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo_e)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Correo inválido.'});
          return;
        }
        if(!['activo','baja'].includes(estatus_e)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Estatus inválido.'});
          return;
        }

        const ok = await CGL.confirm({
          title: 'Confirmar',
          text: isEdit ? '¿Guardar cambios del empleado?' : '¿Crear este empleado?',
          icon: 'question',
          confirmButtonText: 'Sí, guardar',
          cancelButtonText: 'Cancelar'
        });
        if(!ok) return;

        try{
          const res = await fetch('empleados_api.php', {
            method: 'POST',
            body: payload
          }).then(r=>r.json());
          
          if(!res.ok) throw new Error(res.msg || 'Error desconocido');
          
          CGL.toast('success', isEdit ? 'Empleado actualizado' : 'Empleado creado');
          CGL.modal.close();
          await load();
        }catch(err){
          Swal.fire({icon:'error', title:'Error', text: err.message});
        }
      });
    }, 0);
  }

  async function toggleEmpleado(id){
    const row = DATA.find(x => String(x.id_empleado) === String(id));
    if(!row) return;

    const to = (String(row.estatus).toLowerCase()==='activo') ? 'baja' : 'activo';

    const ok = await CGL.confirm({
      title: 'Cambiar estatus',
      text: `¿Deseas poner el empleado como ${to}?`,
      icon: 'warning',
      confirmButtonText: 'Sí, cambiar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'toggle', id_empleado: row.id_empleado, estatus: to});
      CGL.toast('success', 'Estatus actualizado');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  async function deleteEmpleado(id){
    const row = DATA.find(x => String(x.id_empleado) === String(id));
    if(!row) return;

    const ok = await CGL.confirm({
      title: 'Eliminar empleado',
      text: 'Si está ligado a usuarios, se bloqueará.',
      icon: 'warning',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'delete', id_empleado: row.id_empleado});
      CGL.toast('success', 'Empleado eliminado');
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
      const row = DATA.find(x => String(x.id_empleado) === String(id));
      if(row) openForm({mode:'edit', row});
    }
    if(act === 'toggle') toggleEmpleado(id);
    if(act === 'del') deleteEmpleado(id);
  });

  load();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
