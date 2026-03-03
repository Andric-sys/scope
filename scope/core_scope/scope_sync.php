<?php
declare(strict_types=1);

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});

while (ob_get_level() > 0) { ob_end_clean(); }
ob_start();

require __DIR__ . '/conexion.php';
require __DIR__ . '/scope_api.php';
require __DIR__ . '/scope_upsert.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');
ini_set('max_execution_time', '300');
set_time_limit(300);

function json_out(int $code, array $payload): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function extract_orders_any($data): array {
  if (!is_array($data)) return [];
  $best = [];
  $walk = function($node) use (&$walk, &$best) {
    if (!is_array($node)) return;
    $isIndexed = array_keys($node) === range(0, count($node)-1);
    if ($isIndexed && count($node)>0 && is_array($node[0])) {
      $hits=0;
      foreach($node as $x){
        if (is_array($x) && isset($x['identifier']) && is_string($x['identifier']) && trim($x['identifier'])!=='') $hits++;
      }
      if ($hits>0 && count($node)>count($best)) $best=$node;
    }
    foreach($node as $v) $walk($v);
  };
  $walk($data);
  return $best;
}

$runUuid = sprintf(
  '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
  random_int(0, 0xffff), random_int(0, 0xffff),
  random_int(0, 0xffff),
  random_int(0, 0x0fff) | 0x4000,
  random_int(0, 0x3fff) | 0x8000,
  random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);

$startedAtUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$cfg = require __DIR__ . '/config.php';
$scopeCfg = $cfg['scope'] ?? [];
$pdo = db();

$org = (string)($scopeCfg['organizationCode'] ?? '');
$le  = (string)($scopeCfg['legalEntityCode'] ?? '');
$br  = (string)($scopeCfg['branchCode'] ?? '');

if ($org==='' || $le==='' || $br==='') {
  json_out(500, ['success'=>false,'message'=>'Configuración incompleta.']);
}

$pageSize = max(10, min(200, (int)($_GET['size'] ?? 100)));
$maxPages = max(1, min(500, (int)($_GET['max_pages'] ?? 5)));
$mode = (string)($_GET['mode'] ?? 'incremental');
if (!in_array($mode, ['backfill','incremental'], true)) $mode='incremental';

$cursorBackMinutes = 10;
$daysBack = (int)($_GET['days'] ?? 3650);
if ($daysBack < 7) $daysBack = 7;
if ($daysBack > 3650) $daysBack = 3650;

$throttleMs = (int)($_GET['throttle_ms'] ?? 120);
if ($throttleMs < 0) $throttleMs = 0;
if ($throttleMs > 2000) $throttleMs = 2000;

// ✅ NUEVO: reintentos por página (list)
$pageRetries = (int)($_GET['page_retries'] ?? 4); // 4 => hasta 5 intentos (1 + 4)
if ($pageRetries < 0) $pageRetries = 0;
if ($pageRetries > 8) $pageRetries = 8;

$lockName = 'core_scope_sync_' . $org . '_' . $le . '_' . $br;

// Progreso best-effort en scope_sync_runs
$runUpdate = function(string $mensaje, int $fetched, int $upOrders, int $upJC) use ($pdo, $runUuid) {
  try{
    $st = $pdo->prepare("
      UPDATE scope_sync_runs
      SET fetched_count=?, upserted_orders=?, upserted_jobcosting_entries=?, mensaje=?
      WHERE run_uuid=?
    ");
    $st->execute([$fetched,$upOrders,$upJC,mb_substr($mensaje,0,240),$runUuid]);
  }catch(Throwable $ignored){}
};

// Helper: intenta listar una página con retries; si falla, devuelve null y error
$listPage = function(array $params) use ($pageRetries): array {
  $attempt = 0;
  $lastErr = null;
  while (true) {
    $attempt++;
    try {
      $data = scope_list_orders($params);
      return ['ok'=>true, 'data'=>$data, 'error'=>null, 'attempts'=>$attempt];
    } catch (Throwable $e) {
      $lastErr = $e->getMessage();
      if ($attempt <= ($pageRetries + 1)) {
        // backoff suave: 0.8s, 1.6s, 2.4s, 3.2s...
        $sleepMs = min(5000, 800 * $attempt);
        usleep($sleepMs * 1000);
        continue;
      }
      return ['ok'=>false, 'data'=>null, 'error'=>$lastErr, 'attempts'=>$attempt];
    }
  }
};

try {
  $gotLock = (int)($pdo->query("SELECT GET_LOCK(".$pdo->quote($lockName).", 1) AS l")->fetch()['l'] ?? 0);
  if ($gotLock !== 1) {
    json_out(409, ['success'=>false,'message'=>'Ya se está actualizando la información. Intenta de nuevo en un momento.']);
  }

  // Cursor incremental
  $cursorFrom = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->sub(new DateInterval('P'.$daysBack.'D'));
  if ($mode==='incremental') {
    $st = $pdo->prepare("
      SELECT last_modified_max_utc
      FROM scope_sync_state
      WHERE organization_code=? AND legal_entity_code=? AND branch_code=?
      LIMIT 1
    ");
    $st->execute([$org,$le,$br]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['last_modified_max_utc'])) {
      $cursorFrom = new DateTimeImmutable((string)$row['last_modified_max_utc'], new DateTimeZone('UTC'));
      $cursorFrom = $cursorFrom->sub(new DateInterval('PT'.$cursorBackMinutes.'M'));
    }
  }

  // Crear run
  $pdo->prepare("
    INSERT INTO scope_sync_runs (run_uuid, organization_code, legal_entity_code, branch_code, cursor_from_utc, started_at, mensaje, http_status)
    VALUES (?,?,?,?,?,?,?,NULL)
  ")->execute([$runUuid,$org,$le,$br,$cursorFrom->format('Y-m-d H:i:s'),$startedAtUtc,'Iniciando…']);

  $fetched=0; $upOrders=0; $upMil=0; $upRef=0; $upTO=0; $upJC=0; $upTot=0;
  $errors=0; $failedDetails=0; $failedUpserts=0;

  $skippedPages = []; // ✅ páginas saltadas con razón
  $partial = false;

  $lastMaxUtc=null; $lastMaxRaw=null;
  $page=0;

  for ($loop=0; $loop<$maxPages; $loop++) {

    $runUpdate("Consultando página ".($page+1)."…", $fetched, $upOrders, $upJC);

    // Parámetros de listado
    if ($mode==='incremental') {
      $filter = scope_lastModified_filter($cursorFrom,'ge','UTC');
      $params = [
        'page'=>$page,
        'size'=>$pageSize,
        'orderBy'=>'+lastModified',
        'lastModified'=>$filter,
      ];
    } else {
      $params = [
        'page'=>$page,
        'size'=>$pageSize,
        'orderBy'=>'-lastModified',
      ];
    }

    // ✅ List con retries; si falla => saltar página y continuar
    $lp = $listPage($params);
    if (!$lp['ok']) {
      $partial = true;
      $skippedPages[] = [
        'page' => $page,
        'attempts' => $lp['attempts'],
        'reason' => mb_substr((string)$lp['error'], 0, 220),
      ];
      $runUpdate("Intermitencia en página ".($page+1).". Continuando…", $fetched, $upOrders, $upJC);
      $page++;
      continue;
    }

    $list = $lp['data'];
    $orders = extract_orders_any($list);
    if (!$orders) break; // ya no hay más

    foreach($orders as $item){
      $uuid = (string)($item['identifier'] ?? '');
      if ($uuid==='') continue;

      $fetched++;

      // refresca progreso cada ~20 items
      if (($fetched % 20) === 0) $runUpdate('Descargando detalle…', $fetched, $upOrders, $upJC);

      try{
        $detail = scope_get_order($uuid);
      }catch(Throwable $ex){
        $errors++; $failedDetails++;
        if ($throttleMs>0) usleep($throttleMs*1000);
        continue;
      }

      try{
        $r = scope_upsert_order($pdo, $detail);
        $upOrders++;
        $upMil += (int)($r['upserted_milestones'] ?? 0);
        $upRef += (int)($r['upserted_references'] ?? 0);
        $upTO  += (int)($r['upserted_transport_orders'] ?? 0);
        $upJC  += (int)($r['upserted_jobcosting_entries'] ?? 0);
        $upTot += (int)($r['upserted_jobcosting_totals'] ?? 0);

        $lmRaw = (string)($detail['lastModified'] ?? '');
        $lm = iso_to_utc_parts($lmRaw);
        if (!empty($lm['utc'])) {
          if ($lastMaxUtc===null || $lm['utc']>$lastMaxUtc) {
            $lastMaxUtc=$lm['utc']; $lastMaxRaw=$lmRaw;
          }
        }
      }catch(Throwable $ex){
        $errors++; $failedUpserts++;
      }

      if ($throttleMs>0) usleep($throttleMs*1000);
    }

    $page++;
  }

  $finishedAtUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

  // Actualizar state si hubo avance
  if ($lastMaxUtc!==null && $lastMaxUtc!=='') {
    $pdo->prepare("
      INSERT INTO scope_sync_state (
        organization_code, legal_entity_code, branch_code,
        last_modified_max_raw, last_modified_max_utc,
        last_page, last_run_uuid
      ) VALUES (?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        last_modified_max_raw = VALUES(last_modified_max_raw),
        last_modified_max_utc = VALUES(last_modified_max_utc),
        last_page = VALUES(last_page),
        last_run_uuid = VALUES(last_run_uuid),
        updated_at = CURRENT_TIMESTAMP
    ")->execute([$org,$le,$br,$lastMaxRaw,$lastMaxUtc,$page,$runUuid]);
  }

  // ✅ Mensaje final legible
  $msg = 'Sin cambios recientes';
  if ($upOrders > 0) $msg = 'Actualización completada';
  if ($partial || count($skippedPages) > 0) $msg = 'Actualización parcial (se guardó información)';

  $pdo->prepare("
    UPDATE scope_sync_runs
    SET fetched_count=?, upserted_orders=?, upserted_milestones=?, upserted_references=?,
        upserted_transport_orders=?, upserted_jobcosting_entries=?,
        http_status=200, mensaje=?, cursor_to_utc=?, finished_at=?
    WHERE run_uuid=?
  ")->execute([$fetched,$upOrders,$upMil,$upRef,$upTO,$upJC,$msg,$lastMaxUtc,$finishedAtUtc,$runUuid]);

  $pdo->query("SELECT RELEASE_LOCK(".$pdo->quote($lockName).")");

  // ✅ Si guardó algo, siempre success=true aunque haya parcial
  json_out(200, [
    'success'=>true,
    'partial'=>($partial || count($skippedPages)>0),
    'skipped_pages'=>$skippedPages,
    'mode'=>$mode,
    'run_uuid'=>$runUuid,
    'page_size'=>$pageSize,
    'max_pages'=>$maxPages,
    'page_retries'=>$pageRetries,
    'throttle_ms'=>$throttleMs,

    'fetched'=>$fetched,
    'upserted_orders'=>$upOrders,
    'upserted_milestones'=>$upMil,
    'upserted_references'=>$upRef,
    'upserted_transport_orders'=>$upTO,
    'upserted_jobcosting_entries'=>$upJC,
    'upserted_jobcosting_totals'=>$upTot,

    'errors_total'=>$errors,
    'failed_details'=>$failedDetails,
    'failed_upserts'=>$failedUpserts,

    'last_modified_max_utc'=>$lastMaxUtc,
    'server_time'=>date('Y-m-d H:i:s'),
  ]);

} catch (Throwable $e) {

  // Cerrar run con error (best-effort)
  try{
    $finishedAtUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $pdo->prepare("
      UPDATE scope_sync_runs
      SET http_status=500, mensaje=?, finished_at=?
      WHERE run_uuid=?
    ")->execute([mb_substr('Error: '.$e->getMessage(),0,780),$finishedAtUtc,$runUuid]);
  }catch(Throwable $ignored){}

  try { $pdo->query("SELECT RELEASE_LOCK(".$pdo->quote($lockName).")"); } catch(Throwable $ignored){}

  json_out(500, [
    'success'=>false,
    'message'=>'No se pudo actualizar la información.',
    'error'=>$e->getMessage(),
  ]);
}