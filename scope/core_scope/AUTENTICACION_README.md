# Core Scope - Integración con Sistema de Autenticación CGL

## 📋 Descripción

Core Scope ha sido integrado con el sistema de autenticación de CGL (Core Global Logistics). Esto significa que todos los usuarios deben iniciar sesión a través de CGL antes de poder acceder a cualquier funcionalidad de Core Scope.

## 🔐 Sistema de Autenticación

### Archivo Principal: `auth_guard.php`

Este archivo es el guardián de autenticación que se incluye en todos los archivos PHP de Core Scope. Sus funciones principales son:

1. **Verificación de Sesión**: Comprueba que exista una sesión válida de CGL
2. **Validación de Sesión Activa**: Usa las funciones de CGL para verificar que la sesión sigue activa
3. **Manejo de Peticiones AJAX/API**: Detecta automáticamente peticiones AJAX y responde con JSON en lugar de redireccionar
4. **Variables de Usuario**: Expone información del usuario actual para uso en las páginas

### Variables Disponibles

Una vez que `auth_guard.php` se incluye en un archivo, están disponibles las siguientes variables:

```php
$current_user  // Array con toda la información del usuario
$user_id       // ID del usuario (int)
$user_name     // Nombre completo del usuario
$user_email    // Correo electrónico
$user_foto     // Ruta de la foto de perfil
$user_rol      // Rol del usuario
```

## 🏠 Menú Principal

### Archivo: `menu_principal.php`

Se ha creado un menú principal moderno y centralizado para Core Scope que incluye:

- **Dashboard Ejecutivo**: Vista consolidada con métricas clave
- **Órdenes Scope**: Gestión de órdenes de compra y servicios
- **Órdenes de Transporte**: Control de logística
- **Job Costing**: Análisis de costos por proyecto
- **Visor Scope**: Explorador avanzado de datos
- **Sincronización**: Panel de sincronización con sistemas externos
- **Consola Scope**: Herramientas administrativas
- **Gráficas**: Visualización de datos y reportes
- **Auditoría**: Registro de cambios del sistema

### Acceso Directo

El archivo `index.php` ahora redirige automáticamente a `menu_principal.php`, que sirve como punto de entrada principal para Core Scope.

## 🔒 Archivos Protegidos

Todos los siguientes archivos han sido protegidos con autenticación:

### Páginas Principales
- `dashboard_pro.php`
- `menu_principal.php`
- `ordenes_scope.php`
- `transport_orders.php`
- `jobcosting_totals.php`
- `graficas.php`
- `scope_viewer.php`
- `scope_sync_panel.php`
- `scope_console.php`
- `scope_audit.php`
- `scope_menu.php`

### Páginas de Gráficas
- `ordenes_scope_graficas.php`
- `transport_orders_graficas.php`
- `jobcosting_totals_graficas.php`

### APIs
- `api_dashboard.php`
- `api_all_clients.php`
- `api_detail.php`
- `api_metas_ventas.php`
- `scope_sync.php`

### Exportación
- `export_scope_orders.php`
- `export_scope_orders_full.php`
- `export_transport_orders.php`
- `export_jobcosting_totals.php`

### Configuración
- `setup_metas_ventas.php`
- `migrations.php` (protegido solo para acceso web, CLI sigue funcionando)

## 🚀 Flujo de Autenticación

1. Usuario intenta acceder a cualquier página de Core Scope
2. `auth_guard.php` verifica si existe sesión válida de CGL
3. Si NO hay sesión:
   - **Petición normal**: Redirige a `/CGL/login.php`
   - **Petición AJAX/API**: Responde con JSON 401 Unauthorized
4. Si hay sesión válida:
   - Valida que la sesión sigue activa (sesión única de CGL)
   - Permite el acceso a la página solicitada

## 🔄 Integración con CGL

Core Scope ahora comparte la misma sesión con CGL, lo que significa:

- **Sesión Única**: Al iniciar sesión en CGL, automáticamente se tiene acceso a Core Scope
- **Cierre de Sesión Compartido**: Cerrar sesión en cualquiera de los sistemas cierra la sesión en ambos
- **Validación Continua**: Las sesiones se validan en cada petición
- **Dispositivo Único**: Solo una sesión activa por usuario (según configuración de CGL)

## 📱 Navegación

Desde el menú principal de Core Scope se puede:

- Acceder a todas las funcionalidades de Core Scope
- Volver al dashboard de CGL
- Ir al perfil de usuario
- Cerrar sesión

## 🛠️ Mantenimiento

### Agregar Protección a Nuevos Archivos

Si se crea un nuevo archivo PHP en Core Scope que requiera autenticación:

```php
<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';

// ... resto del código
```

### Archivos que NO Necesitan Protección

Los siguientes tipos de archivos NO necesitan incluir `auth_guard.php`:

- **Librerías puras** (solo funciones, no ejecutan código): `scope_api.php`, `scope_upsert.php`, `scope_insert.php`
- **Archivos de configuración**: `conexion.php`, `config.php`
- **Scripts CLI exclusivos** (si nunca se acceden desde navegador)

## ⚠️ Notas Importantes

1. **Sesión de CGL es Requerida**: Core Scope no puede funcionar independientemente, requiere la base de datos y sesión de CGL
2. **Permisos de Rol**: Core Scope aún no implementa permisos granulares por rol como CGL. Cualquier usuario autenticado puede acceder a todas las funciones
3. **Rutas Relativas**: Los archivos asumen que CGL está en `../CGL/` relativo a core_scope
4. **Base de Datos**: CGL usa la base de datos `core_global_logistics` para gestionar usuarios y sesiones

## 🔮 Futuras Mejoras

- Implementar sistema de permisos por rol específico para Core Scope
- Agregar logs de auditoría de acceso
- Implementar permisos granulares por función/módulo
- Crear panel de administración de accesos específico para Core Scope

---

**Última actualización**: 2 de marzo de 2026
