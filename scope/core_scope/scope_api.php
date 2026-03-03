<?php
declare(strict_types=1);

function scope_cfg(): array {
  $cfg = require __DIR__ . '/config.php';
  if (empty($cfg['scope']) || !is_array($cfg['scope'])) {
    throw new RuntimeException('config.php no contiene sección scope.');
  }
  return $cfg['scope'];
}

function scope_orders_resource(): string {
  $s = scope_cfg();
  $res = trim((string)($s['orders_resource'] ?? 'orders'), '/');
  return $res !== '' ? $res : 'orders';
}

function scope_build_url(string $path, array $query = [], bool $applyDefaultExpand = true): string {
  $s = scope_cfg();

  $base = rtrim((string)($s['base_url'] ?? ''), '/');
  if ($base === '') throw new RuntimeException('Falta scope.base_url en config.php.');

  $path = '/' . ltrim($path, '/');

  $tenant = [
    'organizationCode' => (string)($s['organizationCode'] ?? ''),
    'legalEntityCode'  => (string)($s['legalEntityCode'] ?? ''),
    'branchCode'       => (string)($s['branchCode'] ?? ''),
  ];

  $final = $tenant + $query;

  if ($applyDefaultExpand && !isset($final['expand']) && !empty($s['expand'])) {
    $final['expand'] = (string)$s['expand'];
  }

  return $base . $path . '?' . http_build_query($final);
}

function scope_request(string $method, string $path, array $query = [], bool $applyDefaultExpand = true): array {
  $s = scope_cfg();

  $username = (string)($s['username'] ?? '');
  $password = (string)($s['password'] ?? '');
  if ($username === '' || $password === '') {
    throw new RuntimeException('Faltan credenciales en config.php (scope.username/password).');
  }

  $timeoutMs = (int)($s['timeout_ms'] ?? 60000);
  if ($timeoutMs < 3000) $timeoutMs = 3000;

  $connectTimeoutMs = (int)($s['connect_timeout_ms'] ?? 15000);
  if ($connectTimeoutMs < 1000) $connectTimeoutMs = 1000;

  $maxRetries = (int)($s['retries'] ?? 0);
  if ($maxRetries < 0) $maxRetries = 0;

  $retrySleep = (int)($s['retry_sleep_ms'] ?? 800);
  if ($retrySleep < 50) $retrySleep = 50;

  $verifySsl = (bool)($s['verify_ssl'] ?? true);

  $url = scope_build_url($path, $query, $applyDefaultExpand);

  $attempt = 0;
  while (true) {
    $attempt++;

    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('No se pudo inicializar cURL.');

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => true,
      CURLOPT_CUSTOMREQUEST  => strtoupper($method),

      CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
      CURLOPT_USERPWD        => $username . ':' . $password,

      CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Accept-Encoding: gzip, deflate',
        'Connection: keep-alive',
      ],
      CURLOPT_USERAGENT      => 'CORE-SCOPE/1.0',
      CURLOPT_ENCODING       => '',

      CURLOPT_NOSIGNAL          => 1,
      CURLOPT_TIMEOUT_MS        => $timeoutMs,
      CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,
      CURLOPT_DNS_CACHE_TIMEOUT => 60,

      CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_1_1,

      CURLOPT_SSL_VERIFYPEER => $verifySsl ? 1 : 0,
      CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $raw = curl_exec($ch);

    if ($raw === false) {
      $err = curl_error($ch);
      $eno = curl_errno($ch);
      curl_close($ch);

      $retryableCurl = in_array($eno, [7, 28, 35, 52, 56], true);
      if ($retryableCurl && $attempt <= ($maxRetries + 1)) {
        usleep(($retrySleep * $attempt) * 1000);
        continue;
      }
      throw new RuntimeException("Error cURL ({$eno}): {$err}\nURL: {$url}");
    }

    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $bodyTrim = ltrim((string)$body);

    $contentType = '';
    foreach (explode("\n", (string)$headers) as $line) {
      if (stripos($line, 'Content-Type:') === 0) { $contentType = trim(substr($line, 13)); break; }
    }

    $retryableHttp = ($http === 429 || ($http >= 500 && $http <= 599));
    if ($retryableHttp && $attempt <= ($maxRetries + 1)) {
      usleep(($retrySleep * $attempt) * 1000);
      continue;
    }

    if ($http < 200 || $http >= 300) {
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new RuntimeException("HTTP {$http}\nURL: {$url}\nCT: {$contentType}\nBody: {$head}");
    }

    if ($bodyTrim === '' && $attempt <= ($maxRetries + 1)) {
      usleep(($retrySleep * $attempt) * 1000);
      continue;
    }

    $looksJson = (str_starts_with($bodyTrim, '{') || str_starts_with($bodyTrim, '['));
    $isJsonCT  = (stripos($contentType, 'application/json') !== false);

    if (!$looksJson && !$isJsonCT) {
      if ($attempt <= ($maxRetries + 1)) {
        usleep(($retrySleep * $attempt) * 1000);
        continue;
      }
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new RuntimeException("Respuesta no JSON\nURL: {$url}\nCT: {$contentType}\nBody: {$head}");
    }

    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
      if ($attempt <= ($maxRetries + 1)) {
        usleep(($retrySleep * $attempt) * 1000);
        continue;
      }
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new RuntimeException("JSON inválido\nURL: {$url}\nCT: {$contentType}\nBody: {$head}");
    }

    return $data;
  }
}

function scope_get_order(string $orderUuid): array {
  $uuid = trim($orderUuid);
  if ($uuid === '') throw new InvalidArgumentException('orderUuid vacío.');
  $res = scope_orders_resource();
  return scope_request('GET', '/' . $res . '/' . rawurlencode($uuid), [], true);
}

function scope_list_orders(array $params = []): array {
  $res = scope_orders_resource();
  if (isset($params['page'])) $params['page'] = (int)$params['page'];
  if (isset($params['size'])) $params['size'] = (int)$params['size'];
  return scope_request('GET', '/' . $res, $params, false);
}

function scope_lastModified_filter(DateTimeImmutable $from, string $op = 'gt', string $tz = 'UTC'): string {
  $op = strtolower(trim($op));
  if (!in_array($op, ['ge','gt','le','lt','eq'], true)) $op = 'gt';
  $dt = $from->setTimezone(new DateTimeZone($tz));
  return $op . ':' . $dt->format('Y-m-d\TH:i:sO');
}