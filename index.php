<?php
/** Front controller — bootstraps, guards auth, routes to a view. */
session_start();
date_default_timezone_set('Asia/Kolkata');

require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/icons.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/approvals.php';
require __DIR__ . '/lib/reports.php';
require __DIR__ . '/lib/sync.php';

$page = $_GET['page'] ?? 'home';
$page = preg_replace('/[^a-z0-9_-]/', '', (string)$page);
if ($page === '') $page = 'home';

// Token-protected render self-test (temporary diagnostic).
if ($page === 'selftest') {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals((string)(cfg()['setup_token'] ?? ''), (string)($_GET['token'] ?? ''))) { http_response_code(403); exit('Forbidden'); }
    $_SESSION['user'] = ['id'=>'diag','name'=>'Diag Admin','email'=>'diag@test','role'=>'admin','branch'=>'All','modules'=>[]];
    $pages = ['dashboard','appointments','patients','doctors','enquiries','potential-ha','dr-visits','dr-payments','staff-ta','expenses','ha-stock','ha-sales','accessories','ha-repairs','attendance','daily-sheet','invoices','reports','settings','whatsapp','requests','users'];
    foreach ($pages as $p) {
        $f = __DIR__ . '/views/' . $p . '.php';
        if (!file_exists($f)) { echo "MISS  $p\n"; continue; }
        $_GET = ['page'=>$p]; ob_start(); $err=null;
        try { include $f; } catch (\Throwable $e) { $err = get_class($e).': '.$e->getMessage().' @'.basename($e->getFile()).':'.$e->getLine(); }
        ob_end_clean();
        echo ($err ? "FAIL  $p -> $err" : "OK    $p") . "\n";
    }
    unset($_SESSION['user']);
    exit;
}

// One-time DB setup + initial import from Google Sheets (token-protected,
// so it works before any MySQL user rows exist to log in with).
if ($page === 'db-setup') {
    header('Content-Type: text/plain; charset=utf-8');
    $token = $_GET['token'] ?? '';
    if (!hash_equals((string)(cfg()['setup_token'] ?? ''), (string)$token)) { http_response_code(403); echo "Forbidden: bad token"; exit; }
    if (!db_available()) { http_response_code(500); echo "MySQL connection FAILED — check config.php mysql settings."; exit; }
    echo "MySQL connected. Importing from Google Sheets...\n\n";
    $res = sync_now(true); // import-only (does not overwrite sheets)
    foreach ($res['log'] as $line) echo $line . "\n";
    echo "\nDONE. You can now log in. Delete/rotate the setup token when finished.\n";
    exit;
}

// Public routes (no login required)
if ($page === 'home') { require __DIR__ . '/views/home.php'; exit; }
if ($page === 'login') { require __DIR__ . '/views/login.php'; exit; }

// Notifications JSON feed (polled by the header bell)
if ($page === 'notifications-json') {
    header('Content-Type: application/json');
    if (!is_logged_in()) { echo '[]'; exit; }
    $feed = [];
    foreach (approvals_for_current_user() as $a) {
        $when = ($a['status'] === 'pending') ? $a['createdAt'] : ($a['reviewedAt'] ?: $a['createdAt']);
        $feed[] = ['id' => $a['id'], 'moduleLabel' => $a['moduleLabel'], 'summary' => $a['summary'], 'status' => $a['status'], 'requestedBy' => $a['requestedBy'], 'when' => $when];
    }
    echo json_encode($feed);
    exit;
}
if ($page === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

require_login($page);

// Manual "Sync Now" (admin) — mirror MySQL <-> Google Sheets.
if ($page === 'sync-now') {
    if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
    $res = sync_now(false);
    set_flash(isset($res['skipped']) ? 'error' : 'success', isset($res['skipped']) ? $res['skipped'] : 'Sync complete — Google Sheets updated.');
    redirect('index.php?page=settings&tab=database');
}

// CSV export of any table (admin) — download a backup of a sheet.
if ($page === 'export') {
    if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
    $entity = preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['entity'] ?? ''));
    $all = entities();
    if (!isset($all[$entity])) { http_response_code(404); exit('Unknown table'); }
    $c = $all[$entity];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $c['sheet'] . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $c['fields']);
    foreach (entity_all($entity) as $row) {
        $line = [];
        foreach ($c['fields'] as $f) {
            $v = $row[$f] ?? '';
            $line[] = is_array($v) ? json_encode($v) : $v;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$viewFile = __DIR__ . '/views/' . $page . '.php';
if (!file_exists($viewFile)) {
    http_response_code(404);
    $page = 'dashboard';
    $viewFile = __DIR__ . '/views/dashboard.php';
}
require $viewFile;
