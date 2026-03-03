# Sistema de Migraciones de Base de Datos ✓

## 📋 Descripción

Sistema profesional de migraciones para **Core Global Logistics** que:

✅ Registra todos los cambios de BD en un historial  
✅ Evita ejecutar cambios duplicados  
✅ Rastrea el estado de cada migración con lotes  
✅ Incluye todas las migraciones iniciales existentes  
✅ Permite agregar nuevas migraciones fácilmente  

---

## 🚀 Cómo Usar

### Opción 1: Línea de Comandos (Recomendado)

```bash
# Ver estado actual
php migrations.php status

# Ejecutar migraciones pendientes
php migrations.php run

# Ver historial completo
php migrations.php history
```

### Opción 2: Interfaz Web

```
http://localhost/CGL/migrations.php?action=status
http://localhost/CGL/migrations.php?action=run
http://localhost/CGL/migrations.php?action=history
```

---

## 📝 Estructura de Migraciones

Todas las migraciones ejecutadas están registradas en la tabla `migraciones`:

| Campo | Descripción |
|-------|------------|
| `id` | ID único |
| `nombre` | Nombre de la migración |
| `lote` | Número de lote (agrupa migraciones ejecutadas juntas) |
| `ejecutado_en` | Fecha y hora de ejecución |

---

## ➕ Agregar Nueva Migración

Edita [migrations.php](migrations.php) y agrega una nueva entrada antes del cierre del array:

```php
'2026_03_05_025_add_contrato_to_empleados' => function() {
    return "ALTER TABLE `empleados` ADD COLUMN `tipo_contrato` VARCHAR(50) DEFAULT 'indefinido'";
},
```

**Convenio de nombres:**
```
AAAA_MM_DD_XXX_descripcion_de_cambio
```

**Características:**
- Para múltiples queries, sepáralas con `;`
- Usa `INSERT IGNORE` para evitar duplicados en datos
- Usa `DROP ... IF EXISTS` para evitar errores
- Cada migración solo se ejecuta una vez

---

## 📊 Ejemplo: Agregar Nueva Columna

```php
'2026_03_05_025_add_salario_to_empleados' => function() {
    return "ALTER TABLE `empleados` ADD COLUMN `salario` DECIMAL(10,2) DEFAULT NULL;
    ALTER TABLE `empleados` ADD COLUMN `moneda` ENUM('MXN','USD') DEFAULT 'MXN'";
},
```

Luego ejecuta:
```bash
php migrations.php run
```

---

## 🔍 Ver Estado

```bash
php migrations.php status
```

Resultado:
```
Estado de Migraciones:
Total: 24                    # Total de migraciones definidas
Ejecutadas: 24              # Ya ejecutadas
Pendientes: 0               # Por ejecutar
```

---

## 📜 Historial Completo

```bash
php migrations.php history
```

Muestra todas las migraciones con su lote y fecha de ejecución.

---

## ⚠️ Características Importantes

### Idempotencia
Las migraciones son **idempotentes** - si intentas ejecutarlas de nuevo, se omiten:
- Usa `IF NOT EXISTS` al crear tablas
- Usa `INSERT IGNORE` al insertar datos  
- Usa `DROP ... IF EXISTS` al eliminar

### Lotes
Cada ejecución de `php migrations.php run` crea un nuevo lote:
- **Lote 1**: Creación de tablas (9 migraciones)
- **Lote 2**: Inserciones iniciales (5 migraciones)
- **Lote 3**: Claves foráneas (15 migraciones)
- **Lote 4**: Tus nuevas migraciones

### Seguridad
- No se ejecutan duplicados
- Se valida cada query
- Se registra el error si falla
- Compatible con transacciones

---

## 🛠️ Migraciones Actuales

### Tablas Base (9 migraciones)
- `areas` - Áreas de la empresa
- `cargos` - Cargos disponibles
- `roles` - Roles de usuarios
- `empleados` - Datos de empleados
- `usuarios` - Usuarios del sistema
- `vistas` - Páginas del sistema
- `areas_por_rol` - Permisos de áreas por rol
- `usuarios_vistas` - Permisos de vistas por rol
- `sesiones_usuarios` - Sesiones activas

### Claves Foráneas (10 migraciones)
Garantizan integridad referencial entre tablas

### Datos Iniciales (5 migraciones)
- 2 Roles (Administrador, RH)
- 1 Área (Recursos Humanos)
- 1 Cargo (Gerente RH)
- 10 Vistas (módulos del sistema)
- 20 Permisos iniciales

---

## 🎯 Casos de Uso

### 1. Nuevo ambiente/servidor
```bash
php migrations.php run
# Ejecuta TODAS las migraciones desde cero
```

### 2. Agregar columna a tabla existente
```php
'2026_03_06_025_add_fecha_capacitacion' => function() {
    return "ALTER TABLE `empleados` ADD COLUMN `fecha_capacitacion` DATE DEFAULT NULL";
},
```

### 3. Agregar datos iniciales
```php
'2026_03_06_026_insert_new_roles' => function() {
    return "INSERT IGNORE INTO `roles` (id_rol, nombre, estatus) VALUES (3, 'Gerente', 'activo')";
},
```

### 4. Crear índice
```php
'2026_03_06_027_add_index_empleados_email' => function() {
    return "ALTER TABLE `empleados` ADD INDEX `idx_correo` (correo)";
},
```

---

## 📚 API (Uso Programático)

```php
require 'migrations.php';

$migrator = new DatabaseMigrations($conn);

// Ejecutar pendientes
$results = $migrator->runPending();
// ['executed' => [...], 'skipped' => [...], 'errors' => [...]]

// Ver estado
$status = $migrator->getStatus();
// ['total' => 24, 'executed' => 24, 'pending' => 0, ...]

// Ver historial
$history = $migrator->getHistory();
// [['id' => 1, 'nombre' => '...', 'lote' => 1, ...], ...]
```

---

## ✅ Checklist para Nueva Migración

- [ ] Nombra siguiendo patrón: `AAAA_MM_DD_XXX_descripcion`
- [ ] Usa `IF NOT EXISTS` o `DROP ... IF EXISTS`
- [ ] Para datos usa `INSERT IGNORE`
- [ ] Agrega antes del cierre del array en `defineMigrations()`
- [ ] Ejecuta: `php migrations.php run`
- [ ] Verifica: `php migrations.php status`
- [ ] Revisa: `php migrations.php history`

---

## 📞 Soporte

En caso de error:
1. Verifica que la BD esté corriendo
2. Revisa el mensaje de error en la salida
3. Valida la sintaxis SQL de tu migración
4. Consulta el historial: `php migrations.php history`

---

**Última actualización:** 2 de marzo de 2026
