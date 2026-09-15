<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Kolkata');
$_SESSION = [];
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/icons.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/lib/approvals.php';
require __DIR__ . '/lib/reports.php';

// Mock a logged-in admin
$_SESSION['user'] = [
    'id' => 'u-1', 'name' => 'Admin', 'email' => 'admin@test.com',
    'role' => 'admin', 'branch' => 'All', 'modules' => [], 'active' => 'TRUE',
];

$pages = ['dashboard','appointments','patients','doctors','enquiries','potential-ha',
    'dr-visits','dr-payments','staff-ta','expenses','ha-stock','ha-sales','accessories',
    'ha-repairs','attendance','daily-sheet','invoices','reports','settings','whatsapp',
    'requests','users'];

foreach ($pages as $p) {
    $file = __DIR__ . '/views/' . $p . '.php';
    if (!file_exists($file)) { echo "MISSING  $p\n"; continue; }
    $_GET = ['page' => $p];
    ob_start();
    $err = null;
    try {
        include $file;
    } catch (\Throwable $e) {
        $err = get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }
    ob_end_clean();
    echo ($err ? "FAIL  $p  ->  $err" : "OK    $p") . "\n";
}
