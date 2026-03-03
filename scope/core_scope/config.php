<?php
/**
 * config.php — Configuración ÚNICA (BD + Scope)
 *
 * NOTAS IMPORTANTES:
 * - db.user y db.pass → son de MariaDB (XAMPP), NO de Scope.
 * - scope.username y scope.password → son credenciales del sistema Scope.
 * - timeouts están en milisegundos (ms) para evitar cuelgues.
 */

return [

  /* =========================================================
     BASE DE DATOS
  ========================================================= */
  'db' => [
    'host'    => '127.0.0.1',
    'name'    => 'core_scope',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
  ],

  /* =========================================================
     SCOPE API
  ========================================================= */
  'scope' => [

    // Endpoint base oficial
    'base_url' => 'https://scope10.riege.com/scope/rest/v3',

    // Recurso principal (normalmente 'orders')
    'orders_resource' => 'orders',

    /* =========================
       TENANT (OBLIGATORIO)
    ========================= */
    'organizationCode' => 'COG',
    'legalEntityCode'  => 'COGMX',
    'branchCode'       => 'COGMX',

    /* =========================
       CREDENCIALES SCOPE
    ========================= */
    'username' => 'rodrigo.hernandez@core-gl.com',
    'password' => 'RodCgL2026%',

    /* =========================
       EXPAND SOPORTADO
       (según tu tenant)
    ========================= */
    'expand' => 'jobcostingEntries,transportOrders',

    /* =========================
       TIMEOUTS (ANTI-CUELGUE)
    ========================= */

    // Timeout total de request (ms)
    'timeout_ms' => 20000,          // 20 segundos

    // Timeout de conexión (ms)
    'connect_timeout_ms' => 8000,   // 8 segundos

    // SSL verification (true en producción)
    'verify_ssl' => true,

    /* =========================
       RETRIES
    ========================= */
    'retries' => 2,
    'retry_sleep_ms' => 600,

  ],

];




