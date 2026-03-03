/* =========================================================
   /assets/js/app.js (SOPORTE)
   CORE Global Logistics - UX + SweetAlert + Validaciones globales + Modal Global
========================================================= */

document.addEventListener("DOMContentLoaded", () => {

  // Helpers globales
  window.CGL = window.CGL || {};

  CGL.toast = (icon, title, timer = 2200) => Swal.fire({
    toast: true,
    position: "top-end",
    icon,
    title,
    showConfirmButton: false,
    timer,
    timerProgressBar: true
  });

  CGL.confirm = async ({
    title="¿Confirmar?",
    text="Esta acción no se puede deshacer.",
    icon="warning",
    confirmButtonText="Sí, continuar",
    cancelButtonText="Cancelar"
  } = {}) => {
    const r = await Swal.fire({
      title, text, icon,
      showCancelButton: true,
      confirmButtonText,
      cancelButtonText,
      reverseButtons: true,
      focusCancel: true
    });
    return r.isConfirmed;
  };

  // =========================
  // MODAL GLOBAL (uno solo para todo)
  // =========================
  const modal       = document.getElementById("cglModal");
  const modalTitle  = document.getElementById("cglModalTitle");
  const modalBody   = document.getElementById("cglModalBody");
  const modalFooter = document.getElementById("cglModalFooter");
  const modalClose  = document.getElementById("cglModalClose");

  CGL.modal = {
    open({ title = "", body = "", footer = "" } = {}) {
      if (!modal) return;
      modalTitle.innerHTML  = title;
      modalBody.innerHTML   = body;
      modalFooter.innerHTML = footer;
      modal.hidden = false;
    },
    close() {
      if (!modal) return;
      modal.hidden = true;
      modalBody.innerHTML   = "";
      modalFooter.innerHTML = "";
    },
    setTitle(t=""){ if(modalTitle) modalTitle.innerHTML = t; },
    setBody(b=""){ if(modalBody) modalBody.innerHTML = b; },
    setFooter(f=""){ if(modalFooter) modalFooter.innerHTML = f; }
  };

  if (modalClose) modalClose.addEventListener("click", () => CGL.modal.close());
  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) CGL.modal.close();
    });
    document.addEventListener("keydown", (e) => {
      if (!modal.hidden && e.key === "Escape") CGL.modal.close();
    });
  }

  // =========================
  // Theme
  // =========================
  const toggle = document.getElementById("themeToggle");
  const applyIcon = () => {
    const theme = document.documentElement.getAttribute("data-theme") || "light";
    if (toggle) toggle.textContent = theme === "dark" ? "🌙" : "☀";
  };

  const saved = localStorage.getItem("theme");
  if (saved) document.documentElement.setAttribute("data-theme", saved);
  applyIcon();

  if (toggle) {
    toggle.addEventListener("click", () => {
      const html = document.documentElement;
      const cur  = html.getAttribute("data-theme") || "light";
      const next = cur === "dark" ? "light" : "dark";
      html.setAttribute("data-theme", next);
      localStorage.setItem("theme", next);
      applyIcon();
      CGL.toast("success", `Tema: ${next === "dark" ? "Oscuro" : "Claro"}`);
    });
  }

  // =========================
  // Perfil dropdown
  // =========================
  const profileBtn  = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");
  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileMenu.hidden = !profileMenu.hidden;
    });
    document.addEventListener("click", () => (profileMenu.hidden = true));
  }

  // =========================
  // Logout con confirmación SweetAlert (global)
  // =========================
  const logoutForm = document.getElementById("logoutForm");
  if (logoutForm) {
    logoutForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const ok = await CGL.confirm({
        title: "Cerrar sesión",
        text: "Se cerrará tu sesión actual.",
        icon: "question",
        confirmButtonText: "Sí, cerrar",
        cancelButtonText: "Cancelar"
      });
      if (ok) logoutForm.submit();
    });
  }

  // =========================
  // Buscador global: SOLO afecta cards del dashboard
  // =========================
  const globalSearch = document.getElementById("globalSearch");
  if (globalSearch) {
    globalSearch.addEventListener("input", () => {
      const q = globalSearch.value.toLowerCase().trim();
      const cards = document.querySelectorAll(".dashboard-grid .widget");
      if (!cards.length) return;
      cards.forEach((w) => {
        const t = w.textContent.toLowerCase();
        w.style.display = t.includes(q) ? "" : "none";
      });
    });
  }

  // =========================
  // Validaciones GLOBAL (SweetAlert)
  // - Aplica a todos los forms
  // - Para excluir un form: data-no-validate="1"
  // =========================
  const markInvalid = (el, yes=true) => {
    if(!el) return;
    el.classList.toggle("is-invalid", !!yes);
  };

  const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v||"").trim());

  const fileCheck = (input, {maxMB=4, exts=["jpg","jpeg","png","webp","pdf"]}={}) => {
    const f = input?.files?.[0];
    if(!f) return {ok:true};

    const name = (f.name||"").toLowerCase();
    const ext  = name.includes(".") ? name.split(".").pop() : "";
    if(!exts.includes(ext)) return {ok:false, msg:`Formato inválido. Usa: ${exts.join(", ").toUpperCase()}`};

    const max = maxMB * 1024 * 1024;
    if(f.size > max) return {ok:false, msg:`Archivo grande. Máximo ${maxMB}MB.`};

    return {ok:true};
  };

  // Limpia invalid al teclear/cambiar
  document.querySelectorAll("input,select,textarea").forEach(el=>{
    el.addEventListener("input", ()=> markInvalid(el,false));
    el.addEventListener("change", ()=> markInvalid(el,false));
  });

  document.querySelectorAll("form").forEach((form) => {
    if (form.dataset.noValidate === "1") return;

    form.addEventListener("submit", (e) => {

      const required = [...form.querySelectorAll("[required]")];
      let bad = null;

      for (const el of required) {
        const v = String(el.value || "").trim();

        if (!v) { bad = el; break; }

        if ((el.type === "email" || el.name?.toLowerCase().includes("correo")) && !isEmail(v)) {
          bad = el; break;
        }
      }

      if (bad) {
        e.preventDefault();
        markInvalid(bad,true);
        bad.focus();
        Swal.fire({
          icon:"warning",
          title:"Revisa los datos",
          text: (bad.type === "email") ? "Correo inválido." : "Completa los campos obligatorios."
        });
        return;
      }

      const fileInputs = [...form.querySelectorAll("input[type='file']")];
      for (const fi of fileInputs) {
        const chk = fileCheck(fi, {maxMB:4, exts:["jpg","jpeg","png","webp","pdf"]});
        if(!chk.ok){
          e.preventDefault();
          markInvalid(fi,true);
          Swal.fire({ icon:"error", title:"Archivo inválido", text: chk.msg });
          return;
        }
      }
    });
  });

});
