<?php
declare(strict_types=1);

/**
 * scope_viewer.php — CORE SCOPE (VISOR)
 * - Solo visualizar lo que regresa Scope (NO inserta a BD)
 * - Útil para diagnosticar por qué /orders listado regresa vacío
 * - Protegido con autenticación de CGL
 */

// Proteger con autenticación
require __DIR__ . '/auth_guard.php';
require __DIR__ . '/scope_api.php';

date_default_timezone_set('America/Mexico_City');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function json_pretty($data): string {
  $j = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  return is_string($j) ? $j : '—';
}

/**
 * Busca el "primer array grande" en el JSON para inferir items (cuando no sabemos la key exacta).
 * Devuelve ['path'=>'a.b.c', 'items'=>[...]] o null
 */
function find_first_items_array($data): ?array {
  $best = null;

  $walk = function($node, string $path) use (&$walk, &$best) {
    if (!is_array($node)) return;

    // Si es array indexado (lista) y tiene objetos adentro
    $isIndexed = array_keys($node) === range(0, count($node)-1);
    if ($isIndexed && count($node) > 0 && is_array($node[0])) {
      // Preferimos el más grande
      $cand = ['path'=>$path, 'items'=>$node, 'count'=>count($node)];
      if ($best === null || $cand['count'] > $best['count']) $best = $cand;
    }

    foreach ($node as $k => $v) {
      $next = $path === '' ? (string)$k : ($path.'.'.$k);
      $walk($v, $next);
    }
  };

  $walk($data, '');

  if ($best === null) return null;
  unset($best['count']);
  return $best;
}

/**
 * Request Scope con debug:
 * - regresa status + url + data
 */
function scope_request_debug(string $method, string $path, array $query = [], bool $applyDefaultExpand = false): array {
  $s = scope_cfg();

  $username = (string)($s['username'] ?? '');
  $password = (string)($s['password'] ?? '');
  if ($username === '' || $password === '') {
    return ['ok'=>false,'status'=>0,'url'=>'','error'=>'Faltan credenciales Scope en config.php (scope.username/password).','data'=>null];
  }

  $timeout = (int)($s['timeout'] ?? 30);
  if ($timeout <= 0) $timeout = 30;

  // Build url (reusamos el builder existente)
  $url = scope_build_url($path, $query, $applyDefaultExpand);

  $ch = curl_init($url);
  if ($ch === false) {
    return ['ok'=>false,'status'=>0,'url'=>$url,'error'=>'No se pudo inicializar cURL.','data'=>null];
  }

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    CURLOPT_USERPWD        => $username . ':' . $password,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
  ]);

  $raw = curl_exec($ch);
  if ($raw === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok'=>false,'status'=>0,'url'=>$url,'error'=>"Error cURL: {$err}",'data'=>null];
  }

  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  $body = substr($raw, $headerSize);

  $decoded = json_decode($body, true);

  if ($http < 200 || $http >= 300) {
    $head = substr((string)$body, 0, 2000);
    return [
      'ok'=>false,
      'status'=>$http,
      'url'=>$url,
      'error'=>"HTTP {$http}. Body(inicio): ".$head,
      'data'=>is_array($decoded) ? $decoded : $head
    ];
  }

  if (!is_array($decoded)) {
    return [
      'ok'=>false,
      'status'=>$http,
      'url'=>$url,
      'error'=>'Respuesta no es JSON válido.',
      'data'=>substr((string)$body, 0, 2000)
    ];
  }

  return ['ok'=>true,'status'=>$http,'url'=>$url,'error'=>'','data'=>$decoded];
}

/* =========================
   UI State
========================= */
$now = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
$year = (int)($_GET['y'] ?? (int)$now->format('Y'));
$month = (int)($_GET['m'] ?? (int)$now->format('m'));
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;

$mode = (string)($_GET['mode'] ?? 'list_plain'); // list_plain | list_lastmodified_month | detail_uuid
$size = (int)($_GET['size'] ?? 50);
if ($size < 1) $size = 1;
if ($size > 500) $size = 500;
$page = (int)($_GET['page'] ?? 0);
if ($page < 0) $page = 0;

$uuid = trim((string)($_GET['uuid'] ?? ''));

$result = null;
$itemsInfo = null;

try {
  $resName = scope_orders_resource();
  if ($mode === 'detail_uuid') {
    if ($uuid !== '') {
      // Detalle con expand default (config.php)
      $result = scope_request_debug('GET', "/{$resName}/".rawurlencode($uuid), [], true);
      if ($result['ok'] && is_array($result['data'])) {
        $itemsInfo = null;
      }
    }
  } elseif ($mode === 'list_lastmodified_month') {
    // mes seleccionado => construimos rango UTC del mes
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new DateTimeZone('UTC'));
    $end = $start->modify('first day of next month');

    // Scope usa lastModified op:ge / lt
    // OJO: algunos tenants solo aceptan un lastModified (no rango), por eso mostramos el JSON crudo sí o sí.
    $filterGe = scope_lastModified_filter($start, 'ge', 'UTC');
    $filterLt = scope_lastModified_filter($end, 'lt', 'UTC');

    // Intento 1: lastModified=ge:... (y orden por lastModified)
    $result = scope_request_debug('GET', "/{$resName}", [
      'page' => $page,
      'size' => $size,
      'orderBy' => '-lastModified',
      // Algunos aceptan solo un lastModified; otros aceptan lista. Aquí probamos concatenado.
      'lastModified' => $filterGe,
      // dejamos el lt como "lastModifiedTo" si existiera; si no existe, igual se verá en error/respuesta
      'lastModifiedTo' => $filterLt,
    ], false);

    if ($result['ok'] && is_array($result['data'])) {
      $itemsInfo = find_first_items_array($result['data']);
    }

  } else {
    // list_plain
    $result = scope_request_debug('GET', "/{$resName}", [
      'page' => $page,
      'size' => $size,
      'orderBy' => '-lastModified',
    ], false);

    if ($result['ok'] && is_array($result['data'])) {
      $itemsInfo = find_first_items_array($result['data']);
    }
  }
} catch (Throwable $e) {
  $result = ['ok'=>false,'status'=>0,'url'=>'','error'=>$e->getMessage(),'data'=>null];
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CORE SCOPE · Visor (sin insertar)</title>
  <style>
    :root{
      --core-blue:#0171e2;
      --core-navy:#000F9F;
      --bg:#f4f7fb;
      --card:#fff;
      --text:#0f172a;
      --muted:#64748b;
      --border:#e6ebf3;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
    .wrap{max-width:1400px;margin:0 auto;padding:16px 12px 26px}
    .top{
      background:var(--card);border:1px solid var(--border);border-radius:18px;
      box-shadow:0 10px 26px rgba(2,6,23,.06);
      padding:14px 14px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between
    }
    h1{margin:0;font-size:1.05rem}
    .muted{color:var(--muted);font-weight:800;font-size:.9rem}
    .grid{margin-top:12px;display:grid;grid-template-columns:420px 1fr;gap:12px;align-items:start}
    .card{background:var(--card);border:1px solid var(--border);border-radius:18px;box-shadow:0 10px 26px rgba(2,6,23,.06);padding:14px}
    label{display:block;font-weight:950;color:var(--muted);font-size:.8rem;margin-bottom:6px}
    .input,.select{width:100%;border:1px solid var(--border);border-radius:14px;padding:10px 12px;font-weight:850;background:#fff;outline:none}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px}
    .btn{border:1px solid rgba(15,23,42,.12);padding:10px 14px;border-radius:999px;font-weight:950;cursor:pointer;background:#fff;color:var(--core-navy)}
    .btn-primary{background:var(--core-blue);color:#fff;border-color:rgba(1,113,226,.40)}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,.12);background:rgba(2,6,23,.03);font-weight:950;font-size:.82rem;color:var(--core-navy)}
    .kv{display:grid;grid-template-columns:220px 1fr;gap:8px 12px;font-size:.92rem;margin-top:10px}
    .kv b{color:var(--muted);font-weight:950}
    pre{white-space:pre-wrap;word-break:break-word;background:#0b1220;color:#e5e7eb;border-radius:14px;padding:12px;border:1px solid rgba(255,255,255,.08);max-height:68vh;overflow:auto;font-size:.82rem}
    .ok{color:#065f46}
    .bad{color:#b91c1c}
    @media (max-width:1100px){.grid{grid-template-columns:1fr}.kv{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div>
      <h1>CORE SCOPE · Visor</h1>
      <div class="muted">Solo visualiza lo que Scope responde (sin insertar). Ideal para diagnosticar el LIST /orders.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <span class="pill">Modo: <?= h($mode) ?></span>
      <span class="pill">HTTP: <span class="<?= ($result && $result['ok'])?'ok':'bad' ?>"><?= h((string)($result['status'] ?? 0)) ?></span></span>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <form method="get" action="">
        <div style="font-weight:1100;color:var(--core-navy)">Consulta</div>
        <div class="muted">Elige modo y rango. No se guarda nada en tu BD.</div>

        <div class="row">
          <div style="flex:1;min-width:160px">
            <label>Modo</label>
            <select name="mode" class="select">
              <option value="list_plain" <?= $mode==='list_plain'?'selected':'' ?>>Listado (sin filtros)</option>
              <option value="list_lastmodified_month" <?= $mode==='list_lastmodified_month'?'selected':'' ?>>Listado por lastModified (mes)</option>
              <option value="detail_uuid" <?= $mode==='detail_uuid'?'selected':'' ?>>Detalle por UUID</option>
            </select>
          </div>
        </div>

        <div class="row">
          <div style="flex:1;min-width:140px">
            <label>Año</label>
            <input name="y" class="input" value="<?= h((string)$year) ?>" inputmode="numeric">
          </div>
          <div style="flex:1;min-width:140px">
            <label>Mes</label>
            <select name="m" class="select">
              <?php for($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= ($i===$month)?'selected':'' ?>><?= str_pad((string)$i,2,'0',STR_PAD_LEFT) ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <div class="row">
          <div style="flex:1;min-width:140px">
            <label>Página</label>
            <input name="page" class="input" value="<?= h((string)$page) ?>" inputmode="numeric">
          </div>
          <div style="flex:1;min-width:140px">
            <label>Tamaño</label>
            <input name="size" class="input" value="<?= h((string)$size) ?>" inputmode="numeric">
          </div>
        </div>

        <div class="row">
          <div style="width:100%">
            <label>UUID (solo modo detalle)</label>
            <input name="uuid" class="input" value="<?= h($uuid) ?>" placeholder="03a3a6c1-...">
          </div>
        </div>

        <div class="row">
          <button class="btn btn-primary" type="submit">Ver respuesta</button>
        </div>
      </form>

      <div class="kv">
        <b>URL</b><div style="word-break:break-word"><?= h((string)($result['url'] ?? '—')) ?></div>
        <b>OK</b><div><?= ($result && $result['ok']) ? 'YES' : 'NO' ?></div>
        <b>Error</b><div><?= h((string)($result['error'] ?? '')) ?></div>
        <b>Items detectados</b>
        <div>
          <?php if ($itemsInfo && isset($itemsInfo['items']) && is_array($itemsInfo['items'])): ?>
            <?= h($itemsInfo['path']) ?> (<?= count($itemsInfo['items']) ?>)
          <?php else: ?>
            —
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
        <div>
          <div style="font-weight:1100;color:var(--core-navy)">Respuesta JSON</div>
          <div class="muted">Esto es exactamente lo que Scope devolvió.</div>
        </div>
      </div>

      <pre><?= h(json_pretty($result['data'] ?? null)) ?></pre>

      <?php if ($itemsInfo && isset($itemsInfo['items']) && is_array($itemsInfo['items'])): ?>
        <div style="margin-top:12px">
          <div style="font-weight:1100;color:var(--core-navy)">Primeros items detectados</div>
          <div class="muted">Te muestro los primeros 3 para ver estructura (identifier/number/lastModified).</div>
          <pre><?= h(json_pretty(array_slice($itemsInfo['items'], 0, 3))) ?></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>