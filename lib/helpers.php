<?php
/** Shared helpers — mirror src/lib/date-utils.ts and dashboard helpers. */

function h($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Normalize a 2-digit / low year (e.g. "0026-05-28" -> "2026-05-28"). */
function normalize_date_string(?string $value): string {
    if ($value === null || $value === '') return (string)$value;
    if (preg_match('/^(\d{1,4})-(\d{2})-(\d{2})(.*)$/', trim($value), $m)) {
        $year = (int)$m[1];
        if ($year >= 0 && $year < 100) $year += 2000;
        return sprintf('%04d-%s-%s%s', $year, $m[2], $m[3], $m[4] ?? '');
    }
    return $value;
}

/** Parse many date formats to a DateTime (or null). Normalizes low years. */
function safe_parse_date(?string $value): ?DateTime {
    if ($value === null) return null;
    $v = trim($value);
    if ($v === '' || $v === '-' || strtoupper($v) === 'N/A') return null;

    // dd/mm/yyyy -> yyyy-mm-dd
    if (strpos($v, '/') !== false) {
        $parts = array_reverse(explode('/', $v));
        $v = implode('-', $parts);
    }
    $v = normalize_date_string($v);

    $ts = strtotime($v);
    if ($ts === false) return null;
    $d = new DateTime();
    $d->setTimestamp($ts);
    $y = (int)$d->format('Y');
    if ($y >= 0 && $y < 100) $d->setDate($y + 2000, (int)$d->format('m'), (int)$d->format('d'));
    return $d;
}

/** Format a date string; falls back to the raw value if unparseable. */
function format_date(?string $value, string $fmt = 'd M Y'): string {
    $d = safe_parse_date($value);
    if (!$d) return $value !== null && trim($value) !== '' ? trim($value) : '-';
    return $d->format($fmt);
}

/** YYYY-MM key for a date string (normalized). */
function month_key_of(?string $value): string {
    if (!$value) return '';
    $n = strpos($value, '/') !== false ? implode('-', array_reverse(explode('/', $value))) : $value;
    if (preg_match('/^(\d{1,4})-(\d{2})/', $n, $m)) {
        $y = (int)$m[1];
        if ($y >= 0 && $y < 100) $y += 2000;
        return sprintf('%04d-%s', $y, $m[2]);
    }
    return substr($n, 0, 7);
}

function current_month_key(): string {
    return date('Y-m');
}

function is_current_month(?string $value): bool {
    return month_key_of($value) === current_month_key();
}

function is_today(?string $value): bool {
    $d = safe_parse_date($value);
    return $d ? $d->format('Y-m-d') === date('Y-m-d') : false;
}

/** Last 6 months oldest->newest: [['key'=>'2026-04','label'=>'Apr'], ...]. */
function last_six_months(): array {
    $out = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $out[] = ['key' => date('Y-m', $ts), 'label' => date('M', $ts)];
    }
    return $out;
}

function format_currency($amount): string {
    $amount = (float)$amount;
    if ($amount >= 100000) return '₹' . number_format($amount / 100000, 1) . 'L';
    if ($amount >= 1000) return '₹' . number_format($amount / 1000, 1) . 'K';
    return '₹' . number_format($amount);
}

function rupees($amount): string {
    return '₹' . number_format((float)$amount);
}

/** Safe getter for a cell in a row. */
function cell(array $row, int $i, $default = ''): string {
    return isset($row[$i]) ? (string)$row[$i] : $default;
}

/** Redirect helper (PRG pattern). */
function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

/** Flash message helpers. */
function set_flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function take_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Role label + theme colors (mirrors src/lib/theme.ts). */
function role_label(string $role): string {
    $map = ['admin' => 'Administrator', 'doctor' => 'Doctor', 'staff' => 'Staff', 'marketing' => 'Marketing'];
    return $map[$role] ?? ucfirst($role);
}

function role_theme(string $role): array {
    $themes = [
        'admin'     => ['sidebar' => 'bg-indigo-900', 'active' => 'bg-indigo-700', 'header' => 'bg-indigo-600', 'badge' => 'bg-indigo-100 text-indigo-700'],
        'doctor'    => ['sidebar' => 'bg-teal-900',   'active' => 'bg-teal-700',   'header' => 'bg-teal-600',   'badge' => 'bg-teal-100 text-teal-700'],
        'staff'     => ['sidebar' => 'bg-blue-900',   'active' => 'bg-blue-700',   'header' => 'bg-blue-600',   'badge' => 'bg-blue-100 text-blue-700'],
        'marketing' => ['sidebar' => 'bg-amber-900',  'active' => 'bg-amber-700',  'header' => 'bg-amber-600',  'badge' => 'bg-amber-100 text-amber-700'],
    ];
    return $themes[$role] ?? $themes['staff'];
}

/** Form input helper: <div><label><input id="f_<name>"></div> */
function ff(string $label, string $name, string $type = 'text', string $attrs = '', string $wrap = ''): string {
    return '<div class="' . $wrap . '"><label class="block text-sm font-medium text-gray-700 mb-1">' . h($label) . '</label>'
        . '<input type="' . $type . '" name="' . h($name) . '" id="f_' . h($name) . '" ' . $attrs
        . ' class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>';
}
/** Select helper. $options can be list [v,..] or assoc [v=>label]. */
function ffselect(string $label, string $name, array $options, string $wrap = ''): string {
    $opts = '';
    foreach ($options as $k => $v) {
        $val = is_int($k) ? $v : $k;
        $opts .= '<option value="' . h($val) . '">' . h($v) . '</option>';
    }
    return '<div class="' . $wrap . '"><label class="block text-sm font-medium text-gray-700 mb-1">' . h($label) . '</label>'
        . '<select name="' . h($name) . '" id="f_' . h($name) . '" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">' . $opts . '</select></div>';
}
/** Status pill classes. */
function pill(string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['completed','approved','paid','active'])) return 'bg-green-100 text-green-700';
    if (in_array($s, ['pending','due','on follow up','new'])) return 'bg-yellow-100 text-yellow-700';
    if (in_array($s, ['rejected','cancelled','inactive'])) return 'bg-red-100 text-red-700';
    if (in_array($s, ['in progress','uploaded'])) return 'bg-blue-100 text-blue-700';
    if ($s === 'incomplete') return 'bg-orange-100 text-orange-700';
    return 'bg-gray-100 text-gray-600';
}

/** Register a Chart.js config to render for a <canvas id>. */
function add_chart(string $canvasId, array $config): void {
    if (!isset($GLOBALS['__charts'])) $GLOBALS['__charts'] = [];
    $GLOBALS['__charts'][$canvasId] = json_encode($config);
}

/** WhatsApp link like src/lib/whatsapp.ts */
function wa_link(string $phone, string $message = ''): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 10) $digits = '91' . $digits;
    return 'https://wa.me/' . $digits . ($message ? '?text=' . rawurlencode($message) : '');
}
