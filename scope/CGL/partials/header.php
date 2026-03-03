<?php
// /partials/header.php (SOPORTE)
if (session_status() === PHP_SESSION_NONE) session_start();

$page_title = $page_title ?? 'CGL';
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user = $_SESSION['user'] ?? null;
$fullName = $user ? trim(($user['nombre'] ?? '').' '.($user['apellido'] ?? '')) : '';
$photo = $user['foto_perfil'] ?? '';

$initials = 'U';
if ($user) {
  $n = mb_substr((string)($user['nombre'] ?? ''), 0, 1);
  $a = mb_substr((string)($user['apellido'] ?? ''), 0, 1);
  $initials = mb_strtoupper(($n.$a) ?: 'U');
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($page_title) ?></title>

<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0171e2">

<link rel="stylesheet" href="assets/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Evita “flash” de tema
(function(){
  try{
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
  }catch(e){}
})();
// Maneja la imagen del logo
document.addEventListener('DOMContentLoaded', function() {
  const brandText = document.querySelector('.brand-text');
  const logoImg = document.querySelector('.brand img');
  
  if(logoImg) {
    // Si la imagen carga correctamente, oculta el texto
    if(logoImg.complete && logoImg.naturalHeight !== 0) {
      if(brandText) brandText.style.display = 'none';
    }
    // Si la imagen falla al cargar, muestra el texto
    logoImg.addEventListener('error', function() {
      if(brandText) brandText.style.display = 'block';
    });
  }
});</script>
</head>
<body>

<header class="app-header">
  <div class="header-inner">
    <div class="brand" onclick="location.href='dashboard.php'" style="cursor:pointer;">
      <img src="assets/img/Logo.png" alt="CGL">
      <div class="brand-text" style="display:none;">
        <b>CORE</b><br>
        <small>Global Logistics</small>
      </div>
    </div>

    <div class="header-actions">
      <div class="global-search">
        <input id="globalSearch" type="text" placeholder="Buscar módulo..." autocomplete="off">
      </div>

      <button class="theme-toggle" id="themeToggle" type="button" aria-label="Cambiar tema">🌓</button>

      <?php if($user): ?>
      <div class="profile-wrap">
        <button class="profile-chip" id="profileBtn" type="button">
          <?php if($photo): ?>
            <img class="avatar" src="<?= h($photo) ?>" alt="Foto">
          <?php else: ?>
            <span class="avatar-fallback"><?= h($initials) ?></span>
          <?php endif; ?>
          <span class="profile-name"><?= h($fullName ?: 'Usuario') ?></span>
          <span class="chev">▾</span>
        </button>

        <div class="profile-menu" id="profileMenu" hidden>
          <a href="perfil.php" class="pm-item">Administrar perfil</a>

          <form id="logoutForm" method="POST" action="logout.php" style="margin:0;">
            <button type="submit" class="pm-item danger" id="logoutBtn">Cerrar sesión</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</header>
