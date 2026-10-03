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

/** Locate (or create) a patient's base folder, keyed on the immutable patient id.
 *  Finds an existing `<id>` or `<id>__*` folder; otherwise creates `<id>__<slug>`,
 *  so renaming a patient never orphans their files and the id stays the key. */
function patient_base_folder(string $pid, string $name = ''): string {
    $root = __DIR__ . '/uploads/patients';
    if (!is_dir($root)) @mkdir($root, 0775, true);
    $matches = glob($root . '/' . $pid . '__*') ?: [];
    if (is_dir($root . '/' . $pid)) $matches[] = $root . '/' . $pid;
    if ($matches) return $matches[0];
    $slug = $name !== '' ? '__' . trim(preg_replace('/_+/', '_', preg_replace('/[^A-Za-z0-9]+/', '_', $name)), '_') : '';
    if (strlen($slug) > 60) $slug = substr($slug, 0, 60);
    return $root . '/' . $pid . $slug;
}
/** A category subfolder (reports, invoices, …) inside the patient folder. */
function patient_category_dir(string $pid, string $name, string $category): string {
    $dir = patient_base_folder($pid, $name) . '/' . preg_replace('/[^a-z0-9_-]/', '', strtolower($category));
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

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

// ---- Patient files (reports, and future docs) — stored on the server only ----
//
// Layout (DBA-friendly, keyed on the immutable patient id):
//   uploads/patients/<patientId>__<SanitizedName>/reports/<fileId>__<originalName>
// The folder is located by the <patientId> prefix, so renames never orphan files,
// and the same patient folder can later hold invoices/other categories.
if (in_array($page, ['report-upload','report-list','report-file','report-delete','report-zip'], true)) {
    $pid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['id'] ?? ''));   // patient id — GET only
    $pname = trim((string)($_GET['name'] ?? ''));
    if ($pid === '') { http_response_code(400); exit('missing patient id'); }
    $dir = patient_category_dir($pid, $pname, 'reports');
    $allowedExt = ['jpg','jpeg','png','gif','webp','pdf'];
    $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'];

    if ($page === 'report-list') {
        header('Content-Type: application/json');
        $out = [];
        foreach (glob($dir.'/*') ?: [] as $fp) {
            if (!is_file($fp)) continue;
            $f = basename($fp);
            if ($f[0] === '.') continue;
            $parts = explode('__', $f, 2);
            $out[] = ['id'=>$parts[0], 'name'=>$parts[1] ?? $f, 'file'=>$f, 'date'=>date('d M Y', @filemtime($fp) ?: time()), 'size'=>filesize($fp)];
        }
        usort($out, fn($a,$b)=>strcmp((string)$b['id'], (string)$a['id']));
        echo json_encode($out); exit;
    }

    if ($page === 'report-upload') {
        header('Content-Type: application/json');
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errs = [1=>'file exceeds server limit',2=>'file too large',3=>'partial upload',4=>'no file',6=>'no temp dir',7=>'disk write failed'];
            echo json_encode(['ok'=>false,'error'=>$errs[$_FILES['file']['error'] ?? 4] ?? 'upload failed']); exit;
        }
        if ($_FILES['file']['size'] > 15*1024*1024) { echo json_encode(['ok'=>false,'error'=>'file too large (max 15MB)']); exit; }
        $orig = (string)$_FILES['file']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) { echo json_encode(['ok'=>false,'error'=>'only JPG, PNG, GIF, WEBP or PDF allowed']); exit; }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { echo json_encode(['ok'=>false,'error'=>'cannot create patient folder']); exit; }
        $fid = (string)round(microtime(true)*1000);
        $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $orig);
        $file = $fid . '__' . $safeName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir.'/'.$file)) { echo json_encode(['ok'=>false,'error'=>'could not save file']); exit; }
        echo json_encode(['ok'=>true]); exit;
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
        $cleanName = preg_replace('/^\d+__/', '', $f);
        header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $cleanName . '"');
        readfile($path); exit;
    }

    if ($page === 'report-zip') {
        $files = array_filter(glob($dir.'/*') ?: [], 'is_file');
        if (!$files) { http_response_code(404); exit('no files to download'); }
        if (!class_exists('ZipArchive')) { http_response_code(501); exit('ZIP not supported on server'); }
        $zipBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', ($pname !== '' ? $pname.'_' : '') . $pid);
        $tmp = tempnam(sys_get_temp_dir(), 'rzip');
        $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $fp) $z->addFile($fp, preg_replace('/^\d+__/', '', basename($fp)));
        $z->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipBase . '_reports.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp); @unlink($tmp); exit;
    }
}

$viewFile = __DIR__ . '/views/' . $page . '.php';
if (!file_exists($viewFile)) {
    http_response_code(404);
    $page = 'dashboard';
    $viewFile = __DIR__ . '/views/dashboard.php';
}
require $viewFile;
