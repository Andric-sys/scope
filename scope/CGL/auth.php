<?php
// /auth.php (SOPORTE)
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/session_guard.php';

function flash(string $type, string $title, string $text = '', bool $toast = false, int $timer = 2200): void {
  $_SESSION['flash'] = [
    'type'  => $type,
    'title' => $title,
    'text'  => $text,
    'toast' => $toast,
    'timer' => $timer
  ];
}

if (!isset($_SESSION['user'])) {
  flash('warning', 'Sesión requerida', 'Inicia sesión para continuar.', true);
  header('Location: login.php');
  exit;
}

// ✅ Valida sesión única en cada request protegido
enforce_active_session($conn);

/**
 * Valida si el usuario actual tiene acceso a una vista
 * @param string $clave - La clave de la vista (ej: "USUARIOS", "DASHBOARD")
 * @return bool - true si tiene permiso, false si no
 */
function has_view_access(string $clave): bool {
  $claves = $_SESSION['claves_permitidas'] ?? [];
  return in_array($clave, $claves, true);
}

/**
 * Requiere acceso a una vista específica
 * Si no tiene permiso, redirige a dashboard con error
 * @param string $clave - La clave de la vista
 * @param mysqli $conn - Conexión (opcional, para logs futuros)
 */
function require_view_access(string $clave, ?mysqli $conn = null): void {
  if (!has_view_access($clave)) {
    $_SESSION['flash'] = [
      'type'  => 'error',
      'title' => 'Acceso denegado',
      'text'  => 'No tienes permiso para acceder a esta sección.',
      'toast' => true,
      'timer' => 2200
    ];
    header('Location: dashboard.php');
    exit;
  }
}
