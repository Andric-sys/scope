<?php
/**
 * setup_db.php - Script de configuración de base de datos
 * Ejecuta todas las migraciones necesarias de forma idempotente
 * Puede ejecutarse múltiples veces sin causar errores
 */

declare(strict_types=1);

require __DIR__ . '/connection.php';

header('Content-Type: text/html; charset=utf-8');

// Función auxiliar para ejecutar SQL y capturar errores
function execute_sql(mysqli $conn, string $sql, string $description): array {
  $result = [
    'success' => false,
    'description' => $description,
    'error' => null,
    'message' => ''
  ];

  if ($conn->multi_query($sql)) {
    // Consumir todos los resultados de multi_query
    do {
      if ($rs = $conn->store_result()) {
        $rs->free();
      }
    } while ($conn->next_result());

    $result['success'] = true;
    $result['message'] = '✅ OK';
  } else {
    $result['error'] = $conn->error;
    $result['message'] = '❌ Error: ' . $conn->error;
  }

  return $result;
}

// Función para verificar si una tabla existe
function table_exists(mysqli $conn, string $table): bool {
  $rs = $conn->query("SHOW TABLES LIKE '{$table}'");
  return $rs && $rs->num_rows > 0;
}

// Función para verificar si un índice existe
function index_exists(mysqli $conn, string $table, string $index): bool {
  $rs = $conn->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
  return $rs && $rs->num_rows > 0;
}

// Función para verificar si un constraint existe
function constraint_exists(mysqli $conn, string $table, string $constraint): bool {
  $rs = $conn->query("
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_NAME = '{$table}' 
    AND CONSTRAINT_NAME = '{$constraint}'
  ");
  return $rs && $rs->num_rows > 0;
}

// Array de migraciones a ejecutar
$migrations = [];

// ============================================================
// MIGRACIÓN 1: Crear tabla areas_por_rol
// ============================================================
if (!table_exists($conn, 'areas_por_rol')) {
  $migrations[] = [
    'description' => 'Crear tabla areas_por_rol',
    'sql' => "CREATE TABLE IF NOT EXISTS `areas_por_rol` (
      `id_rol` int(11) NOT NULL,
      `id_area` int(11) NOT NULL,
      `permitido` tinyint(1) NOT NULL DEFAULT 1,
      `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id_rol`,`id_area`),
      KEY `fk_ar_area` (`id_area`),
      CONSTRAINT `fk_ar_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_ar_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
  ];
} else {
  // Tabla ya existe, pero verificar que los constraints existan
  if (!constraint_exists($conn, 'areas_por_rol', 'fk_ar_rol')) {
    $migrations[] = [
      'description' => 'Agregar constraint fk_ar_rol a areas_por_rol',
      'sql' => "ALTER TABLE `areas_por_rol` 
        ADD CONSTRAINT `fk_ar_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;"
    ];
  }
  if (!constraint_exists($conn, 'areas_por_rol', 'fk_ar_area')) {
    $migrations[] = [
      'description' => 'Agregar constraint fk_ar_area a areas_por_rol',
      'sql' => "ALTER TABLE `areas_por_rol` 
        ADD CONSTRAINT `fk_ar_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE;"
    ];
  }
}

// ============================================================
// MIGRACIÓN 2: Crear tabla usuarios_vistas si no existe
// ============================================================
if (!table_exists($conn, 'usuarios_vistas')) {
  $migrations[] = [
    'description' => 'Crear tabla usuarios_vistas',
    'sql' => "CREATE TABLE IF NOT EXISTS `usuarios_vistas` (
      `id_rol` int(11) NOT NULL,
      `id_vista` int(11) NOT NULL,
      `permitido` tinyint(1) NOT NULL DEFAULT 1,
      `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id_rol`,`id_vista`),
      KEY `fk_uv_vista` (`id_vista`),
      CONSTRAINT `fk_uv_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk_uv_vista` FOREIGN KEY (`id_vista`) REFERENCES `vistas` (`id_vista`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
  ];
}

// ============================================================
// MIGRACIÓN 3: Crear tabla vistas si no existe
// ============================================================
if (!table_exists($conn, 'vistas')) {
  $migrations[] = [
    'description' => 'Crear tabla vistas',
    'sql' => "CREATE TABLE IF NOT EXISTS `vistas` (
      `id_vista` int(11) NOT NULL AUTO_INCREMENT,
      `path` varchar(200) NOT NULL,
      `clave` varchar(80) NOT NULL,
      `titulo` varchar(120) NOT NULL,
      `estatus` enum('activa','inactiva') NOT NULL DEFAULT 'activa',
      `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
      `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id_vista`),
      UNIQUE KEY `uq_vistas_path` (`path`),
      UNIQUE KEY `uq_vistas_clave` (`clave`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
  ];
}

// ============================================================
// MIGRACIÓN 4: Insertar vistas de ejemplo
// ============================================================
$vistas_check = $conn->query("SELECT COUNT(*) as cnt FROM vistas");
$vistas_count = $vistas_check->fetch_assoc()['cnt'] ?? 0;

if ($vistas_count == 0) {
  $migrations[] = [
    'description' => 'Insertar vistas de ejemplo',
    'sql' => "INSERT INTO `vistas` (`path`, `clave`, `titulo`, `estatus`) VALUES
      ('dashboard.php', 'DASHBOARD', 'Dashboard', 'activa'),
      ('usuarios.php', 'USUARIOS', 'Gestión de Usuarios', 'activa'),
      ('roles.php', 'ROLES', 'Gestión de Roles', 'activa'),
      ('permisos_por_rol.php', 'PERMISOS_VISTAS', 'Permisos de Vistas', 'activa'),
      ('areas_por_rol.php', 'PERMISOS_AREAS', 'Permisos de Áreas', 'activa'),
      ('vistas.php', 'VISTAS_ADMIN', 'Administración de Vistas', 'activa'),
      ('areas.php', 'AREAS', 'Gestión de Áreas', 'activa'),
      ('cargos.php', 'CARGOS', 'Gestión de Cargos', 'activa'),
      ('empleados.php', 'EMPLEADOS', 'Gestión de Empleados', 'activa'),
      ('sesiones.php', 'SESIONES', 'Sesiones Activas', 'activa');"
  ];
}

// Ejecutar todas las migraciones
$results = [];
foreach ($migrations as $migration) {
  $results[] = execute_sql($conn, $migration['sql'], $migration['description']);
}

// HTML de retorno
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup BD - CGL</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .container {
      background: white;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 600px;
      width: 100%;
      padding: 40px;
    }
    h1 {
      color: #333;
      margin-bottom: 10px;
      text-align: center;
    }
    .subtitle {
      color: #666;
      text-align: center;
      margin-bottom: 30px;
      font-size: 14px;
    }
    .migration-item {
      border-left: 4px solid #667eea;
      padding: 15px;
      margin-bottom: 15px;
      background: #f8f9fa;
      border-radius: 6px;
      transition: all 0.3s ease;
    }
    .migration-item.success {
      border-left-color: #10b981;
      background: #f0fdf4;
    }
    .migration-item.error {
      border-left-color: #ef4444;
      background: #fef2f2;
    }
    .migration-item.skipped {
      border-left-color: #f59e0b;
      background: #fffbf0;
    }
    .migration-description {
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    .migration-message {
      font-size: 13px;
      color: #666;
      font-family: 'Courier New', monospace;
    }
    .migration-message.error {
      color: #dc2626;
    }
    .migration-message.success {
      color: #059669;
    }
    .migration-message.skipped {
      color: #d97706;
    }
    .summary {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e5e7eb;
      text-align: center;
    }
    .summary-stat {
      display: inline-block;
      margin: 0 15px;
      font-weight: 600;
      font-size: 14px;
    }
    .stat-success {
      color: #10b981;
    }
    .stat-error {
      color: #ef4444;
    }
    .stat-skipped {
      color: #f59e0b;
    }
    .btn-back {
      display: inline-block;
      margin-top: 20px;
      padding: 10px 20px;
      background: #667eea;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      text-align: center;
      transition: all 0.3s ease;
    }
    .btn-back:hover {
      background: #764ba2;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🔧 Configuración de Base de Datos</h1>
    <p class="subtitle">Migraciones de BD para Core Global Logistics</p>

    <div>
      <?php
        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        if (empty($results)) {
          echo '<div class="migration-item skipped">';
          echo '<div class="migration-description">ℹ️ Base de datos actualizada</div>';
          echo '<div class="migration-message skipped">Todas las tablas ya existen. No hay cambios que aplicar.</div>';
          echo '</div>';
          $skipped_count = 1;
        } else {
          foreach ($results as $result) {
            $class = $result['success'] ? 'success' : 'error';
            echo '<div class="migration-item ' . $class . '">';
            echo '<div class="migration-description">' . htmlspecialchars($result['description']) . '</div>';
            echo '<div class="migration-message ' . $class . '">' . htmlspecialchars($result['message']) . '</div>';
            if (!$result['success'] && $result['error']) {
              echo '<div class="migration-message error" style="margin-top: 8px; font-size: 12px;">' . htmlspecialchars($result['error']) . '</div>';
            }
            echo '</div>';

            if ($result['success']) {
              $success_count++;
            } else {
              $error_count++;
            }
          }
        }
      ?>
    </div>

    <div class="summary">
      <?php if ($success_count > 0): ?>
        <div class="summary-stat"><span class="stat-success">✅ Exitosas: <?php echo $success_count; ?></span></div>
      <?php endif; ?>
      <?php if ($error_count > 0): ?>
        <div class="summary-stat"><span class="stat-error">❌ Errores: <?php echo $error_count; ?></span></div>
      <?php endif; ?>
      <?php if ($skipped_count > 0): ?>
        <div class="summary-stat"><span class="stat-skipped">⏭️ Omitidas: <?php echo $skipped_count; ?></span></div>
      <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 30px;">
      <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>
