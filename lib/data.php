<?php
/**
 * Data layer.
 *
 * PRIMARY store is MySQL (fast, no API quota). Google Sheets is kept as a
 * synced mirror/backup (see lib/sync.php). The entity_* API is unchanged so
 * every view keeps working; only the storage backend moved to MySQL.
 *
 * entities() also drives: the MySQL schema (lib/db.php) and the Sheets sync.
 */

function entities(): array {
    return [
        'users'        => ['cat' => 'master', 'sheet' => 'Users', 'last' => 'J', 'prefix' => 'usr', 'fields' => ['id','name','email','password','role','branch','phone','active','createdAt','modules']],
        'branches'     => ['cat' => 'master', 'sheet' => 'Branches', 'last' => 'E', 'prefix' => 'br', 'fields' => ['id','name','address','phone','active']],
        'brands'       => ['cat' => 'master', 'sheet' => 'Brands', 'last' => 'C', 'prefix' => 'brd', 'fields' => ['id','name','active']],
        'tests'        => ['cat' => 'master', 'sheet' => 'Tests', 'last' => 'C', 'prefix' => 'tst', 'fields' => ['id','name','active']],
        'doctors'      => ['cat' => 'master', 'sheet' => 'Doctors', 'last' => 'F', 'prefix' => 'doc', 'fields' => ['id','name','qualifications','speciality','contactNo','chamberLocations']],
        'patients'     => ['cat' => 'operations', 'sheet' => 'Patients', 'last' => 'N', 'prefix' => 'pat', 'dateFields' => ['date','deliveryDate'], 'fields' => ['id','sn','date','name','ageSex','contactNo','address','referral','test','branch','payment','reportStatus','deliveryDate','remarks']],
        'appointments' => ['cat' => 'operations', 'sheet' => 'Appointments', 'last' => 'M', 'prefix' => 'apt', 'dateFields' => ['date'], 'fields' => ['id','date','time','patientName','ageSex','contactNo','address','referral','test','remarks','branch','payment','reportStatus']],
        'enquiries'    => ['cat' => 'operations', 'sheet' => 'Enquiries', 'last' => 'J', 'prefix' => 'enq', 'fields' => ['id','enquiryDate','patientName','contactNo','address','source','enquiryDetails','tentativeAppointmentDate','status','statusDate']],
        'potential-ha' => ['cat' => 'operations', 'sheet' => 'PotentialHA', 'last' => 'L', 'prefix' => 'pha', 'fields' => ['id','date','name','ageSex','contactNo','referral','branch','diagnosisRight','diagnosisLeft','remarks','followUp1Date','followUp2Date']],
        'approvals'    => ['cat' => 'operations', 'sheet' => 'Approvals', 'last' => 'N', 'prefix' => 'apr', 'fields' => ['id','createdAt','requestedBy','requestedByRole','module','moduleLabel','action','targetId','summary','oldValue','newValue','status','reviewedBy','reviewedAt']],
        'ha-sales'     => ['cat' => 'sales', 'sheet' => 'HASales', 'last' => 'Y', 'prefix' => 'has', 'fields' => ['id','sn','date','name','contactNo','address','referral','branch','haModel','serialNumber','side','billNo','source','mrp','sellingPrice','remarks','warrantyCard','freeOfBattery','model2','rightSerialNo','leftSerialNo','chargerSerialNo','paymentStatus','dueAmount','advanceAmount']],
        'ha-stock'     => ['cat' => 'sales', 'sheet' => 'HAStock', 'last' => 'K', 'prefix' => 'stk', 'fields' => ['id','date','brand','model','serialNumber','mfdDate','source','branch','soldDate','remarks','mrp']],
        'ha-repairs'   => ['cat' => 'sales', 'sheet' => 'HARepairs', 'last' => 'X', 'prefix' => 'rep', 'fields' => ['id','date','patientName','phoneNumber','brand','model','ear','oldSerialNoRight','newSerialNoRight','oldSerialNoLeft','newSerialNoLeft','oldChargerSerial','newChargerSerial','serviceType','cost','status','remarks','branch','courierNo','sentDate','receivedDate','deliveryDate','source','purchaseDate']],
        'accessories'  => ['cat' => 'sales', 'sheet' => 'Accessories', 'last' => 'J', 'prefix' => 'acc', 'fields' => ['id','date','name','model','quantity','source','branch','remarks','price','type']],
        'invoices'     => ['cat' => 'sales', 'sheet' => 'Invoices', 'last' => 'M', 'prefix' => 'inv', 'json' => ['items'], 'fields' => ['id','invoiceNo','date','patientName','age','address','phoneNumber','items','subtotal','discount','total','paymentMode','branch']],
        'expenses'     => ['cat' => 'hr', 'sheet' => 'Expenses', 'last' => 'F', 'prefix' => 'exp', 'fields' => ['id','date','staffName','description','amount','branch']],
        'staff-ta'     => ['cat' => 'hr', 'sheet' => 'StaffTA', 'last' => 'K', 'prefix' => 'ta', 'fields' => ['id','staffName','month','date','from','to','place','doctorName','purpose','expense','totalAmount']],
        'dr-payments'  => ['cat' => 'hr', 'sheet' => 'DrPayments', 'last' => 'I', 'prefix' => 'dp', 'fields' => ['id','date','doctorReferral','noOfPatients','fromDate','tillDate','amount','visitLocation','visitedBy']],
        'dr-visits'    => ['cat' => 'hr', 'sheet' => 'DrVisits', 'last' => 'H', 'prefix' => 'dv', 'fields' => ['id','dateOfVisit','doctorName','speciality','locationOfVisit','discussion','remarks','tentativeFollowUp']],
        'attendance'   => ['cat' => 'hr', 'sheet' => 'Attendance', 'last' => 'Q', 'prefix' => 'att', 'fields' => ['id','date','userName','checkInTime','checkInLocation','checkOutTime','checkOutLocation','totalHours','status','checkIn2Time','checkIn2Location','checkOut2Time','checkOut2Location','checkIn3Time','checkIn3Location','checkOut3Time','checkOut3Location']],
    ];
}

function entity_cfg(string $name): array {
    $e = entities();
    if (!isset($e[$name])) throw new Exception("Unknown entity: $name");
    return $e[$name];
}

/** MySQL table name for an entity (dashes → underscores). */
function entity_table(string $name): string {
    return str_replace('-', '_', $name);
}

/* ============================================================
 * MySQL-backed entity API (used by all views)
 * ============================================================ */

function db_field_value(array $c, string $field, $value): string {
    if (!empty($c['json']) && in_array($field, $c['json'], true)) {
        return json_encode(is_array($value) ? $value : (json_decode((string)$value, true) ?: []));
    }
    if (!empty($c['dateFields']) && in_array($field, $c['dateFields'], true)) {
        return normalize_date_string((string)$value);
    }
    return (string)$value;
}

function db_to_assoc(array $c, array $row): array {
    $out = [];
    foreach ($c['fields'] as $f) {
        $v = $row[$f] ?? '';
        if (!empty($c['json']) && in_array($f, $c['json'], true)) {
            $dec = json_decode(($v === null || $v === '') ? '[]' : (string)$v, true);
            $out[$f] = is_array($dec) ? $dec : [];
        } else {
            $out[$f] = $v === null ? '' : (string)$v;
        }
    }
    return $out;
}

/** Per-request cache (in $GLOBALS['__entity_cache']) avoids repeat queries. */
function entity_all(string $name): array {
    if (isset($GLOBALS['__entity_cache'][$name])) return $GLOBALS['__entity_cache'][$name];
    $c = entity_cfg($name);
    $rows = db_all("SELECT * FROM `" . entity_table($name) . "`");
    $out = [];
    foreach ($rows as $r) $out[] = db_to_assoc($c, $r);
    $GLOBALS['__entity_cache'][$name] = $out;
    return $out;
}

function entity_bust(string $name): void { unset($GLOBALS['__entity_cache'][$name]); }

function entity_find(string $name, string $id): ?array {
    $c = entity_cfg($name);
    $row = db_row("SELECT * FROM `" . entity_table($name) . "` WHERE `id`=? LIMIT 1", [$id]);
    return $row ? db_to_assoc($c, $row) : null;
}

function entity_insert(string $name, array $a): string {
    $c = entity_cfg($name);
    if (empty($a['id'])) $a['id'] = $c['prefix'] . '-' . (time() . rand(100, 999));
    $cols = []; $ph = []; $vals = []; $upd = [];
    foreach ($c['fields'] as $f) {
        $cols[] = "`$f`";
        $ph[] = '?';
        $vals[] = db_field_value($c, $f, $a[$f] ?? '');
        if ($f !== 'id') $upd[] = "`$f`=VALUES(`$f`)";
    }
    $sql = "INSERT INTO `" . entity_table($name) . "` (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ") ON DUPLICATE KEY UPDATE " . implode(',', $upd);
    db_exec($sql, $vals);
    entity_bust($name);
    return $a['id'];
}

function entity_update(string $name, array $a): bool {
    $c = entity_cfg($name);
    if (empty($a['id'])) return false;
    $sets = []; $vals = [];
    foreach ($c['fields'] as $f) {
        if ($f === 'id') continue;
        $sets[] = "`$f`=?";
        $vals[] = db_field_value($c, $f, $a[$f] ?? '');
    }
    $vals[] = $a['id'];
    $n = db_exec("UPDATE `" . entity_table($name) . "` SET " . implode(',', $sets) . " WHERE `id`=?", $vals);
    entity_bust($name);
    // If the row didn't exist yet, insert it (keeps behaviour forgiving).
    if ($n === 0 && !entity_find($name, $a['id'])) { entity_insert($name, $a); }
    return true;
}

function entity_delete(string $name, string $id): bool {
    db_exec("DELETE FROM `" . entity_table($name) . "` WHERE `id`=?", [$id]);
    entity_bust($name);
    return true;
}

/* ============================================================
 * Sheet helpers — ONLY used by the sync engine (lib/sync.php)
 * ============================================================ */

function sheet_rows(string $name): array {
    $c = entity_cfg($name);
    return sheets_get(spreadsheet_id($c['cat']), $c['sheet'] . '!A2:' . $c['last']);
}

function sheet_row_to_assoc(string $name, array $row, int $idx): array {
    $c = entity_cfg($name);
    $out = [];
    foreach ($c['fields'] as $i => $field) {
        $val = cell($row, $i);
        if ($field === 'id' && $val === '') $val = $c['prefix'] . '-' . $idx;
        if (!empty($c['json']) && in_array($field, $c['json'], true)) {
            $decoded = json_decode($val ?: '[]', true);
            $out[$field] = is_array($decoded) ? $decoded : [];
            continue;
        }
        $out[$field] = $val;
    }
    return $out;
}

function sheet_all(string $name): array {
    $out = [];
    foreach (sheet_rows($name) as $i => $row) $out[] = sheet_row_to_assoc($name, $row, $i);
    return $out;
}

function assoc_to_sheet_row(string $name, array $a): array {
    $c = entity_cfg($name);
    $row = [];
    foreach ($c['fields'] as $field) {
        $v = $a[$field] ?? '';
        if (!empty($c['json']) && in_array($field, $c['json'], true)) {
            $v = json_encode(is_array($v) ? $v : (json_decode((string)$v, true) ?: []));
        } elseif (!empty($c['dateFields']) && in_array($field, $c['dateFields'], true)) {
            $v = normalize_date_string((string)$v);
        }
        $row[] = $v;
    }
    return $row;
}

/* ============================================================
 * Branch / brand / test / config helpers (now MySQL-backed)
 * ============================================================ */

function active_branch_names(): array {
    $names = [];
    foreach (entity_all('branches') as $b) {
        if (strtolower($b['active']) !== 'false' && $b['name'] !== '') $names[] = $b['name'];
    }
    return $names ?: ['Serampore', 'Konnagar'];
}

function active_brand_names(): array {
    $names = [];
    foreach (entity_all('brands') as $b) {
        if (strtolower($b['active']) !== 'false' && $b['name'] !== '') $names[] = $b['name'];
    }
    return $names;
}

function active_test_names(): array {
    $names = [];
    foreach (entity_all('tests') as $t) {
        if (strtolower($t['active']) !== 'false' && $t['name'] !== '') $names[] = $t['name'];
    }
    if (!$names) $names = ['PTA','TYMP','ENG','OAE','ABR','VEMP','SRT/SDS','TDT','SISI','ETF','SP. THX','SWALLOW THX','VOICE THX'];
    return $names;
}

function get_config(): array {
    $out = [];
    foreach (db_all("SELECT `k`,`v` FROM `app_config`") as $r) {
        $out[$r['k']] = $r['v'];
    }
    return $out;
}

function config_value(string $key, ?string $default = null): ?string {
    $r = db_row("SELECT `v` FROM `app_config` WHERE `k`=? LIMIT 1", [$key]);
    return $r ? $r['v'] : $default;
}

function set_config_value(string $key, string $value): void {
    db_exec("INSERT INTO `app_config` (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v`=VALUES(`v`)", [$key, $value]);
}

/* ---------- Service catalog (MySQL) ---------- */

function services_all(): array {
    return db_all("SELECT `id`,`category`,`description`,`price` FROM `services` ORDER BY `category`,`description`");
}

/** Upsert a service, matching an existing row by original category+description. */
function service_save(string $category, string $description, string $price, string $origCat = '', string $origDesc = ''): void {
    $existing = db_row("SELECT `id` FROM `services` WHERE `category`=? AND `description`=? LIMIT 1", [$origCat, $origDesc]);
    if ($existing) {
        db_exec("UPDATE `services` SET `category`=?,`description`=?,`price`=? WHERE `id`=?", [$category, $description, $price, $existing['id']]);
    } else {
        db_exec("INSERT INTO `services` (`category`,`description`,`price`) VALUES (?,?,?)", [$category, $description, $price]);
    }
}
