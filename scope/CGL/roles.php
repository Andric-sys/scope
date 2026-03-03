<?php
// /roles.php (VISTA)
// CRUD de Roles (tabla roles)
// - Usa roles_api.php como backend AJAX
// - Requiere sesión: auth.php
declare(strict_types=1);

require __DIR__ . '/auth.php';

// Validar acceso a esta vista
require_view_access('ROLES');

$page_title = 'Roles';
include __DIR__ . '/partials/header.php';
?>
<div class="wrap">
  <div class="widget" style="cursor:default;">
    <div class="widget-title">Administración de Roles</div>
    <div class="muted">Crea, edita y administra estatus de roles.</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; align-items:center; justify-content:space-between;">
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button type="button" id="btnNuevo" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">
          ➕ Nuevo rol
        </button>

        <button type="button" id="btnRefrescar" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text);">
          🔄 Refrescar
        </button>
      </div>

      <div style="min-width:260px; max-width:420px; width:100%;">
        <input type="search" id="q" placeholder="Buscar rol..." autocomplete="off">
      </div>
    </div>

    <div style="margin-top:14px; overflow:auto; border:1px solid var(--border); border-radius:16px;">
      <table id="tabla" style="width:100%; border-collapse:collapse; min-width:820px;">
        <thead>
          <tr style="background:var(--bg-soft);">
            <th style="text-align:left; padding:12px; font-weight:900;">ID</th>
            <th style="text-align:left; padding:12px; font-weight:900;">Nombre</th>
            <th style="text-align:left; padding:12px; font-weight:900;">Descripción</th>
            <th style="text-align:left; padding:12px; font-weight:900;">Estatus</th>
            <th style="text-align:left; padding:12px; font-weight:900;">Actualizado</th>
            <th style="text-align:right; padding:12px; font-weight:900;">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal simple (sin librerías) -->
<div id="modal" hidden style="
  position:fixed; inset:0; background:rgba(0,0,0,.45);
  display:flex; align-items:center; justify-content:center;
  padding:16px; z-index:99999;
">
  <div style="
    width:720px; max-width:100%;
    background:var(--card); color:var(--text);
    border:1px solid var(--border);
    border-radius:18px; box-shadow:var(--shadow-strong);
    padding:16px;
  ">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
      <div style="font-weight:900; font-size:18px;" id="modalTitle">Nuevo rol</div>
      <button type="button" id="modalClose" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text);">
        ✖
      </button>
    </div>

    <form id="formRol" data-no-validate="1" style="margin-top:12px;">
      <input type="hidden" name="id_rol" id="id_rol" value="">

      <div>
        <div class="muted" style="font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em;">Nombre</div>
        <input type="text" name="nombre" id="nombre" required maxlength="100" placeholder="Ej: Administrador">
      </div>

      <div>
        <div class="muted" style="font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em;">Descripción</div>
        <input type="text" name="descripcion" id="descripcion" maxlength="255" placeholder="Opcional">
      </div>

      <div>
        <div class="muted" style="font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.06em;">Estatus</div>
        <select name="estatus" id="estatus" required>
          <option value="activo">activo</option>
          <option value="inactivo">inactivo</option>
        </select>
      </div>

      <div class="actions" style="display:flex; gap:10px; justify-content:flex-end; margin-top:10px;">
        <button type="button" id="btnCancelar" class="btn btn-secondary" style="border:1px solid var(--border); background:var(--card); color:var(--text);">
          Cancelar
        </button>
        <button type="submit" id="btnGuardar" class="btn btn-primary" style="background:var(--core-primary);color:#fff;">
          Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const API = "roles_api.php";

  const tbody = document.getElementById("tbody");
  const q     = document.getElementById("q");

  const modal = document.getElementById("modal");
  const modalTitle = document.getElementById("modalTitle");
  const modalClose = document.getElementById("modalClose");
  const btnCancelar = document.getElementById("btnCancelar");

  const btnNuevo = document.getElementById("btnNuevo");
  const btnRefrescar = document.getElementById("btnRefrescar");

  const form = document.getElementById("formRol");
  const id_rol = document.getElementById("id_rol");
  const nombre = document.getElementById("nombre");
  const descripcion = document.getElementById("descripcion");
  const estatus = document.getElementById("estatus");

  let CACHE = [];

  function esc(s){ return String(s ?? "").replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  function openModal(mode, row=null){
    if(mode === "new"){
      modalTitle.textContent = "Nuevo rol";
      id_rol.value = "";
      nombre.value = "";
      descripcion.value = "";
      estatus.value = "activo";
    } else {
      modalTitle.textContent = "Editar rol";
      id_rol.value = row.id_rol;
      nombre.value = row.nombre ?? "";
      descripcion.value = row.descripcion ?? "";
      estatus.value = row.estatus ?? "activo";
    }
    modal.hidden = false;
    setTimeout(() => nombre.focus(), 50);
  }
  function closeModal(){ modal.hidden = true; }

  modalClose.addEventListener("click", closeModal);
  btnCancelar.addEventListener("click", closeModal);
  modal.addEventListener("click", (e)=>{ if(e.target === modal) closeModal(); });

  btnNuevo.addEventListener("click", ()=> openModal("new"));
  btnRefrescar.addEventListener("click", load);

  q.addEventListener("input", render);

  async function load(){
    const r = await fetch(API + "?action=list");
    const data = await r.json();
    if(!data.ok){
      Swal.fire({icon:"error", title:"Error", text:data.msg || "No se pudo cargar."});
      return;
    }
    CACHE = data.rows || [];
    render();
  }

  function render(){
    const term = (q.value || "").trim().toLowerCase();
    const rows = !term ? CACHE : CACHE.filter(x =>
      String(x.nombre||"").toLowerCase().includes(term) ||
      String(x.descripcion||"").toLowerCase().includes(term) ||
      String(x.estatus||"").toLowerCase().includes(term) ||
      String(x.id_rol||"").toLowerCase().includes(term)
    );

    tbody.innerHTML = rows.map(r => `
      <tr style="border-top:1px solid var(--border);">
        <td style="padding:12px; font-weight:900;">${esc(r.id_rol)}</td>
        <td style="padding:12px; font-weight:900;">${esc(r.nombre)}</td>
        <td style="padding:12px; color:var(--muted); font-weight:800;">${esc(r.descripcion||"")}</td>
        <td style="padding:12px;">
          <span style="
            display:inline-flex; align-items:center; gap:8px;
            padding:6px 10px; border-radius:999px;
            border:1px solid var(--border);
            font-weight:900;
            background:${r.estatus==='activo' ? 'rgba(34,197,94,.10)' : 'rgba(239,68,68,.08)'};
            color:${r.estatus==='activo' ? '#16a34a' : '#ef4444'};
          ">
            ${esc(r.estatus)}
          </span>
        </td>
        <td style="padding:12px; color:var(--muted); font-weight:800;">${esc(r.actualizado_en || "")}</td>
        <td style="padding:12px; text-align:right; white-space:nowrap;">
          <div style="display: inline-flex; gap: 6px; flex-wrap: nowrap; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" data-edit="${esc(r.id_rol)}"
              title="Editar rol"
              style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">
              ✏️ Editar
            </button>
            <button type="button" class="btn btn-secondary" data-permisos="${esc(r.id_rol)}"
              title="Gestionar permisos de vistas"
              style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">
              🔐 Vistas
            </button>
            <button type="button" class="btn btn-secondary" data-areas="${esc(r.id_rol)}"
              title="Gestionar permisos de áreas"
              style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">
              🗺️ Áreas
            </button>
            <button type="button" class="btn btn-secondary" data-toggle="${esc(r.id_rol)}"
              title="Cambiar estatus"
              style="border:1px solid var(--border); background:var(--card); color:var(--text); padding:8px 10px;">
              🔁
            </button>
            <button type="button" class="btn btn-secondary" data-delete="${esc(r.id_rol)}"
              title="Eliminar rol"
              style="border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.08); color:#ef4444; padding:8px 10px;">
              🗑️
            </button>
          </div>
        </td>
      </tr>
    `).join("");

    tbody.querySelectorAll("[data-edit]").forEach(btn=>{
      btn.addEventListener("click", ()=>{
        const id = btn.getAttribute("data-edit");
        const row = CACHE.find(x => String(x.id_rol) === String(id));
        if(row) openModal("edit", row);
      });
    });

    tbody.querySelectorAll("[data-permisos]").forEach(btn=>{
      btn.addEventListener("click", ()=>{
        const id = btn.getAttribute("data-permisos");
        const row = CACHE.find(x => String(x.id_rol) === String(id));
        if(row) window.location.href = `permisos_por_rol.php?id_rol=${id}`;
      });
    });

    tbody.querySelectorAll("[data-areas]").forEach(btn=>{
      btn.addEventListener("click", ()=>{
        const id = btn.getAttribute("data-areas");
        const row = CACHE.find(x => String(x.id_rol) === String(id));
        if(row) window.location.href = `areas_por_rol.php?id_rol=${id}`;
      });
    });

    tbody.querySelectorAll("[data-toggle]").forEach(btn=>{
      btn.addEventListener("click", async ()=>{
        const id = btn.getAttribute("data-toggle");
        const row = CACHE.find(x => String(x.id_rol) === String(id));
        if(!row) return;

        const next = row.estatus === "activo" ? "inactivo" : "activo";
        const ok = await CGL.confirm({
          title: "Cambiar estatus",
          text: `¿Cambiar "${row.nombre}" a "${next}"?`,
          icon: "question",
          confirmButtonText: "Sí, cambiar",
          cancelButtonText: "Cancelar"
        });
        if(!ok) return;

        const res = await fetch(API, {
          method:"POST",
          headers:{ "Content-Type":"application/x-www-form-urlencoded" },
          body:new URLSearchParams({ action:"toggle", id_rol:id })
        }).then(r=>r.json());

        if(!res.ok){
          Swal.fire({icon:"error", title:"Error", text:res.msg || "No se pudo cambiar."});
          return;
        }
        CGL.toast("success","Estatus actualizado");
        await load();
      });
    });

    tbody.querySelectorAll("[data-delete]").forEach(btn=>{
      btn.addEventListener("click", async ()=>{
        const id = btn.getAttribute("data-delete");
        const row = CACHE.find(x => String(x.id_rol) === String(id));
        if(!row) return;

        const ok = await CGL.confirm({
          title: "Eliminar rol",
          text: `¿Eliminar "${row.nombre}"? Esto puede fallar si está asignado a usuarios.`,
          icon: "warning",
          confirmButtonText: "Sí, eliminar",
          cancelButtonText: "Cancelar"
        });
        if(!ok) return;

        const res = await fetch(API, {
          method:"POST",
          headers:{ "Content-Type":"application/x-www-form-urlencoded" },
          body:new URLSearchParams({ action:"delete", id_rol:id })
        }).then(r=>r.json());

        if(!res.ok){
          Swal.fire({icon:"error", title:"No se pudo eliminar", text:res.msg || "Rol en uso o error."});
          return;
        }
        CGL.toast("success","Rol eliminado");
        await load();
      });
    });
  }

  // Guardar (crear/editar) con validación + SweetAlert
  form.addEventListener("submit", async (e)=>{
    e.preventDefault();

    const n = (nombre.value || "").trim();
    const d = (descripcion.value || "").trim();
    const st = estatus.value;

    // Validación mínima (además de tu validación global)
    if(!n){
      nombre.classList.add("is-invalid");
      Swal.fire({icon:"warning", title:"Faltan datos", text:"El nombre es obligatorio."});
      return;
    }

    const payload = {
      action: id_rol.value ? "update" : "create",
      id_rol: id_rol.value,
      nombre: n,
      descripcion: d,
      estatus: st
    };

    const res = await fetch(API, {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body:new URLSearchParams(payload)
    }).then(r=>r.json());

    if(!res.ok){
      Swal.fire({icon:"error", title:"Error", text:res.msg || "No se pudo guardar."});
      return;
    }

    closeModal();
    CGL.toast("success", id_rol.value ? "Rol actualizado" : "Rol creado");
    await load();
  });

  // init
  load();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
