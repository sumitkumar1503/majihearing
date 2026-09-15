<?php
/** Front controller — bootstraps, guards auth, routes to a view. */
session_start();
date_default_timezone_set('Asia/Kolkata');

require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/lib/approvals.php';
require __DIR__ . '/lib/reports.php';

$page = $_GET['page'] ?? 'dashboard';
$page = preg_replace('/[^a-z0-9_-]/', '', (string)$page);
if ($page === '') $page = 'dashboard';

// Public routes
if ($page === 'login') { require __DIR__ . '/views/login.php'; exit; }
if ($page === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

require_login($page);

$viewFile = __DIR__ . '/views/' . $page . '.php';
if (!file_exists($viewFile)) {
    http_response_code(404);
    $page = 'dashboard';
    $viewFile = __DIR__ . '/views/dashboard.php';
}
require $viewFile;
