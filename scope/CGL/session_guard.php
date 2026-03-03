<?php
// /session_guard.php (SOPORTE)
declare(strict_types=1);

/**
 * Fingerprint consistente del dispositivo
 */
if (!function_exists('device_fingerprint')) {
  function device_fingerprint(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ua . '|' . $ip);
  }
}

/**
 * Valida que la sesión actual siga siendo la activa (sesión única).
 * Si no, destruye sesión y manda a login con mensaje.
 */
if (!function_exists('enforce_active_session')) {
  function enforce_active_session(mysqli $conn): void {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user'], $_SESSION['sid'], $_SESSION['session_token'])) {
      return; // aún no hay sesión formal (ej. login)
    }

    $uid   = (int)($_SESSION['user']['id_usuario'] ?? 0);
    $sid   = (string)($_SESSION['sid'] ?? '');
    $token = (string)($_SESSION['session_token'] ?? '');

    if ($uid <= 0 || $sid === '' || $token === '') {
      return;
    }

    $stmt = $conn->prepare("
      SELECT id
      FROM sesiones_usuarios
      WHERE id_usuario = ?
        AND id_sesion = ?
        AND huella_dispositivo = ?
        AND revocado_en IS NULL
      LIMIT 1
    ");
    if (!$stmt) return;

    $stmt->bind_param("iss", $uid, $sid, $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
      // Reemplazada / revocada
      $_SESSION = [];
      if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
      }
      session_destroy();

      session_start();
      $_SESSION['flash'] = [
        'type'  => 'warning',
        'title' => 'Sesión finalizada',
        'text'  => 'Tu sesión fue reemplazada o cerrada desde otro dispositivo.',
        'toast' => false,
        'timer' => 2200
      ];
      header('Location: login.php');
      exit;
    }

    // Actualiza last_seen
    $id = (int)$row['id'];
    $u2 = $conn->prepare("UPDATE sesiones_usuarios SET visto_en = NOW() WHERE id = ?");
    if ($u2) {
      $u2->bind_param("i", $id);
      $u2->execute();
    }
  }
}
