<?php
$title = 'Daily Revenue Sheet';
$admin = is_admin();
$TESTS = ['PTA','TYMP','ENG','OAE','ABR','VEMP','SRT/SDS','TDT','SISI','ETF','SP. THX','SWALLOW THX','VOICE THX'];
$ACCESSORY_ROWS = ['Accessories'];
$allRows = array_merge($TESTS, $ACCESSORY_ROWS);
$MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$year = (int)($_GET['year'] ?? date('Y'));
$month = isset($_GET['month']) ? (int)$_GET['month'] : ((int)date('n') - 1); // 0-based
$branch = $_GET['branch'] ?? (active_branch_names()[0] ?? 'Serampore');
$monthStr = sprintf('%04d-%02d', $year, $month + 1);
$daysCount = (int)date('t', mktime(0, 0, 0, $month + 1, 1, $year));

function ds_day_locked(int $y, int $m0, int $d, bool $admin): bool {
    if ($admin) return false;
    $entry = mktime(0, 0, 0, $m0 + 1, $d, $y);
    $diff = floor((strtotime('today') - $entry) / 86400);
    return $diff > 3;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amt = $_POST['amt'] ?? []; $qty = $_POST['qty'] ?? [];
    $entries = [];
    foreach ($allRows as $ri => $test) {
        for ($d = 1; $d <= $daysCount; $d++) {
            $a = (float)($amt[$ri][$d] ?? 0); $qq = (float)($qty[$ri][$d] ?? 0);
            if ($a > 0 || $qq > 0) $entries[] = ['testName'=>$test,'day'=>$d,'amount'=>$a,'quantity'=>$qq];
        }
    }
    $payload = ['month'=>$monthStr,'branch'=>$branch,'entries'=>$entries];
    if ($admin) { save_daily_sheet($branch, $monthStr, $entries); set_flash('success','Daily sheet saved'); }
    else {
        entity_insert('approvals', ['createdAt'=>date('c'),'requestedBy'=>current_user()['name']??'','requestedByRole'=>current_role(),'module'=>'daily-sheet','moduleLabel'=>'Daily Sheet','action'=>'update','targetId'=>"$branch-$monthStr",'summary'=>"Update daily sheet — $branch / $monthStr",'oldValue'=>'','newValue'=>json_encode($payload),'status'=>'pending','reviewedBy'=>'','reviewedAt'=>'']);
        set_flash('success','Sheet sent for admin approval');
    }
    redirect("index.php?page=daily-sheet&year=$year&month=$month&branch=" . urlencode($branch));
}

// Load existing grid
$grid = [];
foreach (daily_sheet_rows($branch, $monthStr) as $r) { $grid[cell($r,0)][(int)cell($r,1)] = ['amount'=>(float)cell($r,2),'quantity'=>(float)cell($r,3)]; }
$rowIndex = array_flip($allRows);
$branchList = active_branch_names();
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Daily Revenue Sheet</h1><button form="dsForm" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">Save Sheet</button></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-center"><input type="hidden" name="page" value="daily-sheet">
  <select name="month" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach ($MONTH_NAMES as $i=>$mn): ?><option value="<?= $i ?>" <?= $month===$i?'selected':'' ?>><?= $mn ?></option><?php endforeach; ?></select>
  <input type="number" name="year" value="<?= $year ?>" min="2020" max="2030" onchange="this.form.submit()" class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="branch" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach ($branchList as $b): ?><option <?= $branch===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
  <?php if (!$admin): ?><span class="ml-auto text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg">Entries older than 3 days are locked</span><?php endif; ?>
</form>
<?php
// Totals
$testTotal = 0; $accTotal = 0; $grand = 0; $totalQty = 0;
foreach ($allRows as $test) { for ($d=1;$d<=$daysCount;$d++){ $c=$grid[$test][$d]??null; if($c){ $grand+=$c['amount']; $totalQty+=$c['quantity']; if(in_array($test,$TESTS,true))$testTotal+=$c['amount']; else $accTotal+=$c['amount']; } } }
?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Tests Revenue</p><p class="text-xl font-bold text-indigo-600"><?= rupees($testTotal) ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Accessories Revenue</p><p class="text-xl font-bold text-purple-600"><?= rupees($accTotal) ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Grand Total</p><p class="text-2xl font-bold text-green-600" id="grandTotal"><?= rupees($grand) ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Quantity</p><p class="text-2xl font-bold text-gray-900"><?= $totalQty ?></p></div>
</div>
<form method="post" id="dsForm" class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="text-xs border-collapse">
  <thead><tr class="bg-gray-100"><th class="sticky left-0 z-10 bg-gray-100 px-2 py-2 text-left font-semibold border border-gray-200 min-w-[110px]">Test / Item</th><?php for ($d=1;$d<=$daysCount;$d++): $lk=ds_day_locked($year,$month,$d,$admin); ?><th colspan="2" class="px-1 py-2 text-center font-semibold border border-gray-200 min-w-[76px] <?= $lk?'bg-gray-200 text-gray-500':'' ?>"><?= $d ?></th><?php endfor; ?><th colspan="2" class="px-2 py-2 text-center font-bold border border-gray-200 bg-yellow-50">TOTAL</th></tr>
  <tr class="bg-gray-50"><th class="sticky left-0 z-10 bg-gray-50 border border-gray-200"></th><?php for ($d=1;$d<=$daysCount;$d++): ?><th class="px-1 py-1 text-center text-[10px] text-gray-500 border border-gray-200">Amt</th><th class="px-0.5 py-1 text-center text-[10px] text-gray-500 border border-gray-200">Qty</th><?php endfor; ?><th class="px-1 py-1 text-[10px] text-gray-500 border border-gray-200">AMT</th><th class="px-1 py-1 text-[10px] text-gray-500 border border-gray-200">QTY</th></tr></thead>
  <tbody>
  <?php foreach ($allRows as $test): $ri=$rowIndex[$test]; $rAmt=0;$rQty=0; ?>
    <tr class="hover:bg-gray-50"><td class="sticky left-0 z-10 bg-white px-2 py-1 font-medium border border-gray-200 whitespace-nowrap text-xs"><?= h($test) ?></td>
      <?php for ($d=1;$d<=$daysCount;$d++): $c=$grid[$test][$d]??null; $lk=ds_day_locked($year,$month,$d,$admin); $rAmt+=$c['amount']??0; $rQty+=$c['quantity']??0; ?>
        <td colspan="2" class="border border-gray-200 p-0 <?= ($c && ($c['amount']||$c['quantity']))?'bg-green-50':'' ?>"><div class="flex"><input type="number" name="amt[<?= $ri ?>][<?= $d ?>]" value="<?= $c && $c['amount']?$c['amount']:'' ?>" <?= $lk?'disabled':'' ?> class="w-12 px-1 py-1 text-center text-xs border-r border-gray-200 outline-none disabled:bg-gray-100"><input type="number" name="qty[<?= $ri ?>][<?= $d ?>]" value="<?= $c && $c['quantity']?$c['quantity']:'' ?>" <?= $lk?'disabled':'' ?> class="w-7 px-0.5 py-1 text-center text-xs outline-none disabled:bg-gray-100"></div></td>
      <?php endfor; ?>
      <td class="border border-gray-200 px-2 py-1 text-center font-semibold bg-yellow-50"><?= rupees($rAmt) ?></td><td class="border border-gray-200 px-2 py-1 text-center font-semibold bg-yellow-50"><?= $rQty ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></form>
<?php require __DIR__ . '/../partials/bottom.php';
