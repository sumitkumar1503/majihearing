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

// ---- Patient report files: stored on the server + streamed with auth ----
if (in_array($page, ['report-upload','report-list','report-file','report-delete'], true)) {
    $pid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_REQUEST['id'] ?? ''));
    if ($pid === '') { http_response_code(400); exit('bad id'); }
    $dir = __DIR__ . '/uploads/reports/' . $pid;
    $allowedExt = ['jpg','jpeg','png','gif','webp','pdf'];

    if ($page === 'report-list') {
        header('Content-Type: application/json');
        $out = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..' || $f[0] === '.') continue;
                $parts = explode('__', $f, 2);
                $out[] = ['id'=>$parts[0], 'name'=>$parts[1] ?? $f, 'file'=>$f, 'date'=>date('Y-m-d', @filemtime($dir.'/'.$f) ?: time()), 'url'=>'index.php?page=report-file&id='.rawurlencode($pid).'&f='.rawurlencode($f)];
            }
        }
        usort($out, fn($a,$b)=>strcmp((string)$b['id'], (string)$a['id']));
        echo json_encode($out); exit;
    }

    if ($page === 'report-upload') {
        header('Content-Type: application/json');
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['ok'=>false,'error'=>'upload failed']); exit; }
        if ($_FILES['file']['size'] > 5*1024*1024) { echo json_encode(['ok'=>false,'error'=>'too large (max 5MB)']); exit; }
        $orig = (string)$_FILES['file']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) { echo json_encode(['ok'=>false,'error'=>'unsupported type']); exit; }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { echo json_encode(['ok'=>false,'error'=>'cannot create folder']); exit; }
        $id = preg_replace('/\D/', '', (string)($_POST['id'] ?? '')) ?: (string)round(microtime(true)*1000);
        $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $orig);
        $file = $id . '__' . $safeName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir.'/'.$file)) { echo json_encode(['ok'=>false,'error'=>'save failed']); exit; }
        echo json_encode(['ok'=>true, 'file'=>['id'=>$id,'name'=>$safeName,'file'=>$file,'date'=>date('Y-m-d'),'url'=>'index.php?page=report-file&id='.rawurlencode($pid).'&f='.rawurlencode($file)]]); exit;
    }

    if ($page === 'report-delete') {
        header('Content-Type: application/json');
        $f = basename((string)($_POST['f'] ?? ''));
        if ($f !== '' && is_file($dir.'/'.$f)) @unlink($dir.'/'.$f);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($page === 'report-file') {
        $f = basename((string)($_GET['f'] ?? ''));
        $path = $dir.'/'.$f;
        if ($f === '' || !is_file($path)) { http_response_code(404); exit('not found'); }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . preg_replace('/^\d+__/', '', $f) . '"');
        readfile($path); exit;
    }
}

$viewFile = __DIR__ . '/views/' . $page . '.php';
if (!file_exists($viewFile)) {
    http_response_code(404);
    $page = 'dashboard';
    $viewFile = __DIR__ . '/views/dashboard.php';
}
require $viewFile;
