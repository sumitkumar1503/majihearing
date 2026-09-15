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

/** Write a daily sheet grid (DS-<branch>-<month>) — Test, Day, Amount, Quantity. */
function save_daily_sheet(string $branch, string $month, array $entries): void {
    $sheetName = "DS-$branch-$month";
    $sid = spreadsheet_id('operations');
    $exists = false;
    try { sheets_get($sid, "$sheetName!A1:A1"); $exists = true; } catch (Exception $e) {}
    if (!$exists) { sheets_ensure_sheet($sid, $sheetName); sheets_update($sid, "$sheetName!A1:D1", [['Test','Day','Amount','Quantity']]); }
    $rows = [];
    foreach ($entries as $e) $rows[] = [$e['testName'], $e['day'], $e['amount'], $e['quantity']];
    if ($rows) sheets_update($sid, "$sheetName!A2:D" . (count($rows) + 1), $rows);
}

function apply_daily_sheet_approval(string $newValueJson): void {
    $p = json_decode($newValueJson ?: '[]', true);
    if (is_array($p)) save_daily_sheet($p['branch'] ?? '', $p['month'] ?? '', $p['entries'] ?? []);
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
