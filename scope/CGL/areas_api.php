<?php
// /areas_api.php (SOPORTE)
// API JSON para CRUD de áreas

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/connection.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(bool $ok, array $extra = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);

if (!is_array($in)) {
  json_out(false, ['msg'=>'JSON inválido.'], 400);
}

$action = (string)($in['action'] ?? '');

if ($action === '') {
  json_out(false, ['msg'=>'Acción requerida.'], 400);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  if ($action === 'list') {
    $sql = "SELECT id_area, nombre, descripcion, estatus, creado_en, actualizado_en
            FROM areas
            ORDER BY id_area DESC";
    $rs = $conn->query($sql);
    $data = $rs->fetch_all(MYSQLI_ASSOC);
    json_out(true, ['data'=>$data]);
  }

  if ($action === 'create') {
    $nombre = trim((string)($in['nombre'] ?? ''));
    $desc   = trim((string)($in['descripcion'] ?? ''));
    $estatus= trim((string)($in['estatus'] ?? 'activa'));

    if ($nombre === '') json_out(false, ['msg'=>'El nombre es obligatorio.'], 422);
    if (mb_strlen($nombre) > 150) json_out(false, ['msg'=>'Nombre demasiado largo (máx 150).'], 422);
    if (!in_array($estatus, ['activa','inactiva'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    // Unicidad por nombre
    $st = $conn->prepare("SELECT id_area FROM areas WHERE nombre=? LIMIT 1");
    $st->bind_param("s", $nombre);
    $st->execute();
    $ex = $st->get_result()->fetch_assoc();
    if ($ex) json_out(false, ['msg'=>'Ya existe un área con ese nombre.'], 409);

    $st = $conn->prepare("INSERT INTO areas (nombre, descripcion, estatus) VALUES (?,?,?)");
    $st->bind_param("sss", $nombre, $desc, $estatus);
    $st->execute();

    json_out(true, ['msg'=>'Creada', 'id_area'=>$conn->insert_id]);
  }

  if ($action === 'update') {
    $id     = (int)($in['id_area'] ?? 0);
    $nombre = trim((string)($in['nombre'] ?? ''));
    $desc   = trim((string)($in['descripcion'] ?? ''));
    $estatus= trim((string)($in['estatus'] ?? 'activa'));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if ($nombre === '') json_out(false, ['msg'=>'El nombre es obligatorio.'], 422);
    if (mb_strlen($nombre) > 150) json_out(false, ['msg'=>'Nombre demasiado largo (máx 150).'], 422);
    if (!in_array($estatus, ['activa','inactiva'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    // Existe
    $st = $conn->prepare("SELECT id_area FROM areas WHERE id_area=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    $cur = $st->get_result()->fetch_assoc();
    if (!$cur) json_out(false, ['msg'=>'El área no existe.'], 404);

    // Unicidad por nombre (excepto mismo ID)
    $st = $conn->prepare("SELECT id_area FROM areas WHERE nombre=? AND id_area<>? LIMIT 1");
    $st->bind_param("si", $nombre, $id);
    $st->execute();
    $ex = $st->get_result()->fetch_assoc();
    if ($ex) json_out(false, ['msg'=>'Ya existe otra área con ese nombre.'], 409);

    $st = $conn->prepare("UPDATE areas SET nombre=?, descripcion=?, estatus=? WHERE id_area=?");
    $st->bind_param("sssi", $nombre, $desc, $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Actualizada']);
  }

  if ($action === 'toggle') {
    $id = (int)($in['id_area'] ?? 0);
    $estatus = trim((string)($in['estatus'] ?? ''));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if (!in_array($estatus, ['activa','inactiva'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("UPDATE areas SET estatus=? WHERE id_area=?");
    $st->bind_param("si", $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Estatus actualizado']);
  }

  if ($action === 'delete') {
    $id = (int)($in['id_area'] ?? 0);
    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);

    // Bloqueo si está en uso (usuarios/empleados)
    $st = $conn->prepare("SELECT COUNT(*) c FROM usuarios WHERE id_area=?");
    $st->bind_param("i", $id);
    $st->execute();
    $c1 = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);

    $st = $conn->prepare("SELECT COUNT(*) c FROM empleados WHERE id_area=?");
    $st->bind_param("i", $id);
    $st->execute();
    $c2 = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);

    if (($c1 + $c2) > 0) {
      json_out(false, ['msg'=>"No se puede eliminar: está asignada a $c1 usuario(s) y $c2 empleado(s)."], 409);
    }

    $st = $conn->prepare("DELETE FROM areas WHERE id_area=?");
    $st->bind_param("i", $id);
    $st->execute();

    json_out(true, ['msg'=>'Eliminada']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
