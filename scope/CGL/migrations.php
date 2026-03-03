<?php
/**
 * Sistema de Migraciones de Base de Datos
 * Gestiona el versionado de cambios en la BD
 * Solo ejecuta cambios que no hayan sido registrados
 */

declare(strict_types=1);

require_once __DIR__ . '/connection.php';

class DatabaseMigrations
{
    private mysqli $conn;
    private string $migrationsTable = 'migraciones';
    private array $migrations = [];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->initializeMigrationsTable();
        $this->defineMigrations();
    }

    /**
     * Inicializa la tabla de migraciones si no existe
     */
    private function initializeMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(255) NOT NULL UNIQUE,
            `lote` INT NOT NULL,
            `ejecutado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$this->conn->query($sql)) {
            throw new Exception("Error al crear tabla de migraciones: " . $this->conn->error);
        }
    }

    /**
     * Define todas las migraciones disponibles
     * Agregar nuevas migraciones aquí
     */
    private function defineMigrations(): void
    {
        // MIGRACIONES INICIALES - 2026-02-23
        $this->migrations = [
            // ============ TABLAS BASE ============
            '2026_02_23_001_create_areas_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `areas` (
                    `id_area` INT NOT NULL AUTO_INCREMENT,
                    `nombre` VARCHAR(150) NOT NULL,
                    `descripcion` VARCHAR(255) DEFAULT NULL,
                    `estatus` ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_area`),
                    UNIQUE KEY `uq_areas_nombre` (`nombre`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_002_create_cargos_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `cargos` (
                    `id_cargo` INT NOT NULL AUTO_INCREMENT,
                    `nombre` VARCHAR(150) NOT NULL,
                    `descripcion` VARCHAR(255) DEFAULT NULL,
                    `estatus` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_cargo`),
                    UNIQUE KEY `uq_cargos_nombre` (`nombre`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_003_create_roles_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `roles` (
                    `id_rol` INT NOT NULL AUTO_INCREMENT,
                    `nombre` VARCHAR(100) NOT NULL,
                    `descripcion` VARCHAR(255) DEFAULT NULL,
                    `estatus` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_rol`),
                    UNIQUE KEY `uq_roles_nombre` (`nombre`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_004_create_empleados_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `empleados` (
                    `id_empleado` INT NOT NULL AUTO_INCREMENT,
                    `no_empleado` VARCHAR(50) NOT NULL,
                    `nombre` VARCHAR(100) NOT NULL,
                    `apellido` VARCHAR(100) NOT NULL,
                    `id_area` INT DEFAULT NULL,
                    `id_cargo` INT DEFAULT NULL,
                    `telefono` VARCHAR(20) DEFAULT NULL,
                    `correo` VARCHAR(150) DEFAULT NULL,
                    `direccion` TEXT DEFAULT NULL,
                    `estatus` ENUM('activo','baja') NOT NULL DEFAULT 'activo',
                    `fecha_nacimiento` DATE DEFAULT NULL,
                    `fecha_ingreso` DATE DEFAULT NULL,
                    `fecha_salida` DATE DEFAULT NULL,
                    `foto_perfil` VARCHAR(255) DEFAULT NULL,
                    `oficina` VARCHAR(100) DEFAULT NULL,
                    `periodicidad` ENUM('semanal','quincenal','mensual') NOT NULL DEFAULT 'mensual',
                    `edad` INT DEFAULT NULL,
                    `sexo` ENUM('masculino','femenino','otro') DEFAULT NULL,
                    `nacionalidad` VARCHAR(50) DEFAULT NULL,
                    `estado_civil` ENUM('soltero','casado','divorciado','viudo') DEFAULT NULL,
                    `curp` CHAR(18) DEFAULT NULL,
                    `rfc` CHAR(13) DEFAULT NULL,
                    `nss` CHAR(11) DEFAULT NULL,
                    `colonia` VARCHAR(100) DEFAULT NULL,
                    `codigo_postal` CHAR(5) DEFAULT NULL,
                    `ciudad` VARCHAR(100) DEFAULT NULL,
                    `estado` VARCHAR(100) DEFAULT NULL,
                    `tipo_licencia` VARCHAR(50) DEFAULT NULL,
                    `ultimo_grado_estudios` VARCHAR(255) DEFAULT NULL,
                    `fuente_reclutamiento` VARCHAR(255) DEFAULT NULL,
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_empleado`),
                    UNIQUE KEY `uq_empleados_no_empleado` (`no_empleado`),
                    UNIQUE KEY `uq_empleados_curp` (`curp`),
                    UNIQUE KEY `uq_empleados_rfc` (`rfc`),
                    UNIQUE KEY `uq_empleados_nss` (`nss`),
                    KEY `idx_empleados_area` (`id_area`),
                    KEY `idx_empleados_cargo` (`id_cargo`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_005_create_usuarios_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `usuarios` (
                    `id_usuario` INT NOT NULL AUTO_INCREMENT,
                    `nombre` VARCHAR(150) NOT NULL,
                    `apellido` VARCHAR(150) NOT NULL,
                    `correo` VARCHAR(150) NOT NULL,
                    `num_telefono` VARCHAR(45) DEFAULT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `id_rol` INT NOT NULL,
                    `id_empleado` INT DEFAULT NULL,
                    `id_area` INT DEFAULT NULL,
                    `foto_perfil` VARCHAR(255) DEFAULT NULL,
                    `estatus` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_usuario`),
                    UNIQUE KEY `uq_usuarios_correo` (`correo`),
                    KEY `idx_usuarios_rol` (`id_rol`),
                    KEY `idx_usuarios_empleado` (`id_empleado`),
                    KEY `idx_usuarios_area` (`id_area`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_006_create_vistas_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `vistas` (
                    `id_vista` INT NOT NULL AUTO_INCREMENT,
                    `path` VARCHAR(200) NOT NULL,
                    `clave` VARCHAR(80) NOT NULL,
                    `titulo` VARCHAR(120) NOT NULL,
                    `estatus` ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_vista`),
                    UNIQUE KEY `uq_vistas_path` (`path`),
                    UNIQUE KEY `uq_vistas_clave` (`clave`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_007_create_areas_por_rol_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `areas_por_rol` (
                    `id_rol` INT NOT NULL,
                    `id_area` INT NOT NULL,
                    `permitido` TINYINT(1) NOT NULL DEFAULT 1,
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_rol`, `id_area`),
                    KEY `fk_ar_area` (`id_area`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_008_create_usuarios_vistas_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `usuarios_vistas` (
                    `id_rol` INT NOT NULL,
                    `id_vista` INT NOT NULL,
                    `permitido` TINYINT(1) NOT NULL DEFAULT 1,
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_rol`, `id_vista`),
                    KEY `fk_uv_vista` (`id_vista`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            '2026_02_23_009_create_sesiones_usuarios_table' => function() {
                return "CREATE TABLE IF NOT EXISTS `sesiones_usuarios` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `id_usuario` INT NOT NULL,
                    `id_sesion` VARCHAR(128) NOT NULL,
                    `huella_dispositivo` CHAR(64) NOT NULL,
                    `agente_usuario` VARCHAR(255) DEFAULT NULL,
                    `ip` VARCHAR(45) DEFAULT NULL,
                    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `visto_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `revocado_en` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_sesiones_id_sesion` (`id_sesion`),
                    KEY `idx_sesiones_usuario` (`id_usuario`),
                    KEY `idx_sesiones_visto` (`visto_en`),
                    KEY `idx_sesiones_revocado` (`revocado_en`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            },

            // ============ CLAVES FORÁNEAS ============
            '2026_02_23_010_add_foreign_keys_to_empleados' => function() {
                return "ALTER TABLE `empleados` DROP FOREIGN KEY IF EXISTS `fk_empleados_area`;
                ALTER TABLE `empleados` ADD CONSTRAINT `fk_empleados_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE SET NULL ON UPDATE CASCADE";
            },

            '2026_02_23_011_add_fk_empleados_cargo' => function() {
                return "ALTER TABLE `empleados` DROP FOREIGN KEY IF EXISTS `fk_empleados_cargo`;
                ALTER TABLE `empleados` ADD CONSTRAINT `fk_empleados_cargo` FOREIGN KEY (`id_cargo`) REFERENCES `cargos` (`id_cargo`) ON DELETE SET NULL ON UPDATE CASCADE";
            },

            '2026_02_23_012_add_foreign_keys_to_areas_por_rol' => function() {
                return "ALTER TABLE `areas_por_rol` DROP FOREIGN KEY IF EXISTS `fk_ar_area`;
                ALTER TABLE `areas_por_rol` ADD CONSTRAINT `fk_ar_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE";
            },

            '2026_02_23_013_add_fk_areas_por_rol_rol' => function() {
                return "ALTER TABLE `areas_por_rol` DROP FOREIGN KEY IF EXISTS `fk_ar_rol`;
                ALTER TABLE `areas_por_rol` ADD CONSTRAINT `fk_ar_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE";
            },

            '2026_02_23_014_add_fk_usuarios_rol' => function() {
                return "ALTER TABLE `usuarios` DROP FOREIGN KEY IF EXISTS `fk_usuarios_rol`;
                ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE";
            },

            '2026_02_23_015_add_fk_usuarios_empleado' => function() {
                return "ALTER TABLE `usuarios` DROP FOREIGN KEY IF EXISTS `fk_usuarios_empleado`;
                ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE SET NULL ON UPDATE CASCADE";
            },

            '2026_02_23_016_add_fk_usuarios_area' => function() {
                return "ALTER TABLE `usuarios` DROP FOREIGN KEY IF EXISTS `fk_usuarios_area`;
                ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE SET NULL ON UPDATE CASCADE";
            },

            '2026_02_23_017_add_fk_usuarios_vistas_rol' => function() {
                return "ALTER TABLE `usuarios_vistas` DROP FOREIGN KEY IF EXISTS `fk_uv_rol`;
                ALTER TABLE `usuarios_vistas` ADD CONSTRAINT `fk_uv_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE";
            },

            '2026_02_23_018_add_fk_usuarios_vistas_vista' => function() {
                return "ALTER TABLE `usuarios_vistas` DROP FOREIGN KEY IF EXISTS `fk_uv_vista`;
                ALTER TABLE `usuarios_vistas` ADD CONSTRAINT `fk_uv_vista` FOREIGN KEY (`id_vista`) REFERENCES `vistas` (`id_vista`) ON DELETE CASCADE ON UPDATE CASCADE";
            },

            '2026_02_23_019_add_fk_sesiones_usuario' => function() {
                return "ALTER TABLE `sesiones_usuarios` DROP FOREIGN KEY IF EXISTS `fk_sesiones_usuario`;
                ALTER TABLE `sesiones_usuarios` ADD CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE";
            },

            // ============ DATOS INICIALES ============
            '2026_02_23_020_insert_initial_roles' => function() {
                return "INSERT IGNORE INTO `roles` (`id_rol`, `nombre`, `descripcion`, `estatus`) VALUES
                    (1, 'Administrador', 'Acceso total al sistema', 'activo'),
                    (2, 'RH', 'Recursos Humanos', 'activo')";
            },

            '2026_02_23_021_insert_initial_areas' => function() {
                return "INSERT IGNORE INTO `areas` (`id_area`, `nombre`, `descripcion`, `estatus`) VALUES
                    (1, 'Recursos Humanos', 'Recursos humanos', 'activa')";
            },

            '2026_02_23_022_insert_initial_cargos' => function() {
                return "INSERT IGNORE INTO `cargos` (`id_cargo`, `nombre`, `descripcion`, `estatus`) VALUES
                    (1, 'Gerente RH', 'Gerente de Recursos humanos', 'activo')";
            },

            '2026_02_23_023_insert_initial_vistas' => function() {
                return "INSERT IGNORE INTO `vistas` (`id_vista`, `path`, `clave`, `titulo`, `estatus`) VALUES
                    (1, 'dashboard.php', 'DASHBOARD', 'Dashboard', 'activa'),
                    (2, 'usuarios.php', 'USUARIOS', 'Gestión de Usuarios', 'activa'),
                    (3, 'roles.php', 'ROLES', 'Gestión de Roles', 'activa'),
                    (4, 'permisos_por_rol.php', 'PERMISOS_VISTAS', 'Permisos de Vistas', 'activa'),
                    (5, 'areas_por_rol.php', 'PERMISOS_AREAS', 'Permisos de Áreas', 'activa'),
                    (6, 'vistas.php', 'VISTAS_ADMIN', 'Administración de Vistas', 'activa'),
                    (7, 'areas.php', 'AREAS', 'Gestión de Áreas', 'activa'),
                    (8, 'cargos.php', 'CARGOS', 'Gestión de Cargos', 'activa'),
                    (9, 'empleados.php', 'EMPLEADOS', 'Gestión de Empleados', 'activa'),
                    (10, 'sesiones.php', 'SESIONES', 'Sesiones Activas', 'activa')";
            },

            '2026_02_23_024_insert_initial_usuarios_vistas' => function() {
                return "INSERT IGNORE INTO `usuarios_vistas` (`id_rol`, `id_vista`, `permitido`) VALUES
                    (1, 1, 1), (1, 2, 1), (1, 3, 1), (1, 4, 1), (1, 5, 1),
                    (1, 6, 1), (1, 7, 1), (1, 8, 1), (1, 9, 1), (1, 10, 1),
                    (2, 1, 1), (2, 2, 0), (2, 3, 0), (2, 4, 0), (2, 5, 0),
                    (2, 6, 0), (2, 7, 1), (2, 8, 1), (2, 9, 1), (2, 10, 0)";
            },

            // ========================
            // AGREGAR NUEVAS MIGRACIONES AQUÍ
            // ========================
            // Ejemplo de migración futura:
            // '2026_03_02_025_add_column_to_empleados' => function() {
            //     return "ALTER TABLE `empleados` ADD COLUMN `nueva_columna` VARCHAR(100) DEFAULT NULL";
            // },
        ];
    }

    /**
     * Ejecuta todas las migraciones pendientes
     */
    public function runPending(): array
    {
        $results = [
            'executed' => [],
            'skipped' => [],
            'errors' => []
        ];

        $executedMigrations = $this->getExecutedMigrations();
        $batch = $this->getNextBatch();

        foreach ($this->migrations as $name => $migration) {
            if (in_array($name, $executedMigrations)) {
                $results['skipped'][] = $name;
                continue;
            }

            try {
                $sql = $migration();
                
                // Ejecutar múltiples queries si es necesario
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($queries as $query) {
                    if (empty($query)) continue;
                    
                    $this->conn->query($query);
                    
                    if ($this->conn->error) {
                        throw new Exception($this->conn->error);
                    }
                }

                // Registrar migración ejecutada
                $this->recordMigration($name, $batch);
                $results['executed'][] = $name;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'migration' => $name,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Obtiene las migraciones ya ejecutadas
     */
    private function getExecutedMigrations(): array
    {
        $sql = "SELECT DISTINCT nombre FROM `{$this->migrationsTable}`";
        $result = $this->conn->query($sql);

        if (!$result) {
            return [];
        }

        $executed = [];
        while ($row = $result->fetch_assoc()) {
            $executed[] = $row['nombre'];
        }

        return $executed;
    }

    /**
     * Obtiene el próximo número de lote
     */
    private function getNextBatch(): int
    {
        $sql = "SELECT MAX(lote) as max_batch FROM `{$this->migrationsTable}`";
        $result = $this->conn->query($sql);

        if (!$result) {
            return 1;
        }

        $row = $result->fetch_assoc();
        return ($row['max_batch'] ?? 0) + 1;
    }

    /**
     * Registra una migración como ejecutada
     */
    private function recordMigration(string $name, int $batch): void
    {
        $sql = "INSERT INTO `{$this->migrationsTable}` (nombre, lote) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Error preparando statement: " . $this->conn->error);
        }

        $stmt->bind_param('si', $name, $batch);

        if (!$stmt->execute()) {
            throw new Exception("Error registrando migración: " . $stmt->error);
        }

        $stmt->close();
    }

    /**
     * Obtiene el historial completo de migraciones
     */
    public function getHistory(): array
    {
        $sql = "SELECT * FROM `{$this->migrationsTable}` ORDER BY lote DESC, id DESC";
        $result = $this->conn->query($sql);

        if (!$result) {
            return [];
        }

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }

        return $history;
    }

    /**
     * Obtiene el estado actual de las migraciones
     */
    public function getStatus(): array
    {
        $executed = $this->getExecutedMigrations();
        $pending = [];

        foreach (array_keys($this->migrations) as $name) {
            if (!in_array($name, $executed)) {
                $pending[] = $name;
            }
        }

        return [
            'total' => count($this->migrations),
            'executed' => count($executed),
            'pending' => count($pending),
            'executed_list' => $executed,
            'pending_list' => $pending
        ];
    }
}

// ============ INTERFAZ DE LÍNEA DE COMANDOS ============

if (php_sapi_name() === 'cli') {
    try {
        $migrator = new DatabaseMigrations($conn);

        if (isset($argv[1])) {
            switch ($argv[1]) {
                case 'run':
                    echo "Ejecutando migraciones...\n";
                    $results = $migrator->runPending();

                    echo "\n✓ Ejecutadas: " . count($results['executed']) . "\n";
                    echo "  " . implode("\n  ", $results['executed']) . "\n\n";

                    echo "⊘ Omitidas: " . count($results['skipped']) . "\n";
                    if (count($results['skipped']) > 0 && count($results['skipped']) <= 5) {
                        echo "  " . implode("\n  ", $results['skipped']) . "\n\n";
                    }

                    if (count($results['errors']) > 0) {
                        echo "✗ Errores: " . count($results['errors']) . "\n";
                        foreach ($results['errors'] as $error) {
                            echo "  • {$error['migration']}: {$error['error']}\n";
                        }
                    } else {
                        echo "\n✓ Todas las migraciones se completaron correctamente\n";
                    }
                    break;

                case 'status':
                    $status = $migrator->getStatus();
                    echo "Estado de Migraciones:\n";
                    echo "Total: {$status['total']}\n";
                    echo "Ejecutadas: {$status['executed']}\n";
                    echo "Pendientes: {$status['pending']}\n";
                    break;

                case 'history':
                    echo "Historial de Migraciones:\n";
                    echo str_repeat("-", 80) . "\n";
                    $history = $migrator->getHistory();

                    foreach ($history as $migration) {
                        echo "Lote: {$migration['lote']} | {$migration['nombre']} | {$migration['ejecutado_en']}\n";
                    }
                    break;

                default:
                    echo "Comandos disponibles:\n";
                    echo "  php migrations.php run      - Ejecuta migraciones pendientes\n";
                    echo "  php migrations.php status   - Muestra estado de migraciones\n";
                    echo "  php migrations.php history  - Muestra historial completo\n";
            }
        } else {
            echo "Uso: php migrations.php [comando]\n";
            echo "Digite 'php migrations.php' sin argumentos para ver opciones\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Interfaz web
    header('Content-Type: application/json');

    try {
        $migrator = new DatabaseMigrations($conn);
        $action = $_GET['action'] ?? 'status';

        switch ($action) {
            case 'run':
                $results = $migrator->runPending();
                echo json_encode(['success' => true, 'results' => $results]);
                break;

            case 'status':
                $status = $migrator->getStatus();
                echo json_encode(['success' => true, 'status' => $status]);
                break;

            case 'history':
                $history = $migrator->getHistory();
                echo json_encode(['success' => true, 'history' => $history]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
