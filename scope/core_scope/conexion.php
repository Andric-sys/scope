<?php
declare(strict_types=1);

/**
 * conexion.php — CORE SCOPE
 * - db(): PDO singleton
 * - core_brand(): paleta oficial Core Global Logistics
 * - core_brand_css_vars(): variables CSS listas para UI
 */

function core_brand(): array {
  return [
    'primary' => [
      'blue' => '#0171e2',
      'navy' => '#000F9F',
      'gray' => '#65625F',
    ],
    'secondary' => [
      'sky'      => '#9cc1f7',
      'blue_700' => '#004fa8',
      'navy_900' => '#001b56',
    ],
    'ui' => [
      'bg'     => '#f4f7fb',
      'card'   => '#ffffff',
      'text'   => '#0f172a',
      'muted'  => '#64748b',
      'border' => '#e6ebf3',
    ],
  ];
}

function core_brand_css_vars(): string {
  $b = core_brand();
  return <<<CSS
:root{
  --core-blue: {$b['primary']['blue']};
  --core-navy: {$b['primary']['navy']};
  --core-gray: {$b['primary']['gray']};

  --core-sky: {$b['secondary']['sky']};
  --core-blue-700: {$b['secondary']['blue_700']};
  --core-navy-900: {$b['secondary']['navy_900']};

  --bg: {$b['ui']['bg']};
  --card: {$b['ui']['card']};
  --text: {$b['ui']['text']};
  --muted: {$b['ui']['muted']};
  --border: {$b['ui']['border']};
}
CSS;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $cfg = require __DIR__ . '/config.php';
  if (empty($cfg['db']) || !is_array($cfg['db'])) {
    throw new RuntimeException('config.php no contiene sección db.');
  }
  $db = $cfg['db'];

  $host    = (string)($db['host'] ?? '127.0.0.1');
  $name    = (string)($db['name'] ?? '');
  $user    = (string)($db['user'] ?? '');
  $pass    = (string)($db['pass'] ?? '');
  $charset = (string)($db['charset'] ?? 'utf8mb4');

  if ($name === '' || $user === '') {
    throw new RuntimeException('Falta db.name o db.user en config.php.');
  }

  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
  ]);

  // Consistencia: guardamos y comparamos timestamps en UTC
  try { $pdo->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) {}

  return $pdo;
}

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}