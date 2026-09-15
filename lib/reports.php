<?php
/** Revenue + report + daily-sheet helpers — mirror /api/revenue, /api/reports, daily-sheet. */

const TEST_TYPES = ['PTA','TYMP','ENG','OAE','ABR','VEMP','SRT/SDS','TDT','SISI','ETF','SP. THX','SWALLOW THX','VOICE THX'];

function ds_sheet_name(string $branch, string $month): string {
    return "DS-$branch-$month";
}

/** Read a daily sheet grid → rows of [testName, day, amount, quantity]. */
function daily_sheet_rows(string $branch, string $month): array {
    return sheets_get(spreadsheet_id('operations'), ds_sheet_name($branch, $month) . '!A2:D');
}

/** Revenue for a month = daily sheet amount totals (per branch) + HA sales. */
function revenue_for_month(string $month): array {
    $branches = active_branch_names();
    $byBranch = [];
    $daily = 0.0;
    foreach ($branches as $b) {
        $rows = daily_sheet_rows($b, $month);
        $sum = 0.0;
        foreach ($rows as $r) $sum += (float)cell($r, 2);
        $byBranch[$b] = $sum;
        $daily += $sum;
    }
    $ha = 0.0;
    foreach (entity_all('ha-sales') as $s) {
        if (month_key_of($s['date']) === $month) $ha += (float)$s['sellingPrice'];
    }
    return ['dailyRevenue' => $daily, 'byBranch' => $byBranch, 'haSalesRevenue' => $ha, 'totalRevenue' => $daily + $ha];
}

/** Report summary for a month + branch (test/accessory from daily sheet). */
function report_summary(string $month, string $branch): array {
    $branches = $branch === 'All' ? active_branch_names() : [$branch];
    $testRevenue = 0.0;
    $accessoryRevenue = 0.0;
    foreach ($branches as $b) {
        foreach (daily_sheet_rows($b, $month) as $r) {
            $name = cell($r, 0);
            $amt = (float)cell($r, 2);
            if (in_array($name, TEST_TYPES, true)) $testRevenue += $amt;
            else $accessoryRevenue += $amt;
        }
    }
    $haRevenue = 0.0; $haCount = 0;
    foreach (entity_all('ha-sales') as $s) {
        if (($branch === 'All' || $s['branch'] === $branch) && month_key_of($s['date']) === $month) {
            $haRevenue += (float)$s['sellingPrice'];
            $haCount++;
        }
    }
    $patients = 0;
    foreach (entity_all('patients') as $p) {
        if (($branch === 'All' || $p['branch'] === $branch) && month_key_of($p['date']) === $month) $patients++;
    }
    $expenses = 0.0;
    foreach (entity_all('expenses') as $e) {
        if (($branch === 'All' || $e['branch'] === $branch) && month_key_of($e['date']) === $month) $expenses += (float)$e['amount'];
    }
    return [
        'testRevenue' => $testRevenue,
        'accessoryRevenue' => $accessoryRevenue,
        'haRevenue' => $haRevenue,
        'grandTotal' => $testRevenue + $accessoryRevenue + $haRevenue,
        'totalPatients' => $patients,
        'totalHASales' => $haCount,
        'expenses' => $expenses,
    ];
}
