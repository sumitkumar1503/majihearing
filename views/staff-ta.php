<?php
$title = 'Staff Travel Allowance';
$admin = is_admin();
$me = current_user()['name'] ?? '';
$MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$curMonthLabel = $MONTHS[(int)date('n') - 1] . ' ' . date('Y');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'staffName'=>$_POST['staffName']??'','month'=>$_POST['month']??$curMonthLabel,'date'=>$_POST['date']??'','from'=>$_POST['from']??'','to'=>$_POST['to']??'','place'=>$_POST['place']??'','doctorName'=>$_POST['doctorName']??'','purpose'=>$_POST['purpose']??'','expense'=>$_POST['expense']??'','totalAmount'=>$_POST['totalAmount']??''];
        if ($assoc['staffName']==='' || $assoc['date']==='') { set_flash('error','Staff name and date are required'); redirect('index.php?page=staff-ta'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('staff-ta', $assoc['id']) : null;
        $res = submit_change('staff-ta','Staff TA',$isEdit?'update':'create',$assoc,($isEdit?'Update':'Add').' TA — '.$assoc['staffName'].' (₹'.$assoc['totalAmount'].')',$old,!$admin);
        set_flash('success', $res==='queued' ? 'TA submitted for admin approval' : ($isEdit?'Entry updated':'Entry added'));
        redirect('index.php?page=staff-ta');
    }
    if ($a === 'delete' && $admin) { entity_delete('staff-ta', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=staff-ta'); }
}
$committed = entity_all('staff-ta');
$committedIds = array_column($committed, 'id');
$rows = [];
foreach ($committed as $e) { $e['status'] = 'Approved'; $rows[] = $e; }
foreach (entity_all('approvals') as $ap) {
    if ($ap['module'] !== 'staff-ta' || $ap['action'] !== 'create' || $ap['status'] === 'approved') continue;
    if (in_array($ap['targetId'], $committedIds, true)) continue;
    $rec = json_decode($ap['newValue'] ?: 'null', true);
    if (is_array($rec) && !empty($rec['id'])) { $rec['status'] = $ap['status']==='rejected'?'Rejected':'Pending'; $rows[] = $rec; }
}
if (!$admin) $rows = array_values(array_filter($rows, fn($e) => $e['staffName'] === $me));
$monthOpts = array_values(array_unique(array_filter(array_map(fn($e)=>$e['month'], $rows))));
$sel = $_GET['month'] ?? $curMonthLabel;
$staffFilter = $_GET['staff'] ?? 'All';
$staffNames = array_values(array_unique(array_filter(array_map(fn($e)=>$e['staffName'], $rows))));
$q = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($rows, function($e) use ($q,$sel,$staffFilter){ if ($q!=='' && stripos($e['place'],$q)===false && stripos($e['doctorName'],$q)===false) return false; if ($sel && $e['month']!==$sel) return false; if ($staffFilter!=='All' && $e['staffName']!==$staffFilter) return false; return true; }));
$monthTotal = 0; foreach ($rows as $e) $monthTotal += (float)$e['totalAmount'];
$users = array_map(fn($u)=>$u['name'], entity_all('users'));
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Staff Travel Allowance</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Entry</button></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3"><input type="hidden" name="page" value="staff-ta">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search place or doctor..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="month" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="<?= h($curMonthLabel) ?>"><?= h($curMonthLabel) ?></option><?php foreach ($monthOpts as $m): if ($m===$curMonthLabel) continue; ?><option value="<?= h($m) ?>" <?= $sel===$m?'selected':'' ?>><?= h($m) ?></option><?php endforeach; ?></select>
  <select name="staff" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Staff</option><?php foreach ($staffNames as $n): ?><option <?= $staffFilter===$n?'selected':'' ?>><?= h($n) ?></option><?php endforeach; ?></select>
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button>
</form>
<div class="bg-gradient-to-r from-indigo-600 to-purple-700 rounded-xl p-6 text-white mb-5"><p class="text-sm opacity-80">Monthly Total — <?= h($sel) ?></p><p class="text-3xl font-bold mt-1"><?= rupees($monthTotal) ?></p><p class="text-sm opacity-80 mt-1"><?= count($rows) ?> entries</p></div>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Staff','From','To','Place','Doctor','Purpose','Expense','Amount','Status',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $e): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($e)) ?>)'>
      <td class="px-4 py-3"><?= h($e['date']) ?></td><td class="px-4 py-3 font-medium text-gray-900"><?= h($e['staffName']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($e['from']) ?></td><td class="px-4 py-3 text-gray-600"><?= h($e['to']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($e['place']) ?></td><td class="px-4 py-3 text-gray-600"><?= h($e['doctorName']) ?></td>
      <td class="px-4 py-3 text-gray-600 max-w-[150px] truncate"><?= h($e['purpose']) ?></td><td class="px-4 py-3 text-gray-600"><?= h($e['expense']) ?></td>
      <td class="px-4 py-3 font-medium text-green-600">₹<?= h($e['totalAmount']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= pill($e['status']) ?>"><?= h($e['status']) ?></span></td>
      <td class="px-4 py-3"><?php if ($admin): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($e['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="11" class="px-4 py-10 text-center text-gray-400">No entries for this month</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add TA Entry</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Staff Name *</label>
        <select name="staffName" id="f_staffName" <?= $admin?'':'disabled' ?> class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100"><option value="">Select Staff</option><?php foreach ($users as $n): ?><option value="<?= h($n) ?>"><?= h($n) ?></option><?php endforeach; ?></select>
        <?php if (!$admin): ?><input type="hidden" name="staffName" value="<?= h($me) ?>"><?php endif; ?></div>
      <?= ff('Month','month','text','placeholder="e.g. September 2026"') ?>
      <?= ff('Date (day) *','date','number','min="1" max="31"') ?><?= ff('From','from') ?><?= ff('To','to') ?><?= ff('Place','place') ?>
      <?= ff('Doctor Name','doctorName') ?><?= ff('Purpose','purpose') ?><?= ff('Expense','expense','text','placeholder="e.g. Bus, Auto"') ?><?= ff('Total Amount (₹)','totalAmount') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
var ME=<?= json_encode($me) ?>, ADMIN=<?= $admin?'true':'false' ?>, CURM=<?= json_encode($curMonthLabel) ?>;
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add TA Entry'; document.getElementById('f_month').value=CURM; document.getElementById('f_date').value=new Date().getDate(); if(!ADMIN){var s=document.getElementById('f_staffName'); if(s)s.value=ME;} openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit TA Entry'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
