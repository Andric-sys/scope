<?php
declare(strict_types=1);

/**
 * scope_upsert.php — CORE SCOPE
 * Compatible con tu BD core_scope (tablas:
 * scope_orders, scope_order_milestones, scope_order_references, scope_transport_orders,
 * scope_jobcosting_entries, scope_jobcosting_totals, scope_order_ops)
 */

/* =========================
   Helpers
========================= */
function sha64(string $s): string { return hash('sha256', $s); }

function arr_get(array $a, array $path, $default=null) {
  $cur = $a;
  foreach ($path as $k) {
    if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
    $cur = $cur[$k];
  }
  return $cur;
}

function iso_to_utc_parts(?string $iso): array {
  $iso = $iso !== null ? trim($iso) : '';
  if ($iso === '') return ['raw'=>null,'utc'=>null];
  try {
    $dt = new DateTimeImmutable($iso);
    $utc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    return ['raw'=>$iso,'utc'=>$utc];
  } catch (Throwable $e) {
    return ['raw'=>$iso,'utc'=>null];
  }
}

function date_only(?string $v): ?string {
  $v = $v !== null ? trim($v) : '';
  if ($v === '') return null;
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
  if (preg_match('/^(\d{4}-\d{2}-\d{2})T/', $v, $m)) return $m[1];
  return null;
}

function dec_or_null($v): ?string {
  if ($v === null || $v === '') return null;
  return is_numeric($v) ? (string)$v : null;
}

function normalize_list($maybeList, string $childKey): array {
  if (!is_array($maybeList)) return [];
  if (!isset($maybeList[$childKey]) || !is_array($maybeList[$childKey])) return [];
  $x = $maybeList[$childKey];
  // si viene objeto asociativo, envolver
  if (array_keys($x) !== range(0, count($x)-1)) return [$x];
  return $x;
}

function first_partner_related_data(array $order): ?array {
  if (!isset($order['partnerRelatedData']) || !is_array($order['partnerRelatedData'])) return null;
  $prd = $order['partnerRelatedData'];
  if (array_keys($prd) === range(0, count($prd)-1)) return $prd[0] ?? null;
  return $prd;
}

function pick_first($v, array $keys) {
  if (!is_array($v)) return null;
  foreach ($keys as $k) {
    if (array_key_exists($k, $v) && $v[$k] !== null && $v[$k] !== '') return $v[$k];
  }
  return null;
}

/* =========================
   Select helpers
========================= */
function scope_select_order_id_and_hash(PDO $pdo, string $org, string $le, string $br, string $uuid): ?array {
  $sel = $pdo->prepare("
    SELECT id, raw_hash
    FROM scope_orders
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=? AND scope_uuid=?
    LIMIT 1
  ");
  $sel->execute([$org,$le,$br,$uuid]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function scope_select_order_id(PDO $pdo, string $org, string $le, string $br, string $uuid): int {
  $sel = $pdo->prepare("
    SELECT id
    FROM scope_orders
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=? AND scope_uuid=?
    LIMIT 1
  ");
  $sel->execute([$org,$le,$br,$uuid]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new RuntimeException('No se pudo obtener order_id después del upsert.');
  return (int)$row['id'];
}

/* =========================
   MAIN Upsert
========================= */
function scope_upsert_order(PDO $pdo, array $order): array {
  $cfg = require __DIR__ . '/config.php';
  $tenant = $cfg['scope'] ?? null;
  if (!is_array($tenant)) throw new RuntimeException('config.php sin sección scope.');

  $org = (string)$tenant['organizationCode'];
  $le  = (string)$tenant['legalEntityCode'];
  $br  = (string)$tenant['branchCode'];

  $scopeUuid = trim((string)($order['identifier'] ?? ''));
  if ($scopeUuid === '') {
    throw new RuntimeException('El JSON no trae identifier (UUID) de la orden.');
  }

  $rawJson = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($rawJson === false) $rawJson = null;
  $rawHash = $rawJson ? sha64($rawJson) : null;

  $lm = iso_to_utc_parts($order['lastModified'] ?? null);

  $prd0 = first_partner_related_data($order);
  $financialStatus    = is_array($prd0) ? ($prd0['financialStatus'] ?? null) : null;
  $statusToClosedDate = is_array($prd0) ? date_only($prd0['statusToClosedDate'] ?? null) : null;
  $costCenterCode     = is_array($prd0) ? arr_get($prd0, ['costCenter','code'], null) : null;

  $totLoc = is_array($prd0) ? ($prd0['jobcostingTotalsLocalCurrency'] ?? null) : null;
  $totOrg = is_array($prd0) ? ($prd0['jobcostingTotalsOrganizationCurrency'] ?? null) : null;

  $grossKg  = arr_get($order, ['grossWeight','value'], null);
  $chargeKg = arr_get($order, ['chargeableWeight','value'], null);
  $volM3    = arr_get($order, ['volume','value'], null);

  $custCode    = arr_get($order, ['customer','partner','code'], null);
  $custName    = arr_get($order, ['customer','partner','name'], null);
  $custCity    = arr_get($order, ['customer','address','city'], null);
  $custState   = arr_get($order, ['customer','address','state'], null);
  $custCountry = arr_get($order, ['customer','address','country'], null);

  $shipCode    = arr_get($order, ['shipper','partner','code'], null);
  $shipName    = arr_get($order, ['shipper','partner','name'], null);
  $shipCountry = arr_get($order, ['shipper','address','country'], null);

  $conCode     = arr_get($order, ['consignee','partner','code'], null);
  $conName     = arr_get($order, ['consignee','partner','name'], null);
  $conCountry  = arr_get($order, ['consignee','address','country'], null);

  $depCountry  = arr_get($order, ['departure','country'], null);
  $depUnl      = arr_get($order, ['departure','unlocode'], null);
  $depName     = arr_get($order, ['departure','name'], null);

  $dstCountry  = arr_get($order, ['destination','country'], null);
  $dstUnl      = arr_get($order, ['destination','unlocode'], null);
  $dstName     = arr_get($order, ['destination','name'], null);

  $etd = date_only(arr_get($order, ['etd','date'], null));
  $atd = date_only(arr_get($order, ['atd','date'], null));
  $eta = date_only(arr_get($order, ['eta','date'], null));
  $ata = date_only(arr_get($order, ['ata','date'], null));

  $pdo->beginTransaction();
  try {
    $existing = scope_select_order_id_and_hash($pdo, $org, $le, $br, $scopeUuid);

    // CABECERA
    $sql = "
      INSERT INTO scope_orders (
        organization_code, legal_entity_code, branch_code,
        scope_uuid, legacy_identifier, order_number, usi,
        last_modified_utc, last_modified_raw,
        cancelled, blocked, consolidated,
        module, conveyance_type, clerk,
        order_date, economic_date, transport_date,
        etd_date, atd_date, eta_date, ata_date,
        inco_terms, incoterm_place, main_transport_freight_term, movement_scope,
        customer_code, customer_name, customer_city, customer_state, customer_country,
        shipper_code, shipper_name, shipper_country,
        consignee_code, consignee_name, consignee_country,
        departure_country, departure_unlocode, departure_name,
        destination_country, destination_unlocode, destination_name,
        pieces, gross_weight_kg, chargeable_weight_kg, volume_m3, nature_of_goods, dgr,
        financial_status, status_to_closed_date, cost_center_code,
        raw_hash, raw_json
      ) VALUES (
        :org,:le,:br,
        :scope_uuid,:legacy_identifier,:order_number,:usi,
        :last_modified_utc,:last_modified_raw,
        :cancelled,:blocked,:consolidated,
        :module,:conveyance_type,:clerk,
        :order_date,:economic_date,:transport_date,
        :etd_date,:atd_date,:eta_date,:ata_date,
        :inco_terms,:incoterm_place,:mt_freight_term,:movement_scope,
        :customer_code,:customer_name,:customer_city,:customer_state,:customer_country,
        :shipper_code,:shipper_name,:shipper_country,
        :consignee_code,:consignee_name,:consignee_country,
        :departure_country,:departure_unlocode,:departure_name,
        :destination_country,:destination_unlocode,:destination_name,
        :pieces,:gross_weight_kg,:chargeable_weight_kg,:volume_m3,:nature_of_goods,:dgr,
        :financial_status,:status_to_closed_date,:cost_center_code,
        :raw_hash,:raw_json
      )
      ON DUPLICATE KEY UPDATE
        legacy_identifier = VALUES(legacy_identifier),
        order_number = VALUES(order_number),
        usi = VALUES(usi),
        last_modified_utc = VALUES(last_modified_utc),
        last_modified_raw = VALUES(last_modified_raw),
        cancelled = VALUES(cancelled),
        blocked = VALUES(blocked),
        consolidated = VALUES(consolidated),
        module = VALUES(module),
        conveyance_type = VALUES(conveyance_type),
        clerk = VALUES(clerk),
        order_date = VALUES(order_date),
        economic_date = VALUES(economic_date),
        transport_date = VALUES(transport_date),
        etd_date = VALUES(etd_date),
        atd_date = VALUES(atd_date),
        eta_date = VALUES(eta_date),
        ata_date = VALUES(ata_date),
        inco_terms = VALUES(inco_terms),
        incoterm_place = VALUES(incoterm_place),
        main_transport_freight_term = VALUES(main_transport_freight_term),
        movement_scope = VALUES(movement_scope),
        customer_code = VALUES(customer_code),
        customer_name = VALUES(customer_name),
        customer_city = VALUES(customer_city),
        customer_state = VALUES(customer_state),
        customer_country = VALUES(customer_country),
        shipper_code = VALUES(shipper_code),
        shipper_name = VALUES(shipper_name),
        shipper_country = VALUES(shipper_country),
        consignee_code = VALUES(consignee_code),
        consignee_name = VALUES(consignee_name),
        consignee_country = VALUES(consignee_country),
        departure_country = VALUES(departure_country),
        departure_unlocode = VALUES(departure_unlocode),
        departure_name = VALUES(departure_name),
        destination_country = VALUES(destination_country),
        destination_unlocode = VALUES(destination_unlocode),
        destination_name = VALUES(destination_name),
        pieces = VALUES(pieces),
        gross_weight_kg = VALUES(gross_weight_kg),
        chargeable_weight_kg = VALUES(chargeable_weight_kg),
        volume_m3 = VALUES(volume_m3),
        nature_of_goods = VALUES(nature_of_goods),
        dgr = VALUES(dgr),
        financial_status = VALUES(financial_status),
        status_to_closed_date = VALUES(status_to_closed_date),
        cost_center_code = VALUES(cost_center_code),
        raw_hash = VALUES(raw_hash),
        raw_json = VALUES(raw_json),
        updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':org'=>$org,':le'=>$le,':br'=>$br,
      ':scope_uuid'=>$scopeUuid,
      ':legacy_identifier'=>$order['legacyIdentifier'] ?? null,
      ':order_number'=>$order['number'] ?? null,
      ':usi'=>$order['usi'] ?? null,
      ':last_modified_utc'=>$lm['utc'],
      ':last_modified_raw'=>$lm['raw'],
      ':cancelled'=>(int)!!($order['cancelled'] ?? false),
      ':blocked'=>(int)!!($order['blocked'] ?? false),
      ':consolidated'=>(int)!!($order['consolidated'] ?? false),
      ':module'=>$order['module'] ?? null,
      ':conveyance_type'=>$order['conveyanceType'] ?? null,
      ':clerk'=>$order['clerk'] ?? null,
      ':order_date'=>date_only($order['orderDate'] ?? null),
      ':economic_date'=>date_only($order['economicDate'] ?? null),
      ':transport_date'=>date_only($order['transportDate'] ?? null),
      ':etd_date'=>$etd,
      ':atd_date'=>$atd,
      ':eta_date'=>$eta,
      ':ata_date'=>$ata,
      ':inco_terms'=>$order['incoTerms'] ?? null,
      ':incoterm_place'=>$order['incotermPlace'] ?? null,
      ':mt_freight_term'=>$order['mainTransportFreightTerm'] ?? null,
      ':movement_scope'=>$order['movementScope'] ?? null,

      ':customer_code'=>$custCode,
      ':customer_name'=>$custName,
      ':customer_city'=>$custCity,
      ':customer_state'=>$custState,
      ':customer_country'=>$custCountry,

      ':shipper_code'=>$shipCode,
      ':shipper_name'=>$shipName,
      ':shipper_country'=>$shipCountry,

      ':consignee_code'=>$conCode,
      ':consignee_name'=>$conName,
      ':consignee_country'=>$conCountry,

      ':departure_country'=>$depCountry,
      ':departure_unlocode'=>$depUnl,
      ':departure_name'=>$depName,

      ':destination_country'=>$dstCountry,
      ':destination_unlocode'=>$dstUnl,
      ':destination_name'=>$dstName,

      ':pieces'=>isset($order['pieces']) ? (int)$order['pieces'] : null,
      ':gross_weight_kg'=>dec_or_null($grossKg),
      ':chargeable_weight_kg'=>dec_or_null($chargeKg),
      ':volume_m3'=>dec_or_null($volM3),
      ':nature_of_goods'=>$order['natureOfGoods'] ?? null,
      ':dgr'=>(int)!!($order['dgr'] ?? false),

      ':financial_status'=>$financialStatus,
      ':status_to_closed_date'=>$statusToClosedDate,
      ':cost_center_code'=>$costCenterCode,

      ':raw_hash'=>$rawHash,
      ':raw_json'=>$rawJson,
    ]);

    $orderId = scope_select_order_id($pdo, $org, $le, $br, $scopeUuid);

    // si no cambió el hash y ya existía => no reprocesar hijos
    if ($existing && $rawHash && isset($existing['raw_hash']) && $existing['raw_hash'] === $rawHash) {
      $pdo->commit();
      return [
        'order_id'=>$orderId,
        'skipped_children'=>true,
        'upserted_milestones'=>0,
        'upserted_references'=>0,
        'upserted_transport_orders'=>0,
        'upserted_jobcosting_entries'=>0,
        'upserted_jobcosting_totals'=>0,
        'updated_ops'=>0,
      ];
    }

    $upMil = scope_upsert_milestones($pdo, $orderId, $order);
    $upRef = scope_upsert_references($pdo, $orderId, $order);
    $upTO  = scope_upsert_transport_orders($pdo, $orderId, $order);
    $upJC  = scope_upsert_jobcosting_entries($pdo, $orderId, $order);
    $upTot = scope_upsert_jobcosting_totals($pdo, $orderId, $totLoc, $totOrg);
    $upOps = scope_update_ops_from_milestones($pdo, $orderId);

    $pdo->commit();

    return [
      'order_id'=>$orderId,
      'skipped_children'=>false,
      'upserted_milestones'=>$upMil,
      'upserted_references'=>$upRef,
      'upserted_transport_orders'=>$upTO,
      'upserted_jobcosting_entries'=>$upJC,
      'upserted_jobcosting_totals'=>$upTot,
      'updated_ops'=>$upOps,
    ];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

/* =========================
   Milestones
========================= */
function scope_upsert_milestones(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['milestones'] ?? null, 'milestone');
  if (!$items) return 0;

  $sql = "
    INSERT INTO scope_order_milestones (
      order_id, code, description,
      completed, active, public_visible,
      planned_time_utc, planned_time_raw,
      actual_time_utc, actual_time_raw,
      fingerprint
    ) VALUES (
      :order_id, :code, :description,
      :completed, :active, :public_visible,
      :planned_time_utc, :planned_time_raw,
      :actual_time_utc, :actual_time_raw,
      :fingerprint
    )
    ON DUPLICATE KEY UPDATE
      description = VALUES(description),
      completed = VALUES(completed),
      active = VALUES(active),
      public_visible = VALUES(public_visible),
      planned_time_utc = VALUES(planned_time_utc),
      planned_time_raw = VALUES(planned_time_raw),
      actual_time_utc = VALUES(actual_time_utc),
      actual_time_raw = VALUES(actual_time_raw),
      updated_at = CURRENT_TIMESTAMP
  ";
  $stmt = $pdo->prepare($sql);

  $count = 0;
  foreach ($items as $m) {
    $code = trim((string)($m['code'] ?? ''));
    if ($code === '') continue;

    $planned = iso_to_utc_parts($m['plannedTime'] ?? null);
    $actual  = iso_to_utc_parts($m['actualTime'] ?? null);

    // fingerprint (order_id + fingerprint) es unique. usamos code+planned_raw para estabilidad.
    $fingerprint = sha64($code . '|' . (string)($planned['raw'] ?? ''));

    $stmt->execute([
      ':order_id' => $orderId,
      ':code' => $code,
      ':description' => $m['description'] ?? null,
      ':completed' => (int)!!($m['completed'] ?? false),
      ':active' => (int)!!($m['active'] ?? false),
      ':public_visible' => (int)!!($m['publicVisible'] ?? false),
      ':planned_time_utc' => $planned['utc'],
      ':planned_time_raw' => $planned['raw'],
      ':actual_time_utc' => $actual['utc'],
      ':actual_time_raw' => $actual['raw'],
      ':fingerprint' => $fingerprint,
    ]);
    $count++;
  }
  return $count;
}

/* =========================
   References
========================= */
function scope_upsert_references(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['references'] ?? null, 'reference');
  if (!$items) return 0;

  $sql = "INSERT IGNORE INTO scope_order_references (order_id, ref_code, ref_number)
          VALUES (:order_id,:ref_code,:ref_number)";
  $stmt = $pdo->prepare($sql);

  $count = 0;
  foreach ($items as $r) {
    $code = trim((string)($r['code'] ?? ''));
    $num  = trim((string)($r['number'] ?? ''));
    if ($code === '' || $num === '') continue;

    $stmt->execute([
      ':order_id'=>$orderId,
      ':ref_code'=>$code,
      ':ref_number'=>$num,
    ]);
    $count++;
  }
  return $count;
}

/* =========================
   Transport Orders
========================= */
function scope_upsert_transport_orders(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['transportOrders'] ?? null, 'transportOrder');
  if (!$items) return 0;

  $sql = "
    INSERT INTO scope_transport_orders (
      order_id,
      transport_order_uuid, transport_order_number,
      transport_type, conveyance_type, transport_date,
      pickup_partner_code, pickup_partner_name, pickup_country, pickup_city,
      unloading_partner_code, unloading_partner_name, unloading_country, unloading_city,
      pickup_unlocode, pickup_location_name,
      unloading_unlocode, unloading_location_name,
      pickup_window_start, pickup_window_end,
      delivery_window_start, delivery_window_end,
      pieces, gross_weight_kg, chargeable_weight_kg, volume_m3,
      freight_term, nature_of_goods,
      fingerprint
    ) VALUES (
      :order_id,
      :to_uuid, :to_number,
      :transport_type, :conveyance_type, :transport_date,
      :pickup_code, :pickup_name, :pickup_country, :pickup_city,
      :unload_code, :unload_name, :unload_country, :unload_city,
      :pickup_unlocode, :pickup_loc_name,
      :unload_unlocode, :unload_loc_name,
      :pickup_ws, :pickup_we,
      :del_ws, :del_we,
      :pieces, :gw, :cw, :vol,
      :freight_term, :nog,
      :fingerprint
    )
    ON DUPLICATE KEY UPDATE
      transport_order_number = VALUES(transport_order_number),
      transport_type = VALUES(transport_type),
      conveyance_type = VALUES(conveyance_type),
      transport_date = VALUES(transport_date),
      pickup_partner_code = VALUES(pickup_partner_code),
      pickup_partner_name = VALUES(pickup_partner_name),
      pickup_country = VALUES(pickup_country),
      pickup_city = VALUES(pickup_city),
      unloading_partner_code = VALUES(unloading_partner_code),
      unloading_partner_name = VALUES(unloading_partner_name),
      unloading_country = VALUES(unloading_country),
      unloading_city = VALUES(unloading_city),
      pickup_unlocode = VALUES(pickup_unlocode),
      pickup_location_name = VALUES(pickup_location_name),
      unloading_unlocode = VALUES(unloading_unlocode),
      unloading_location_name = VALUES(unloading_location_name),
      pickup_window_start = VALUES(pickup_window_start),
      pickup_window_end = VALUES(pickup_window_end),
      delivery_window_start = VALUES(delivery_window_start),
      delivery_window_end = VALUES(delivery_window_end),
      pieces = VALUES(pieces),
      gross_weight_kg = VALUES(gross_weight_kg),
      chargeable_weight_kg = VALUES(chargeable_weight_kg),
      volume_m3 = VALUES(volume_m3),
      freight_term = VALUES(freight_term),
      nature_of_goods = VALUES(nature_of_goods),
      updated_at = CURRENT_TIMESTAMP
  ";
  $stmt = $pdo->prepare($sql);

  $count = 0;
  foreach ($items as $to) {
    $toUuid = trim((string)($to['identifier'] ?? ''));
    if ($toUuid === '') continue;

    $pickupCode    = arr_get($to, ['pickup','partner','code'], null);
    $pickupName    = arr_get($to, ['pickup','partner','name'], null);
    $pickupCountry = arr_get($to, ['pickup','address','country'], null);
    $pickupCity    = arr_get($to, ['pickup','address','city'], null);

    $unloadCode    = arr_get($to, ['unloading','partner','code'], null);
    $unloadName    = arr_get($to, ['unloading','partner','name'], null);
    $unloadCountry = arr_get($to, ['unloading','address','country'], null);
    $unloadCity    = arr_get($to, ['unloading','address','city'], null);

    $pickupUnl     = arr_get($to, ['pickupLocation','unlocode'], null);
    $pickupLocName = arr_get($to, ['pickupLocation','name'], null);

    $unloadUnl     = arr_get($to, ['unloadingLocation','unlocode'], null);
    $unloadLocName = arr_get($to, ['unloadingLocation','name'], null);

    $pickupWs = date_only(arr_get($to, ['pickupTimeWindow','startInclusive','date'], null));
    $pickupWe = date_only(arr_get($to, ['pickupTimeWindow','endInclusive','date'], null));
    $delWs    = date_only(arr_get($to, ['deliveryTimeWindow','startInclusive','date'], null));
    $delWe    = date_only(arr_get($to, ['deliveryTimeWindow','endInclusive','date'], null));

    $gw = arr_get($to, ['grossWeight','value'], null);
    $cw = arr_get($to, ['chargeableWeight','value'], null);
    $vol= arr_get($to, ['volume','value'], null);

    $stmt->execute([
      ':order_id'=>$orderId,
      ':to_uuid'=>$toUuid,
      ':to_number'=>$to['number'] ?? null,
      ':transport_type'=>$to['transportType'] ?? null,
      ':conveyance_type'=>$to['conveyanceType'] ?? null,
      ':transport_date'=>date_only($to['transportDate'] ?? null),

      ':pickup_code'=>$pickupCode,
      ':pickup_name'=>$pickupName,
      ':pickup_country'=>$pickupCountry,
      ':pickup_city'=>$pickupCity,

      ':unload_code'=>$unloadCode,
      ':unload_name'=>$unloadName,
      ':unload_country'=>$unloadCountry,
      ':unload_city'=>$unloadCity,

      ':pickup_unlocode'=>$pickupUnl,
      ':pickup_loc_name'=>$pickupLocName,

      ':unload_unlocode'=>$unloadUnl,
      ':unload_loc_name'=>$unloadLocName,

      ':pickup_ws'=>$pickupWs,
      ':pickup_we'=>$pickupWe,
      ':del_ws'=>$delWs,
      ':del_we'=>$delWe,

      ':pieces'=>isset($to['pieces']) ? (int)$to['pieces'] : null,
      ':gw'=>dec_or_null($gw),
      ':cw'=>dec_or_null($cw),
      ':vol'=>dec_or_null($vol),

      ':freight_term'=>$to['freightTerm'] ?? null,
      ':nog'=>$to['natureOfGoods'] ?? null,

      ':fingerprint'=>sha64($toUuid),
    ]);
    $count++;
  }
  return $count;
}

/* =========================
   Jobcosting Entries
========================= */
function scope_upsert_jobcosting_entries(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['jobcostingEntries'] ?? null, 'jobcostingEntry');
  if (!$items) return 0;

  $sql = "
    INSERT INTO scope_jobcosting_entries (
      order_id,
      entry_type, charge_type_code, cost_center_code,
      booking_date, economic_date, invoice_date,
      amount_value, amount_currency,
      tax_value, tax_currency,
      local_amount_value, local_amount_currency,
      local_tax_value, local_tax_currency,
      org_amount_value, org_amount_currency,
      partner_code, partner_name,
      subledger_account_number, general_ledger_account_number,
      entry_number, external_number, tax_key,
      fingerprint
    ) VALUES (
      :order_id,
      :entry_type, :charge_type_code, :cost_center_code,
      :booking_date, :economic_date, :invoice_date,
      :amount_value, :amount_currency,
      :tax_value, :tax_currency,
      :local_amount_value, :local_amount_currency,
      :local_tax_value, :local_tax_currency,
      :org_amount_value, :org_amount_currency,
      :partner_code, :partner_name,
      :subledger, :gl,
      :entry_number, :external_number, :tax_key,
      :fingerprint
    )
    ON DUPLICATE KEY UPDATE
      entry_type = VALUES(entry_type),
      charge_type_code = VALUES(charge_type_code),
      cost_center_code = VALUES(cost_center_code),
      booking_date = VALUES(booking_date),
      economic_date = VALUES(economic_date),
      invoice_date = VALUES(invoice_date),
      amount_value = VALUES(amount_value),
      amount_currency = VALUES(amount_currency),
      tax_value = VALUES(tax_value),
      tax_currency = VALUES(tax_currency),
      local_amount_value = VALUES(local_amount_value),
      local_amount_currency = VALUES(local_amount_currency),
      local_tax_value = VALUES(local_tax_value),
      local_tax_currency = VALUES(local_tax_currency),
      org_amount_value = VALUES(org_amount_value),
      org_amount_currency = VALUES(org_amount_currency),
      partner_code = VALUES(partner_code),
      partner_name = VALUES(partner_name),
      subledger_account_number = VALUES(subledger_account_number),
      general_ledger_account_number = VALUES(general_ledger_account_number),
      entry_number = VALUES(entry_number),
      external_number = VALUES(external_number),
      tax_key = VALUES(tax_key),
      updated_at = CURRENT_TIMESTAMP
  ";
  $stmt = $pdo->prepare($sql);

  $count = 0;
  foreach ($items as $e) {

    // ✅ FIX 1: Scope manda "type" (income/payable)
    $entryType = trim((string)($e['type'] ?? $e['entryType'] ?? ''));

    // si por alguna razón viene vacío, mejor saltar que romper (aunque no debería)
    if ($entryType === '') continue;

    $chargeCode = arr_get($e, ['chargeType','code'], null);
    $cc         = arr_get($e, ['costCenter','code'], null);

    $booking = date_only($e['bookingDate'] ?? null);
    $econ    = date_only($e['economicDate'] ?? null);
    $invoice = date_only($e['invoiceDate'] ?? null);

    $amountVal = arr_get($e, ['amount','value'], null);
    $amountCur = arr_get($e, ['amount','currency'], null);

    // ✅ FIX 2: Scope manda taxAmount / localTaxAmount
    $taxVal = arr_get($e, ['taxAmount','value'], null);
    $taxCur = arr_get($e, ['taxAmount','currency'], null);

    $lAmtVal = arr_get($e, ['localAmount','value'], null);
    $lAmtCur = arr_get($e, ['localAmount','currency'], null);

    $lTaxVal = arr_get($e, ['localTaxAmount','value'], null);
    $lTaxCur = arr_get($e, ['localTaxAmount','currency'], null);

    $oAmtVal = arr_get($e, ['organizationAmount','value'], null);
    $oAmtCur = arr_get($e, ['organizationAmount','currency'], null);

    $pCode = arr_get($e, ['partner','code'], null);
    $pName = arr_get($e, ['partner','name'], null);

    $subledger = $e['subledgerAccountNumber'] ?? null;
    $gl        = $e['generalLedgerAccountNumber'] ?? null;

    // ✅ FIX 3: Scope manda "number" (no entryNumber)
    $entryNo = $e['number'] ?? $e['entryNumber'] ?? null;
    $extNo   = $e['externalNumber'] ?? null;
    $taxKey  = $e['taxKey'] ?? null;

    // fingerprint anti duplicados (estable)
    $fingerprint = sha64(
      $entryType.'|'.(string)$chargeCode.'|'.(string)$entryNo.'|'.(string)$extNo.'|'.(string)$booking.'|'.(string)$econ.'|'.(string)$invoice.'|'.(string)$pCode.'|'.(string)$lAmtVal
    );

    $stmt->execute([
      ':order_id' => $orderId,

      ':entry_type' => $entryType,
      ':charge_type_code' => $chargeCode,
      ':cost_center_code' => $cc,

      ':booking_date' => $booking,
      ':economic_date' => $econ,
      ':invoice_date' => $invoice,

      ':amount_value' => dec_or_null($amountVal),
      ':amount_currency' => $amountCur,

      ':tax_value' => dec_or_null($taxVal),
      ':tax_currency' => $taxCur,

      ':local_amount_value' => dec_or_null($lAmtVal),
      ':local_amount_currency' => $lAmtCur,

      ':local_tax_value' => dec_or_null($lTaxVal),
      ':local_tax_currency' => $lTaxCur,

      ':org_amount_value' => dec_or_null($oAmtVal),
      ':org_amount_currency' => $oAmtCur,

      ':partner_code' => $pCode,
      ':partner_name' => $pName,

      ':subledger' => $subledger,
      ':gl' => $gl,

      ':entry_number' => $entryNo,
      ':external_number' => $extNo,
      ':tax_key' => $taxKey,

      ':fingerprint' => $fingerprint,
    ]);

    $count++;
  }

  return $count;
}

/* =========================
   Jobcosting Totals (TU ESQUEMA EXACTO)
========================= */
function scope_upsert_jobcosting_totals(PDO $pdo, int $orderId, $totLoc, $totOrg): int {
  if (!is_array($totLoc) && !is_array($totOrg)) return 0;

  $locCurrency = pick_first($totLoc, ['currency','localCurrency','curr','code']);
  $orgCurrency = pick_first($totOrg, ['currency','organizationCurrency','orgCurrency','curr','code']);

  // LOCAL
  $lBookedIncome = pick_first($totLoc, ['bookedIncome','localBookedIncome']);
  $lBookedCost   = pick_first($totLoc, ['bookedCost','localBookedCost']);
  $lTransitBookedIncome = pick_first($totLoc, ['transitBookedIncome','localTransitBookedIncome']);
  $lTransitBookedCost   = pick_first($totLoc, ['transitBookedCost','localTransitBookedCost']);
  $lTotalIncome  = pick_first($totLoc, ['totalIncome','localTotalIncome']);
  $lTotalCost    = pick_first($totLoc, ['totalCost','localTotalCost']);

  $lProfit = pick_first($totLoc, ['profit','localProfit']);
  if ($lProfit === null && is_numeric($lTotalIncome) && is_numeric($lTotalCost)) {
    $lProfit = (float)$lTotalIncome - (float)$lTotalCost;
  }

  $lMargin = pick_first($totLoc, ['grossMargin','localGrossMargin']);
  if ($lMargin === null && is_numeric($lProfit) && is_numeric($lTotalIncome) && (float)$lTotalIncome != 0.0) {
    $lMargin = ((float)$lProfit / (float)$lTotalIncome);
  }

  // ORG
  $oTotalIncome = pick_first($totOrg, ['totalIncome','orgTotalIncome','organizationTotalIncome']);
  $oTotalCost   = pick_first($totOrg, ['totalCost','orgTotalCost','organizationTotalCost']);

  $oProfit = pick_first($totOrg, ['profit','orgProfit','organizationProfit']);
  if ($oProfit === null && is_numeric($oTotalIncome) && is_numeric($oTotalCost)) {
    $oProfit = (float)$oTotalIncome - (float)$oTotalCost;
  }

  $oMargin = pick_first($totOrg, ['grossMargin','orgGrossMargin','organizationGrossMargin']);
  if ($oMargin === null && is_numeric($oProfit) && is_numeric($oTotalIncome) && (float)$oTotalIncome != 0.0) {
    $oMargin = ((float)$oProfit / (float)$oTotalIncome);
  }

  $sql = "
    INSERT INTO scope_jobcosting_totals (
      order_id,
      local_currency,
      local_booked_income,
      local_booked_cost,
      local_transit_booked_income,
      local_transit_booked_cost,
      local_total_income,
      local_total_cost,
      local_profit,
      local_gross_margin,
      org_currency,
      org_total_income,
      org_total_cost,
      org_profit,
      org_gross_margin
    ) VALUES (
      :order_id,
      :local_currency,
      :local_booked_income,
      :local_booked_cost,
      :local_transit_booked_income,
      :local_transit_booked_cost,
      :local_total_income,
      :local_total_cost,
      :local_profit,
      :local_gross_margin,
      :org_currency,
      :org_total_income,
      :org_total_cost,
      :org_profit,
      :org_gross_margin
    )
    ON DUPLICATE KEY UPDATE
      local_currency = VALUES(local_currency),
      local_booked_income = VALUES(local_booked_income),
      local_booked_cost = VALUES(local_booked_cost),
      local_transit_booked_income = VALUES(local_transit_booked_income),
      local_transit_booked_cost = VALUES(local_transit_booked_cost),
      local_total_income = VALUES(local_total_income),
      local_total_cost = VALUES(local_total_cost),
      local_profit = VALUES(local_profit),
      local_gross_margin = VALUES(local_gross_margin),
      org_currency = VALUES(org_currency),
      org_total_income = VALUES(org_total_income),
      org_total_cost = VALUES(org_total_cost),
      org_profit = VALUES(org_profit),
      org_gross_margin = VALUES(org_gross_margin),
      updated_at = CURRENT_TIMESTAMP
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':order_id'=>$orderId,
    ':local_currency'=>$locCurrency,
    ':local_booked_income'=>dec_or_null($lBookedIncome),
    ':local_booked_cost'=>dec_or_null($lBookedCost),
    ':local_transit_booked_income'=>dec_or_null($lTransitBookedIncome),
    ':local_transit_booked_cost'=>dec_or_null($lTransitBookedCost),
    ':local_total_income'=>dec_or_null($lTotalIncome),
    ':local_total_cost'=>dec_or_null($lTotalCost),
    ':local_profit'=>dec_or_null($lProfit),
    ':local_gross_margin'=>dec_or_null($lMargin),
    ':org_currency'=>$orgCurrency,
    ':org_total_income'=>dec_or_null($oTotalIncome),
    ':org_total_cost'=>dec_or_null($oTotalCost),
    ':org_profit'=>dec_or_null($oProfit),
    ':org_gross_margin'=>dec_or_null($oMargin),
  ]);

  return 1;
}

/* =========================
   Ops derivado (no-op seguro por ahora)
   Luego lo definimos con reglas de milestones.
========================= */
function scope_update_ops_from_milestones(PDO $pdo, int $orderId): int {
  return 0;
}