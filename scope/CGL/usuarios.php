<?php
// /usuarios.php (VISTA)
// CRUD Usuarios (modal global) + búsqueda rápida + selects roles/empleados/áreas + SUBIDA DE FOTO

require __DIR__ . '/auth.php';
// Validar acceso a esta vista
require_view_access('USUARIOS');
$page_title = 'Usuarios';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Usuarios</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <div style="flex:1; min-width:220px;">
        <input type="search" id="q" placeholder="Buscar (nombre, correo, rol, estatus)..." autocomplete="off">
      </div>
      <button type="button" id="btnNew" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">➕ Nuevo usuario</button>
    </div>

    <div class="muted" style="margin-top:10px;">
      Administra usuarios (rol requerido; empleado/área opcionales).
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
            <th style="padding:6px 10px;">Correo</th>
            <th style="padding:6px 10px;">Rol</th>
            <th style="padding:6px 10px;">Área</th>
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
  let META = { roles: [], empleados: [], areas: [] };

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

  function optList(list, selectedId, emptyLabel='— Sin asignar —'){
    const sel = String(selectedId ?? '');
    return [`<option value="">${esc(emptyLabel)}</option>`]
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
      const id = r.id_usuario;

      const btnEdit = `<button type="button" data-act="edit" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">✏️ Editar</button>`;
      const btnToggle = (String(r.estatus).toLowerCase()==='activo')
        ? `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Inactivar</button>`
        : `<button type="button" data-act="toggle" data-id="${id}" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">🔁 Activar</button>`;
      const btnDel = `<button type="button" data-act="del" data-id="${id}" class="btn btn-secondary" style="border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.08); color:#ef4444; padding:8px 10px;">🗑️ Eliminar</button>`;

      const fullName = `${r.nombre||''} ${r.apellido||''}`.trim();

      $rows.insertAdjacentHTML('beforeend', `
        <tr style="${rowCardStyle}">
          <td style="padding:12px 10px;font-weight:900;">${esc(r.id_usuario)}</td>
          <td style="padding:12px 10px;">${esc(fullName)}</td>
          <td style="padding:12px 10px;">${esc(r.correo)}</td>
          <td style="padding:12px 10px;">${esc(r.rol_nombre || '—')}</td>
          <td style="padding:12px 10px;">${esc(r.area_nombre || '—')}</td>
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

  // JSON API (para meta/list/toggle/delete)
  async function apiJSON(payload){
    const res = await fetch('usuarios_api.php', {
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

  // FormData API (para create/update con archivo)
  async function apiForm(fd){
    const res = await fetch('usuarios_api.php', {
      method: 'POST',
      body: fd
    });
    const j = await res.json().catch(()=>null);
    if(!j || j.ok !== true){
      throw new Error((j && j.msg) ? j.msg : 'Error interno.');
    }
    return j;
  }

  async function loadMeta(){
    const j = await apiJSON({ action:'meta' });
    META.roles = j.roles || [];
    META.empleados = j.empleados || [];
    META.areas = j.areas || [];
  }

  async function load(){
    try{
      await loadMeta();
      const j = await apiJSON({ action:'list' });
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
      const t = `${x.id_usuario} ${x.nombre||''} ${x.apellido||''} ${x.correo||''} ${x.rol_nombre||''} ${x.area_nombre||''} ${x.estatus||''}`.toLowerCase();
      return t.includes(q);
    });
    render(f);
  }

  function openForm({mode='new', row=null}){
    const isEdit = (mode==='edit');
    const title = isEdit ? `Editar usuario #${row.id_usuario}` : 'Nuevo usuario';
    const v = (k, d='') => isEdit ? (row[k] ?? d) : d;

    const currentFoto = String(v('foto_perfil','') || '').trim();
    const currentFotoHTML = currentFoto
      ? `<div class="muted" style="margin-top:6px;">Foto actual:</div>
         <div style="display:flex; gap:10px; align-items:center; margin-top:6px;">
           <img src="${esc(currentFoto)}" alt="foto" style="width:54px;height:54px;border-radius:12px;object-fit:cover;border:1px solid var(--border);">
           <div class="muted" style="font-size:12px; word-break:break-all;">${esc(currentFoto)}</div>
         </div>`
      : `<div class="muted" style="margin-top:6px;">Sin foto.</div>`;

    CGL.modal.open({
      title,
      body: `
        <form id="usrForm" enctype="multipart/form-data">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <input type="text" name="nombre" placeholder="Nombre(s)" required value="${esc(v('nombre'))}">
            <input type="text" name="apellido" placeholder="Apellidos" required value="${esc(v('apellido'))}">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
            <input type="email" name="correo" placeholder="Correo" required value="${esc(v('correo'))}">
            <input type="text" name="num_telefono" placeholder="Teléfono (opcional)" value="${esc(v('num_telefono',''))}">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
            <select name="id_rol" required>
              <option value="">— Selecciona rol —</option>
              ${META.roles.map(x => {
                const s = String(x.id) === String(v('id_rol','')) ? 'selected' : '';
                return `<option value="${esc(x.id)}" ${s}>${esc(x.nombre)}</option>`;
              }).join('')}
            </select>

            <select name="estatus" required>
              <option value="activo" ${String(v('estatus','activo')).toLowerCase()==='activo'?'selected':''}>Activo</option>
              <option value="inactivo" ${String(v('estatus','activo')).toLowerCase()==='inactivo'?'selected':''}>Inactivo</option>
            </select>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
            <select name="id_empleado">
              ${optList(META.empleados, v('id_empleado',''), '— Sin empleado —')}
            </select>
            <select name="id_area">
              ${optList(META.areas, v('id_area',''), '— Sin área —')}
            </select>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px;">
            <input type="text" name="password" placeholder="${isEdit ? 'Password (dejar vacío para no cambiar)' : 'Password'}" ${isEdit ? '' : 'required'} value="">
            <div>
              <input type="file" id="foto_file" name="foto_file" accept="image/png,image/jpeg,image/webp" />
              <div class="muted" style="font-size:12px; margin-top:6px;">
                PNG/JPG/WEBP · máximo 2MB
              </div>
              <div id="fotoPreviewWrap" style="margin-top:8px; display:none; gap:10px; align-items:center;">
                <img id="fotoPreview" src="" alt="preview" style="width:54px;height:54px;border-radius:12px;object-fit:cover;border:1px solid var(--border);">
                <button type="button" id="btnClearFoto">Quitar</button>
              </div>
            </div>
          </div>

          <input type="hidden" name="foto_actual" value="${esc(currentFoto)}">

          ${isEdit ? `<div class="muted" style="margin-top:8px;">* Para mantener el password actual, deja el campo vacío.</div>` : ''}

          <div style="margin-top:10px;">
            ${currentFotoHTML}
          </div>
        </form>
      `,
      footer: `
        <button type="button" onclick="CGL.modal.close()" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">❌ Cancelar</button>
        <button type="button" id="btnSaveUsr" class="btn btn-primary" style="background:var(--core-primary);color:#fff; padding:8px 10px;">✅ Guardar</button>
      `
    });

    setTimeout(() => {
      const btn = document.getElementById('btnSaveUsr');
      const form = document.getElementById('usrForm');
      const file = document.getElementById('foto_file');
      const prevWrap = document.getElementById('fotoPreviewWrap');
      const prevImg = document.getElementById('fotoPreview');
      const btnClear = document.getElementById('btnClearFoto');

      if(file && prevWrap && prevImg){
        file.addEventListener('change', () => {
          const f = file.files && file.files[0] ? file.files[0] : null;
          if(!f){
            prevWrap.style.display = 'none';
            prevImg.src = '';
            return;
          }
          // preview
          prevImg.src = URL.createObjectURL(f);
          prevWrap.style.display = 'flex';
        });
      }

      if(btnClear && file){
        btnClear.addEventListener('click', () => {
          file.value = '';
          if(prevWrap) prevWrap.style.display = 'none';
          if(prevImg) prevImg.src = '';
        });
      }

      if(!btn || !form) return;

      btn.addEventListener('click', async () => {
        const fd = new FormData(form);

        const nombre   = String(fd.get('nombre')||'').trim();
        const apellido = String(fd.get('apellido')||'').trim();
        const correo   = String(fd.get('correo')||'').trim();
        const tel      = String(fd.get('num_telefono')||'').trim();
        const password = String(fd.get('password')||'').trim();
        const id_rol   = String(fd.get('id_rol')||'').trim();
        const id_emp   = String(fd.get('id_empleado')||'').trim();
        const id_area  = String(fd.get('id_area')||'').trim();
        const estatus  = String(fd.get('estatus')||'activo').trim();

        if(!nombre || !apellido || !correo || !id_rol){
          Swal.fire({icon:'warning', title:'Faltan datos', text:'Nombre, apellido, correo y rol son obligatorios.'});
          return;
        }
        if(correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Correo inválido.'});
          return;
        }
        if(!['activo','inactivo'].includes(estatus)){
          Swal.fire({icon:'warning', title:'Revisa', text:'Estatus inválido.'});
          return;
        }
        if(!isEdit && !password){
          Swal.fire({icon:'warning', title:'Faltan datos', text:'El password es obligatorio para crear.'});
          return;
        }
        if(password && password.length < 4){
          Swal.fire({icon:'warning', title:'Revisa', text:'El password es muy corto (mín 4).'});
          return;
        }

        // validar archivo (cliente, la real la hace PHP)
        const f = (document.getElementById('foto_file')?.files?.[0]) || null;
        if(f){
          const okType = ['image/png','image/jpeg','image/webp'].includes(f.type);
          if(!okType){
            Swal.fire({icon:'warning', title:'Archivo inválido', text:'Solo PNG/JPG/WEBP.'});
            return;
          }
          if(f.size > 2 * 1024 * 1024){
            Swal.fire({icon:'warning', title:'Archivo muy grande', text:'Máximo 2MB.'});
            return;
          }
        }

        const ok = await CGL.confirm({
          title: 'Confirmar',
          text: isEdit ? '¿Guardar cambios del usuario?' : '¿Crear este usuario?',
          icon: 'question',
          confirmButtonText: 'Sí, guardar',
          cancelButtonText: 'Cancelar'
        });
        if(!ok) return;

        try{
          // Armamos FormData explícito (para evitar basura)
          const out = new FormData();
          out.append('action', isEdit ? 'update' : 'create');
          if(isEdit) out.append('id_usuario', String(row.id_usuario));

          out.append('nombre', nombre);
          out.append('apellido', apellido);
          out.append('correo', correo);
          out.append('num_telefono', tel);

          out.append('password', password); // puede ir vacío en update
          out.append('id_rol', id_rol);
          out.append('id_empleado', id_emp);
          out.append('id_area', id_area);

          out.append('estatus', estatus);
          out.append('foto_actual', String(fd.get('foto_actual')||''));

          if(f) out.append('foto_file', f);

          await apiForm(out);

          CGL.toast('success', isEdit ? 'Usuario actualizado' : 'Usuario creado');
          CGL.modal.close();
          await load();
        }catch(err){
          Swal.fire({icon:'error', title:'Error', text: err.message});
        }
      });
    }, 0);
  }

  async function toggleUsuario(id){
    const row = DATA.find(x => String(x.id_usuario) === String(id));
    if(!row) return;

    const to = (String(row.estatus).toLowerCase()==='activo') ? 'inactivo' : 'activo';

    const ok = await CGL.confirm({
      title: 'Cambiar estatus',
      text: `¿Deseas poner el usuario como ${to}?`,
      icon: 'warning',
      confirmButtonText: 'Sí, cambiar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await apiJSON({action:'toggle', id_usuario: row.id_usuario, estatus: to});
      CGL.toast('success', 'Estatus actualizado');
      await load();
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  async function deleteUsuario(id){
    const row = DATA.find(x => String(x.id_usuario) === String(id));
    if(!row) return;

    const ok = await CGL.confirm({
      title: 'Eliminar usuario',
      text: 'Esta acción elimina el usuario y sus sesiones asociadas (cascade).',
      icon: 'warning',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    });
    if(!ok) return;

    try{
      await apiJSON({action:'delete', id_usuario: row.id_usuario});
      CGL.toast('success', 'Usuario eliminado');
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
      const row = DATA.find(x => String(x.id_usuario) === String(id));
      if(row) openForm({mode:'edit', row});
    }
    if(act === 'toggle') toggleUsuario(id);
    if(act === 'del') deleteUsuario(id);
  });

  load();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
