<?php
declare(strict_types=1);

final class ScopeApiException extends RuntimeException {
  public int $httpStatus;
  public string $url;
  public string $responseSnippet;

  public function __construct(string $message, int $httpStatus = 0, string $url = '', string $responseSnippet = '', ?Throwable $previous = null) {
    parent::__construct($message, 0, $previous);
    $this->httpStatus = $httpStatus;
    $this->url = $url;
    $this->responseSnippet = $responseSnippet;
  }
}

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

function scope_log(string $level, string $message, array $context = []): void {
  try {
    $s = scope_cfg();
    $payload = [
      'time' => gmdate('Y-m-d H:i:s'),
      'level' => strtolower(trim($level)),
      'message' => $message,
      'context' => $context,
    ];
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ($message . ' ' . print_r($context, true));

    $logFile = trim((string)($s['log_file'] ?? ''));
    if ($logFile !== '') {
      @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
      return;
    }
    error_log('[scope_api] ' . $line);
  } catch (Throwable $ignored) {}
}

function scope_parse_headers(string $headersRaw): array {
  $headers = [];
  foreach (preg_split('/\r\n|\n|\r/', $headersRaw) as $line) {
    $line = trim((string)$line);
    if ($line === '' || strpos($line, ':') === false) continue;
    [$name, $value] = explode(':', $line, 2);
    $headers[strtolower(trim($name))] = trim($value);
  }
  return $headers;
}

function scope_retry_after_ms(array $headers): int {
  $raw = trim((string)($headers['retry-after'] ?? ''));
  if ($raw === '') return 0;

  if (ctype_digit($raw)) {
    return max(0, (int)$raw * 1000);
  }

  $ts = strtotime($raw);
  if ($ts === false) return 0;
  return max(0, ($ts - time()) * 1000);
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

  if ($tenant['organizationCode'] === '' || $tenant['legalEntityCode'] === '' || $tenant['branchCode'] === '') {
    throw new RuntimeException('Faltan parámetros tenant obligatorios (organizationCode/legalEntityCode/branchCode).');
  }

  $final = $tenant + $query;

  foreach ($final as $k => $v) {
    if ($v === null) unset($final[$k]);
  }

  if ($applyDefaultExpand && !isset($final['expand']) && !empty($s['expand'])) {
    $final['expand'] = (string)$s['expand'];
  }

  return $base . $path . '?' . http_build_query($final, '', '&', PHP_QUERY_RFC3986);
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
  if ($maxRetries > 10) $maxRetries = 10;

  $retrySleep = (int)($s['retry_sleep_ms'] ?? 800);
  if ($retrySleep < 50) $retrySleep = 50;
  if ($retrySleep > 10000) $retrySleep = 10000;

  $verifySsl = (bool)($s['verify_ssl'] ?? true);

  $url = scope_build_url($path, $query, $applyDefaultExpand);

  $attempt = 0;
  $maxAttempts = $maxRetries + 1;
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
      scope_log('warn', 'Error cURL en request a Scope', [
        'attempt' => $attempt,
        'maxAttempts' => $maxAttempts,
        'errno' => $eno,
        'error' => $err,
        'url' => $url,
      ]);

      if ($retryableCurl && $attempt < $maxAttempts) {
        $waitMs = min(15000, $retrySleep * $attempt + random_int(0, 250));
        usleep($waitMs * 1000);
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
    $headersAssoc = scope_parse_headers((string)$headers);

    $contentType = (string)($headersAssoc['content-type'] ?? '');

    $retryableHttp = ($http === 429 || ($http >= 500 && $http <= 599));

    if ($http === 403) {
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new ScopeApiException("HTTP 403: acceso denegado\nURL: {$url}\nBody: {$head}", 403, $url, $head);
    }

    if ($http === 404) {
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new ScopeApiException("HTTP 404: recurso no encontrado\nURL: {$url}\nBody: {$head}", 404, $url, $head);
    }

    if ($retryableHttp && $attempt < $maxAttempts) {
      $retryAfterMs = scope_retry_after_ms($headersAssoc);
      $waitMs = $retryAfterMs > 0
        ? min(60000, $retryAfterMs)
        : min(15000, $retrySleep * $attempt + random_int(0, 250));

      scope_log('warn', 'HTTP retryable en Scope API', [
        'attempt' => $attempt,
        'maxAttempts' => $maxAttempts,
        'http' => $http,
        'wait_ms' => $waitMs,
        'url' => $url,
      ]);

      usleep($waitMs * 1000);
      continue;
    }

    if ($http === 503) {
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new ScopeApiException("HTTP 503: servicio Scope no disponible\nURL: {$url}\nBody: {$head}", 503, $url, $head);
    }

    if ($http < 200 || $http >= 300) {
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new ScopeApiException("HTTP {$http}\nURL: {$url}\nCT: {$contentType}\nBody: {$head}", $http, $url, $head);
    }

    if ($bodyTrim === '' && $attempt < $maxAttempts) {
      $waitMs = min(10000, $retrySleep * $attempt + random_int(0, 250));
      usleep($waitMs * 1000);
      continue;
    }

    if ($bodyTrim === '') {
      throw new RuntimeException("Respuesta vacía desde Scope\nURL: {$url}");
    }

    $looksJson = (str_starts_with($bodyTrim, '{') || str_starts_with($bodyTrim, '['));
    $isJsonCT  = (stripos($contentType, 'application/json') !== false);

    if (!$looksJson && !$isJsonCT) {
      if ($attempt < $maxAttempts) {
        $waitMs = min(10000, $retrySleep * $attempt + random_int(0, 250));
        usleep($waitMs * 1000);
        continue;
      }
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new RuntimeException("Respuesta no JSON\nURL: {$url}\nCT: {$contentType}\nBody: {$head}");
    }

    try {
      $data = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $je) {
      if ($attempt < $maxAttempts) {
        $waitMs = min(10000, $retrySleep * $attempt + random_int(0, 250));
        usleep($waitMs * 1000);
        continue;
      }
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new RuntimeException("JSON inválido\nURL: {$url}\nCT: {$contentType}\nBody: {$head}\nError: {$je->getMessage()}", 0, $je);
    }

    if (!is_array($data)) {
      $head = mb_substr($bodyTrim, 0, 1200);
      throw new RuntimeException("Respuesta JSON no es objeto/arreglo\nURL: {$url}\nBody: {$head}");
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

function scope_extract_orders_any(array $data): array {
  $best = [];
  $walk = function($node) use (&$walk, &$best) {
    if (!is_array($node)) return;
    $isIndexed = array_keys($node) === range(0, count($node)-1);
    if ($isIndexed && count($node) > 0 && is_array($node[0])) {
      $hits = 0;
      foreach ($node as $x) {
        if (is_array($x) && isset($x['identifier']) && is_string($x['identifier']) && trim($x['identifier']) !== '') {
          $hits++;
        }
      }
      if ($hits > 0 && count($node) > count($best)) {
        $best = $node;
      }
    }
    foreach ($node as $v) $walk($v);
  };
  $walk($data);
  return $best;
}

function scope_list_orders_all(array $params = [], int $maxPages = 200, ?callable $onPage = null): array {
  $size = (int)($params['size'] ?? 100);
  if ($size < 10) $size = 10;
  if ($size > 200) $size = 200;

  $startPage = (int)($params['page'] ?? 0);
  if ($startPage < 0) $startPage = 0;

  if ($maxPages < 1) $maxPages = 1;
  if ($maxPages > 2000) $maxPages = 2000;

  $orders = [];
  $pagesFetched = 0;
  $stopReason = 'max_pages';

  for ($offset = 0; $offset < $maxPages; $offset++) {
    $page = $startPage + $offset;
    $pageParams = $params;
    $pageParams['page'] = $page;
    $pageParams['size'] = $size;

    $payload = scope_list_orders($pageParams);
    $chunk = scope_extract_orders_any($payload);
    $count = count($chunk);
    $pagesFetched++;

    if ($onPage !== null) {
      $onPage($page, $chunk, $payload);
    }

    if ($count === 0) {
      $stopReason = 'empty_page';
      break;
    }

    foreach ($chunk as $row) {
      $orders[] = $row;
    }

    if ($count < $size) {
      $stopReason = 'last_page';
      break;
    }
  }

  return [
    'orders' => $orders,
    'pages_fetched' => $pagesFetched,
    'stop_reason' => $stopReason,
    'size' => $size,
    'start_page' => $startPage,
  ];
}

function scope_lastModified_filter(DateTimeImmutable $from, string $op = 'gt', string $tz = 'UTC'): string {
  $op = strtolower(trim($op));
  if (!in_array($op, ['ge','gt','le','lt','eq'], true)) $op = 'gt';
  $dt = $from->setTimezone(new DateTimeZone($tz));
  return $op . ':' . $dt->format('Y-m-d\TH:i:sO');
}