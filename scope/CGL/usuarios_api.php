<?php
// /usuarios_api.php (SOPORTE)
// API para CRUD de usuarios + meta (roles/empleados/áreas)
// - JSON: meta/list/toggle/delete
// - FormData (multipart): create/update (permite subir foto_file)

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/connection.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(bool $ok, array $extra = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function to_null_int($v): ?int {
  $v = trim((string)$v);
  if ($v === '' || $v === '0') return null;
  $n = (int)$v;
  return $n > 0 ? $n : null;
}
function to_null_str($v): ?string {
  $v = trim((string)$v);
  return ($v === '') ? null : $v;
}

/**
 * Detecta si viene JSON o multipart/form-data
 */
function read_input(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
  $ct = strtolower((string)$ct);

  if (str_contains($ct, 'application/json')) {
    $raw = file_get_contents('php://input');
    $in  = json_decode($raw ?: '[]', true);
    if (!is_array($in)) json_out(false, ['msg'=>'JSON inválido.'], 400);
    return $in;
  }

  // multipart o form normal
  return $_POST ?: [];
}

/**
 * Sube y valida imagen. Devuelve ruta relativa (string) o null si no se subió.
 */
function handle_upload(?array $file, string $uploadDirAbs, string $uploadDirRel): ?string {
  if (!$file || !isset($file['error'])) return null;
  if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    json_out(false, ['msg'=>'Error al subir archivo (código '.$file['error'].').'], 422);
  }

  $maxBytes = 2 * 1024 * 1024; // 2MB
  if ((int)$file['size'] > $maxBytes) json_out(false, ['msg'=>'La imagen excede 2MB.'], 422);

  $tmp = (string)$file['tmp_name'];
  if (!is_uploaded_file($tmp)) json_out(false, ['msg'=>'Carga inválida.'], 422);

  // MIME real
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($tmp) ?: '';

  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($allowed[$mime])) json_out(false, ['msg'=>'Formato no permitido. Solo PNG/JPG/WEBP.'], 422);

  // extra sanity (opcional)
  if (!@getimagesize($tmp)) json_out(false, ['msg'=>'Archivo no parece imagen válida.'], 422);

  // asegurar carpeta
  if (!is_dir($uploadDirAbs)) {
    if (!mkdir($uploadDirAbs, 0775, true)) {
      json_out(false, ['msg'=>'No se pudo crear directorio de carga.'], 500);
    }
  }

  $ext = $allowed[$mime];
  $name = 'usr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

  $destAbs = rtrim($uploadDirAbs, '/\\') . DIRECTORY_SEPARATOR . $name;
  if (!move_uploaded_file($tmp, $destAbs)) {
    json_out(false, ['msg'=>'No se pudo guardar la imagen.'], 500);
  }

  // ruta relativa para BD
  $destRel = rtrim($uploadDirRel, '/\\') . '/' . $name;
  return $destRel;
}

try {
  $in = read_input();

  $action = trim((string)($in['action'] ?? ''));
  if ($action === '') json_out(false, ['msg'=>'Acción requerida.'], 400);

  // Ajusta estas rutas si quieres
  $uploadDirRel = 'uploads/usuarios'; // relativo al webroot
  $uploadDirAbs = __DIR__ . '/' . $uploadDirRel;

  if ($action === 'meta') {
    $roles = [];
    $empleados = [];
    $areas = [];

    $rs = $conn->query("SELECT id_rol, nombre FROM roles WHERE estatus='activo' ORDER BY nombre ASC");
    while($r = $rs->fetch_assoc()){
      $roles[] = ['id'=>(int)$r['id_rol'], 'nombre'=>$r['nombre']];
    }

    $rs = $conn->query("SELECT id_empleado, no_empleado, nombre, apellido FROM empleados WHERE estatus='activo' ORDER BY nombre ASC, apellido ASC");
    while($r = $rs->fetch_assoc()){
      $nm = trim(($r['no_empleado'] ?? '').' · '.($r['nombre'] ?? '').' '.($r['apellido'] ?? ''));
      $empleados[] = ['id'=>(int)$r['id_empleado'], 'nombre'=>$nm];
    }

    // Filtrar áreas según el rol si viene especificado
    $id_rol_filter = (int)($in['id_rol'] ?? 0);
    if ($id_rol_filter > 0) {
      // Traer solo áreas permitidas para ese rol
      $rs = $conn->query("
        SELECT a.id_area, a.nombre 
        FROM areas a
        INNER JOIN areas_por_rol apr ON apr.id_area = a.id_area
        WHERE a.estatus='activa' 
          AND apr.id_rol={$id_rol_filter} 
          AND apr.permitido=1
        ORDER BY a.nombre ASC
      ");
    } else {
      // Traer todas las áreas activas (para admin o sin filtro)
      $rs = $conn->query("SELECT id_area, nombre FROM areas WHERE estatus='activa' ORDER BY nombre ASC");
    }
    
    while($r = $rs->fetch_assoc()){
      $areas[] = ['id'=>(int)$r['id_area'], 'nombre'=>$r['nombre']];
    }

    json_out(true, ['roles'=>$roles, 'empleados'=>$empleados, 'areas'=>$areas]);
  }

  if ($action === 'list') {
    $sql = "
      SELECT
        u.id_usuario, u.nombre, u.apellido, u.correo, u.num_telefono,
        u.id_rol, r.nombre AS rol_nombre,
        u.id_empleado,
        u.id_area, a.nombre AS area_nombre,
        u.foto_perfil,
        u.estatus,
        u.creado_en, u.actualizado_en
      FROM usuarios u
      INNER JOIN roles r ON r.id_rol = u.id_rol
      LEFT JOIN areas a ON a.id_area = u.id_area
      ORDER BY u.id_usuario DESC
    ";
    $rs = $conn->query($sql);
    json_out(true, ['data'=>$rs->fetch_all(MYSQLI_ASSOC)]);
  }

  if ($action === 'create') {
    // multipart/FormData esperado
    $nombre   = trim((string)($in['nombre'] ?? ''));
    $apellido = trim((string)($in['apellido'] ?? ''));
    $correo   = trim((string)($in['correo'] ?? ''));
    $tel      = to_null_str($in['num_telefono'] ?? '');
    $password = trim((string)($in['password'] ?? ''));

    $id_rol      = (int)($in['id_rol'] ?? 0);
    $id_empleado = to_null_int($in['id_empleado'] ?? '');
    $id_area     = to_null_int($in['id_area'] ?? '');

    $estatus = trim((string)($in['estatus'] ?? 'activo'));
    if ($estatus === '') $estatus = 'activo';

    if ($nombre === '' || $apellido === '' || $correo === '' || $password === '') {
      json_out(false, ['msg'=>'Nombre, apellido, correo y password son obligatorios.'], 422);
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) json_out(false, ['msg'=>'Correo inválido.'], 422);
    if ($id_rol <= 0) json_out(false, ['msg'=>'Rol inválido.'], 422);
    if (!in_array($estatus, ['activo','inactivo'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    // Correo único
    $st = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo=? LIMIT 1");
    $st->bind_param("s", $correo);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe un usuario con ese correo.'], 409);

    // Rol existe
    $st = $conn->prepare("SELECT id_rol FROM roles WHERE id_rol=? LIMIT 1");
    $st->bind_param("i", $id_rol);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Rol no existe.'], 422);

    if ($id_empleado !== null) {
      $st = $conn->prepare("SELECT id_empleado FROM empleados WHERE id_empleado=? LIMIT 1");
      $st->bind_param("i", $id_empleado);
      $st->execute();
      if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Empleado no existe.'], 422);
    }

    if ($id_area !== null) {
      $st = $conn->prepare("SELECT id_area FROM areas WHERE id_area=? LIMIT 1");
      $st->bind_param("i", $id_area);
      $st->execute();
      if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Área no existe.'], 422);
    }

    // Subir foto si viene
    $foto = handle_upload($_FILES['foto_file'] ?? null, $uploadDirAbs, $uploadDirRel);

    $sql = "INSERT INTO usuarios
      (nombre, apellido, correo, num_telefono, password, id_rol, id_empleado, id_area, foto_perfil, estatus)
      VALUES (?,?,?,?,?,?,?,?,?,?)";
    $st = $conn->prepare($sql);

    $types = "sssssiiiss";
    $st->bind_param($types,
      $nombre, $apellido, $correo, $tel, $password,
      $id_rol, $id_empleado, $id_area,
      $foto, $estatus
    );
    $st->execute();

    json_out(true, ['msg'=>'Creado', 'id_usuario'=>$conn->insert_id]);
  }

  if ($action === 'update') {
    $id = (int)($in['id_usuario'] ?? 0);

    $nombre   = trim((string)($in['nombre'] ?? ''));
    $apellido = trim((string)($in['apellido'] ?? ''));
    $correo   = trim((string)($in['correo'] ?? ''));
    $tel      = to_null_str($in['num_telefono'] ?? '');
    $password = trim((string)($in['password'] ?? '')); // puede venir vacío

    $id_rol      = (int)($in['id_rol'] ?? 0);
    $id_empleado = to_null_int($in['id_empleado'] ?? '');
    $id_area     = to_null_int($in['id_area'] ?? '');

    $estatus = trim((string)($in['estatus'] ?? 'activo'));
    if ($estatus === '') $estatus = 'activo';

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if ($nombre === '' || $apellido === '' || $correo === '') {
      json_out(false, ['msg'=>'Nombre, apellido y correo son obligatorios.'], 422);
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) json_out(false, ['msg'=>'Correo inválido.'], 422);
    if ($id_rol <= 0) json_out(false, ['msg'=>'Rol inválido.'], 422);
    if (!in_array($estatus, ['activo','inactivo'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    // Existe + foto actual real desde BD (no confíes en hidden)
    $st = $conn->prepare("SELECT id_usuario, foto_perfil FROM usuarios WHERE id_usuario=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) json_out(false, ['msg'=>'El usuario no existe.'], 404);
    $fotoOld = (string)($row['foto_perfil'] ?? '');

    // Correo único excepto mismo ID
    $st = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo=? AND id_usuario<>? LIMIT 1");
    $st->bind_param("si", $correo, $id);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe otro usuario con ese correo.'], 409);

    // Validación opcional de empleado/área
    if ($id_empleado !== null) {
      $st = $conn->prepare("SELECT id_empleado FROM empleados WHERE id_empleado=? LIMIT 1");
      $st->bind_param("i", $id_empleado);
      $st->execute();
      if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Empleado no existe.'], 422);
    }
    if ($id_area !== null) {
      $st = $conn->prepare("SELECT id_area FROM areas WHERE id_area=? LIMIT 1");
      $st->bind_param("i", $id_area);
      $st->execute();
      if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Área no existe.'], 422);
    }

    // Subir foto si viene; si no viene, conservar old
    $fotoNew = handle_upload($_FILES['foto_file'] ?? null, $uploadDirAbs, $uploadDirRel);
    $fotoFinal = $fotoNew !== null ? $fotoNew : (to_null_str($fotoOld) ?: null);

    // Si subió nueva foto, opcional: borrar la anterior (si está dentro de uploads/usuarios)
    if ($fotoNew !== null && $fotoOld !== '') {
      $oldRel = str_replace('\\','/', $fotoOld);
      if (str_starts_with($oldRel, $uploadDirRel.'/')) {
        $oldAbs = __DIR__ . '/' . $oldRel;
        if (is_file($oldAbs)) @unlink($oldAbs);
      }
    }

    if ($password !== '') {
      $sql = "UPDATE usuarios SET
        nombre=?, apellido=?, correo=?, num_telefono=?,
        password=?,
        id_rol=?, id_empleado=?, id_area=?,
        foto_perfil=?, estatus=?
        WHERE id_usuario=?";
      $st = $conn->prepare($sql);
      $types = "sssssiiissi";

      $st->bind_param($types,
        $nombre, $apellido, $correo, $tel,
        $password,
        $id_rol, $id_empleado, $id_area,
        $fotoFinal, $estatus,
        $id
      );
      $st->execute();
    } else {
      $sql = "UPDATE usuarios SET
        nombre=?, apellido=?, correo=?, num_telefono=?,
        id_rol=?, id_empleado=?, id_area=?,
        foto_perfil=?, estatus=?
        WHERE id_usuario=?";
      $st = $conn->prepare($sql);
      $types = "ssssiiissi";

      $st->bind_param($types,
        $nombre, $apellido, $correo, $tel,
        $id_rol, $id_empleado, $id_area,
        $fotoFinal, $estatus,
        $id
      );
      $st->execute();
    }

    json_out(true, ['msg'=>'Actualizado']);
  }

  if ($action === 'toggle') {
    $id = (int)($in['id_usuario'] ?? 0);
    $estatus = trim((string)($in['estatus'] ?? ''));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if (!in_array($estatus, ['activo','inactivo'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("UPDATE usuarios SET estatus=? WHERE id_usuario=?");
    $st->bind_param("si", $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Estatus actualizado']);
  }

  if ($action === 'delete') {
    $id = (int)($in['id_usuario'] ?? 0);
    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);

    // si quieres borrar foto del disco, primero léela
    $st = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $fotoOld = (string)($r['foto_perfil'] ?? '');

    $st = $conn->prepare("DELETE FROM usuarios WHERE id_usuario=?");
    $st->bind_param("i", $id);
    $st->execute();

    // borrar archivo (si está en uploads/usuarios)
    if ($fotoOld !== '') {
      $oldRel = str_replace('\\','/', $fotoOld);
      if (str_starts_with($oldRel, $uploadDirRel.'/')) {
        $oldAbs = __DIR__ . '/' . $oldRel;
        if (is_file($oldAbs)) @unlink($oldAbs);
      }
    }

    json_out(true, ['msg'=>'Eliminado']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
