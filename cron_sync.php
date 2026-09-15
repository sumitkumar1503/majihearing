<?php
/**
 * Auto-sync entry point (MySQL <-> Google Sheets).
 *
 * Hostinger cron job (hPanel -> Advanced -> Cron Jobs), run every 15 minutes.
 * Schedule "0,15,30,45 * * * *" (or the 15-min preset) with command:
 *   /usr/bin/php /home/USER/domains/majihearing.eu/public_html/cron_sync.php
 *
 * It only performs a sync when the interval configured in Settings has elapsed,
 * so you can change the frequency from the UI without editing the cron line.
 *
 * Web fallback (if cron is unavailable): a page/uptime pinger can hit
 *   https://majihearing.eu/cron_sync.php?token=YOUR_SETUP_TOKEN
 */
chdir(__DIR__);
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/lib/approvals.php';
require __DIR__ . '/lib/reports.php';
require __DIR__ . '/lib/sync.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // Web access requires the setup token to prevent abuse.
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals((string)(cfg()['setup_token'] ?? ''), (string)($_GET['token'] ?? ''))) {
        http_response_code(403); exit('Forbidden');
    }
}

$force = ($isCli && in_array('--force', $argv ?? [], true)) || isset($_GET['force']);

if (!db_available()) { echo "MySQL unavailable\n"; exit(1); }

if ($force || sync_is_due()) {
    $res = sync_now(false);
    echo 'Synced at ' . ($res['at'] ?? '') . (isset($res['skipped']) ? ' (skipped: ' . $res['skipped'] . ')' : '') . "\n";
} else {
    echo 'Not due yet (interval ' . sync_interval_minutes() . 'm). Last sync: ' . config_value('last_sync_at', 'never') . "\n";
}
