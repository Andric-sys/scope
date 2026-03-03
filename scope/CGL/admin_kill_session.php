<?php
// /ajax_online_sessions.php (SOPORTE)
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

$minutes = 5;

$stmt = $conn->prepare("
  SELECT
    u.id_usuario,
    u.nombre,
    u.apellido,
    u.foto_perfil,
    s.visto_en
  FROM sesiones_usuarios s
  JOIN usuarios u ON u.id_usuario = s.id_usuario
  WHERE s.revocado_en IS NULL
    AND s.visto_en >= (NOW() - INTERVAL ? MINUTE)
  ORDER BY s.visto_en DESC
  LIMIT 30
");
$stmt->bind_param("i", $minutes);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
