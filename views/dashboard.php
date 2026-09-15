<?php
$title = 'Dashboard';
$role = current_role();
$months = last_six_months();

$PIE = ['#818cf8','#2dd4bf','#fbbf24','#f87171','#a78bfa','#22d3ee','#ec4899','#10b981'];

function stat_card(string $title, $value, string $subtitle, string $grad): string {
    return '<div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br ' . $grad . ' p-5 shadow-lg hover:shadow-xl hover:scale-[1.02] transition-all duration-300">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -translate-y-8 translate-x-8"></div>
        <div class="absolute bottom-0 left-0 w-20 h-20 bg-white/5 rounded-full translate-y-6 -translate-x-6"></div>
        <div class="relative">
          <p class="text-sm font-medium text-white/80">' . h($title) . '</p>
          <p class="text-3xl font-extrabold text-white tracking-tight mt-1">' . h((string)$value) . '</p>
          <p class="text-xs text-white/60 mt-1">' . h($subtitle) . '</p>
        </div></div>';
}
function card_open(string $title, string $sub = ''): string {
    return '<div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden"><div class="px-5 py-4"><h3 class="font-bold text-gray-900 text-sm">' . h($title) . '</h3>' . ($sub ? '<p class="text-[11px] text-gray-400 mt-0.5">' . h($sub) . '</p>' : '') . '</div><div class="px-4 pb-4">';
}
function card_close(): string { return '</div></div>'; }

/* ============================= ADMIN ============================= */
if ($role === 'admin'):
    $branch = $_GET['branch'] ?? 'all';
    $appointments = entity_all('appointments');
    $haSales = entity_all('ha-sales');
    $enquiries = entity_all('enquiries');
    $drVisits = entity_all('dr-visits');
    $drPayments = entity_all('dr-payments');
    $branchList = active_branch_names();

    $fAppts = array_values(array_filter($appointments, fn($a) => $branch === 'all' || $a['branch'] === $branch));
    $fSales = array_values(array_filter($haSales, fn($s) => $branch === 'all' || $s['branch'] === $branch));

    // Revenue trend (daily sheet + HA sales) per month
    $revTrend = [];
    foreach ($months as $m) {
        $daily = 0.0;
        foreach (($branch === 'all' ? $branchList : [$branch]) as $b) {
            foreach (daily_sheet_rows($b, $m['key']) as $r) $daily += (float)cell($r, 2);
        }
        $ha = 0.0;
        foreach ($fSales as $s) if (month_key_of($s['date']) === $m['key']) $ha += (float)$s['sellingPrice'];
        $revTrend[] = ['label' => $m['label'], 'value' => $daily + $ha];
    }
    $curRev = end($revTrend)['value'] ?? 0;

    // Sales by branch
    $byBranch = [];
    foreach ($fSales as $s) { $b = $s['branch'] ?: 'Unknown'; $byBranch[$b] = ($byBranch[$b] ?? 0) + 1; }

    // Inquiry source
    $bySource = [];
    foreach ($enquiries as $e) { $src = $e['source'] ?: 'Unknown'; $bySource[$src] = ($bySource[$src] ?? 0) + 1; }
    arsort($bySource); $bySource = array_slice($bySource, 0, 6, true);

    // Testing trend (this month)
    $testTrend = [];
    foreach ($fAppts as $a) if (is_current_month($a['date'])) { $t = $a['test'] ?: 'Unknown'; $testTrend[$t] = ($testTrend[$t] ?? 0) + 1; }
    arsort($testTrend); $testTrend = array_slice($testTrend, 0, 8, true);

    // Dr meet vs referral
    $meet = []; $ref = []; $mlabels = [];
    foreach ($months as $m) {
        $mlabels[] = $m['label'];
        $meet[] = count(array_filter($drVisits, fn($v) => month_key_of($v['dateOfVisit']) === $m['key']));
        $r = 0; foreach ($drPayments as $p) if (month_key_of($p['date']) === $m['key']) $r += (int)$p['noOfPatients'];
        $ref[] = $r;
    }

    $todaysAppts = array_filter($fAppts, fn($a) => is_today($a['date']));
    $outstanding = array_filter($fAppts, fn($a) => $a['reportStatus'] && !in_array(strtolower($a['reportStatus']), ['completed','delivered','n/a']));
    $haRevMonth = 0.0; foreach ($fSales as $s) if (is_current_month($s['date'])) $haRevMonth += (float)$s['sellingPrice'];
    $rev = revenue_for_month(current_month_key());
    $dailyPart = $branch === 'all' ? $rev['dailyRevenue'] : ($rev['byBranch'][$branch] ?? 0);
    $totalRevenue = $dailyPart + $haRevMonth;

    $recent = $fAppts;
    usort($recent, fn($a, $b) => strcmp($b['date'], $a['date']));
    $recent = array_slice($recent, 0, 6);

    add_chart('c_rev', ['type'=>'line','data'=>['labels'=>array_column($revTrend,'label'),'datasets'=>[['label'=>'Revenue','data'=>array_column($revTrend,'value'),'borderColor'=>'#10b981','backgroundColor'=>'rgba(16,185,129,.15)','fill'=>true,'tension'=>.35]]],'options'=>['plugins'=>['legend'=>['display'=>false]]]]);
    add_chart('c_branch', ['type'=>'doughnut','data'=>['labels'=>array_keys($byBranch),'datasets'=>[['data'=>array_values($byBranch),'backgroundColor'=>$PIE]]]]);
    add_chart('c_meet', ['type'=>'bar','data'=>['labels'=>$mlabels,'datasets'=>[['label'=>'Doctor Meets','data'=>$meet,'backgroundColor'=>'#6366f1'],['label'=>'Patients Referred','data'=>$ref,'backgroundColor'=>'#10b981']]]]);
    add_chart('c_src', ['type'=>'doughnut','data'=>['labels'=>array_keys($bySource),'datasets'=>[['data'=>array_values($bySource),'backgroundColor'=>$PIE]]]]);
    add_chart('c_test', ['type'=>'bar','data'=>['labels'=>array_keys($testTrend),'datasets'=>[['label'=>'Tests','data'=>array_values($testTrend),'backgroundColor'=>'#0ea5e9']]],'options'=>['indexAxis'=>'y','plugins'=>['legend'=>['display'=>false]]]]);

    require __DIR__ . '/../partials/top.php';
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <?= stat_card("Today's Appointments", count($todaysAppts), 'Scheduled today', 'from-teal-500 to-teal-700') ?>
      <?= stat_card('Outstanding Appointments', count($outstanding), 'Reports pending', 'from-amber-500 to-orange-600') ?>
      <?= stat_card('Revenue', format_currency($totalRevenue), 'Daily sheets + HA sales (this month)', 'from-emerald-500 to-green-600') ?>
      <?= stat_card('Hearing Sales', count($fSales), 'Units sold', 'from-violet-500 to-purple-700') ?>
    </div>
    <form method="get" class="mt-5 flex items-center gap-3">
      <input type="hidden" name="page" value="dashboard">
      <label class="text-sm font-medium text-gray-600">Location:</label>
      <select name="branch" onchange="this.form.submit()" class="rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white shadow-sm">
        <option value="all" <?= $branch === 'all' ? 'selected' : '' ?>>All Branches</option>
        <?php foreach ($branchList as $b): ?><option value="<?= h($b) ?>" <?= $branch === $b ? 'selected' : '' ?>><?= h($b) ?></option><?php endforeach; ?>
      </select>
    </form>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Revenue Trend', 'Daily sheet + HA sales (monthly)') ?><div class="relative" style="height:280px"><canvas id="c_rev"</canvas></div><?= card_close() ?>
      <?= card_open('Sales by Branch', 'HA sales distribution') ?><div class="relative" style="height:280px"><canvas id="c_branch"</canvas></div><?= card_close() ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Doctor Meet vs Doctor Referral', 'Monthly visits vs patients referred') ?><div class="relative" style="height:280px"><canvas id="c_meet"</canvas></div><?= card_close() ?>
      <?= card_open('Inquiry Source', 'Where leads come from') ?><div class="relative" style="height:280px"><canvas id="c_src"</canvas></div><?= card_close() ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Testing Trend', 'Tests performed this month') ?><div class="relative" style="height:280px"><canvas id="c_test"</canvas></div><?= card_close() ?>
      <?= card_open('Recent Appointments', '') ?>
        <div class="divide-y divide-gray-50">
        <?php foreach ($recent as $a): ?>
          <div class="flex items-center justify-between py-2.5">
            <div><p class="text-sm font-semibold text-gray-800"><?= h($a['patientName']) ?></p><p class="text-[11px] text-gray-400"><?= h($a['test']) ?> · <?= h(format_date($a['date'], 'd M')) ?></p></div>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold bg-gray-50 text-gray-600 border"><?= h($a['reportStatus'] ?: 'N/A') ?></span>
          </div>
        <?php endforeach; if (!$recent): ?><p class="py-6 text-center text-sm text-gray-300">No appointments</p><?php endif; ?>
        </div>
      <?= card_close() ?>
    </div>
    <?php
    require __DIR__ . '/../partials/bottom.php';

/* ============================= DOCTOR ============================= */
elseif ($role === 'doctor'):
    $appointments = entity_all('appointments');
    $patients = entity_all('patients');
    $potential = entity_all('potential-ha');

    $todays = array_filter($appointments, fn($a) => is_today($a['date']));
    $pendingReports = array_filter($appointments, fn($a) => strtolower($a['reportStatus']) === 'pending');
    $monthPatients = count(array_filter($patients, fn($p) => is_current_month($p['date'])));
    $monthAppts = count(array_filter($appointments, fn($a) => is_current_month($a['date'])));

    $testDist = [];
    foreach ($appointments as $a) { $t = $a['test'] ?: 'Unknown'; $testDist[$t] = ($testDist[$t] ?? 0) + 1; }
    arsort($testDist); $testDist = array_slice($testDist, 0, 6, true);
    $completed = count(array_filter($appointments, fn($a) => strtolower($a['reportStatus']) === 'completed'));
    $pending = count($pendingReports);
    $other = count($appointments) - $completed - $pending;

    add_chart('c_tests', ['type'=>'bar','data'=>['labels'=>array_keys($testDist),'datasets'=>[['label'=>'Count','data'=>array_values($testDist),'backgroundColor'=>'#14b8a6']]],'options'=>['indexAxis'=>'y','plugins'=>['legend'=>['display'=>false]]]]);
    add_chart('c_reports', ['type'=>'doughnut','data'=>['labels'=>['Completed','Pending','Other'],'datasets'=>[['data'=>[$completed,$pending,max(0,$other)],'backgroundColor'=>['#34d399','#fbbf24','#cbd5e1']]]]]);

    require __DIR__ . '/../partials/top.php';
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <?= stat_card("Today's Appointments", count($todays), 'Scheduled today', 'from-teal-500 to-teal-700') ?>
      <?= stat_card('Total Patients', $monthPatients, 'This month', 'from-sky-500 to-blue-600') ?>
      <?= stat_card('Pending Reports', count($pendingReports), 'Needs action', 'from-amber-500 to-orange-600') ?>
      <?= stat_card('Total Appointments', $monthAppts, 'This month', 'from-emerald-500 to-green-600') ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Tests Performed', 'Top tests') ?><div class="relative" style="height:280px"><canvas id="c_tests"</canvas></div><?= card_close() ?>
      <?= card_open('Report Status', 'Completion overview') ?><div class="relative" style="height:280px"><canvas id="c_reports"</canvas></div><?= card_close() ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Potential Hearing Patients', '') ?>
        <div class="divide-y divide-gray-50">
        <?php foreach (array_slice($potential, 0, 8) as $p): ?>
          <div class="flex items-center justify-between py-2.5"><div><p class="text-sm font-semibold text-gray-800"><?= h($p['name']) ?></p><p class="text-[11px] text-gray-400"><?= h($p['ageSex']) ?> <?= $p['contactNo'] ? '· ' . h($p['contactNo']) : '' ?></p></div><span class="text-xs text-gray-500"><?= h(format_date($p['date'], 'd M')) ?></span></div>
        <?php endforeach; if (!$potential): ?><p class="py-6 text-center text-sm text-gray-300">No potential hearing patients</p><?php endif; ?>
        </div>
      <?= card_close() ?>
      <?= card_open("Today's Schedule", '') ?>
        <div class="divide-y divide-gray-50">
        <?php foreach (array_slice(array_values($todays), 0, 8) as $a): ?>
          <div class="flex items-center justify-between py-2.5"><div><p class="text-sm font-semibold text-gray-800"><?= h($a['patientName']) ?></p><p class="text-[11px] text-gray-400"><?= h($a['test']) ?></p></div><span class="text-xs text-gray-500 font-medium"><?= h($a['time']) ?></span></div>
        <?php endforeach; if (!$todays): ?><p class="py-6 text-center text-sm text-gray-300">No appointments today</p><?php endif; ?>
        </div>
      <?= card_close() ?>
    </div>
    <?php
    require __DIR__ . '/../partials/bottom.php';

/* ============================= MARKETING ============================= */
elseif ($role === 'marketing'):
    $enquiries = entity_all('enquiries');
    $drVisits = entity_all('dr-visits');
    $drPayments = entity_all('dr-payments');

    $totalVisits = count($drVisits);
    $visitsMonth = count(array_filter($drVisits, fn($v) => is_current_month($v['dateOfVisit'])));
    $refMonth = 0; foreach ($drPayments as $p) if (is_current_month($p['date'])) $refMonth += (int)$p['noOfPatients'];
    $incomplete = count(array_filter($enquiries, fn($e) => !in_array($e['status'], ['Completed','Rejected'], true)));

    $bySource = [];
    foreach ($enquiries as $e) { $src = $e['source'] ?: 'Unknown'; $bySource[$src] = ($bySource[$src] ?? 0) + 1; }
    arsort($bySource); $bySource = array_slice($bySource, 0, 6, true);

    $mlabels = []; $visits = []; $refs = [];
    foreach ($months as $m) {
        $mlabels[] = $m['label'];
        $visits[] = count(array_filter($drVisits, fn($v) => month_key_of($v['dateOfVisit']) === $m['key']));
        $r = 0; foreach ($drPayments as $p) if (month_key_of($p['date']) === $m['key']) $r += (int)$p['noOfPatients'];
        $refs[] = $r;
    }

    add_chart('c_src', ['type'=>'doughnut','data'=>['labels'=>array_keys($bySource),'datasets'=>[['data'=>array_values($bySource),'backgroundColor'=>$PIE]]]]);
    add_chart('c_meet', ['type'=>'bar','data'=>['labels'=>$mlabels,'datasets'=>[['label'=>'Doctor Visits','data'=>$visits,'backgroundColor'=>'#6366f1'],['label'=>'Patients Referred','data'=>$refs,'backgroundColor'=>'#10b981']]]]);
    add_chart('c_growth', ['type'=>'line','data'=>['labels'=>$mlabels,'datasets'=>[['label'=>'Visits','data'=>$visits,'borderColor'=>'#6366f1','backgroundColor'=>'rgba(99,102,241,.15)','fill'=>true,'tension'=>.35]]],'options'=>['plugins'=>['legend'=>['display'=>false]]]]);

    $recentEnq = $enquiries; usort($recentEnq, fn($a,$b)=>strcmp($b['enquiryDate'],$a['enquiryDate'])); $recentEnq = array_slice($recentEnq,0,6);

    require __DIR__ . '/../partials/top.php';
    ?>
    <a href="index.php?page=attendance" class="mb-5 flex items-center gap-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 p-4 text-white shadow-lg">
      <div class="flex-1"><p class="font-bold text-lg">Mark Attendance</p><p class="text-sm text-emerald-100">Check in / Check out with GPS location</p></div><div class="text-3xl">→</div>
    </a>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <?= stat_card('Total Number of Visits', $totalVisits, 'Lifetime cumulative', 'from-indigo-500 to-indigo-700') ?>
      <?= stat_card('Visits This Month', $visitsMonth, 'Resets monthly', 'from-teal-500 to-teal-700') ?>
      <?= stat_card('Patients Referred (Month)', $refMonth, 'Referred back by doctors', 'from-emerald-500 to-green-600') ?>
      <?= stat_card('Incomplete Inquiries', $incomplete, 'New, Follow-up & No Answer', 'from-amber-500 to-orange-600') ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Inquiry Source', 'Where leads come from') ?><div class="relative" style="height:280px"><canvas id="c_src"</canvas></div><?= card_close() ?>
      <?= card_open('Doctor Meet vs Doctor Referral', 'Monthly visits vs referrals (ROI)') ?><div class="relative" style="height:280px"><canvas id="c_meet"</canvas></div><?= card_close() ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Doctor Visits Growth Trend', 'Monthly visits') ?><div class="relative" style="height:280px"><canvas id="c_growth"</canvas></div><?= card_close() ?>
      <?= card_open('Recent Enquiries', '') ?>
        <div class="divide-y divide-gray-50">
        <?php foreach ($recentEnq as $e): ?>
          <div class="flex items-center justify-between py-2.5"><div><p class="text-sm font-semibold text-gray-800"><?= h($e['patientName']) ?></p><p class="text-[11px] text-gray-400"><?= h($e['source']) ?> · <?= h(format_date($e['enquiryDate'], 'd M')) ?></p></div><span class="rounded-full px-2 py-0.5 text-[11px] font-medium bg-gray-50 text-gray-600 border"><?= h($e['status']) ?></span></div>
        <?php endforeach; if (!$recentEnq): ?><p class="py-6 text-center text-sm text-gray-300">No enquiries</p><?php endif; ?>
        </div>
      <?= card_close() ?>
    </div>
    <?php
    require __DIR__ . '/../partials/bottom.php';

/* ============================= STAFF (default) ============================= */
else:
    $appointments = entity_all('appointments');
    $enquiries = entity_all('enquiries');
    $haStock = entity_all('ha-stock');

    $todays = array_filter($appointments, fn($a) => is_today($a['date']));
    $pendingEnq = array_filter($enquiries, fn($e) => $e['status'] !== 'Completed');

    $enqStatus = [];
    foreach ($enquiries as $e) { $s = $e['status'] ?: 'Unknown'; $enqStatus[$s] = ($enqStatus[$s] ?? 0) + 1; }
    $stockByBrand = [];
    foreach ($haStock as $s) { $b = $s['brand'] ?: 'Unknown'; $stockByBrand[$b] = ($stockByBrand[$b] ?? 0) + 1; }
    arsort($stockByBrand); $stockByBrand = array_slice($stockByBrand, 0, 8, true);

    add_chart('c_enq', ['type'=>'doughnut','data'=>['labels'=>array_keys($enqStatus),'datasets'=>[['data'=>array_values($enqStatus),'backgroundColor'=>$PIE]]]]);
    add_chart('c_stock', ['type'=>'bar','data'=>['labels'=>array_keys($stockByBrand),'datasets'=>[['label'=>'Units','data'=>array_values($stockByBrand),'backgroundColor'=>'#8b5cf6']]],'options'=>['plugins'=>['legend'=>['display'=>false]]]]);

    require __DIR__ . '/../partials/top.php';
    ?>
    <a href="index.php?page=attendance" class="mb-5 flex items-center gap-4 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 p-4 text-white shadow-lg">
      <div class="flex-1"><p class="font-bold text-lg">Mark Attendance</p><p class="text-sm text-emerald-100">Check in / Check out with GPS location</p></div><div class="text-3xl">→</div>
    </a>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <?= stat_card("Today's Appointments", count($todays), 'Scheduled today', 'from-sky-500 to-blue-600') ?>
      <?= stat_card('Pending Enquiries', count($pendingEnq), 'Needs follow-up', 'from-amber-500 to-orange-600') ?>
      <?= stat_card('HA Stock', count($haStock), 'Items in inventory', 'from-violet-500 to-purple-700') ?>
      <?= stat_card('Total Enquiries', count($enquiries), 'All received', 'from-rose-500 to-pink-600') ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open('Enquiry Pipeline', 'Status breakdown') ?><div class="relative" style="height:280px"><canvas id="c_enq"</canvas></div><?= card_close() ?>
      <?= card_open('Stock by Brand', 'Hearing aid inventory') ?><div class="relative" style="height:280px"><canvas id="c_stock"</canvas></div><?= card_close() ?>
    </div>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
      <?= card_open("Today's Appointments", '') ?>
        <div class="divide-y divide-gray-50">
        <?php foreach (array_slice(array_values($todays),0,6) as $a): ?>
          <div class="flex items-center justify-between py-2.5"><div><p class="text-sm font-semibold text-gray-800"><?= h($a['patientName']) ?></p><p class="text-[11px] text-gray-400"><?= h($a['test']) ?> · <?= h($a['time']) ?></p></div></div>
        <?php endforeach; if (!$todays): ?><p class="py-6 text-center text-sm text-gray-300">No appointments today</p><?php endif; ?>
        </div>
      <?= card_close() ?>
      <?= card_open('Pending Enquiries', '') ?>
        <div class="divide-y divide-gray-50">
        <?php foreach (array_slice(array_values($pendingEnq),0,6) as $e): ?>
          <div class="flex items-center justify-between py-2.5"><div><p class="text-sm font-semibold text-gray-800"><?= h($e['patientName']) ?></p><p class="text-[11px] text-gray-400"><?= h($e['source']) ?></p></div><span class="rounded-full px-2 py-0.5 text-[11px] font-medium bg-gray-50 text-gray-600 border"><?= h($e['status']) ?></span></div>
        <?php endforeach; if (!$pendingEnq): ?><p class="py-6 text-center text-sm text-gray-300">No pending enquiries</p><?php endif; ?>
        </div>
      <?= card_close() ?>
    </div>
    <?php
    require __DIR__ . '/../partials/bottom.php';
endif;
