<?php
/** Maker-checker approvals — mirrors change-request.ts + approvals-apply.ts */

/** Module name maps 1:1 to an entity (except daily-sheet, handled in its page). */
function module_entity(string $module): string {
    return $module; // ha-sales, ha-stock, accessories, patients, staff-ta, dr-payments, dr-visits, expenses...
}

function apply_direct(string $module, string $action, array $assoc, string $targetId): void {
    $entity = module_entity($module);
    if ($action === 'create') { entity_insert($entity, $assoc); return; }
    if ($action === 'update') { entity_update($entity, $assoc); return; }
    if ($action === 'delete') { entity_delete($entity, $targetId); return; }
    throw new Exception("Unknown action: $action");
}

/**
 * Central write path.
 * - Admin (or !$needsApproval): applies immediately.
 * - Otherwise: queues a pending Approval row.
 * Returns 'applied' | 'queued'.
 */
function submit_change(string $module, string $moduleLabel, string $action, array $assoc, string $summary, ?array $old, bool $needsApproval): string {
    // Give creates a stable id up-front (like the Next.js app did client-side)
    // so the pending row and the eventual committed record share one id.
    if ($action === 'create' && empty($assoc['id'])) {
        $c = entity_cfg(module_entity($module));
        $assoc['id'] = $c['prefix'] . '-' . (time() . rand(100, 999));
    }
    $targetId = $assoc['id'] ?? '';
    if (is_admin() || !$needsApproval) {
        apply_direct($module, $action, $assoc, $targetId);
        return 'applied';
    }
    $u = current_user();
    entity_insert('approvals', [
        'createdAt'       => date('c'),
        'requestedBy'     => $u['name'] ?? '',
        'requestedByRole' => $u['role'] ?? '',
        'module'          => $module,
        'moduleLabel'     => $moduleLabel,
        'action'          => $action,
        'targetId'        => $targetId,
        'summary'         => $summary,
        'oldValue'        => $old !== null ? json_encode($old) : '',
        'newValue'        => json_encode($assoc),
        'status'          => 'pending',
        'reviewedBy'      => '',
        'reviewedAt'      => '',
    ]);
    return 'queued';
}

/** Save a daily grid (MySQL). Replaces all cells for that branch+month. */
function save_daily_sheet(string $branch, string $month, array $entries): void {
    db_exec("DELETE FROM `daily_entries` WHERE `branch`=? AND `month`=?", [$branch, $month]);
    foreach ($entries as $e) {
        db_exec(
            "INSERT INTO `daily_entries` (`branch`,`month`,`test_name`,`day`,`amount`,`quantity`) VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `amount`=VALUES(`amount`),`quantity`=VALUES(`quantity`)",
            [$branch, $month, $e['testName'], (int)$e['day'], (float)$e['amount'], (float)$e['quantity']],
            'sssidd'
        );
    }
}

function apply_daily_sheet_approval(string $newValueJson): void {
    $p = json_decode($newValueJson ?: '[]', true);
    if (is_array($p)) {
        save_daily_sheet($p['branch'] ?? '', $p['month'] ?? '', $p['entries'] ?? []);
        if (function_exists('mark_daily_dirty')) mark_daily_dirty($p['branch'] ?? '', $p['month'] ?? '');
    }
}

/** Admin approves a pending request → apply the change to the real sheet. */
function apply_approval(array $approval): void {
    $module = $approval['module'];
    $action = $approval['action'];
    $targetId = $approval['targetId'];
    if ($module === 'daily-sheet') {
        apply_daily_sheet_approval($approval['newValue']);
        return;
    }
    $assoc = json_decode($approval['newValue'] ?: '[]', true);
    if (!is_array($assoc)) $assoc = [];
    apply_direct($module, $action, $assoc, $targetId);
}

/** Approvals visible to the current user (admin: all; others: own). */
function approvals_for_current_user(): array {
    $all = entity_all('approvals');
    $u = current_user();
    if (!is_admin()) {
        $all = array_filter($all, fn($a) => ($a['requestedBy'] ?? '') === ($u['name'] ?? ''));
    }
    usort($all, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return array_values($all);
}

function pending_approvals_count(): int {
    $c = 0;
    foreach (approvals_for_current_user() as $a) if (($a['status'] ?? '') === 'pending') $c++;
    return $c;
}
