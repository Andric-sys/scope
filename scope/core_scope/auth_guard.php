<?php
// core_scope/auth_guard.php
// Guardián de autenticación para Core Scope
// Verifica que exista una sesión válida de CGL antes de permitir acceso

declare(strict_types=1);

// Inicia la sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ruta relativa a CGL para funciones de sesión
$cgl_path = dirname(__DIR__) . '/CGL';

// Cargar las funciones de sesión de CGL si existen
if (file_exists($cgl_path . '/session_guard.php')) {
    require_once $cgl_path . '/session_guard.php';
}

if (file_exists($cgl_path . '/connection.php')) {
    require_once $cgl_path . '/connection.php';
}

/**
 * Función para crear mensaje flash
 */
function flash_core(string $type, string $title, string $text = '', bool $toast = false, int $timer = 2200): void {
    $_SESSION['flash'] = [
        'type'  => $type,
        'title' => $title,
        'text'  => $text,
        'toast' => $toast,
        'timer' => $timer
    ];
}

/**
 * Detectar si es una petición AJAX/API
 */
function is_ajax_request(): bool {
    // Verificar header X-Requested-With
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    
    // Verificar si se espera JSON como respuesta
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) {
        return true;
    }
    
    // Verificar Content-Type
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        return true;
    }
    
    return false;
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    // Si es una petición AJAX/API, responder con JSON
    if (is_ajax_request()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'No autenticado',
            'message' => 'Inicia sesión en CGL para acceder a Core Scope.',
            'redirect' => '../CGL/login.php'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Si es una petición normal, redirigir al login
    flash_core('warning', 'Sesión requerida', 'Inicia sesión en CGL para acceder a Core Scope.', true);
    header('Location: ../CGL/login.php');
    exit;
}

// Si existe la función de validación de sesión activa, usarla
if (function_exists('enforce_active_session') && isset($conn)) {
    enforce_active_session($conn);
}

// Opcional: Guardar información del usuario para uso en Core Scope
$current_user = $_SESSION['user'];
$user_id = $current_user['id_usuario'] ?? 0;
$user_name = trim(($current_user['nombre'] ?? '') . ' ' . ($current_user['apellido'] ?? ''));
$user_email = $current_user['correo'] ?? '';
$user_foto = $current_user['foto_perfil'] ?? '';
$user_rol = $current_user['rol'] ?? 'Usuario';
