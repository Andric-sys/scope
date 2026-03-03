<?php
// /vistas_api.php (SOPORTE)
// API JSON CRUD para vistas + bloqueo delete si está referenciada (usuarios_vistas)

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/connection.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(bool $ok, array $extra = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

// Evita abrir el endpoint en navegador
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/plain; charset=utf-8');
  http_response_code(405);
  exit("API endpoint. Abre vistas.php (vista).");
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
if (!is_array($in)) json_out(false, ['msg'=>'JSON inválido.'], 400);

$action = (string)($in['action'] ?? '');
if ($action === '') json_out(false, ['msg'=>'Acción requerida.'], 400);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  if ($action === 'list') {
    $sql = "SELECT id_vista, path, clave, titulo, estatus, creado_en, actualizado_en
            FROM vistas
            ORDER BY id_vista DESC";
    $rs = $conn->query($sql);
    json_out(true, ['data'=>$rs->fetch_all(MYSQLI_ASSOC)]);
  }

  if ($action === 'create') {
    $path   = trim((string)($in['path'] ?? ''));
    $clave  = trim((string)($in['clave'] ?? ''));
    $titulo = trim((string)($in['titulo'] ?? ''));
    $estatus= trim((string)($in['estatus'] ?? 'activa'));

    if ($path==='' || $clave==='' || $titulo==='') json_out(false, ['msg'=>'Path, clave y título son obligatorios.'], 422);
    if (mb_strlen($path) > 200)   json_out(false, ['msg'=>'Path demasiado largo (máx 200).'], 422);
    if (mb_strlen($clave) > 80)   json_out(false, ['msg'=>'Clave demasiado larga (máx 80).'], 422);
    if (mb_strlen($titulo) > 120) json_out(false, ['msg'=>'Título demasiado largo (máx 120).'], 422);
    if (!in_array($estatus, ['activa','inactiva'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    // Unicidad por path
    $st = $conn->prepare("SELECT id_vista FROM vistas WHERE path=? LIMIT 1");
    $st->bind_param("s", $path);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe una vista con ese path.'], 409);

    // Unicidad por clave
    $st = $conn->prepare("SELECT id_vista FROM vistas WHERE clave=? LIMIT 1");
    $st->bind_param("s", $clave);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe una vista con esa clave.'], 409);

    $st = $conn->prepare("INSERT INTO vistas (path, clave, titulo, estatus) VALUES (?,?,?,?)");
    $st->bind_param("ssss", $path, $clave, $titulo, $estatus);
    $st->execute();

    json_out(true, ['msg'=>'Creada', 'id_vista'=>$conn->insert_id]);
  }

  if ($action === 'update') {
    $id     = (int)($in['id_vista'] ?? 0);
    $path   = trim((string)($in['path'] ?? ''));
    $clave  = trim((string)($in['clave'] ?? ''));
    $titulo = trim((string)($in['titulo'] ?? ''));
    $estatus= trim((string)($in['estatus'] ?? 'activa'));

    if ($id<=0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if ($path==='' || $clave==='' || $titulo==='') json_out(false, ['msg'=>'Path, clave y título son obligatorios.'], 422);
    if (mb_strlen($path) > 200)   json_out(false, ['msg'=>'Path demasiado largo (máx 200).'], 422);
    if (mb_strlen($clave) > 80)   json_out(false, ['msg'=>'Clave demasiado larga (máx 80).'], 422);
    if (mb_strlen($titulo) > 120) json_out(false, ['msg'=>'Título demasiado largo (máx 120).'], 422);
    if (!in_array($estatus, ['activa','inactiva'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    // Existe
    $st = $conn->prepare("SELECT id_vista FROM vistas WHERE id_vista=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'La vista no existe.'], 404);

    // Unicidad path (excepto mismo ID)
    $st = $conn->prepare("SELECT id_vista FROM vistas WHERE path=? AND id_vista<>? LIMIT 1");
    $st->bind_param("si", $path, $id);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe otra vista con ese path.'], 409);

    // Unicidad clave (excepto mismo ID)
    $st = $conn->prepare("SELECT id_vista FROM vistas WHERE clave=? AND id_vista<>? LIMIT 1");
    $st->bind_param("si", $clave, $id);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Ya existe otra vista con esa clave.'], 409);

    $st = $conn->prepare("UPDATE vistas SET path=?, clave=?, titulo=?, estatus=? WHERE id_vista=?");
    $st->bind_param("ssssi", $path, $clave, $titulo, $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Actualizada']);
  }

  if ($action === 'toggle') {
    $id = (int)($in['id_vista'] ?? 0);
    $estatus = trim((string)($in['estatus'] ?? ''));

    if ($id<=0) json_out(false, ['msg'=>'ID inválido.'], 422);
    if (!in_array($estatus, ['activa','inactiva'], true)) json_out(false, ['msg'=>'Estatus inválido.'], 422);

    $st = $conn->prepare("UPDATE vistas SET estatus=? WHERE id_vista=?");
    $st->bind_param("si", $estatus, $id);
    $st->execute();

    json_out(true, ['msg'=>'Estatus actualizado']);
  }

  if ($action === 'delete') {
    $id = (int)($in['id_vista'] ?? 0);
    if ($id<=0) json_out(false, ['msg'=>'ID inválido.'], 422);

    // Bloqueo si está en usuarios_vistas
    $st = $conn->prepare("SELECT COUNT(*) c FROM usuarios_vistas WHERE id_vista=?");
    $st->bind_param("i", $id);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    if ($c > 0) json_out(false, ['msg'=>"No se puede eliminar: está asignada en permisos ($c registro(s))."], 409);

    $st = $conn->prepare("DELETE FROM vistas WHERE id_vista=?");
    $st->bind_param("i", $id);
    $st->execute();

    json_out(true, ['msg'=>'Eliminada']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
