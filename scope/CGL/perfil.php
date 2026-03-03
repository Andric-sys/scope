<?php
require __DIR__ . '/auth.php';

$page_title = 'Mi Perfil';
include __DIR__ . '/partials/header.php';

// ❌ NO declares h() aquí. Ya existe en header.php.

$u   = $_SESSION['user'];
$uid = (int)($u['id_usuario'] ?? 0);

if ($uid <= 0) {
  flash('error', 'Error', 'Sesión inválida.', false);
  header('Location: dashboard.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $nombre   = trim((string)($_POST['nombre'] ?? ''));
  $apellido = trim((string)($_POST['apellido'] ?? ''));

  if ($nombre === '' || $apellido === '') {
    flash('warning', 'Faltan datos', 'Nombre y apellido son obligatorios.', false);
    header('Location: perfil.php');
    exit;
  }

  $avatarPath = (string)($u['foto_perfil'] ?? '');

  if (!empty($_FILES['foto']['name'])) {

    $dir = __DIR__ . '/files/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
      flash('error', 'Formato inválido', 'Usa JPG, PNG o WEBP.', false);
      header('Location: perfil.php');
      exit;
    }

    // tamaño máximo recomendado (ej. 4MB)
    if (!empty($_FILES['foto']['size']) && (int)$_FILES['foto']['size'] > 4 * 1024 * 1024) {
      flash('warning', 'Archivo grande', 'Máximo 4MB.', false);
      header('Location: perfil.php');
      exit;
    }

    $name = 'u'.$uid.'_'.time().'.'.$ext;
    $dest = $dir . '/' . $name;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
      flash('error', 'Error', 'No se pudo subir la imagen.', false);
      header('Location: perfil.php');
      exit;
    }

    $avatarPath = 'files/avatars/' . $name;
  }

  $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, foto_perfil=? WHERE id_usuario=?");
  $stmt->bind_param("sssi", $nombre, $apellido, $avatarPath, $uid);
  $stmt->execute();

  // refresca sesión
  $_SESSION['user']['nombre'] = $nombre;
  $_SESSION['user']['apellido'] = $apellido;
  $_SESSION['user']['foto_perfil'] = $avatarPath;

  flash('success', 'Actualizado', 'Tu perfil fue actualizado.', true);
  header('Location: perfil.php');
  exit;
}

$photo = $u['foto_perfil'] ?? '';
?>
<div class="wrap">
  <div class="widget" style="cursor:default;">
    <div class="widget-title">Mi Perfil</div>

    <form method="POST" enctype="multipart/form-data" data-validate="swal"
          style="margin-top:14px; display:grid; gap:12px;">

      <div style="display:flex; gap:14px; align-items:center;">
        <?php if($photo): ?>
          <img class="avatar" src="<?= h($photo) ?>" style="width:60px;height:60px;" alt="">
        <?php else: ?>
          <span class="avatar-fallback" style="width:60px;height:60px;font-size:16px;">
            <?= h(mb_strtoupper(mb_substr((string)$u['nombre'],0,1).mb_substr((string)$u['apellido'],0,1))) ?>
          </span>
        <?php endif; ?>

        <div>
          <div style="font-weight:900;"><?= h($u['correo'] ?? '') ?></div>
          <div class="muted">Administra tu información</div>
        </div>
      </div>

      <input type="text" name="nombre" value="<?= h($u['nombre'] ?? '') ?>" required>
      <input type="text" name="apellido" value="<?= h($u['apellido'] ?? '') ?>" required>
      <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">

      <button type="submit"
              style="padding:12px;border-radius:14px;border:none;background:var(--core-primary);color:#fff;font-weight:900;cursor:pointer;">
        Guardar cambios
      </button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
