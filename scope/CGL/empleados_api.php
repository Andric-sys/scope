<?php
// /empleados_api.php (SOPORTE)
// API JSON para CRUD de empleados + meta (áreas/cargos)

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/connection.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(bool $ok, array $extra = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function to_null_int($v): ?int {
  $v = trim((string)$v);
  if ($v === '' || $v === '0') return null;
  $n = (int)$v;
  return $n > 0 ? $n : null;
}

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
  $name = 'emp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

  $destAbs = rtrim($uploadDirAbs, '/\\') . DIRECTORY_SEPARATOR . $name;
  if (!move_uploaded_file($tmp, $destAbs)) {
    json_out(false, ['msg'=>'No se pudo guardar la imagen.'], 500);
  }

  // ruta relativa para BD
  $destRel = rtrim($uploadDirRel, '/\\') . '/' . $name;
  return $destRel;
}

// Detecta si viene JSON o multipart/form-data
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

$in = read_input();
$action = (string)($in['action'] ?? '');
if ($action === '') json_out(false, ['msg'=>'Acción requerida.'], 400);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  if ($action === 'meta') {
    // Áreas activas + Cargos activos para selects
    $areas = [];
    $cargos = [];

    $rs = $conn->query("SELECT id_area, nombre FROM areas WHERE estatus='activa' ORDER BY nombre ASC");
    while($r = $rs->fetch_assoc()){
      $areas[] = ['id'=>(int)$r['id_area'], 'nombre'=>$r['nombre']];
    }

    $rs = $conn->query("SELECT id_cargo, nombre FROM cargos WHERE estatus='activo' ORDER BY nombre ASC");
    while($r = $rs->fetch_assoc()){
      $cargos[] = ['id'=>(int)$r['id_cargo'], 'nombre'=>$r['nombre']];
    }

    json_out(true, ['areas'=>$areas, 'cargos'=>$cargos]);
  }

  if ($action === 'list') {
    $sql = "
      SELECT
        e.id_empleado, e.no_empleado, e.nombre, e.apellido,
        e.id_area, a.nombre AS area_nombre,
        e.id_cargo, c.nombre AS cargo_nombre,
        e.telefono, e.correo, e.direccion,
        e.estatus, e.fecha_ingreso, e.fecha_salida,
        e.foto_perfil,
        e.creado_en, e.actualizado_en
      FROM empleados e
      LEFT JOIN areas  a ON a.id_area = e.id_area
      LEFT JOIN cargos c ON c.id_cargo = e.id_cargo
      ORDER BY e.id_empleado DESC
    ";
    $rs = $conn->query($sql);
    json_out(true, ['data'=>$rs->fetch_all(MYSQLI_ASSOC)]);
  }

  if ($action === 'create') {
    $uploadDirRel = 'uploads/empleados';
    $uploadDirAbs = __DIR__ . '/' . $uploadDirRel;

    $no_empleado = trim((string)($in['no_empleado'] ?? ''));
    $nombre      = trim((string)($in['nombre'] ?? ''));
    $apellido    = trim((string)($in['apellido'] ?? ''));

    $id_area     = to_null_int($in['id_area'] ?? '');
    $id_cargo    = to_null_int($in['id_cargo'] ?? '');

    $telefono    = trim((string)($in['telefono'] ?? ''));
    $correo      = trim((string)($in['correo'] ?? ''));
    $direccion   = trim((string)($in['direccion'] ?? ''));

    $estatus     = trim((string)($in['estatus'] ?? 'activo'));
    $fecha_ingreso = trim((string)($in['fecha_ingreso'] ?? ''));
    $fecha_salida  = trim((string)($in['fecha_salida'] ?? ''));

    if ($no_empleado === '' || $nombre === '' || $apellido === '') {
      json_out(false, ['msg'=>'No. empleado, nombre y apellido son obligatorios.'], 422);
    }
    if (mb_strlen($no_empleado) > 50) json_out(false, ['msg'=>'No. empleado demasiado largo (máx 50).'], 422);
    if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) json_out(false, ['msg'=>'Nombre/apellido demasiado largos.'], 422);
    if (!in_array($estatus, ['activo','baja'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
      json_out(false, ['msg'=>'Correo inválido.'], 422);
    }

    // Unicidad no_empleado
    $st = $conn->prepare("SELECT id_empleado FROM empleados WHERE no_empleado=? LIMIT 1");
    $st->bind_param("s", $no_empleado);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe un empleado con ese No. empleado.'], 409);

    // Manejar upload de foto
    $foto_perfil = handle_upload($_FILES['foto_file'] ?? null, $uploadDirAbs, $uploadDirRel);

    // (Opcional) si mandan fechas vacías, meter NULL
    $fi = ($fecha_ingreso !== '') ? $fecha_ingreso : null;
    $fs = ($fecha_salida  !== '') ? $fecha_salida  : null;

    $tel = ($telefono !== '') ? $telefono : null;
    $cor = ($correo !== '') ? $correo : null;
    $dir = ($direccion !== '') ? $direccion : null;

    $sql = "INSERT INTO empleados
      (no_empleado, nombre, apellido, id_area, id_cargo, telefono, correo, direccion, estatus, fecha_ingreso, fecha_salida, foto_perfil)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";

    $st = $conn->prepare($sql);
    $st->bind_param(
      "ssiissssssss",
      $no_empleado, $nombre, $apellido,
      $id_area, $id_cargo,
      $tel, $cor, $dir,
      $estatus,
      $fi, $fs,
      $foto_perfil
    );
    $st->execute();

    json_out(true, ['msg'=>'Creado', 'id_empleado'=>$conn->insert_id]);
  }

  if ($action === 'update') {
    $uploadDirRel = 'uploads/empleados';
    $uploadDirAbs = __DIR__ . '/' . $uploadDirRel;

    $id = (int)($in['id_empleado'] ?? 0);

    $no_empleado = trim((string)($in['no_empleado'] ?? ''));
    $nombre      = trim((string)($in['nombre'] ?? ''));
    $apellido    = trim((string)($in['apellido'] ?? ''));

    $id_area     = to_null_int($in['id_area'] ?? '');
    $id_cargo    = to_null_int($in['id_cargo'] ?? '');

    $telefono    = trim((string)($in['telefono'] ?? ''));
    $correo      = trim((string)($in['correo'] ?? ''));
    $direccion   = trim((string)($in['direccion'] ?? ''));

    $estatus     = trim((string)($in['estatus'] ?? 'activo'));
    $fecha_ingreso = trim((string)($in['fecha_ingreso'] ?? ''));
    $fecha_salida  = trim((string)($in['fecha_salida'] ?? ''));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if ($no_empleado === '' || $nombre === '' || $apellido === '') {
      json_out(false, ['msg'=>'No. empleado, nombre y apellido son obligatorios.'], 422);
    }
    if (mb_strlen($no_empleado) > 50) json_out(false, ['msg'=>'No. empleado demasiado largo (máx 50).'], 422);
    if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) json_out(false, ['msg'=>'Nombre/apellido demasiado largos.'], 422);
    if (!in_array($estatus, ['activo','baja'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
      json_out(false, ['msg'=>'Correo inválido.'], 422);
    }

    $st = $conn->prepare("SELECT id_empleado, foto_perfil FROM empleados WHERE id_empleado=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) json_out(false, ['msg'=>'El empleado no existe.'], 404);
    $fotoOld = (string)($row['foto_perfil'] ?? '');

    // Unicidad no_empleado excepto mismo ID
    $st = $conn->prepare("SELECT id_empleado FROM empleados WHERE no_empleado=? AND id_empleado<>? LIMIT 1");
    $st->bind_param("si", $no_empleado, $id);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe otro empleado con ese No. empleado.'], 409);

    // Manejar upload de foto; si no viene, conservar old
    $fotoNew = handle_upload($_FILES['foto_file'] ?? null, $uploadDirAbs, $uploadDirRel);
    $foto_perfil = $fotoNew !== null ? $fotoNew : (($fotoOld !== '') ? $fotoOld : null);

    // Si subió nueva foto, opcional: borrar la anterior
    if ($fotoNew !== null && $fotoOld !== '') {
      $oldRel = str_replace('\\','/', $fotoOld);
      if (str_starts_with($oldRel, $uploadDirRel.'/')) {
        $oldAbs = __DIR__ . '/' . $oldRel;
        if (is_file($oldAbs)) @unlink($oldAbs);
      }
    }

    $fi = ($fecha_ingreso !== '') ? $fecha_ingreso : null;
    $fs = ($fecha_salida  !== '') ? $fecha_salida  : null;

    $tel = ($telefono !== '') ? $telefono : null;
    $cor = ($correo !== '') ? $correo : null;
    $dir = ($direccion !== '') ? $direccion : null;

    $sql = "UPDATE empleados SET
      no_empleado=?, nombre=?, apellido=?,
      id_area=?, id_cargo=?,
      telefono=?, correo=?, direccion=?,
      estatus=?,
      fecha_ingreso=?, fecha_salida=?,
      foto_perfil=?
      WHERE id_empleado=?";

    $st = $conn->prepare($sql);
    $st->bind_param(
      "ssiissssssssi",
      $no_empleado, $nombre, $apellido,
      $id_area, $id_cargo,
      $tel, $cor, $dir,
      $estatus,
      $fi, $fs,
      $foto_perfil,
      $id
    );
    $st->execute();

    json_out(true, ['msg'=>'Actualizado']);
  }

  if ($action === 'toggle') {
    $id = (int)($in['id_empleado'] ?? 0);
    $estatus = trim((string)($in['estatus'] ?? ''));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if (!in_array($estatus, ['activo','baja'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("UPDATE empleados SET estatus=? WHERE id_empleado=?");
    $st->bind_param("si", $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Estatus actualizado']);
  }

  if ($action === 'delete') {
    $id = (int)($in['id_empleado'] ?? 0);
    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);

    // Bloqueo si está referenciado por usuarios
    $st = $conn->prepare("SELECT COUNT(*) c FROM usuarios WHERE id_empleado=?");
    $st->bind_param("i", $id);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);

    if ($c > 0) {
      json_out(false, ['msg'=>"No se puede eliminar: está ligado a $c usuario(s)."], 409);
    }

    $st = $conn->prepare("DELETE FROM empleados WHERE id_empleado=?");
    $st->bind_param("i", $id);
    $st->execute();

    json_out(true, ['msg'=>'Eliminado']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
