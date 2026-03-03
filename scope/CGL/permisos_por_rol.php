<?php
// /permisos_por_rol.php (VISTA)
// Permisos por rol (usuarios_vistas) — matriz rol ↔ vistas

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('PERMISOS_VISTAS');

$page_title = 'Permisos por Rol';
include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <div class="widget" style="cursor:default;">
    <div class="widget-title">📖 ¿Cómo ver y crear vistas?</div>
    
    <div style="background: rgba(102, 126, 234, 0.08); border-left: 4px solid #667eea; padding: 14px; border-radius: 8px; margin-bottom: 16px;">
      <div style="font-size: 13px; line-height: 1.6; color: var(--text);">
        <strong>Las "Vistas" son módulos o páginas de tu aplicación</strong> que quieres controlar con permisos.<br>
        <br>
        <strong>Para crear/editar vistas:</strong> Ve a <a href="vistas.php" style="color: #667eea; font-weight: 900;">Administración de Vistas</a> y crea nuevas vistas (ej: usuarios.php, dashboard.php).<br>
        <br>
        <strong>Aquí asignas:</strong> Qué vistas/módulos puede ver cada rol (ej: el rol "Vendedor" solo ve dashboard y clientes).
      </div>
    </div>
  </div>

  <div class="widget" style="cursor:default;">
    <div class="widget-title">Permisos por Rol</div>

    <div class="muted" style="margin-top:6px;">
      Asigna acceso a vistas por rol (tabla usuarios_vistas).
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:12px; justify-content:space-between;">
      <div style="flex:1; min-width:260px;">
        <select id="roleSelect">
          <option value="">Selecciona un rol...</option>
        </select>
      </div>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button type="button" id="btnSave" disabled class="btn btn-primary" style="background:var(--core-primary);color:#fff; padding:8px 10px;">💾 Guardar cambios</button>
        <a href="vistas.php" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); text-decoration:none; padding:8px 10px;">🔧 Crear Vistas</a>
        <a href="roles.php" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text); text-decoration:none; padding:8px 10px;">← Volver</a>
      </div>
    </div>
  </div>

  <div class="widget" style="cursor:default; margin-top:16px;">
    <div class="widget-title">Vistas</div>

    <div class="muted" id="hint" style="margin-top:10px;">
      Selecciona un rol para cargar sus permisos.
    </div>

    <div style="background: rgba(102, 126, 234, 0.05); border: 1px solid rgba(102, 126, 234, 0.2); padding: 12px; border-radius: 8px; margin: 12px 0; display: none;" id="infoBox">
      <div style="font-size: 12px; color: var(--muted); line-height: 1.6;">
        <strong style="color: var(--text);">💡 Información:</strong><br>
        Marca una vista para <strong>permitir</strong> que este rol la vea.<br>
        Desmarca para <strong>denegar</strong> el acceso.
      </div>
    </div>

    <div style="overflow:auto; margin-top:10px;">
      <table style="width:100%; border-collapse:separate; border-spacing:0 10px;">
        <thead>
          <tr class="muted" style="text-align:left;">
            <th style="padding:6px 10px;">ID</th>
            <th style="padding:6px 10px;">Título</th>
            <th style="padding:6px 10px;">Path</th>
            <th style="padding:6px 10px;">Clave</th>
            <th style="padding:6px 10px; width:140px; text-align:right;">Permitido</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
    </div>

    <div class="muted" id="empty" style="margin-top:10px; display:none;">Sin vistas.</div>
  </div>

</div>

<script>
(() => {
  const $role  = document.getElementById('roleSelect');
  const $rows  = document.getElementById('rows');
  const $empty = document.getElementById('empty');
  const $hint  = document.getElementById('hint');
  const $btnSave = document.getElementById('btnSave');

  let META = { roles: [], vistas: [] };
  let CURRENT_ROLE_ID = null;
  let PERMS = new Map(); // id_vista -> permitido (0/1)
  let DIRTY = false;

  const esc = (s) => String(s ?? '')
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");

  const rowCardStyle = `
    background:var(--card);
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow-soft);
  `;

  async function api(payload){
    const res = await fetch('permisos_por_rol_api.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json().catch(()=>null);
    if(!j || j.ok !== true) throw new Error((j && j.msg) ? j.msg : 'Error interno.');
    return j;
  }

  function setDirty(v){
    DIRTY = !!v;
    $btnSave.disabled = !CURRENT_ROLE_ID || !DIRTY;
  }

  function renderTable(){
    $rows.innerHTML = '';
    const vistas = META.vistas || [];
    if(!vistas.length){
      $empty.style.display = '';
      return;
    }
    $empty.style.display = 'none';

    for(const v of vistas){
      const allowed = Number(PERMS.get(String(v.id_vista)) || 0) === 1;

      $rows.insertAdjacentHTML('beforeend', `
        <tr style="${rowCardStyle}">
          <td style="padding:12px 10px;font-weight:900;">${esc(v.id_vista)}</td>
          <td style="padding:12px 10px;font-weight:900;">${esc(v.titulo)}</td>
          <td style="padding:12px 10px;">${esc(v.path)}</td>
          <td style="padding:12px 10px;">${esc(v.clave)}</td>
          <td style="padding:12px 10px; text-align:right;">
            <label style="display:inline-flex; align-items:center; gap:10px; font-weight:900;">
              <input type="checkbox" data-vista="${esc(v.id_vista)}" ${allowed ? 'checked' : ''} style="width:18px;height:18px;">
              ${allowed ? 'Sí' : 'No'}
            </label>
          </td>
        </tr>
      `);
    }
  }

  async function loadMeta(){
    const j = await api({action:'meta'});
    META.roles = j.roles || [];
    META.vistas = j.vistas || [];

    // Roles select
    $role.innerHTML = `<option value="">Selecciona un rol...</option>`;
    for(const r of META.roles){
      $role.insertAdjacentHTML('beforeend', `<option value="${esc(r.id_rol)}">${esc(r.nombre)} (${esc(r.estatus)})</option>`);
    }
  }

  async function loadRolePerms(id_rol){
    CURRENT_ROLE_ID = id_rol ? String(id_rol) : null;
    PERMS = new Map();
    setDirty(false);

    if(!CURRENT_ROLE_ID){
      $hint.style.display = '';
      $hint.innerHTML = '👉 Selecciona un rol para cargar sus permisos.';
      document.getElementById('infoBox').style.display = 'none';
      $rows.innerHTML = '';
      $empty.style.display = 'none';
      $btnSave.disabled = true;
      return;
    }

    try{
      const j = await api({action:'get', id_rol: CURRENT_ROLE_ID});
      const allowed = j.allowed || [];
      for(const id_vista of allowed){
        PERMS.set(String(id_vista), 1);
      }
      $hint.style.display = 'none';
      document.getElementById('infoBox').style.display = '';
      renderTable();
      $btnSave.disabled = true;
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  }

  $role.addEventListener('change', () => loadRolePerms($role.value));

  $rows.addEventListener('change', (e) => {
    const cb = e.target.closest('input[type="checkbox"][data-vista]');
    if(!cb) return;

    const id_vista = String(cb.dataset.vista);
    PERMS.set(id_vista, cb.checked ? 1 : 0);

    // Actualiza texto Sí/No
    const label = cb.closest('label');
    if(label) label.lastChild.nodeValue = cb.checked ? 'Sí' : 'No';

    setDirty(true);
  });

  $btnSave.addEventListener('click', async () => {
    if(!CURRENT_ROLE_ID) return;

    const list = [];
    for(const v of META.vistas){
      const idv = String(v.id_vista);
      list.push({ id_vista: idv, permitido: Number(PERMS.get(idv) || 0) });
    }

    const ok = await CGL.confirm({
      title:'Guardar permisos',
      text:'Se actualizarán los accesos de este rol.',
      icon:'question',
      confirmButtonText:'Sí, guardar',
      cancelButtonText:'Cancelar'
    });
    if(!ok) return;

    try{
      await api({action:'save', id_rol: CURRENT_ROLE_ID, permisos: list});
      CGL.toast('success', 'Permisos actualizados');
      setDirty(false);
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  });

  (async () => {
    try{
      await loadMeta();
      
      // Si viene id_rol en URL, cargar automáticamente
      const urlParams = new URLSearchParams(window.location.search);
      const id_rol_param = urlParams.get('id_rol');
      if(id_rol_param){
        $role.value = id_rol_param;
        await loadRolePerms(id_rol_param);
      }
    }catch(err){
      Swal.fire({icon:'error', title:'Error', text: err.message});
    }
  })();

})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
