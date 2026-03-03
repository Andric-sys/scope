<?php
// /cargos_api.php (SOPORTE)
// API JSON para CRUD de cargos

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

if (!is_array($in)) json_out(false, ['msg'=>'JSON inválido.'], 400);

$action = (string)($in['action'] ?? '');
if ($action === '') json_out(false, ['msg'=>'Acción requerida.'], 400);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  if ($action === 'list') {
    $sql = "SELECT id_cargo, nombre, descripcion, estatus, creado_en, actualizado_en
            FROM cargos
            ORDER BY id_cargo DESC";
    $rs = $conn->query($sql);
    json_out(true, ['data'=>$rs->fetch_all(MYSQLI_ASSOC)]);
  }

  if ($action === 'create') {
    $nombre = trim((string)($in['nombre'] ?? ''));
    $desc   = trim((string)($in['descripcion'] ?? ''));
    $estatus= trim((string)($in['estatus'] ?? 'activo'));

    if ($nombre === '') json_out(false, ['msg'=>'El nombre es obligatorio.'], 422);
    if (mb_strlen($nombre) > 150) json_out(false, ['msg'=>'Nombre demasiado largo (máx 150).'], 422);
    if (!in_array($estatus, ['activo','inactivo'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("SELECT id_cargo FROM cargos WHERE nombre=? LIMIT 1");
    $st->bind_param("s", $nombre);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe un cargo con ese nombre.'], 409);

    $st = $conn->prepare("INSERT INTO cargos (nombre, descripcion, estatus) VALUES (?,?,?)");
    $st->bind_param("sss", $nombre, $desc, $estatus);
    $st->execute();

    json_out(true, ['msg'=>'Creado', 'id_cargo'=>$conn->insert_id]);
  }

  if ($action === 'update') {
    $id     = (int)($in['id_cargo'] ?? 0);
    $nombre = trim((string)($in['nombre'] ?? ''));
    $desc   = trim((string)($in['descripcion'] ?? ''));
    $estatus= trim((string)($in['estatus'] ?? 'activo'));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if ($nombre === '') json_out(false, ['msg'=>'El nombre es obligatorio.'], 422);
    if (mb_strlen($nombre) > 150) json_out(false, ['msg'=>'Nombre demasiado largo (máx 150).'], 422);
    if (!in_array($estatus, ['activo','inactivo'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("SELECT id_cargo FROM cargos WHERE id_cargo=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'El cargo no existe.'], 404);

    $st = $conn->prepare("SELECT id_cargo FROM cargos WHERE nombre=? AND id_cargo<>? LIMIT 1");
    $st->bind_param("si", $nombre, $id);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe otro cargo con ese nombre.'], 409);

    $st = $conn->prepare("UPDATE cargos SET nombre=?, descripcion=?, estatus=? WHERE id_cargo=?");
    $st->bind_param("sssi", $nombre, $desc, $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Actualizado']);
  }

  if ($action === 'toggle') {
    $id = (int)($in['id_cargo'] ?? 0);
    $estatus = trim((string)($in['estatus'] ?? ''));

    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if (!in_array($estatus, ['activo','inactivo'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("UPDATE cargos SET estatus=? WHERE id_cargo=?");
    $st->bind_param("si", $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Estatus actualizado']);
  }

  if ($action === 'delete') {
    $id = (int)($in['id_cargo'] ?? 0);
    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);

    $st = $conn->prepare("SELECT COUNT(*) c FROM empleados WHERE id_cargo=?");
    $st->bind_param("i", $id);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);

    if ($c > 0) {
      json_out(false, ['msg'=>"No se puede eliminar: está asignado a $c empleado(s)."], 409);
    }

    $st = $conn->prepare("DELETE FROM cargos WHERE id_cargo=?");
    $st->bind_param("i", $id);
    $st->execute();

    json_out(true, ['msg'=>'Eliminado']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
