<?php
$title = 'Reports & Summary';
$month = $_GET['month'] ?? date('Y-m');
$branch = $_GET['branch'] ?? 'All';
$data = report_summary($month, $branch);
$maxVal = max($data['testRevenue'], $data['accessoryRevenue'], $data['haRevenue'], 1);
// last 24 months
$monthOpts = [];
for ($i = 0; $i < 24; $i++) { $ts = strtotime("first day of -$i month"); $monthOpts[date('Y-m', $ts)] = date('F Y', $ts); }
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Reports &amp; Summary</h1></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3"><input type="hidden" name="page" value="reports">
  <div><label class="block text-xs text-gray-500 mb-1">Month</label><select name="month" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach ($monthOpts as $v=>$l): ?><option value="<?= h($v) ?>" <?= $month===$v?'selected':'' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
  <div><label class="block text-xs text-gray-500 mb-1">Branch</label><select name="branch" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach (array_merge(['All'], active_branch_names()) as $b): ?><option <?= $branch===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select></div>
  <div class="flex items-end"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Apply</button></div>
</form>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
  <div class="bg-blue-50 rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-600">Test Revenue</p><p class="text-2xl font-bold mt-2 text-blue-600"><?= rupees($data['testRevenue']) ?></p></div>
  <div class="bg-amber-50 rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-600">Accessories</p><p class="text-2xl font-bold mt-2 text-amber-600"><?= rupees($data['accessoryRevenue']) ?></p></div>
  <div class="bg-purple-50 rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-600">Hearing Aids</p><p class="text-2xl font-bold mt-2 text-purple-600"><?= rupees($data['haRevenue']) ?></p></div>
  <div class="bg-green-50 rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-600">Grand Total</p><p class="text-2xl font-bold mt-2 text-green-600"><?= rupees($data['grandTotal']) ?></p></div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
  <div class="bg-white rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-500">Total Patients</p><p class="text-3xl font-bold text-gray-900 mt-1"><?= $data['totalPatients'] ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-500">Total HA Sales</p><p class="text-3xl font-bold text-gray-900 mt-1"><?= $data['totalHASales'] ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-5"><p class="text-sm text-gray-500">Total Expenses</p><p class="text-3xl font-bold text-red-600 mt-1"><?= rupees($data['expenses']) ?></p></div>
</div>
<div class="bg-white rounded-xl border border-gray-200 p-6 mb-5"><h3 class="text-lg font-semibold text-gray-900 mb-6">Revenue Breakdown</h3><div class="space-y-4">
  <?php foreach ([['Tests',$data['testRevenue'],'bg-blue-500'],['Accessories',$data['accessoryRevenue'],'bg-amber-500'],['Hearing Aids',$data['haRevenue'],'bg-purple-500']] as $it): ?>
    <div class="space-y-1"><div class="flex justify-between text-sm"><span class="font-medium text-gray-700"><?= $it[0] ?></span><span class="font-semibold"><?= rupees($it[1]) ?></span></div><div class="w-full bg-gray-100 rounded-full h-4"><div class="<?= $it[2] ?> h-4 rounded-full" style="width: <?= max(($it[1]/$maxVal)*100,0) ?>%"></div></div></div>
  <?php endforeach; ?>
</div></div>
<div class="bg-gradient-to-r from-indigo-600 to-purple-700 rounded-xl p-6 text-white"><p class="text-sm opacity-80">Report Period</p><p class="text-xl font-bold mt-1"><?= h($monthOpts[$month] ?? $month) ?></p><p class="text-sm opacity-80 mt-2">Branch: <?= h($branch) ?></p></div>
<?php require __DIR__ . '/../partials/bottom.php';
