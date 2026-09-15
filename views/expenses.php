<?php
$title = 'Expenses';
$admin = is_admin();
$me = current_user()['name'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'date'=>$_POST['date']??'','staffName'=>$_POST['staffName']??'','description'=>trim($_POST['description']??''),'amount'=>$_POST['amount']??'0','branch'=>$_POST['branch']??''];
        if ($assoc['date']==='' || $assoc['description']==='' || (float)$assoc['amount']<=0) { set_flash('error','Date, description and amount are required'); redirect('index.php?page=expenses'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('expenses', $assoc['id']) : null;
        $res = submit_change('expenses', 'Expenses', $isEdit?'update':'create', $assoc, ($isEdit?'Update':'Add').' expense — '.$assoc['description'].' (₹'.$assoc['amount'].')', $old, !$admin);
        set_flash('success', $res==='queued' ? 'Change sent for admin approval' : ($isEdit?'Expense updated':'Expense added'));
        redirect('index.php?page=expenses');
    }
    if ($a === 'delete') {
        $rec = entity_find('expenses', $_POST['id']??'');
        $res = submit_change('expenses','Expenses','delete',['id'=>$_POST['id']??''],'Delete expense — '.($rec['description']??''),$rec,!$admin);
        set_flash('success', $res==='queued' ? 'Delete sent for admin approval' : 'Deleted');
        redirect('index.php?page=expenses');
    }
}
// Committed (approved) + pending/rejected create-requests as virtual rows
$committed = entity_all('expenses');
$committedIds = array_column($committed, 'id');
$rows = [];
foreach ($committed as $e) { $e['status'] = 'Approved'; $rows[] = $e; }
foreach (entity_all('approvals') as $ap) {
    if ($ap['module'] !== 'expenses' || $ap['action'] !== 'create' || $ap['status'] === 'approved') continue;
    if (in_array($ap['targetId'], $committedIds, true)) continue;
    $rec = json_decode($ap['newValue'] ?: 'null', true);
    if (is_array($rec) && !empty($rec['id'])) { $rec['status'] = $ap['status'] === 'rejected' ? 'Rejected' : 'Pending'; $rows[] = $rec; }
}
if (!$admin) $rows = array_values(array_filter($rows, fn($e) => $e['staffName'] === $me));
$q = trim($_GET['q'] ?? ''); $mf = $_GET['month'] ?? 'all';
$monthOpts = [];
foreach ($rows as $e) { $k = month_key_of($e['date']); if ($k) $monthOpts[$k] = true; }
krsort($monthOpts);
$rows = array_values(array_filter($rows, function($e) use ($q,$mf){ if ($mf!=='all' && month_key_of($e['date'])!==$mf) return false; if ($q!=='' && stripos($e['staffName'],$q)===false && stripos($e['description'],$q)===false && stripos($e['branch'],$q)===false) return false; return true; }));
$monthTotal = 0; foreach ($rows as $e) if (is_current_month($e['date'])) $monthTotal += (float)$e['amount'];
$yearTotal = 0; foreach ($rows as $e) if (substr(month_key_of($e['date']),0,4)===date('Y')) $yearTotal += (float)$e['amount'];
$branchList = active_branch_names();
$users = array_map(fn($u)=>$u['name'], entity_all('users'));
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Expenses</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Expense</button></div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
  <div class="bg-gradient-to-r from-indigo-600 to-purple-700 rounded-xl p-6 text-white"><p class="text-sm opacity-80"><?= $admin?'Total Expenses (All Staff)':'My Expenses' ?> — This Month</p><p class="text-3xl font-bold mt-1"><?= rupees($monthTotal) ?></p></div>
  <div class="bg-gradient-to-r from-emerald-600 to-teal-700 rounded-xl p-6 text-white"><p class="text-sm opacity-80"><?= $admin?'Total Expenses (All Staff)':'My Expenses' ?> — This Year</p><p class="text-3xl font-bold mt-1"><?= rupees($yearTotal) ?></p></div>
</div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3"><input type="hidden" name="page" value="expenses">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search staff, description or branch..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="month" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="all">All Months</option><?php foreach (array_keys($monthOpts) as $mk): ?><option value="<?= h($mk) ?>" <?= $mf===$mk?'selected':'' ?>><?= h(date('F Y', strtotime($mk.'-01'))) ?></option><?php endforeach; ?></select>
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button>
</form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Staff Name','Description','Amount','Branch','Status',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $e): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($e)) ?>)'>
      <td class="px-4 py-3 text-gray-600"><?= h($e['date']) ?></td>
      <td class="px-4 py-3 font-medium text-gray-900"><?= h($e['staffName']) ?></td>
      <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate"><?= h($e['description']) ?></td>
      <td class="px-4 py-3 font-medium text-green-600"><?= rupees($e['amount']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($e['branch']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= pill($e['status']) ?>"><?= h($e['status']) ?></span></td>
      <td class="px-4 py-3"><?php if ($admin): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete this expense?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($e['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No expenses found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Expense</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Date *','date','date') ?>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Staff Name *</label>
        <select name="staffName" id="f_staffName" <?= $admin?'':'disabled' ?> class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100">
          <option value="">Select Staff</option><?php foreach ($users as $n): ?><option value="<?= h($n) ?>"><?= h($n) ?></option><?php endforeach; ?>
        </select><?php if (!$admin): ?><input type="hidden" name="staffName" value="<?= h($me) ?>"><?php endif; ?></div>
      <?= ff('Description *','description','text','','md:col-span-2') ?>
      <?= ff('Amount (₹) *','amount','number','min="0"') ?>
      <?= ffselect('Branch','branch', array_merge(['' => 'Select branch'], array_combine($branchList, $branchList))) ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
var ME=<?= json_encode($me) ?>, ADMIN=<?= $admin?'true':'false' ?>;
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Expense'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); if(!ADMIN){var s=document.getElementById('f_staffName'); if(s)s.value=ME;} openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Expense'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
