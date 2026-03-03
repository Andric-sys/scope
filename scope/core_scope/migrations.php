<?php
declare(strict_types=1);

/**
 * migrations.php
 * Crea la base de datos y todas las tablas necesarias (sin insertar información).
 *
 * Uso:
 * - Navegador: http://localhost/core_scope/migrations.php
 * - CLI: php migrations.php
 * - PROTEGIDO: Requiere autenticación para ejecutar desde navegador
 */

// Proteger con autenticación si es acceso desde navegador
if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/auth_guard.php';
}

header('Content-Type: text/plain; charset=utf-8');

function out(string $line): void {
  echo $line . PHP_EOL;
}

try {
  $cfg = require __DIR__ . '/config.php';
  if (!isset($cfg['db']) || !is_array($cfg['db'])) {
    throw new RuntimeException('No se encontró la sección db en config.php');
  }

  $dbCfg = $cfg['db'];
  $host = (string)($dbCfg['host'] ?? '127.0.0.1');
  $name = (string)($dbCfg['name'] ?? 'core_scope');
  $user = (string)($dbCfg['user'] ?? 'root');
  $pass = (string)($dbCfg['pass'] ?? '');
  $charset = (string)($dbCfg['charset'] ?? 'utf8mb4');

  if ($name === '') {
    throw new RuntimeException('db.name está vacío en config.php');
  }

  $pdo = new PDO(
    sprintf('mysql:host=%s;charset=%s', $host, $charset),
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );

  out('Iniciando migración...');
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
  out("Base de datos lista: {$name}");

  $pdo->exec("USE `{$name}`");
  $pdo->exec("SET NAMES {$charset}");
  $pdo->exec("SET time_zone = '+00:00'");

  $statements = [];

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_code VARCHAR(32) NOT NULL,
  legal_entity_code VARCHAR(32) NOT NULL,
  branch_code VARCHAR(32) NOT NULL,
  scope_uuid VARCHAR(64) NOT NULL,
  legacy_identifier VARCHAR(128) NULL,
  order_number VARCHAR(128) NULL,
  usi VARCHAR(128) NULL,
  last_modified_utc DATETIME NULL,
  last_modified_raw VARCHAR(64) NULL,
  cancelled TINYINT(1) NOT NULL DEFAULT 0,
  blocked TINYINT(1) NOT NULL DEFAULT 0,
  consolidated TINYINT(1) NOT NULL DEFAULT 0,
  module VARCHAR(64) NULL,
  conveyance_type VARCHAR(32) NULL,
  clerk VARCHAR(128) NULL,
  order_date DATE NULL,
  economic_date DATE NULL,
  transport_date DATE NULL,
  etd_date DATE NULL,
  atd_date DATE NULL,
  eta_date DATE NULL,
  ata_date DATE NULL,
  inco_terms VARCHAR(32) NULL,
  incoterm_place VARCHAR(128) NULL,
  main_transport_freight_term VARCHAR(64) NULL,
  movement_scope VARCHAR(64) NULL,
  customer_code VARCHAR(64) NULL,
  customer_name VARCHAR(255) NULL,
  customer_city VARCHAR(128) NULL,
  customer_state VARCHAR(128) NULL,
  customer_country VARCHAR(128) NULL,
  shipper_code VARCHAR(64) NULL,
  shipper_name VARCHAR(255) NULL,
  shipper_country VARCHAR(128) NULL,
  consignee_code VARCHAR(64) NULL,
  consignee_name VARCHAR(255) NULL,
  consignee_country VARCHAR(128) NULL,
  departure_country VARCHAR(128) NULL,
  departure_unlocode VARCHAR(32) NULL,
  departure_name VARCHAR(255) NULL,
  destination_country VARCHAR(128) NULL,
  destination_unlocode VARCHAR(32) NULL,
  destination_name VARCHAR(255) NULL,
  pieces INT UNSIGNED NULL,
  gross_weight_kg DECIMAL(18,3) NULL,
  chargeable_weight_kg DECIMAL(18,3) NULL,
  volume_m3 DECIMAL(18,3) NULL,
  nature_of_goods VARCHAR(255) NULL,
  dgr TINYINT(1) NOT NULL DEFAULT 0,
  financial_status VARCHAR(64) NULL,
  status_to_closed_date DATE NULL,
  cost_center_code VARCHAR(32) NULL,
  raw_hash CHAR(64) NULL,
  raw_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_orders_tenant_uuid (organization_code, legal_entity_code, branch_code, scope_uuid),
  KEY idx_scope_orders_scope_uuid (scope_uuid),
  KEY idx_scope_orders_order_number (order_number),
  KEY idx_scope_orders_last_modified (last_modified_utc),
  KEY idx_scope_orders_updated_at (updated_at),
  KEY idx_scope_orders_financial_status (financial_status),
  KEY idx_scope_orders_conveyance_type (conveyance_type),
  KEY idx_scope_orders_cost_center (cost_center_code),
  KEY idx_scope_orders_transport_date (transport_date),
  KEY idx_scope_orders_order_date (order_date),
  KEY idx_scope_orders_economic_date (economic_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_order_milestones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  description VARCHAR(255) NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 0,
  public_visible TINYINT(1) NOT NULL DEFAULT 0,
  planned_time_utc DATETIME NULL,
  planned_time_raw VARCHAR(64) NULL,
  actual_time_utc DATETIME NULL,
  actual_time_raw VARCHAR(64) NULL,
  fingerprint CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_order_milestones_order_fp (order_id, fingerprint),
  KEY idx_scope_order_milestones_order (order_id),
  KEY idx_scope_order_milestones_code (code),
  CONSTRAINT fk_scope_order_milestones_order FOREIGN KEY (order_id)
    REFERENCES scope_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_order_references (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  ref_code VARCHAR(64) NOT NULL,
  ref_number VARCHAR(191) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_order_references_order_code (order_id, ref_code),
  KEY idx_scope_order_references_order (order_id),
  KEY idx_scope_order_references_number (ref_number),
  CONSTRAINT fk_scope_order_references_order FOREIGN KEY (order_id)
    REFERENCES scope_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_transport_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  transport_order_uuid VARCHAR(64) NOT NULL,
  transport_order_number VARCHAR(128) NULL,
  transport_type VARCHAR(64) NULL,
  conveyance_type VARCHAR(32) NULL,
  transport_date DATE NULL,
  pickup_partner_code VARCHAR(64) NULL,
  pickup_partner_name VARCHAR(255) NULL,
  pickup_country VARCHAR(128) NULL,
  pickup_city VARCHAR(128) NULL,
  unloading_partner_code VARCHAR(64) NULL,
  unloading_partner_name VARCHAR(255) NULL,
  unloading_country VARCHAR(128) NULL,
  unloading_city VARCHAR(128) NULL,
  pickup_unlocode VARCHAR(32) NULL,
  pickup_location_name VARCHAR(255) NULL,
  unloading_unlocode VARCHAR(32) NULL,
  unloading_location_name VARCHAR(255) NULL,
  pickup_window_start DATE NULL,
  pickup_window_end DATE NULL,
  delivery_window_start DATE NULL,
  delivery_window_end DATE NULL,
  pieces INT UNSIGNED NULL,
  gross_weight_kg DECIMAL(18,3) NULL,
  chargeable_weight_kg DECIMAL(18,3) NULL,
  volume_m3 DECIMAL(18,3) NULL,
  freight_term VARCHAR(64) NULL,
  nature_of_goods VARCHAR(255) NULL,
  fingerprint CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_transport_orders_order_fp (order_id, fingerprint),
  KEY idx_scope_transport_orders_order (order_id),
  KEY idx_scope_transport_orders_uuid (transport_order_uuid),
  KEY idx_scope_transport_orders_date (transport_date),
  KEY idx_scope_transport_orders_type (transport_type),
  KEY idx_scope_transport_orders_conveyance (conveyance_type),
  KEY idx_scope_transport_orders_pickup_country (pickup_country),
  KEY idx_scope_transport_orders_unloading_country (unloading_country),
  CONSTRAINT fk_scope_transport_orders_order FOREIGN KEY (order_id)
    REFERENCES scope_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_jobcosting_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  entry_type VARCHAR(32) NOT NULL,
  charge_type_code VARCHAR(64) NULL,
  cost_center_code VARCHAR(32) NULL,
  booking_date DATE NULL,
  economic_date DATE NULL,
  invoice_date DATE NULL,
  amount_value DECIMAL(18,4) NULL,
  amount_currency VARCHAR(8) NULL,
  tax_value DECIMAL(18,4) NULL,
  tax_currency VARCHAR(8) NULL,
  local_amount_value DECIMAL(18,4) NULL,
  local_amount_currency VARCHAR(8) NULL,
  local_tax_value DECIMAL(18,4) NULL,
  local_tax_currency VARCHAR(8) NULL,
  org_amount_value DECIMAL(18,4) NULL,
  org_amount_currency VARCHAR(8) NULL,
  partner_code VARCHAR(64) NULL,
  partner_name VARCHAR(255) NULL,
  subledger_account_number VARCHAR(64) NULL,
  general_ledger_account_number VARCHAR(64) NULL,
  entry_number VARCHAR(128) NULL,
  external_number VARCHAR(128) NULL,
  tax_key VARCHAR(64) NULL,
  fingerprint CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_jobcosting_entries_order_fp (order_id, fingerprint),
  KEY idx_scope_jobcosting_entries_order (order_id),
  KEY idx_scope_jobcosting_entries_invoice (invoice_date),
  KEY idx_scope_jobcosting_entries_economic (economic_date),
  KEY idx_scope_jobcosting_entries_entry_type (entry_type),
  KEY idx_scope_jobcosting_entries_charge_code (charge_type_code),
  KEY idx_scope_jobcosting_entries_cost_center (cost_center_code),
  CONSTRAINT fk_scope_jobcosting_entries_order FOREIGN KEY (order_id)
    REFERENCES scope_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_jobcosting_totals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  local_currency VARCHAR(8) NULL,
  local_booked_income DECIMAL(18,4) NULL,
  local_booked_cost DECIMAL(18,4) NULL,
  local_transit_booked_income DECIMAL(18,4) NULL,
  local_transit_booked_cost DECIMAL(18,4) NULL,
  local_total_income DECIMAL(18,4) NULL,
  local_total_cost DECIMAL(18,4) NULL,
  local_profit DECIMAL(18,4) NULL,
  local_gross_margin DECIMAL(12,6) NULL,
  org_currency VARCHAR(8) NULL,
  org_total_income DECIMAL(18,4) NULL,
  org_total_cost DECIMAL(18,4) NULL,
  org_profit DECIMAL(18,4) NULL,
  org_gross_margin DECIMAL(12,6) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_jobcosting_totals_order (order_id),
  KEY idx_scope_jobcosting_totals_currency (local_currency),
  KEY idx_scope_jobcosting_totals_profit (local_profit),
  KEY idx_scope_jobcosting_totals_updated (updated_at),
  CONSTRAINT fk_scope_jobcosting_totals_order FOREIGN KEY (order_id)
    REFERENCES scope_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_order_ops (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  stage_code VARCHAR(64) NULL,
  stage_description VARCHAR(255) NULL,
  stage_planned_time_utc DATETIME NULL,
  stage_actual_time_utc DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_order_ops_order (order_id),
  KEY idx_scope_order_ops_stage_code (stage_code),
  CONSTRAINT fk_scope_order_ops_order FOREIGN KEY (order_id)
    REFERENCES scope_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_sync_state (
  organization_code VARCHAR(32) NOT NULL,
  legal_entity_code VARCHAR(32) NOT NULL,
  branch_code VARCHAR(32) NOT NULL,
  last_modified_max_raw VARCHAR(64) NULL,
  last_modified_max_utc DATETIME NULL,
  last_page INT NOT NULL DEFAULT 0,
  last_run_uuid CHAR(36) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_code, legal_entity_code, branch_code),
  KEY idx_scope_sync_state_last_modified (last_modified_max_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS scope_sync_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_uuid CHAR(36) NOT NULL,
  organization_code VARCHAR(32) NOT NULL,
  legal_entity_code VARCHAR(32) NOT NULL,
  branch_code VARCHAR(32) NOT NULL,
  cursor_from_utc DATETIME NULL,
  cursor_to_utc DATETIME NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  fetched_count INT NOT NULL DEFAULT 0,
  upserted_orders INT NOT NULL DEFAULT 0,
  upserted_milestones INT NOT NULL DEFAULT 0,
  upserted_references INT NOT NULL DEFAULT 0,
  upserted_transport_orders INT NOT NULL DEFAULT 0,
  upserted_jobcosting_entries INT NOT NULL DEFAULT 0,
  http_status INT NULL,
  mensaje VARCHAR(800) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_scope_sync_runs_uuid (run_uuid),
  KEY idx_scope_sync_runs_started (started_at),
  KEY idx_scope_sync_runs_finished (finished_at),
  KEY idx_scope_sync_runs_tenant_started (organization_code, legal_entity_code, branch_code, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

  foreach ($statements as $i => $sql) {
    $pdo->exec($sql);
    out('OK [' . ($i + 1) . '/' . count($statements) . '] tabla creada/verificada');
  }

  out('Migración completada correctamente.');
  out('La base quedó creada sin información (solo estructura).');
} catch (Throwable $e) {
  http_response_code(500);
  out('ERROR en migración: ' . $e->getMessage());
  exit(1);
}
