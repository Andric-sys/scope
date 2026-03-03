<?php
// /sesiones_api.php (SOPORTE)
// API JSON: listar sesiones + revocar sesión (sesiones_usuarios)

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
  exit("API endpoint. Abre sesiones.php (vista).");
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
if (!is_array($in)) json_out(false, ['msg'=>'JSON inválido.'], 400);

$action = (string)($in['action'] ?? '');
if ($action === '') json_out(false, ['msg'=>'Acción requerida.'], 400);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

  if ($action === 'list') {
    // Nota: tu tabla sesiones_usuarios tiene id (PK autoincrement) y id_sesion único
    $sql = "
      SELECT
        s.id,
        s.id_usuario,
        s.id_sesion,
        s.huella_dispositivo,
        s.agente_usuario,
        s.ip,
        s.creado_en,
        s.visto_en,
        s.revocado_en,
        CONCAT(u.nombre,' ',u.apellido) AS usuario,
        u.correo
      FROM sesiones_usuarios s
      INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
      ORDER BY s.visto_en DESC, s.id DESC
      LIMIT 500
    ";
    $rs = $conn->query($sql);
    json_out(true, ['data'=>$rs->fetch_all(MYSQLI_ASSOC)]);
  }

  if ($action === 'revoke') {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) json_out(false, ['msg'=>'ID inválido.'], 422);

    $st = $conn->prepare("SELECT id, revocado_en FROM sesiones_usuarios WHERE id=? LIMIT 1");
    $st->bind_param("i", $id);
    $st->execute();
    $cur = $st->get_result()->fetch_assoc();
    if (!$cur) json_out(false, ['msg'=>'Sesión no existe.'], 404);
    if (!empty($cur['revocado_en'])) json_out(false, ['msg'=>'La sesión ya estaba revocada.'], 409);

    $st = $conn->prepare("UPDATE sesiones_usuarios SET revocado_en=NOW() WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();

    json_out(true, ['msg'=>'Sesión revocada']);
  }

  json_out(false, ['msg'=>'Acción no soportada.'], 400);

} catch (Throwable $e) {
  json_out(false, ['msg'=>'Error interno: '.$e->getMessage()], 500);
}
