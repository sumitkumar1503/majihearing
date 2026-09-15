<?php
/**
 * Data layer — generic read/write over the SAME Google Sheets & columns the
 * Next.js app uses. Each entity maps to a sheet tab + ordered field list.
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

function entity_rows(string $name): array {
    $c = entity_cfg($name);
    return sheets_get(spreadsheet_id($c['cat']), $c['sheet'] . '!A2:' . $c['last']);
}

function row_to_assoc(string $name, array $row, int $idx): array {
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

function entity_all(string $name): array {
    $rows = entity_rows($name);
    $out = [];
    foreach ($rows as $i => $row) $out[] = row_to_assoc($name, $row, $i);
    return $out;
}

function entity_find(string $name, string $id): ?array {
    foreach (entity_all($name) as $r) {
        if (($r['id'] ?? null) === $id) return $r;
    }
    return null;
}

function assoc_to_row(string $name, array $a): array {
    $c = entity_cfg($name);
    $row = [];
    foreach ($c['fields'] as $field) {
        $v = $a[$field] ?? '';
        if (!empty($c['json']) && in_array($field, $c['json'], true)) {
            $v = json_encode(is_array($v) ? $v : []);
        } elseif (!empty($c['dateFields']) && in_array($field, $c['dateFields'], true)) {
            $v = normalize_date_string((string)$v);
        }
        $row[] = $v;
    }
    return $row;
}

function entity_insert(string $name, array $a): string {
    $c = entity_cfg($name);
    if (empty($a['id'])) $a['id'] = $c['prefix'] . '-' . (time() . rand(100, 999));
    sheets_ensure_sheet(spreadsheet_id($c['cat']), $c['sheet']);
    sheets_append(spreadsheet_id($c['cat']), $c['sheet'], assoc_to_row($name, $a));
    return $a['id'];
}

function entity_update(string $name, array $a): bool {
    $c = entity_cfg($name);
    $rows = entity_rows($name);
    foreach ($rows as $i => $row) {
        if (cell($row, 0) === ($a['id'] ?? '')) {
            $rowNum = $i + 2;
            sheets_update(spreadsheet_id($c['cat']), $c['sheet'] . '!A' . $rowNum . ':' . $c['last'] . $rowNum, [assoc_to_row($name, $a)]);
            return true;
        }
    }
    return false;
}

function entity_delete(string $name, string $id): bool {
    $c = entity_cfg($name);
    $rows = entity_rows($name);
    foreach ($rows as $i => $row) {
        if (cell($row, 0) === $id) {
            sheets_delete_row(spreadsheet_id($c['cat']), $c['sheet'], $i + 1);
            return true;
        }
    }
    return false;
}

/* ---------- Branch / brand / test helpers ---------- */

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

function get_config(): array {
    $out = [];
    foreach (sheets_get(spreadsheet_id('master'), 'Config!A2:B') as $r) {
        if (cell($r, 0) !== '') $out[cell($r, 0)] = cell($r, 1);
    }
    return $out;
}

function set_config_value(string $key, string $value): void {
    $sid = spreadsheet_id('master');
    sheets_ensure_sheet($sid, 'Config');
    $rows = sheets_get($sid, 'Config!A2:B');
    foreach ($rows as $i => $r) {
        if (cell($r, 0) === $key) { sheets_update($sid, 'Config!A' . ($i + 2) . ':B' . ($i + 2), [[$key, $value]]); return; }
    }
    sheets_append($sid, 'Config', [$key, $value]);
}

function active_test_names(): array {
    $names = [];
    foreach (entity_all('tests') as $t) {
        if (strtolower($t['active']) !== 'false' && $t['name'] !== '') $names[] = $t['name'];
    }
    if (!$names) $names = ['PTA','TYMP','ENG','OAE','ABR','VEMP','SRT/SDS','TDT','SISI','ETF','SP. THX','SWALLOW THX','VOICE THX'];
    return $names;
}
