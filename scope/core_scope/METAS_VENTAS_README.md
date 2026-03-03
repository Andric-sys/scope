# Sistema de Gestión de Metas de Ventas

## 📋 Descripción
Sistema completo para gestionar y editar metas de ventas mensuales y anuales en el dashboard ejecutivo de CORE SCOPE.

## 🗂️ Archivos Creados

### 1. Base de Datos
- **`create_table_metas_ventas.sql`**: Script SQL para crear la tabla manualmente
- **`setup_metas_ventas.php`**: Script PHP ejecutable desde navegador para crear la tabla automáticamente

### 2. API
- **`api_metas_ventas.php`**: API RESTful para CRUD de metas
  - GET: Obtener metas de un año
  - POST: Crear/actualizar meta
  - PUT: Actualizar meta existente
  - DELETE: Eliminar meta

### 3. Vista
- **`dashboard_pro.php`**: Modificado para incluir:
  - Botón de edición en KPI Meta Anual
  - Columna de Acciones en tabla de Metas Mensuales
  - Modal de edición de metas
  - Funcionalidad JavaScript completa

## 🗄️ Estructura de la Tabla

```sql
CREATE TABLE metas_ventas (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  anio INT(11) NOT NULL,
  mes INT(11) NOT NULL COMMENT '0 = anual, 1-12 = mes específico',
  meta DECIMAL(12,2) NOT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY anio_mes (anio, mes)
);
```

### Campos:
- **id**: Identificador único (auto-increment)
- **anio**: Año de la meta (ej: 2026)
- **mes**: Mes de la meta (0 = anual, 1-12 = mensual)
- **meta**: Valor de la meta en formato decimal (12,2)
- **fecha_registro**: Fecha de creación/actualización automática

## 🚀 Instalación

### Opción 1: Usando el script PHP (Recomendado)
1. Accede desde tu navegador a: `http://localhost/core_scope/setup_metas_ventas.php`
2. El script creará la tabla e insertará metas predeterminadas para 2026
3. Verifica el mensaje de confirmación

### Opción 2: Usando el script SQL
1. Accede a phpMyAdmin o tu cliente MySQL
2. Selecciona tu base de datos
3. Ejecuta el contenido de `create_table_metas_ventas.sql`

## 📱 Uso

### Acceder al Dashboard
1. Abre: `http://localhost/core_scope/dashboard_pro.php`
2. Navega al tablero "🎯 Objetivos" usando los botones superiores

### Editar Meta Anual
1. En el KPI "Meta Anual 2026", haz clic en el botón ✏️
2. Ingresa el nuevo valor de la meta
3. Haz clic en "💾 Guardar"

### Editar Metas Mensuales
1. En la tabla "Metas Mensuales 2026", haz clic en el botón ✏️ de cualquier mes
2. Ingresa el nuevo valor de la meta
3. Haz clic en "💾 Guardar"

### Características
- ✅ Validación de valores positivos
- ✅ Actualización en tiempo real
- ✅ Interfaz moderna y responsive
- ✅ Mensajes de confirmación
- ✅ Almacenamiento persistente en BD

## 🔌 API Endpoints

### GET - Obtener metas
```
GET /api_metas_ventas.php?anio=2026
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "anio": 2026,
      "mes": 0,
      "meta": 5000000.00,
      "fecha_registro": "2026-03-02 10:00:00"
    }
  ]
}
```

### POST - Crear/Actualizar meta
```
POST /api_metas_ventas.php
Content-Type: application/json

{
  "anio": 2026,
  "mes": 1,
  "meta": 450000.00
}
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Meta actualizada correctamente",
  "id": 2
}
```

## 🎨 Funcionalidades UI

### Estilos Agregados
- `.btn-edit`: Botón de edición con efecto hover
- `.btn-edit-small`: Versión pequeña del botón
- `.form-group`, `.form-label`, `.form-input`: Formularios
- `.btn-save`, `.btn-cancel`: Botones de acción

### JavaScript
- `loadMetas(anio)`: Carga metas desde API
- `updateMetasUI()`: Actualiza KPIs y tabla
- `editMetaAnual()`: Abre modal para meta anual
- `editMetaMensual(mes, nombre)`: Abre modal para meta mensual
- `saveMeta(event)`: Guarda cambios en la BD
- `closeMetaModal()`: Cierra el modal

## 📊 Valores Predeterminados

El sistema viene con metas predeterminadas para 2026:
- **Meta Anual**: $5,000,000.00
- **Meta Mensual**: $416,666.67 (promedio)

Puedes modificarlas según tus necesidades desde la interfaz.

## 🔧 Troubleshooting

### Error: "No se puede conectar a la base de datos"
- Verifica que XAMPP/MySQL esté ejecutándose
- Revisa las credenciales en `config.php`

### Error: "Tabla metas_ventas no existe"
- Ejecuta `setup_metas_ventas.php` desde el navegador
- O ejecuta el SQL manualmente

### Las metas no se cargan
- Abre la consola del navegador (F12)
- Verifica que `api_metas_ventas.php` responda correctamente
- Revisa los logs de PHP

## 📝 Notas Técnicas

- La tabla usa `UNIQUE KEY (anio, mes)` para evitar duplicados
- El mes 0 representa la meta anual
- Los meses 1-12 representan enero-diciembre
- Las actualizaciones usan `ON DUPLICATE KEY UPDATE`
- El formato de moneda es `decimal(12,2)` para precisión

## 👨‍💻 Autor
Desarrollado para CORE SCOPE - Executive Dashboard
Fecha: Marzo 2026
