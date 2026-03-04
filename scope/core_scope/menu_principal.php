<?php
declare(strict_types=1);

// Proteger esta página con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';

$cssVars = core_brand_css_vars();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Core Scope · Menú Principal</title>
  
  <style>
    <?= $cssVars ?>
    
    * { box-sizing: border-box; }
    
    body {
      margin: 0;
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    
    .wrap {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }
    
    /* Header */
    .header {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 10px 26px rgba(2, 6, 23, 0.06);
      padding: 20px 24px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }
    
    .brand {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .logo {
      width: 52px;
      height: 52px;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--core-blue), var(--core-navy));
      box-shadow: 0 14px 30px rgba(0, 15, 159, 0.18);
      position: relative;
      overflow: hidden;
    }
    
    .logo:after {
      content: "";
      position: absolute;
      inset: auto -22px -22px auto;
      width: 46px;
      height: 46px;
      border-radius: 18px;
      background: rgba(156, 193, 247, 0.55);
      transform: rotate(18deg);
    }
    
    .brand-text h1 {
      margin: 0;
      font-size: 1.5rem;
      letter-spacing: 0.3px;
      color: var(--core-navy);
    }
    
    .brand-text p {
      margin: 0;
      color: var(--muted);
      font-weight: 600;
      font-size: 0.9rem;
    }
    
    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 16px;
      background: rgba(1, 113, 226, 0.08);
      border-radius: 999px;
      border: 1px solid rgba(1, 113, 226, 0.2);
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--core-blue);
    }
    
    .user-details {
      display: flex;
      flex-direction: column;
    }
    
    .user-name {
      font-weight: 700;
      font-size: 0.95rem;
      color: var(--core-navy);
    }
    
    .user-role {
      font-size: 0.8rem;
      color: var(--muted);
      font-weight: 600;
    }
    
    /* Welcome Section */
    .welcome {
      background: linear-gradient(135deg, var(--core-blue), var(--core-navy));
      color: white;
      padding: 40px 30px;
      border-radius: 18px;
      margin-bottom: 30px;
      box-shadow: 0 14px 30px rgba(0, 15, 159, 0.2);
    }
    
    .welcome h2 {
      margin: 0 0 10px 0;
      font-size: 2rem;
    }
    
    .welcome p {
      margin: 0;
      font-size: 1.1rem;
      opacity: 0.9;
    }
    
    /* Menu Grid */
    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .menu-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 12px rgba(2, 6, 23, 0.04);
      transition: all 0.3s ease;
      text-decoration: none;
      color: inherit;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .menu-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(2, 6, 23, 0.12);
      border-color: var(--core-blue);
    }
    
    .menu-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--core-blue), var(--core-navy));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
      box-shadow: 0 8px 16px rgba(1, 113, 226, 0.2);
    }
    
    .menu-title {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--core-navy);
      margin: 0;
    }
    
    .menu-desc {
      color: var(--muted);
      font-size: 0.9rem;
      margin: 0;
      line-height: 1.5;
    }
    
    .menu-arrow {
      margin-top: auto;
      align-self: flex-end;
      color: var(--core-blue);
      font-weight: 700;
    }
    
    /* Footer Actions */
    .footer-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn {
      padding: 12px 24px;
      border-radius: 999px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      border: 1px solid transparent;
    }
    
    .btn-secondary {
      background: var(--card);
      color: var(--core-navy);
      border-color: var(--border);
    }
    
    .btn-secondary:hover {
      background: var(--bg);
    }
    
    .btn-danger {
      background: #ef4444;
      color: white;
    }
    
    .btn-danger:hover {
      background: #dc2626;
    }
    
    @media (max-width: 768px) {
      .menu-grid {
        grid-template-columns: 1fr;
      }
      
      .header {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <!-- Header -->
    <div class="header">
      <div class="brand">
        <div class="logo"></div>
        <div class="brand-text">
          <h1>Core Scope</h1>
          <p>Sistema de Gestión Empresarial</p>
        </div>
      </div>
      
      <div class="user-info">
        <?php if ($user_foto && file_exists('../CGL/' . $user_foto)): ?>
          <img src="../CGL/<?= htmlspecialchars($user_foto) ?>" alt="Avatar" class="user-avatar">
        <?php else: ?>
          <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect fill='%230171e2' width='100' height='100'/%3E%3Ctext x='50%25' y='50%25' font-size='50' fill='white' text-anchor='middle' dy='.35em'%3E<?= strtoupper(substr($user_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E" alt="Avatar" class="user-avatar">
        <?php endif; ?>
        <div class="user-details">
          <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
          <span class="user-role"><?= htmlspecialchars($user_rol) ?></span>
        </div>
      </div>
    </div>
    
    <!-- Welcome Section -->
    <div class="welcome">
      <h2>¡Bienvenido, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?>!</h2>
      <p>Selecciona una opción del menú para comenzar a trabajar</p>
    </div>
    
    <!-- Menu Grid -->
    <div class="menu-grid">
      <a href="dashboard_pro.php" class="menu-card">
        <div class="menu-icon">📊</div>
        <h3 class="menu-title">Dashboard Ejecutivo</h3>
        <p class="menu-desc">Vista consolidada con métricas clave y análisis de rendimiento empresarial</p>
        <span class="menu-arrow">→</span>
      </a>
      
      <a href="scope_viewer.php" class="menu-card">
        <div class="menu-icon">🔍</div>
        <h3 class="menu-title">Visor Scope</h3>
        <p class="menu-desc">Explorador avanzado de datos y registros del sistema</p>
        <span class="menu-arrow">→</span>
      </a>
      
      <a href="scope_sync_panel.php" class="menu-card">
        <div class="menu-icon">🔄</div>
        <h3 class="menu-title">Sincronización</h3>
        <p class="menu-desc">Panel de control para sincronizar datos con sistemas externos</p>
        <span class="menu-arrow">→</span>
      </a>
      
      <a href="scope_console.php" class="menu-card">
        <div class="menu-icon">⚙️</div>
        <h3 class="menu-title">Consola Scope</h3>
        <p class="menu-desc">Herramientas administrativas y configuración del sistema</p>
        <span class="menu-arrow">→</span>
      </a>
      
      <a href="graficas.php" class="menu-card">
        <div class="menu-icon">📈</div>
        <h3 class="menu-title">Gráficas</h3>
        <p class="menu-desc">Visualización de datos y reportes gráficos interactivos</p>
        <span class="menu-arrow">→</span>
      </a>
      
      <a href="scope_audit.php" class="menu-card">
        <div class="menu-icon">📋</div>
        <h3 class="menu-title">Auditoría</h3>
        <p class="menu-desc">Registro de cambios y actividad del sistema</p>
        <span class="menu-arrow">→</span>
      </a>
    </div>
    
    <!-- Footer Actions -->
    <div class="footer-actions">
      <a href="../CGL/dashboard.php" class="btn btn-secondary">
        🏠 Volver a CGL
      </a>
      <a href="../CGL/perfil.php" class="btn btn-secondary">
        👤 Mi Perfil
      </a>
      <a href="../CGL/logout.php" class="btn btn-danger">
        🚪 Cerrar Sesión
      </a>
    </div>
  </div>
</body>
</html>
