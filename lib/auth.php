<?php
/** Session auth + role/module access — mirrors auth.ts, useRole.ts, middleware.ts */

const ALL_NAV = [
    'dashboard'   => ['Dashboard', 'dashboard'],
    'requests'    => ['Requests', 'requests'],
    'users'       => ['Users', 'users'],
    'appointments'=> ['Appointments', 'appointments'],
    'patients'    => ['Patients', 'patients'],
    'doctors'     => ['Doctors', 'doctors'],
    'dailySheets' => ['Daily Sheet', 'daily-sheet'],
    'haSales'     => ['HA Sales', 'ha-sales'],
    'haStock'     => ['HA Stock', 'ha-stock'],
    'accessories' => ['Accessories', 'accessories'],
    'haRepairs'   => ['HA Repairs', 'ha-repairs'],
    'enquiries'   => ['Enquiries', 'enquiries'],
    'potentialHA' => ['Potential HA', 'potential-ha'],
    'drVisits'    => ['Dr Visits', 'dr-visits'],
    'drPayments'  => ['Dr Payments', 'dr-payments'],
    'expenses'    => ['Expenses', 'expenses'],
    'staffTA'     => ['Staff TA', 'staff-ta'],
    'invoices'    => ['Invoices', 'invoices'],
    'reports'     => ['Reports', 'reports'],
    'attendance'  => ['Attendance', 'attendance'],
    'whatsapp'    => ['WhatsApp', 'whatsapp'],
    'settings'    => ['Settings', 'settings'],
];

const ADMIN_ONLY_KEYS = ['requests', 'reports', 'settings'];

const ROLE_NAV = [
    'admin' => ['dashboard','requests','users','appointments','patients','doctors','dailySheets','haSales','haStock','accessories','haRepairs','enquiries','potentialHA','drVisits','drPayments','expenses','staffTA','invoices','whatsapp','attendance','reports','settings'],
    'doctor' => ['dashboard','appointments','patients','potentialHA','haStock','expenses','staffTA','attendance','whatsapp'],
    'staff' => ['dashboard','appointments','patients','dailySheets','haSales','haStock','accessories','haRepairs','enquiries','potentialHA','expenses','invoices','whatsapp','attendance'],
    'marketing' => ['dashboard','enquiries','drVisits','drPayments','expenses','staffTA','whatsapp','attendance'],
];

function slug_to_key(string $slug): ?string {
    foreach (ALL_NAV as $key => $item) {
        if ($item[1] === $slug) return $key;
    }
    return null;
}

function attempt_login(string $email, string $password): bool {
    foreach (entity_all('users') as $u) {
        $active = strtolower((string)($u['active'] ?? '')) === 'true';
        if (($u['email'] ?? '') === $email && $active) {
            if (password_verify($password, (string)($u['password'] ?? ''))) {
                $modules = array_filter(array_map('trim', explode(',', (string)($u['modules'] ?? ''))));
                $_SESSION['user'] = [
                    'id' => $u['id'] ?? '',
                    'name' => $u['name'] ?? '',
                    'email' => $u['email'] ?? '',
                    'role' => $u['role'] ?? '',
                    'branch' => $u['branch'] ?? '',
                    'modules' => array_values($modules),
                ];
                return true;
            }
            return false;
        }
    }
    return false;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function current_role(): string {
    return $_SESSION['user']['role'] ?? 'staff';
}

function is_admin(): bool {
    return current_role() === 'admin';
}

function resolve_nav_keys(): array {
    $role = current_role();
    $modules = $_SESSION['user']['modules'] ?? [];
    if ($role === 'admin') return ROLE_NAV['admin'];

    $keys = !empty($modules) ? $modules : (ROLE_NAV[$role] ?? ['dashboard']);
    $keys = array_values(array_filter($keys, fn($k) => !in_array($k, ADMIN_ONLY_KEYS, true)));
    if (!in_array('dashboard', $keys, true)) array_unshift($keys, 'dashboard');
    // de-dupe preserving order
    return array_values(array_unique($keys));
}

function nav_items(): array {
    $items = [];
    foreach (resolve_nav_keys() as $key) {
        if (isset(ALL_NAV[$key])) $items[] = ['key' => $key, 'label' => ALL_NAV[$key][0], 'slug' => ALL_NAV[$key][1]];
    }
    return $items;
}

function can_access(string $slug): bool {
    if (in_array($slug, ['logout', 'login'], true)) return true;
    if (is_admin()) return true;
    $key = slug_to_key($slug);
    if ($key === null) return true; // non-nav utility pages
    if (in_array($key, ADMIN_ONLY_KEYS, true)) return false;
    return in_array($key, resolve_nav_keys(), true);
}

function require_login(string $page): void {
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }
    if (!can_access($page)) {
        redirect('index.php?page=dashboard');
    }
}
