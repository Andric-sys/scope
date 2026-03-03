<?php
// /login.php (VISTA)
declare(strict_types=1);

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/session_guard.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function flash(string $type, string $title, string $text = '', bool $toast = false, int $timer = 2200): void {
  $_SESSION['flash'] = [
    'type'  => $type,
    'title' => $title,
    'text'  => $text,
    'toast' => $toast,
    'timer' => $timer
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $correo   = trim((string)($_POST['correo'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($correo === '' || $password === '') {
    flash('warning', 'Faltan datos', 'Completa correo y contraseña.', false);
    header('Location: login.php'); exit;
  }

  $stmt = $conn->prepare("SELECT * FROM usuarios WHERE correo=? AND estatus='activo' LIMIT 1");
  $stmt->bind_param("s", $correo);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  if (!$user || $user['password'] !== $password) {
    flash('error', 'Acceso denegado', 'Credenciales incorrectas.', false);
    header('Location: login.php'); exit;
  }

  if (!function_exists('device_fingerprint')) {
    flash('error', 'Error interno', 'session_guard.php no definió device_fingerprint().', false);
    header('Location: login.php'); exit;
  }

  session_regenerate_id(true);
  $_SESSION['user'] = $user;

  $uid = (int)$user['id_usuario'];
  $id_rol = (int)$user['id_rol'];

  // Cargar permisos de vistas para este rol
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
  
  // Cargar también las claves de vistas (para búsqueda rápida)
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

  // Revoca sesiones previas
  $rev = $conn->prepare("UPDATE sesiones_usuarios SET revocado_en = NOW() WHERE id_usuario=? AND revocado_en IS NULL");
  $rev->bind_param("i", $uid);
  $rev->execute();

  // Inserta nueva sesión
  $sid = session_id();
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

  flash('success', 'Bienvenido', 'Acceso correcto.', true);
  header('Location: dashboard.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar sesión · CGL</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0171e2">
<link rel="stylesheet" href="assets/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  try{ document.documentElement.setAttribute('data-theme', localStorage.getItem('theme')||'light'); }catch(e){}
})();
</script>
</head>
<body>

<div class="login-wrapper">
  <div class="login-container">
    
    <!-- FASE 1: Formulario tradicional usuario/contraseña -->
    <div id="phase1" class="login-phase active">
      <div class="login-header">
        <div class="login-logo-small">
          <img src="assets/img/Logo.png" alt="CORE Global Logistics">
        </div>
        <h2>Iniciar sesión</h2>
      </div>
      
      <div class="login-card">
        <form id="loginFormTraditional" method="POST">
          <input type="email" id="correoInput" name="correo" placeholder="Correo electrónico" required autocomplete="off">
          <input type="password" id="passwordInput" name="password" placeholder="Contraseña" required autocomplete="off">
          <button type="submit">Entrar</button>
        </form>
        
        <div class="login-divider">
          <span>o</span>
        </div>
        
        <button type="button" id="btnPhotoLogin" class="btn-photo-login">
          📸 Entrar con foto
        </button>
      </div>
    </div>
    
    <!-- FASE 2: Seleccionar usuario por foto -->
    <div id="phase2" class="login-phase">
      <div class="login-header">
        <button type="button" id="btnBack" class="btn-back-phase">← Atrás</button>
        <h2>Selecciona tu usuario</h2>
      </div>
      
      <div id="usersList" class="users-grid"></div>
      
      <div id="usersEmpty" class="login-error" style="display:none; margin-top:20px;">
        Error cargando usuarios
      </div>
    </div>
    
    <!-- FASE 3: Ingresar contraseña (después de seleccionar por foto) -->
    <div id="phase3" class="login-phase">
      <div class="login-card">
        <button type="button" id="btnBackFromPassword" class="btn-back-phase">← Atrás</button>
        
        <div class="login-user-info">
          <img id="userPhoto" class="user-photo-lg" src="" alt="Usuario">
          <div class="user-name-info">
            <span id="userName" class="user-name-lg"></span>
            <span id="userEmail" class="user-email-lg"></span>
          </div>
        </div>
        
        <form id="loginForm" method="POST" data-validate="swal">
          <input type="hidden" id="userId" name="id_usuario">
          <input type="password" id="userPassword" name="password" placeholder="Contraseña" required autocomplete="off">
          <button type="submit">Entrar</button>
        </form>
      </div>
    </div>
    
  </div>
</div>

<?php include __DIR__ . '/partials/flash.php'; ?>
<script src="assets/js/app.js"></script>

<script>
(function(){
  const $phase1 = document.getElementById('phase1');
  const $phase2 = document.getElementById('phase2');
  const $phase3 = document.getElementById('phase3');
  const $usersList = document.getElementById('usersList');
  const $usersEmpty = document.getElementById('usersEmpty');
  const $loginFormTraditional = document.getElementById('loginFormTraditional');
  const $loginForm = document.getElementById('loginForm');
  const $btnPhotoLogin = document.getElementById('btnPhotoLogin');
  const $btnBack = document.getElementById('btnBack');
  const $btnBackFromPassword = document.getElementById('btnBackFromPassword');
  const $userId = document.getElementById('userId');
  const $userName = document.getElementById('userName');
  const $userEmail = document.getElementById('userEmail');
  const $userPhoto = document.getElementById('userPhoto');
  const $password = document.getElementById('userPassword');
  
  // Cargar lista de usuarios
  async function loadUsers() {
    try {
      const res = await fetch('login_api.php?action=list');
      const json = await res.json();
      
      if (!json.success || !json.data?.length) {
        $usersEmpty.style.display = '';
        return;
      }
      
      $usersList.innerHTML = json.data.map(u => `
        <div class="user-card" onclick="selectUser(${u.id}, '${escHtml(u.nombre)}', '${escHtml(u.foto)}', '${escHtml(u.correo)}')">
          <img class="user-avatar-login" src="${escHtml(u.foto)}" alt="${escHtml(u.nombre)}">
          <span class="user-card-name">${escHtml(u.nombre)}</span>
        </div>
      `).join('');
    } catch(e) {
      console.error(e);
      $usersEmpty.style.display = '';
    }
  }
  
  // Ir a Phase 2 (galería de fotos)
  $btnPhotoLogin.addEventListener('click', () => {
    $phase1.classList.remove('active');
    $phase2.classList.add('active');
    loadUsers();
  });
  
  // Volver de Phase 2 a Phase 1
  $btnBack.addEventListener('click', () => {
    $phase2.classList.remove('active');
    $phase1.classList.add('active');
  });
  
  // Seleccionar usuario y pasar a phase 3
  window.selectUser = function(id, name, photo, email) {
    $userId.value = id;
    $userName.textContent = name;
    $userEmail.textContent = email;
    $userPhoto.src = photo;
    $password.value = '';
    $password.focus();
    
    $phase2.classList.remove('active');
    $phase3.classList.add('active');
  };
  
  // Volver de Phase 3 a Phase 2
  $btnBackFromPassword.addEventListener('click', () => {
    $password.value = '';
    $phase3.classList.remove('active');
    $phase2.classList.add('active');
  });
  
  // Manejar login tradicional desde Phase 1
  $loginFormTraditional.addEventListener('submit', (e) => {
    // Permitir envío natural del forms
  });
  
  // Manejar login desde Phase 3 (con foto)
  $loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData($loginForm);
    formData.append('action', 'login');
    
    try {
      const res = await fetch('login_api.php', {
        method: 'POST',
        body: formData
      });
      
      const json = await res.json();
      
      if (!json.success) {
        Swal.fire('Error', json.msg || 'Acceso denegado', 'error');
        return;
      }
      
      Swal.fire('Éxito', 'Acceso correcto', 'success').then(() => {
        location.href = json.data?.redirect || 'dashboard.php';
      });
    } catch(e) {
      console.error(e);
      Swal.fire('Error', 'Error en el servidor', 'error');
    }
  });
  
  // Helper: escapar HTML
  function escHtml(s) {
    return (s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }
  
  window.escHtml = escHtml;
})();
</script>
<script>
if ('serviceWorker' in navigator) navigator.serviceWorker.register('/CGL/sw.js');
</script>
</body>
</html>
