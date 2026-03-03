<?php
// /roles_api.php (SOPORTE)
// API JSON para CRUD de roles (tabla roles)
// Acciones:
// - GET  ?action=list
// - POST action=create|update|toggle|delete
declare(strict_types=1);

require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

function out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
  $sql = "SELECT id_rol, nombre, descripcion, estatus, DATE_FORMAT(actualizado_en,'%Y-%m-%d %H:%i') AS actualizado_en
          FROM roles
          ORDER BY id_rol DESC";
  $rs = $conn->query($sql);
  if (!$rs) out(false, ['msg'=>'Error al consultar roles.']);

  $rows = [];
  while ($r = $rs->fetch_assoc()) $rows[] = $r;
  out(true, ['rows'=>$rows]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  out(false, ['msg'=>'Método no permitido.']);
}

// Helpers
$trim = fn($v) => trim((string)$v);

if ($action === 'create') {
  $nombre = $trim($_POST['nombre'] ?? '');
  $desc   = $trim($_POST['descripcion'] ?? '');
  $estatus = $trim($_POST['estatus'] ?? 'activo');

  if ($nombre === '') out(false, ['msg'=>'El nombre es obligatorio.']);
  if (!in_array($estatus, ['activo','inactivo'], true)) out(false, ['msg'=>'Estatus inválido.']);

  // Unicidad por uq_roles_nombre
  $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion, estatus) VALUES (?,?,?)");
  $stmt->bind_param("sss", $nombre, $desc, $estatus);

  if (!$stmt->execute()) {
    // 1062 duplicado
    $err = (int)($conn->errno ?? 0);
    if ($err === 1062) out(false, ['msg'=>'Ya existe un rol con ese nombre.']);
    out(false, ['msg'=>'No se pudo crear el rol.']);
  }
  out(true, ['id_rol'=>$conn->insert_id]);
}

if ($action === 'update') {
  $id     = (int)($_POST['id_rol'] ?? 0);
  $nombre = $trim($_POST['nombre'] ?? '');
  $desc   = $trim($_POST['descripcion'] ?? '');
  $estatus = $trim($_POST['estatus'] ?? 'activo');

  if ($id <= 0) out(false, ['msg'=>'ID inválido.']);
  if ($nombre === '') out(false, ['msg'=>'El nombre es obligatorio.']);
  if (!in_array($estatus, ['activo','inactivo'], true)) out(false, ['msg'=>'Estatus inválido.']);

  $stmt = $conn->prepare("UPDATE roles SET nombre=?, descripcion=?, estatus=? WHERE id_rol=?");
  $stmt->bind_param("sssi", $nombre, $desc, $estatus, $id);

  if (!$stmt->execute()) {
    $err = (int)($conn->errno ?? 0);
    if ($err === 1062) out(false, ['msg'=>'Ya existe un rol con ese nombre.']);
    out(false, ['msg'=>'No se pudo actualizar el rol.']);
  }
  out(true);
}

if ($action === 'toggle') {
  $id = (int)($_POST['id_rol'] ?? 0);
  if ($id <= 0) out(false, ['msg'=>'ID inválido.']);

  $rs = $conn->prepare("SELECT estatus FROM roles WHERE id_rol=? LIMIT 1");
  $rs->bind_param("i", $id);
  $rs->execute();
  $row = $rs->get_result()->fetch_assoc();
  if (!$row) out(false, ['msg'=>'Rol no encontrado.']);

  $cur = (string)$row['estatus'];
  $next = ($cur === 'activo') ? 'inactivo' : 'activo';

  $up = $conn->prepare("UPDATE roles SET estatus=? WHERE id_rol=?");
  $up->bind_param("si", $next, $id);
  if (!$up->execute()) out(false, ['msg'=>'No se pudo cambiar el estatus.']);

  out(true, ['estatus'=>$next]);
}

if ($action === 'delete') {
  $id = (int)($_POST['id_rol'] ?? 0);
  if ($id <= 0) out(false, ['msg'=>'ID inválido.']);

  // OJO: puede fallar por FK (usuarios.id_rol) ON DELETE RESTRICT
  $del = $conn->prepare("DELETE FROM roles WHERE id_rol=?");
  $del->bind_param("i", $id);

  if (!$del->execute()) {
    // 1451: Cannot delete or update a parent row: a foreign key constraint fails
    $err = (int)($conn->errno ?? 0);
    if ($err === 1451) out(false, ['msg'=>'No se puede eliminar: el rol está asignado a usuarios.']);
    out(false, ['msg'=>'No se pudo eliminar el rol.']);
  }
  out(true);
}

out(false, ['msg'=>'Acción inválida.']);
