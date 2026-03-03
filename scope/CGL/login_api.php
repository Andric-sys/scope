<?php
// /login_api.php (API PARA LOGIN)
// Endpoints: list (obtener usuarios) y login (autenticar)

require __DIR__ . '/connection.php';

function json_out($success, $data = null, $msg = '') {
  header('Content-Type: application/json; charset=utf-8');
  json_encode(compact('success', 'data', 'msg'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  echo json_encode(compact('success', 'data', 'msg'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// GET: Obtener lista de usuarios activos
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $rs = $conn->query("
    SELECT 
      id_usuario,
      TRIM(CONCAT(nombre,' ',apellido)) as nombre,
      foto_perfil,
      correo
    FROM usuarios 
    WHERE estatus='activo' 
    ORDER BY nombre ASC
  ");
  
  $usuarios = [];
  while($u = $rs->fetch_assoc()) {
    $foto = $u['foto_perfil'] && file_exists(__DIR__ . '/' . $u['foto_perfil']) 
      ? $u['foto_perfil'] 
      : 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%230171e2%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2250%22 fill=%22white%22 text-anchor=%22middle%22 dy=%22.35em%22%3E%3F%3C/text%3E%3C/svg%3E';
    
    $usuarios[] = [
      'id' => (int)$u['id_usuario'],
      'nombre' => $u['nombre'],
      'foto' => $foto,
      'correo' => $u['correo']
    ];
  }
  
  json_out(true, $usuarios);
  exit;
}

// POST: Autenticar con id_usuario + password
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_usuario = (int)($_POST['id_usuario'] ?? 0);
  $password = $_POST['password'] ?? '';
  
  if (!$id_usuario || !$password) {
    json_out(false, null, 'Usuario y contraseña requeridos');
    exit;
  }
  
  $st = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario=? AND estatus='activo' LIMIT 1");
  $st->bind_param("i", $id_usuario);
  $st->execute();
  $user = $st->get_result()->fetch_assoc();
  
  if (!$user || $user['password'] !== $password) {
    json_out(false, null, 'Contraseña incorrecta');
    exit;
  }
  
  // Verificar que session_guard.php exista
  if (!function_exists('device_fingerprint')) {
    require_once __DIR__ . '/session_guard.php';
  }
  
  if (session_status() === PHP_SESSION_NONE) session_start();
  
  session_regenerate_id(true);
  $_SESSION['user'] = $user;
  
  $uid = $user['id_usuario'];
  $id_rol = (int)$user['id_rol'];
  
  // Cargar permisos de vistas
  $stPerms = $conn->prepare("
    SELECT uv.id_vista FROM usuarios_vistas uv
    WHERE uv.id_rol=? AND uv.permitido=1
  ");
  $stPerms->bind_param("i", $id_rol);
  $stPerms->execute();
  $rsPerms = $stPerms->get_result();
  
  $permisos_vistas = [];
  while($perm = $rsPerms->fetch_assoc()) {
    $permisos_vistas[] = (int)$perm['id_vista'];
  }
  
  // Cargar claves de vistas
  $stClaves = $conn->prepare("
    SELECT v.clave FROM usuarios_vistas uv
    INNER JOIN vistas v ON v.id_vista = uv.id_vista
    WHERE uv.id_rol=? AND uv.permitido=1
  ");
  $stClaves->bind_param("i", $id_rol);
  $stClaves->execute();
  $rsClaves = $stClaves->get_result();
  
  $claves_permitidas = [];
  while($clave = $rsClaves->fetch_assoc()) {
    $claves_permitidas[] = $clave['clave'];
  }
  
  $_SESSION['permisos_vistas'] = $permisos_vistas;
  $_SESSION['claves_permitidas'] = $claves_permitidas;
  
  // Revocar sesiones previas
  $rev = $conn->prepare("UPDATE sesiones_usuarios SET revocado_en = NOW() WHERE id_usuario=? AND revocado_en IS NULL");
  $rev->bind_param("i", $uid);
  $rev->execute();
  
  // Insertar nueva sesión
  $sid = session_id();
  if (!function_exists('device_fingerprint')) {
    require_once __DIR__ . '/session_guard.php';
  }
  $fp  = device_fingerprint();
  $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
  
  $ins = $conn->prepare("
    INSERT INTO sesiones_usuarios (id_usuario, id_sesion, huella_dispositivo, agente_usuario, ip, creado_en, visto_en)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
  ");
  $ins->bind_param("issss", $uid, $sid, $fp, $ua, $ip);
  $ins->execute();
  
  $_SESSION['sid'] = $sid;
  $_SESSION['session_token'] = $fp;
  
  json_out(true, ['redirect' => 'dashboard.php']);
  exit;
}

json_out(false, null, 'Acción no válida');
?>
