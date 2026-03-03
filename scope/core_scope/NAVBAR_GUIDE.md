# Guía de Uso - Navbar Core Scope

## 📋 Descripción

`navbar_core.php` es un componente de navegación reutilizable que se puede incluir en cualquier página de Core Scope para proporcionar navegación consistente y acceso rápido a funciones clave.

## 🎯 Características

- **Navegación Rápida**: Acceso directo al menú principal y dashboard de CGL
- **Información del Usuario**: Muestra avatar y nombre del usuario actual
- **Responsive**: Se adapta a dispositivos móviles
- **Diseño Consistente**: Mantiene la paleta de colores de Core Scope
- **Sticky**: Permanece visible al hacer scroll

## 💻 Uso

### Inclusión Básica

Para agregar la navbar a cualquier página, simplemente incluye el archivo después de `auth_guard.php`:

```php
<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mi Página</title>
</head>
<body>
    <?php include __DIR__ . '/navbar_core.php'; ?>
    
    <!-- Tu contenido aquí -->
    
</body>
</html>
```

### Ejemplo Completo

```php
<?php
declare(strict_types=1);

require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';

$cssVars = core_brand_css_vars();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Core Scope - Mi Vista</title>
    <style>
        <?= $cssVars ?>
        
        body {
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar_core.php'; ?>
    
    <div class="container">
        <h1>Mi Vista</h1>
        <p>Contenido de la página...</p>
    </div>
    
</body>
</html>
```

## 🎨 Elementos de la Navbar

### Logo y Marca
- Logo animado con efecto de superposición
- Nombre "CORE SCOPE" (se oculta en móviles)
- Click lleva al menú principal

### Botones de Navegación

1. **Menú (☰)**: Abre el menú principal de Core Scope
2. **Home (🏠)**: Redirige al dashboard de CGL
3. **Usuario**: Muestra avatar y nombre (nombre se oculta en móviles)
4. **Logout (→)**: Cierra sesión

## 📱 Comportamiento Responsive

### Desktop (≥640px)
- Muestra todos los elementos
- Nombre de usuario visible
- Logo y nombre de marca visibles

### Mobile (<640px)
- Logo sin texto
- Botones más compactos
- Nombre de usuario oculto (solo avatar)
- Espaciado reducido

## 🎨 Personalización

### Colores

La navbar usa el gradiente de Core Scope por defecto:

```css
background: linear-gradient(135deg, #0171e2, #000F9F);
```

Para personalizar, puedes agregar estilos adicionales en tu página:

```php
<style>
.core-navbar {
    background: linear-gradient(135deg, #tu-color-1, #tu-color-2);
}
</style>

<?php include __DIR__ . '/navbar_core.php'; ?>
```

### Ocultar Elementos

Para ocultar elementos específicos:

```php
<style>
.nav-user { display: none; }  /* Ocultar info de usuario */
.brand-logo { display: none; } /* Ocultar logo */
</style>

<?php include __DIR__ . '/navbar_core.php'; ?>
```

## ⚙️ Variables Requeridas

La navbar espera que estas variables estén disponibles (proporcionadas por `auth_guard.php`):

- `$user_name`: Nombre del usuario
- `$user_rol`: Rol del usuario
- `$user_foto`: Ruta a la foto de perfil

Si alguna variable no está disponible, la navbar usa valores por defecto.

## 🔧 Integración con Páginas Existentes

Para agregar la navbar a páginas existentes sin romper el diseño:

1. Incluye la navbar **después** del `<body>` y **antes** de tu contenido
2. Asegúrate de que tu contenido tenga margen superior si es necesario
3. La navbar es sticky, así que considera el espacio que ocupa

## 📝 Notas

- La navbar usa `position: sticky` y `z-index: 1000`
- Los estilos están autocontenidos en el archivo
- No requiere CSS o JS externos
- Compatible con todos los navegadores modernos

## 🚀 Agregar a Todas las Páginas

Si quieres agregar la navbar a todas las páginas de Core Scope, puedes crear un header común:

**partials/header_core.php**:
```php
<?php
// Este archivo asume que auth_guard.php ya fue incluido
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $pageTitle ?? 'Core Scope' ?></title>
    <style>
        <?= $cssVars ?? core_brand_css_vars() ?>
        /* Estilos globales aquí */
    </style>
</head>
<body>
    <?php include __DIR__ . '/../navbar_core.php'; ?>
```

Luego en tus páginas:
```php
<?php
$pageTitle = "Mi Vista";
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/partials/header_core.php';
?>

<!-- Tu contenido -->

<?php require __DIR__ . '/partials/footer_core.php'; ?>
```

---

**Creado**: 2 de marzo de 2026
