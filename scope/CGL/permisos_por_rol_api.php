<?php
// /permisos_por_rol_api.php (SOPORTE)
// API JSON para matriz de permisos (usuarios_vistas)

declare(strict_types=1);

require __DIR__ . '/auth.php';
require __DIR__ . '/connection.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(bool $ok, array $extra = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/plain; charset=utf-8');
  http_response_code(405);
  exit("API endpoint. Abre permisos_por_rol.php (vista).");
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
if (!is_array($in)) json_out(false, ['msg'=>'JSON inválido.'], 400);

$action = (string)($in['action'] ?? '');
if ($action === '') json_out(false, ['msg'=>'Acción requerida.'], 400);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  if ($action === 'meta') {
    $roles = [];
    $vistas = [];

    $rs = $conn->query("SELECT id_rol, nombre, estatus FROM roles ORDER BY nombre ASC");
    while($r = $rs->fetch_assoc()){
      $roles[] = ['id_rol'=>(int)$r['id_rol'], 'nombre'=>$r['nombre'], 'estatus'=>$r['estatus']];
    }

    // Puedes decidir si filtras solo activas; aquí muestro TODAS para admin
    $rs = $conn->query("SELECT id_vista, path, clave, titulo, estatus FROM vistas ORDER BY titulo ASC");
    while($r = $rs->fetch_assoc()){
      $vistas[] = [
        'id_vista'=>(int)$r['id_vista'],
        'path'=>$r['path'],
        'clave'=>$r['clave'],
        'titulo'=>$r['titulo'],
        'estatus'=>$r['estatus']
      ];
    }

    json_out(true, ['roles'=>$roles, 'vistas'=>$vistas]);
  }

  if ($action === 'get') {
    $id_rol = (int)($in['id_rol'] ?? 0);
    if ($id_rol <= 0) json_out(false, ['msg'=>'ID de rol inválido.'], 422);

    // Existe rol
    $st = $conn->prepare("SELECT id_rol FROM roles WHERE id_rol=? LIMIT 1");
    $st->bind_param("i", $id_rol);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Rol no existe.'], 404);

    $allowed = [];
    $st = $conn->prepare("SELECT id_vista FROM usuarios_vistas WHERE id_rol=? AND permitido=1");
    $st->bind_param("i", $id_rol);
    $st->execute();
    $rs = $st->get_result();
    while($r = $rs->fetch_assoc()){
      $allowed[] = (int)$r['id_vista'];
    }

    json_out(true, ['allowed'=>$allowed]);
  }

  if ($action === 'save') {
    $id_rol = (int)($in['id_rol'] ?? 0);
    $permisos = $in['permisos'] ?? null;

    if ($id_rol <= 0) json_out(false, ['msg'=>'ID de rol inválido.'], 422);
    if (!is_array($permisos)) json_out(false, ['msg'=>'Permisos inválidos.'], 422);

    // Existe rol
    $st = $conn->prepare("SELECT id_rol FROM roles WHERE id_rol=? LIMIT 1");
    $st->bind_param("i", $id_rol);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) json_out(false, ['msg'=>'Rol no existe.'], 404);

    $conn->begin_transaction();

    // Estrategia: upsert por cada vista
    $stUp = $conn->prepare("
      INSERT INTO usuarios_vistas (id_rol, id_vista, permitido)
      VALUES (?,?,?)
      ON DUPLICATE KEY UPDATE permitido=VALUES(permitido)
    ");

    foreach($permisos as $p){
      $id_vista = (int)($p['id_vista'] ?? 0);
      $permitido = (int)($p['permitido'] ?? 0);
      if ($id_vista <= 0) continue;
      $permitido = ($permitido === 1) ? 1 : 0;

      $stUp->bind_param("iii", $id_rol, $id_vista, $permitido);
      $stUp->execute();
    }

    $conn->commit();
    json_out(true, ['msg'=>'Permisos actualizados']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  if ($conn && $conn->errno === 0) {
    // no-op
  }
  if ($conn && $conn->affected_rows >= 0) {
    // no-op
  }
  // rollback si aplica
  try { if ($conn) $conn->rollback(); } catch(Throwable $x) {}
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
