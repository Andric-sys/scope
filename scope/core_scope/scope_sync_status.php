<?php
declare(strict_types=1);

// Evita validación pesada de sesión activa para polling frecuente
define('CORE_SCOPE_SKIP_ACTIVE_SESSION_CHECK', true);

require __DIR__ . '/auth_guard.php';
require __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

function out_json(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $pdo = db();
  $cfg = require __DIR__ . '/config.php';
  $scopeCfg = $cfg['scope'] ?? [];

  $org = (string)($scopeCfg['organizationCode'] ?? '');
  $le  = (string)($scopeCfg['legalEntityCode'] ?? '');
  $br  = (string)($scopeCfg['branchCode'] ?? '');

  if ($org === '' || $le === '' || $br === '') {
    out_json(500, ['success' => false, 'message' => 'Configuración incompleta.']);
  }

  $sinceId = (int)($_GET['since_id'] ?? 0);
  if ($sinceId < 0) $sinceId = 0;

  $sql = "
    SELECT id, run_uuid, started_at, finished_at, updated_at,
           fetched_count, upserted_orders, upserted_milestones, upserted_references,
           upserted_transport_orders, upserted_jobcosting_entries,
           http_status, mensaje
    FROM scope_sync_runs
    WHERE organization_code = ?
      AND legal_entity_code = ?
      AND branch_code = ?
      AND id > ?
    ORDER BY id DESC
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$org, $le, $br, $sinceId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    out_json(200, [
      'success' => true,
      'has_run' => false,
      'since_id' => $sinceId,
      'server_time' => gmdate('Y-m-d H:i:s'),
    ]);
  }

  $isRunning = empty($row['finished_at']);
  $writeCount =
    (int)$row['upserted_orders'] +
    (int)$row['upserted_milestones'] +
    (int)$row['upserted_references'] +
    (int)$row['upserted_transport_orders'] +
    (int)$row['upserted_jobcosting_entries'];

  $secondsSinceUpdate = 0;
  if (!empty($row['updated_at'])) {
    $secondsSinceUpdate = max(0, time() - strtotime((string)$row['updated_at'] . ' UTC'));
  }

  out_json(200, [
    'success' => true,
    'has_run' => true,
    'run' => [
      'id' => (int)$row['id'],
      'run_uuid' => (string)$row['run_uuid'],
      'started_at' => (string)$row['started_at'],
      'finished_at' => $row['finished_at'] ? (string)$row['finished_at'] : null,
      'updated_at' => $row['updated_at'] ? (string)$row['updated_at'] : null,
      'is_running' => $isRunning,
      'is_waiting' => ($isRunning && $secondsSinceUpdate >= 8),
      'seconds_since_update' => $secondsSinceUpdate,
      'http_status' => $row['http_status'] !== null ? (int)$row['http_status'] : null,
      'message' => (string)($row['mensaje'] ?? ''),
      'fetched_count' => (int)$row['fetched_count'],
      'upserted_orders' => (int)$row['upserted_orders'],
      'upserted_milestones' => (int)$row['upserted_milestones'],
      'upserted_references' => (int)$row['upserted_references'],
      'upserted_transport_orders' => (int)$row['upserted_transport_orders'],
      'upserted_jobcosting_entries' => (int)$row['upserted_jobcosting_entries'],
      'write_count_total' => $writeCount,
    ],
    'server_time' => gmdate('Y-m-d H:i:s'),
  ]);

} catch (Throwable $e) {
  out_json(500, [
    'success' => false,
    'message' => 'No se pudo consultar el estado de sincronización.',
    'error' => $e->getMessage(),
  ]);
}
