<?php
// /logout.php (SOPORTE)
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  flash('warning', 'Acción no permitida', 'Método inválido.', true);
  header('Location: dashboard.php'); exit;
}

$uid = (int)($_SESSION['user']['id_usuario'] ?? 0);
$sid = (string)($_SESSION['sid'] ?? '');

if ($uid && $sid) {
  $stmt = $conn->prepare("UPDATE sesiones_usuarios SET revocado_en = NOW() WHERE id_usuario=? AND id_sesion=? AND revocado_en IS NULL");
  $stmt->bind_param("is", $uid, $sid);
  $stmt->execute();
}

// destruir sesión PHP
$_SESSION = [];
session_destroy();

session_start();
flash('success', 'Sesión cerrada', 'Hasta luego.', true);
header("Location: login.php");
exit;
