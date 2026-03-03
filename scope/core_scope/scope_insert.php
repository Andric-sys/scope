<?php
declare(strict_types=1);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Convierte ISO8601 con zona a:
 * - raw: el string original
 * - utc: DATETIME "Y-m-d H:i:s" en UTC (o null si no se puede)
 */
function iso_to_utc_parts(?string $iso): array {
  $iso = $iso !== null ? trim($iso) : '';
  if ($iso === '') return ['raw' => null, 'utc' => null];

  try {
    $dt = new DateTimeImmutable($iso);
    $utc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    return ['raw' => $iso, 'utc' => $utc];
  } catch (Throwable $e) {
    return ['raw' => $iso, 'utc' => null];
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
  if (is_numeric($v)) return (string)$v;
  return null;
}

function sha64(string $s): string {
  return hash('sha256', $s);
}

function arr_get(array $a, array $path, $default=null) {
  $cur = $a;
  foreach ($path as $k) {
    if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
    $cur = $cur[$k];
  }
  return $cur;
}

/**
 * Scope a veces manda {"milestone":[...]} o null; a veces 1 objeto.
 */
function normalize_list($maybeList, string $childKey): array {
  if (!is_array($maybeList)) return [];
  if (isset($maybeList[$childKey]) && is_array($maybeList[$childKey])) {
    $x = $maybeList[$childKey];
    if (array_keys($x) !== range(0, count($x)-1)) return [$x];
    return $x;
  }
  return [];
}

/**
 * partnerRelatedData: puede venir lista o objeto.
 */
function first_partner_related_data(array $order): ?array {
  if (!isset($order['partnerRelatedData']) || !is_array($order['partnerRelatedData'])) return null;
  $prd = $order['partnerRelatedData'];
  if (array_keys($prd) === range(0, count($prd)-1)) return $prd[0] ?? null;
  return $prd;
}

/**
 * Inserta/actualiza una orden completa en tu modelo.
 * Retorna contadores: order_id, upserts de hijos.
 */
function scope_upsert_order(PDO $pdo, array $order): array {
  $cfg = require __DIR__ . '/config.php';
  $tenant = $cfg['scope'];

  $org = (string)$tenant['organizationCode'];
  $le  = (string)$tenant['legalEntityCode'];
  $br  = (string)$tenant['branchCode'];

  // JSON RAW
  $rawJson = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($rawJson === false) $rawJson = null;
  $rawHash = $rawJson ? sha64($rawJson) : null;

  // lastModified
  $lm = iso_to_utc_parts($order['lastModified'] ?? null);

  // PartnerRelatedData (finanzas / cost center)
  $prd0 = first_partner_related_data($order);
  $financialStatus = is_array($prd0) ? ($prd0['financialStatus'] ?? null) : null;
  $statusToClosedDate = is_array($prd0) ? date_only($prd0['statusToClosedDate'] ?? null) : null;
  $costCenterCode = is_array($prd0) ? arr_get($prd0, ['costCenter','code'], null) : null;

  // Totals
  $totLoc = is_array($prd0) ? ($prd0['jobcostingTotalsLocalCurrency'] ?? null) : null;
  $totOrg = is_array($prd0) ? ($prd0['jobcostingTotalsOrganizationCurrency'] ?? null) : null;

  // Peso/volumen
  $grossKg = arr_get($order, ['grossWeight','value'], null);
  $chargeKg = arr_get($order, ['chargeableWeight','value'], null);
  $volM3 = arr_get($order, ['volume','value'], null);

  // Customer
  $custCode = arr_get($order, ['customer','partner','code'], null);
  $custName = arr_get($order, ['customer','partner','name'], null);
  $custCity = arr_get($order, ['customer','address','city'], null);
  $custState= arr_get($order, ['customer','address','state'], null);
  $custCountry= arr_get($order, ['customer','address','country'], null);

  // Shipper
  $shipCode = arr_get($order, ['shipper','partner','code'], null);
  $shipName = arr_get($order, ['shipper','partner','name'], null);
  $shipCountry = arr_get($order, ['shipper','address','country'], null);

  // Consignee
  $conCode = arr_get($order, ['consignee','partner','code'], null);
  $conName = arr_get($order, ['consignee','partner','name'], null);
  $conCountry = arr_get($order, ['consignee','address','country'], null);

  // Departure / Destination
  $depCountry = arr_get($order, ['departure','country'], null);
  $depUnl = arr_get($order, ['departure','unlocode'], null);
  $depName = arr_get($order, ['departure','name'], null);

  $dstCountry = arr_get($order, ['destination','country'], null);
  $dstUnl = arr_get($order, ['destination','unlocode'], null);
  $dstName = arr_get($order, ['destination','name'], null);

  // ETD/ATD/ETA/ATA (en JSON vienen como {"date":"YYYY-MM-DD"})
  $etd = date_only(arr_get($order, ['etd','date'], null));
  $atd = date_only(arr_get($order, ['atd','date'], null));
  $eta = date_only(arr_get($order, ['eta','date'], null));
  $ata = date_only(arr_get($order, ['ata','date'], null));

  // Validación mínima
  $scopeUuid = (string)($order['identifier'] ?? '');
  if ($scopeUuid === '') {
    throw new RuntimeException('El JSON no trae identifier (UUID) de la orden.');
  }

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
    )
    VALUES (
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
    ':org' => $org, ':le' => $le, ':br' => $br,
    ':scope_uuid' => $scopeUuid,
    ':legacy_identifier' => $order['legacyIdentifier'] ?? null,
    ':order_number' => $order['number'] ?? null,
    ':usi' => $order['usi'] ?? null,
    ':last_modified_utc' => $lm['utc'],
    ':last_modified_raw' => $lm['raw'],
    ':cancelled' => (int)!!($order['cancelled'] ?? false),
    ':blocked' => (int)!!($order['blocked'] ?? false),
    ':consolidated' => (int)!!($order['consolidated'] ?? false),
    ':module' => $order['module'] ?? null,
    ':conveyance_type' => $order['conveyanceType'] ?? null,
    ':clerk' => $order['clerk'] ?? null,
    ':order_date' => date_only($order['orderDate'] ?? null),
    ':economic_date' => date_only($order['economicDate'] ?? null),
    ':transport_date' => date_only($order['transportDate'] ?? null),
    ':etd_date' => $etd,
    ':atd_date' => $atd,
    ':eta_date' => $eta,
    ':ata_date' => $ata,
    ':inco_terms' => $order['incoTerms'] ?? null,
    ':incoterm_place' => $order['incotermPlace'] ?? null,
    ':mt_freight_term' => $order['mainTransportFreightTerm'] ?? null,
    ':movement_scope' => $order['movementScope'] ?? null,
    ':customer_code' => $custCode,
    ':customer_name' => $custName,
    ':customer_city' => $custCity,
    ':customer_state' => $custState,
    ':customer_country' => $custCountry,
    ':shipper_code' => $shipCode,
    ':shipper_name' => $shipName,
    ':shipper_country' => $shipCountry,
    ':consignee_code' => $conCode,
    ':consignee_name' => $conName,
    ':consignee_country' => $conCountry,
    ':departure_country' => $depCountry,
    ':departure_unlocode' => $depUnl,
    ':departure_name' => $depName,
    ':destination_country' => $dstCountry,
    ':destination_unlocode' => $dstUnl,
    ':destination_name' => $dstName,
    ':pieces' => isset($order['pieces']) ? (int)$order['pieces'] : null,
    ':gross_weight_kg' => dec_or_null($grossKg),
    ':chargeable_weight_kg' => dec_or_null($chargeKg),
    ':volume_m3' => dec_or_null($volM3),
    ':nature_of_goods' => $order['natureOfGoods'] ?? null,
    ':dgr' => (int)!!($order['dgr'] ?? false),
    ':financial_status' => $financialStatus,
    ':status_to_closed_date' => $statusToClosedDate,
    ':cost_center_code' => $costCenterCode,
    ':raw_hash' => $rawHash,
    ':raw_json' => $rawJson,
  ]);

  // Obtener order_id (por UNIQUE tenant+scope_uuid)
  $sel = $pdo->prepare("
    SELECT id FROM scope_orders
    WHERE organization_code=? AND legal_entity_code=? AND branch_code=? AND scope_uuid=?
    LIMIT 1
  ");
  $sel->execute([$org, $le, $br, $scopeUuid]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new RuntimeException('No se pudo obtener order_id después del upsert.');
  $orderId = (int)$row['id'];

  $upMil = scope_upsert_milestones($pdo, $orderId, $order);
  $upRef = scope_upsert_references($pdo, $orderId, $order);
  $upTO  = scope_upsert_transport_orders($pdo, $orderId, $order);
  $upJC  = scope_upsert_jobcosting_entries($pdo, $orderId, $order);
  $upTot = scope_upsert_jobcosting_totals($pdo, $orderId, $totLoc, $totOrg);
  $upOps = scope_update_ops_from_milestones($pdo, $orderId);

  return [
    'order_id' => $orderId,
    'upserted_milestones' => $upMil,
    'upserted_references' => $upRef,
    'upserted_transport_orders' => $upTO,
    'upserted_jobcosting_entries' => $upJC,
    'upserted_jobcosting_totals' => $upTot,
    'updated_ops' => $upOps,
  ];
}

function scope_upsert_milestones(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['milestones'] ?? null, 'milestone');
  if (!$items) return 0;

  /**
   * IMPORTANTE:
   * El fingerprint debe ser ESTABLE para que no inserte duplicados.
   * Regla usada:
   *   fingerprint = sha256( code + '|' + plannedTimeRaw )
   * Si no hay plannedTime -> code + '|'
   */
  $sql = "
    INSERT INTO scope_order_milestones (
      order_id, code, description, completed, active, public_visible,
      planned_time_utc, planned_time_raw,
      actual_time_utc, actual_time_raw,
      fingerprint
    ) VALUES (
      :order_id,:code,:description,:completed,:active,:public_visible,
      :planned_time_utc,:planned_time_raw,
      :actual_time_utc,:actual_time_raw,
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

function scope_upsert_references(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['references'] ?? null, 'reference');
  if (!$items) return 0;

  $sql = "
    INSERT INTO scope_order_references (order_id, ref_code, ref_number)
    VALUES (:order_id,:ref_code,:ref_number)
    ON DUPLICATE KEY UPDATE
      ref_number = VALUES(ref_number)
  ";
  $stmt = $pdo->prepare($sql);

  $count = 0;
  foreach ($items as $r) {
    $code = trim((string)($r['code'] ?? ''));
    $num  = trim((string)($r['number'] ?? ''));
    if ($code === '' || $num === '') continue;

    $stmt->execute([
      ':order_id' => $orderId,
      ':ref_code' => $code,
      ':ref_number' => $num,
    ]);
    $count++;
  }

  return $count;
}

function scope_upsert_transport_orders(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['transportOrders'] ?? null, 'transportOrder');
  if (!$items) return 0;

  /**
   * fingerprint estable:
   *   transportOrderUUID es único, lo usamos como fingerprint base.
   */
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

    $pickupCode = arr_get($to, ['pickup','partner','code'], null);
    $pickupName = arr_get($to, ['pickup','partner','name'], null);
    $pickupCountry = arr_get($to, ['pickup','address','country'], null);
    $pickupCity = arr_get($to, ['pickup','address','city'], null);

    $unloadCode = arr_get($to, ['unloading','partner','code'], null);
    $unloadName = arr_get($to, ['unloading','partner','name'], null);
    $unloadCountry = arr_get($to, ['unloading','address','country'], null);
    $unloadCity = arr_get($to, ['unloading','address','city'], null);

    $pickupUnl = arr_get($to, ['pickupLocation','unlocode'], null);
    $pickupLocName = arr_get($to, ['pickupLocation','name'], null);

    $unloadUnl = arr_get($to, ['unloadingLocation','unlocode'], null);
    $unloadLocName = arr_get($to, ['unloadingLocation','name'], null);

    $pickupWs = date_only(arr_get($to, ['pickupTimeWindow','startInclusive','date'], null));
    $pickupWe = date_only(arr_get($to, ['pickupTimeWindow','endInclusive','date'], null));
    $delWs    = date_only(arr_get($to, ['deliveryTimeWindow','startInclusive','date'], null));
    $delWe    = date_only(arr_get($to, ['deliveryTimeWindow','endInclusive','date'], null));

    $gw = arr_get($to, ['grossWeight','value'], null);
    $cw = arr_get($to, ['chargeableWeight','value'], null);
    $vol= arr_get($to, ['volume','value'], null);

    $fingerprint = sha64($toUuid);

    $stmt->execute([
      ':order_id' => $orderId,
      ':to_uuid' => $toUuid,
      ':to_number' => $to['number'] ?? null,
      ':transport_type' => $to['transportType'] ?? null,
      ':conveyance_type' => $to['conveyanceType'] ?? null,
      ':transport_date' => date_only($to['transportDate'] ?? null),
      ':pickup_code' => $pickupCode,
      ':pickup_name' => $pickupName,
      ':pickup_country' => $pickupCountry,
      ':pickup_city' => $pickupCity,
      ':unload_code' => $unloadCode,
      ':unload_name' => $unloadName,
      ':unload_country' => $unloadCountry,
      ':unload_city' => $unloadCity,
      ':pickup_unlocode' => $pickupUnl,
      ':pickup_loc_name' => $pickupLocName,
      ':unload_unlocode' => $unloadUnl,
      ':unload_loc_name' => $unloadLocName,
      ':pickup_ws' => $pickupWs,
      ':pickup_we' => $pickupWe,
      ':del_ws' => $delWs,
      ':del_we' => $delWe,
      ':pieces' => isset($to['pieces']) ? (int)$to['pieces'] : null,
      ':gw' => dec_or_null($gw),
      ':cw' => dec_or_null($cw),
      ':vol' => dec_or_null($vol),
      ':freight_term' => $to['freightTerm'] ?? null,
      ':nog' => $to['natureOfGoods'] ?? null,
      ':fingerprint' => $fingerprint,
    ]);
    $count++;
  }

  return $count;
}

function scope_upsert_jobcosting_entries(PDO $pdo, int $orderId, array $order): int {
  $items = normalize_list($order['jobcostingEntries'] ?? null, 'jobcostingEntry');
  if (!$items) return 0;

  /**
   * fingerprint estable para evitar duplicados:
   * Si viene "number" y "type" suele ser bastante estable.
   * Usamos (type|number|externalNumber|chargeTypeCode|amount|currency|invoiceDate|partnerCode)
   */
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
    $type = trim((string)($e['type'] ?? ''));
    if ($type === '') continue;

    $chargeTypeCode = arr_get($e, ['chargeType','code'], null);
    $costCenterCode = arr_get($e, ['costCenter','code'], null);

    $amountV = arr_get($e, ['amount','value'], null);
    $amountC = arr_get($e, ['amount','currency'], null);

    $taxV = arr_get($e, ['taxAmount','value'], null);
    $taxC = arr_get($e, ['taxAmount','currency'], null);

    $localAmountV = arr_get($e, ['localAmount','value'], null);
    $localAmountC = arr_get($e, ['localAmount','currency'], null);

    $localTaxV = arr_get($e, ['localTaxAmount','value'], null);
    $localTaxC = arr_get($e, ['localTaxAmount','currency'], null);

    $orgAmountV = arr_get($e, ['organizationAmount','value'], null);
    $orgAmountC = arr_get($e, ['organizationAmount','currency'], null);

    $partnerCode = arr_get($e, ['partner','code'], null);
    $partnerName = arr_get($e, ['partner','name'], null);

    $subledger = $e['subledgerAccountNumber'] ?? null;
    $gl = $e['generalLedgerAccountNumber'] ?? null;

    $entryNumber = $e['number'] ?? null;
    $externalNumber = $e['externalNumber'] ?? null;
    $taxKey = $e['taxKey'] ?? null;

    $fpBase = implode('|', [
      $type,
      (string)($entryNumber ?? ''),
      (string)($externalNumber ?? ''),
      (string)($chargeTypeCode ?? ''),
      (string)($amountV ?? ''), (string)($amountC ?? ''),
      (string)($e['invoiceDate'] ?? ''),
      (string)($partnerCode ?? ''),
      (string)($subledger ?? ''),
      (string)($gl ?? ''),
      (string)($taxKey ?? ''),
    ]);
    $fingerprint = sha64($fpBase);

    $stmt->execute([
      ':order_id' => $orderId,
      ':entry_type' => $type,
      ':charge_type_code' => $chargeTypeCode,
      ':cost_center_code' => $costCenterCode,
      ':booking_date' => date_only($e['bookingDate'] ?? null),
      ':economic_date' => date_only($e['economicDate'] ?? null),
      ':invoice_date' => date_only($e['invoiceDate'] ?? null),
      ':amount_value' => dec_or_null($amountV),
      ':amount_currency' => $amountC,
      ':tax_value' => dec_or_null($taxV),
      ':tax_currency' => $taxC,
      ':local_amount_value' => dec_or_null($localAmountV),
      ':local_amount_currency' => $localAmountC,
      ':local_tax_value' => dec_or_null($localTaxV),
      ':local_tax_currency' => $localTaxC,
      ':org_amount_value' => dec_or_null($orgAmountV),
      ':org_amount_currency' => $orgAmountC,
      ':partner_code' => $partnerCode,
      ':partner_name' => $partnerName,
      ':subledger' => $subledger,
      ':gl' => $gl,
      ':entry_number' => $entryNumber,
      ':external_number' => $externalNumber,
      ':tax_key' => $taxKey,
      ':fingerprint' => $fingerprint,
    ]);
    $count++;
  }

  return $count;
}

function scope_upsert_jobcosting_totals(PDO $pdo, int $orderId, $totLoc, $totOrg): int {
  $localCurrency = is_array($totLoc) ? arr_get($totLoc, ['totalIncome','currency'], null) : null;
  $orgCurrency   = is_array($totOrg) ? arr_get($totOrg, ['totalIncome','currency'], null) : null;

  $sql = "
    INSERT INTO scope_jobcosting_totals (
      order_id,
      local_currency,
      local_booked_income, local_booked_cost,
      local_transit_booked_income, local_transit_booked_cost,
      local_total_income, local_total_cost, local_profit, local_gross_margin,
      org_currency,
      org_total_income, org_total_cost, org_profit, org_gross_margin
    ) VALUES (
      :order_id,
      :local_currency,
      :l_bi, :l_bc,
      :l_tbi, :l_tbc,
      :l_ti, :l_tc, :l_p, :l_gm,
      :org_currency,
      :o_ti, :o_tc, :o_p, :o_gm
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
    ':order_id' => $orderId,

    ':local_currency' => $localCurrency ?? (is_array($totLoc) ? arr_get($totLoc, ['bookedIncome','currency'], null) : null),
    ':l_bi' => dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['bookedIncome','value'], null) : null),
    ':l_bc' => dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['bookedCost','value'], null) : null),
    ':l_tbi'=> dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['transitBookedIncome','value'], null) : null),
    ':l_tbc'=> dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['transitBookedCost','value'], null) : null),
    ':l_ti' => dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['totalIncome','value'], null) : null),
    ':l_tc' => dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['totalCost','value'], null) : null),
    ':l_p'  => dec_or_null(is_array($totLoc) ? arr_get($totLoc, ['profit','value'], null) : null),
    ':l_gm' => dec_or_null(is_array($totLoc) ? ($totLoc['grossMargin'] ?? null) : null),

    ':org_currency' => $orgCurrency ?? (is_array($totOrg) ? arr_get($totOrg, ['bookedIncome','currency'], null) : null),
    ':o_ti' => dec_or_null(is_array($totOrg) ? arr_get($totOrg, ['totalIncome','value'], null) : null),
    ':o_tc' => dec_or_null(is_array($totOrg) ? arr_get($totOrg, ['totalCost','value'], null) : null),
    ':o_p'  => dec_or_null(is_array($totOrg) ? arr_get($totOrg, ['profit','value'], null) : null),
    ':o_gm' => dec_or_null(is_array($totOrg) ? ($totOrg['grossMargin'] ?? null) : null),
  ]);

  return 1;
}

function scope_update_ops_from_milestones(PDO $pdo, int $orderId): int {
  $m = $pdo->prepare("
    SELECT code, description, planned_time_utc, actual_time_utc
    FROM scope_order_milestones
    WHERE order_id = ?
    ORDER BY
      active DESC,
      (actual_time_utc IS NOT NULL) DESC,
      actual_time_utc DESC,
      planned_time_utc DESC
    LIMIT 1
  ");
  $m->execute([$orderId]);
  $row = $m->fetch(PDO::FETCH_ASSOC);
  if (!$row) return 0;

  $sql = "
    INSERT INTO scope_order_ops (
      order_id, stage_code, stage_description,
      stage_planned_time_utc, stage_actual_time_utc
    ) VALUES (
      :order_id, :stage_code, :stage_description,
      :planned, :actual
    )
    ON DUPLICATE KEY UPDATE
      stage_code = VALUES(stage_code),
      stage_description = VALUES(stage_description),
      stage_planned_time_utc = VALUES(stage_planned_time_utc),
      stage_actual_time_utc = VALUES(stage_actual_time_utc),
      updated_at = CURRENT_TIMESTAMP
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':order_id' => $orderId,
    ':stage_code' => $row['code'],
    ':stage_description' => $row['description'],
    ':planned' => $row['planned_time_utc'],
    ':actual' => $row['actual_time_utc'],
  ]);

  return 1;
}
