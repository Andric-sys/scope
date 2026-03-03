<?php
// /dashboard.php (VISTA)
// Panel principal + navegación a módulos

// ✅ DEBUG TEMPORAL (quítalo cuando todo esté OK)
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/auth.php';

$page_title = "Dashboard";

// Obtener estadísticas
$stats = [
  'usuarios_activos' => 0,
  'empleados_activos' => 0,
  'sesiones_activas' => 0,
  'areas_activas' => 0,
  'cargos_activos' => 0,
  'roles_totales' => 0
];

// Usuarios activos
$usuariosQ = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE estatus='activo'");
$stats['usuarios_activos'] = $usuariosQ ? $usuariosQ->fetch_assoc()['total'] : 0;

// Empleados activos
$empleadosQ = $conn->query("SELECT COUNT(*) as total FROM empleados WHERE estatus='activo'");
$stats['empleados_activos'] = $empleadosQ ? $empleadosQ->fetch_assoc()['total'] : 0;

// Sesiones activas (no revocadas)
$sesionesQ = $conn->query("SELECT COUNT(*) as total FROM sesiones_usuarios WHERE revocado_en IS NULL");
$stats['sesiones_activas'] = $sesionesQ ? $sesionesQ->fetch_assoc()['total'] : 0;

// Áreas activas
$areasQ = $conn->query("SELECT COUNT(*) as total FROM areas WHERE estatus='activa'");
$stats['areas_activas'] = $areasQ ? $areasQ->fetch_assoc()['total'] : 0;

// Cargos activos
$cargosQ = $conn->query("SELECT COUNT(*) as total FROM cargos WHERE estatus='activo'");
$stats['cargos_activos'] = $cargosQ ? $cargosQ->fetch_assoc()['total'] : 0;

// Roles totales
$rolesQ = $conn->query("SELECT COUNT(*) as total FROM roles");
$stats['roles_totales'] = $rolesQ ? $rolesQ->fetch_assoc()['total'] : 0;

include __DIR__ . '/partials/header.php';
?>

<div class="wrap">

  <!-- SECCIÓN DE BIENVENIDA -->
  <div class="dashboard-welcome">
    <div class="welcome-content">
      <h1 class="welcome-title">Bienvenido al sistema de gestión CORE</h1>
      <p class="welcome-subtitle">Soluciones de traspasos internacionales y administración de personal</p>
      <div class="welcome-user">
        <span class="user-email"><?= h($_SESSION['user']['correo'] ?? '') ?></span>
        <span class="user-status">Rol: <?= h($_SESSION['user']['id_rol'] ?? '') ?> • <?= h($_SESSION['user']['estatus'] ?? '') ?></span>
      </div>
    </div>

    <!-- ESTADÍSTICAS DENTRO DEL WELCOME -->
    <div class="welcome-stats">
      <div class="welcome-stat">
        <div class="welcome-stat-icon">👥</div>
        <div class="welcome-stat-value"><?= $stats['usuarios_activos'] ?></div>
        <div class="welcome-stat-label">Usuarios</div>
      </div>

      <div class="welcome-stat">
        <div class="welcome-stat-icon">💼</div>
        <div class="welcome-stat-value"><?= $stats['empleados_activos'] ?></div>
        <div class="welcome-stat-label">Empleados</div>
      </div>

      <div class="welcome-stat">
        <div class="welcome-stat-icon">⚡</div>
        <div class="welcome-stat-value"><?= $stats['sesiones_activas'] ?></div>
        <div class="welcome-stat-label">Sesiones</div>
      </div>

      <div class="welcome-stat">
        <div class="welcome-stat-icon">🏢</div>
        <div class="welcome-stat-value"><?= $stats['areas_activas'] ?></div>
        <div class="welcome-stat-label">Áreas</div>
      </div>

      <div class="welcome-stat">
        <div class="welcome-stat-icon">👔</div>
        <div class="welcome-stat-value"><?= $stats['cargos_activos'] ?></div>
        <div class="welcome-stat-label">Cargos</div>
      </div>

      <div class="welcome-stat">
        <div class="welcome-stat-icon">🛡️</div>
        <div class="welcome-stat-value"><?= $stats['roles_totales'] ?></div>
        <div class="welcome-stat-label">Roles</div>
      </div>
    </div>
  </div>

  <!-- GRID DE MÓDULOS -->
  <div class="dashboard-grid">

    <!-- SECCIÓN: Estructura Organizacional -->
    <div class="grid-section">
      <div class="section-title">Estructura Organizacional</div>
      <div class="module-grid">
        <div class="module-card widget-org" onclick="location.href='areas.php'" role="button" tabindex="0">
          <div class="module-icon">🏢</div>
          <div class="module-name">Áreas</div>
        </div>

        <div class="module-card widget-org" onclick="location.href='cargos.php'" role="button" tabindex="0">
          <div class="module-icon">👔</div>
          <div class="module-name">Cargos</div>
        </div>

        <div class="module-card widget-org" onclick="location.href='empleados.php'" role="button" tabindex="0">
          <div class="module-icon">👥</div>
          <div class="module-name">Empleados</div>
        </div>

        <div class="module-card widget-org" onclick="location.href='vistas.php'" role="button" tabindex="0">
          <div class="module-icon">🌍</div>
          <div class="module-name">Módulos</div>
        </div>
      </div>
    </div>

    <!-- SECCIÓN: Admin y Control -->
    <div class="grid-section">
      <div class="section-title">Administración y Control</div>
      <div class="module-grid">
        <div class="module-card widget-admin" onclick="location.href='roles.php'" role="button" tabindex="0">
          <div class="module-icon">🛡️</div>
          <div class="module-name">Roles</div>
        </div>

        <div class="module-card widget-admin" onclick="location.href='usuarios.php'" role="button" tabindex="0">
          <div class="module-icon">🔐</div>
          <div class="module-name">Usuarios</div>
        </div>

        <div class="module-card widget-admin" onclick="location.href='permisos_por_rol.php'" role="button" tabindex="0">
          <div class="module-icon">✓</div>
          <div class="module-name">Permisos</div>
        </div>

        <div class="module-card widget-admin" onclick="location.href='sesiones.php'" role="button" tabindex="0">
          <div class="module-icon">⚡</div>
          <div class="module-name">Sesiones</div>
        </div>
      </div>
    </div>

    <!-- SECCIÓN: Sistemas Integrados -->
    <div class="grid-section">
      <div class="section-title">Sistemas Integrados</div>
      <div class="module-grid">
        <div class="module-card widget-core-scope" onclick="location.href='../core_scope/menu_principal.php'" role="button" tabindex="0">
          <div class="core-scope-logo">
            <div class="core-scope-mark"></div>
          </div>
          <div class="module-name">CORE SCOPE</div>
          <div class="module-desc">Sistema de Gestión Empresarial</div>
          <div class="module-arrow">→</div>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
/* Estilos para el botón de Core Scope */
.widget-core-scope {
  background: linear-gradient(135deg, #0171e2, #000F9F);
  color: white;
  position: relative;
  overflow: hidden;
  min-height: 140px;
  grid-column: span 2;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 8px;
  box-shadow: 0 8px 24px rgba(1, 113, 226, 0.25);
  transition: all 0.3s ease;
}

.widget-core-scope:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(1, 113, 226, 0.35);
}

.core-scope-logo {
  position: relative;
  width: 52px;
  height: 52px;
  margin-bottom: 4px;
}

.core-scope-mark {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.25);
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.core-scope-mark:after {
  content: "";
  position: absolute;
  inset: auto -22px -22px auto;
  width: 46px;
  height: 46px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.4);
  transform: rotate(18deg);
}

.widget-core-scope .module-name {
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: 1px;
  margin: 0;
}

.widget-core-scope .module-desc {
  font-size: 0.9rem;
  opacity: 0.9;
  font-weight: 500;
}

.widget-core-scope .module-arrow {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 8px;
  opacity: 0.8;
  transition: transform 0.3s ease;
}

.widget-core-scope:hover .module-arrow {
  transform: translateX(4px);
}

@media (max-width: 768px) {
  .widget-core-scope {
    grid-column: span 1;
  }
}
</style>

<script>
// Accesibilidad: Enter/Space activa las cards
document.querySelectorAll(".widget[role='button']").forEach(w=>{
  w.addEventListener("keydown", (e)=>{
    if(e.key === "Enter" || e.key === " "){
      e.preventDefault();
      w.click();
    }
  });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
