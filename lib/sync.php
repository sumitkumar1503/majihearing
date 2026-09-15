<?php
/**
 * Bidirectional MySQL <-> Google Sheets sync.
 *
 * Model: MySQL is the source of truth (the website reads/writes it).
 * Google Sheets is a mirror/backup, kept identical on every sync:
 *   1. Rows that exist in the Sheet but NOT in MySQL are imported into MySQL
 *      (captures manual edits made directly in the spreadsheet).
 *   2. The Sheet is then rewritten from MySQL, so both ends match exactly.
 *
 * Runs from cron (hourly) or the "Sync Now" button in Settings.
 */

/** Sync one entity between MySQL and its sheet. */
function sync_entity(string $name, array &$log, array &$counts): void {
    $c = entity_cfg($name);
    $sid = spreadsheet_id($c['cat']);

    // Read the sheet (throws on hard error → we skip overwrite for safety).
    $sheetRaw = sheet_rows($name);
    $sheetAssoc = [];
    foreach ($sheetRaw as $i => $row) {
        $a = sheet_row_to_assoc($name, $row, $i);
        if (($a['id'] ?? '') !== '') $sheetAssoc[$a['id']] = $a;
    }

    // Current MySQL rows.
    $dbAssoc = [];
    foreach (entity_all($name) as $a) $dbAssoc[$a['id']] = $a;

    // 1) Import sheet-only rows into MySQL.
    $imported = 0;
    foreach ($sheetAssoc as $id => $a) {
        if (!isset($dbAssoc[$id])) { entity_insert($name, $a); $dbAssoc[$id] = $a; $imported++; }
    }

    // 2) Rewrite the sheet from MySQL (single update; pad to clear leftovers).
    entity_bust($name);
    $dbRows = entity_all($name);
    $out = [];
    foreach ($dbRows as $a) $out[] = assoc_to_sheet_row($name, $a);
    $prev = count($sheetRaw);
    $numFields = count($c['fields']);
    for ($i = count($out); $i < $prev; $i++) $out[] = array_fill(0, $numFields, '');

    sheets_ensure_sheet($sid, $c['sheet']);
    if ($out) {
        $endRow = count($out) + 1;
        sheets_update($sid, $c['sheet'] . '!A2:' . $c['last'] . $endRow, $out);
    }

    $counts[$name] = ['db' => count($dbRows), 'imported' => $imported];
    $log[] = sprintf('%-14s db=%d imported=%d', $name, count($dbRows), $imported);
}

/** Sync the daily-revenue grid (DS-<branch>-<month> tabs). */
function sync_daily(array &$log): void {
    $sid = spreadsheet_id('operations');

    // Group MySQL daily cells by branch|month.
    $dbGroups = [];
    foreach (db_all("SELECT `branch`,`month`,`test_name`,`day`,`amount`,`quantity` FROM `daily_entries`") as $r) {
        $dbGroups[$r['branch'] . '|' . $r['month']][] = $r;
    }

    // Import any DS tabs not present in MySQL.
    $imported = 0;
    foreach (sheets_tab_titles($sid) as $t) {
        if (strpos($t, 'DS-') !== 0) continue;
        if (!preg_match('/^DS-(.*)-(\d{4}-\d{2})$/', $t, $m)) continue;
        $branch = $m[1]; $month = $m[2]; $key = $branch . '|' . $month;
        if (isset($dbGroups[$key])) continue; // MySQL authoritative
        $entries = [];
        foreach (sheets_get($sid, $t . '!A2:D') as $r) {
            $entries[] = ['testName' => cell($r, 0), 'day' => (int)cell($r, 1), 'amount' => (float)cell($r, 2), 'quantity' => (float)cell($r, 3)];
        }
        if ($entries) { save_daily_sheet($branch, $month, $entries); $imported++; $log[] = "daily import $t (" . count($entries) . ')'; }
    }

    // Export only the months changed via the app (dirty set) to save quota.
    $dirty = json_decode(config_value('daily_dirty', '[]') ?: '[]', true);
    $dirty = is_array($dirty) ? array_unique($dirty) : [];
    foreach ($dirty as $key) {
        if (strpos($key, '|') === false) continue;
        [$branch, $month] = explode('|', $key, 2);
        $rows = [];
        foreach (db_all("SELECT `test_name`,`day`,`amount`,`quantity` FROM `daily_entries` WHERE `branch`=? AND `month`=?", [$branch, $month]) as $r) {
            $rows[] = [$r['test_name'], $r['day'], $r['amount'], $r['quantity']];
        }
        $tab = "DS-$branch-$month";
        sheets_ensure_sheet($sid, $tab);
        sheets_update($sid, "$tab!A1:D1", [['Test', 'Day', 'Amount', 'Quantity']]);
        sheets_clear($sid, "$tab!A2:D100000");
        if ($rows) sheets_update($sid, "$tab!A2:D" . (count($rows) + 1), $rows);
        $log[] = "daily export $tab (" . count($rows) . ')';
    }
    set_config_value('daily_dirty', '[]');
    if ($imported) $log[] = "daily tabs imported: $imported";
}

/** Sync the key/value Config (master 'Config' tab). */
function sync_config(array &$log): void {
    $sid = spreadsheet_id('master');
    $sheetKv = [];
    foreach (sheets_get($sid, 'Config!A2:B') as $r) {
        if (cell($r, 0) !== '') $sheetKv[cell($r, 0)] = cell($r, 1);
    }
    $dbKv = get_config();
    // Import sheet-only keys.
    foreach ($sheetKv as $k => $v) {
        if ($k === 'daily_dirty' || $k === 'sync_lock') continue;
        if (!array_key_exists($k, $dbKv)) { set_config_value($k, (string)$v); $dbKv[$k] = $v; }
    }
    // Rewrite Config sheet from MySQL (skip internal keys).
    $rows = [];
    foreach ($dbKv as $k => $v) {
        if (in_array($k, ['daily_dirty', 'sync_lock', 'last_sync_result'], true)) continue;
        $rows[] = [$k, $v];
    }
    sheets_ensure_sheet($sid, 'Config');
    sheets_clear($sid, 'Config!A2:B100000');
    if ($rows) sheets_update($sid, 'Config!A2:B' . (count($rows) + 1), $rows);
    $log[] = 'config keys=' . count($rows);
}

/** Sync the service catalog (master 'Services' tab: category/description/price). */
function sync_services(array &$log, bool $importOnly = false): void {
    $sid = spreadsheet_id('master');
    $db = services_all();
    $dbKeys = [];
    foreach ($db as $s) $dbKeys[strtolower($s['category'] . '|' . $s['description'])] = true;
    // Import sheet-only services.
    $imported = 0;
    foreach (sheets_get($sid, 'Services!A2:C') as $r) {
        $cat = cell($r, 0) ?: 'Service'; $desc = cell($r, 1); $price = cell($r, 2);
        if ($desc === '') continue;
        if (!isset($dbKeys[strtolower($cat . '|' . $desc)])) { service_save($cat, $desc, $price); $imported++; }
    }
    if ($importOnly) { $log[] = "services imported=$imported"; return; }
    // Rewrite Services sheet from MySQL.
    $rows = [];
    foreach (services_all() as $s) $rows[] = [$s['category'], $s['description'], $s['price']];
    sheets_ensure_sheet($sid, 'Services');
    sheets_clear($sid, 'Services!A2:C100000');
    if ($rows) sheets_update($sid, 'Services!A2:C' . (count($rows) + 1), $rows);
    $log[] = 'services=' . count($rows) . " imported=$imported";
}

/** Mark a daily (branch,month) as changed so the next sync exports it. */
function mark_daily_dirty(string $branch, string $month): void {
    $dirty = json_decode(config_value('daily_dirty', '[]') ?: '[]', true);
    if (!is_array($dirty)) $dirty = [];
    $dirty[] = "$branch|$month";
    set_config_value('daily_dirty', json_encode(array_values(array_unique($dirty))));
}

/**
 * Run a full sync. $importOnly skips writing back to Sheets (fast first import).
 * Returns a result summary array.
 */
function sync_now(bool $importOnly = false): array {
    // Simple lock to avoid overlapping runs.
    $lock = config_value('sync_lock', '');
    if ($lock && (time() - (int)$lock) < 600) {
        return ['skipped' => 'A sync is already running', 'at' => date('c')];
    }
    set_config_value('sync_lock', (string)time());
    @set_time_limit(0);
    @ignore_user_abort(true);
    $log = []; $counts = [];
    try {
        foreach (array_keys(entities()) as $name) {
            try {
                if ($importOnly) { sync_import_only($name, $log, $counts); }
                else { sync_entity($name, $log, $counts); }
            } catch (\Throwable $e) {
                $log[] = "ERROR $name: " . $e->getMessage();
            }
        }
        try { sync_daily($log); } catch (\Throwable $e) { $log[] = 'ERROR daily: ' . $e->getMessage(); }
        try { sync_services($log, $importOnly); } catch (\Throwable $e) { $log[] = 'ERROR services: ' . $e->getMessage(); }
        if (!$importOnly) { try { sync_config($log); } catch (\Throwable $e) { $log[] = 'ERROR config: ' . $e->getMessage(); } }
        else { try { sync_config_import($log); } catch (\Throwable $e) { $log[] = 'ERROR config: ' . $e->getMessage(); } }
    } finally {
        set_config_value('sync_lock', '');
    }
    $result = ['at' => date('c'), 'importOnly' => $importOnly, 'counts' => $counts, 'log' => $log];
    set_config_value('last_sync_at', date('c'));
    set_config_value('last_sync_result', json_encode($result));
    return $result;
}

/** Import-only variant for an entity (sheet → MySQL, no write-back). */
function sync_import_only(string $name, array &$log, array &$counts): void {
    $dbIds = [];
    foreach (entity_all($name) as $a) $dbIds[$a['id']] = true;
    $imported = 0;
    foreach (sheet_all($name) as $a) {
        if (($a['id'] ?? '') === '') continue;
        if (!isset($dbIds[$a['id']])) { entity_insert($name, $a); $imported++; }
    }
    entity_bust($name);
    $counts[$name] = ['imported' => $imported];
    $log[] = sprintf('%-14s imported=%d', $name, $imported);
}

/** Import-only Config (sheet → MySQL). */
function sync_config_import(array &$log): void {
    $sid = spreadsheet_id('master');
    $dbKv = get_config();
    $n = 0;
    foreach (sheets_get($sid, 'Config!A2:B') as $r) {
        $k = cell($r, 0);
        if ($k === '' || array_key_exists($k, $dbKv)) continue;
        set_config_value($k, cell($r, 1)); $n++;
    }
    $log[] = "config imported=$n";
}

/** Minutes between auto-syncs (configurable in Settings). */
function sync_interval_minutes(): int {
    return max(5, (int)config_value('sync_interval_minutes', '60'));
}

/** True if enough time has elapsed since the last successful sync. */
function sync_is_due(): bool {
    $last = config_value('last_sync_at', '');
    if (!$last) return true;
    return (time() - strtotime($last)) >= sync_interval_minutes() * 60;
}
