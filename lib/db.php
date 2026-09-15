<?php
/**
 * MySQL data layer (primary datastore).
 * Google Sheets becomes a synced mirror/backup (see lib/sync.php).
 */

function db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;
    $m = cfg()['mysql'] ?? null;
    if (!$m) throw new Exception('MySQL not configured in config.php');
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($m['host'], $m['user'], $m['pass'], $m['name'], (int)($m['port'] ?? 3306));
    if ($conn->connect_errno) {
        throw new Exception('MySQL connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    db_ensure_schema($conn);
    return $conn;
}

function db_available(): bool {
    try { db(); return true; } catch (\Throwable $e) { return false; }
}

/** Run all CREATE TABLE IF NOT EXISTS once per schema version. */
function db_ensure_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $marker = __DIR__ . '/../cache/schema_v1.ok';
    if (file_exists($marker)) return;

    foreach (entities() as $name => $c) {
        $table = entity_table($name);
        $cols = ["`id` VARCHAR(191) NOT NULL"];
        foreach ($c['fields'] as $f) {
            if ($f === 'id') continue;
            $cols[] = "`$f` LONGTEXT NULL";
        }
        $cols[] = "PRIMARY KEY (`id`)";
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (" . implode(', ', $cols) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql);
    }

    // Daily revenue grid cells
    $conn->query("CREATE TABLE IF NOT EXISTS `daily_entries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch` VARCHAR(191) NOT NULL,
        `month` VARCHAR(7) NOT NULL,
        `test_name` VARCHAR(191) NOT NULL,
        `day` INT NOT NULL,
        `amount` DOUBLE NOT NULL DEFAULT 0,
        `quantity` DOUBLE NOT NULL DEFAULT 0,
        UNIQUE KEY `cell` (`branch`,`month`,`test_name`,`day`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Key/value app config (whatsapp messages, sync settings, etc.)
    $conn->query("CREATE TABLE IF NOT EXISTS `app_config` (
        `k` VARCHAR(191) NOT NULL PRIMARY KEY,
        `v` LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Service catalog (mirrors the master 'Services' tab: category/description/price)
    $conn->query("CREATE TABLE IF NOT EXISTS `services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category` VARCHAR(191) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `price` VARCHAR(64) NOT NULL DEFAULT '0'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mkdir(__DIR__ . '/../cache', 0775, true);
    @file_put_contents($marker, date('c'));
}

/** SELECT → array of assoc rows. */
function db_all(string $sql, array $params = [], string $types = ''): array {
    $stmt = db()->prepare($sql);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . db()->error . ' | ' . $sql);
    if ($params) $stmt->bind_param($types ?: str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** SELECT one row (or null). */
function db_row(string $sql, array $params = [], string $types = ''): ?array {
    $rows = db_all($sql, $params, $types);
    return $rows[0] ?? null;
}

/** INSERT/UPDATE/DELETE → affected rows. */
function db_exec(string $sql, array $params = [], string $types = ''): int {
    $stmt = db()->prepare($sql);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . db()->error . ' | ' . $sql);
    if ($params) $stmt->bind_param($types ?: str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}
