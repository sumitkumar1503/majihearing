<?php
$title = 'Doctor Payments';
$admin = is_admin();
$canDelete = is_admin();
function ds_days(?string $d): int { $t = safe_parse_date($d); return $t ? (int)((time() - $t->getTimestamp())/86400) : 0; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'date'=>$_POST['date']??'','doctorReferral'=>$_POST['doctorReferral']??'','noOfPatients'=>$_POST['noOfPatients']??'0','fromDate'=>$_POST['fromDate']??'','tillDate'=>$_POST['tillDate']??'','amount'=>$_POST['amount']??'','visitLocation'=>$_POST['visitLocation']??'','visitedBy'=>$_POST['visitedBy']??''];
        foreach (['date','doctorReferral','noOfPatients','amount','fromDate','tillDate','visitLocation','visitedBy'] as $req) if ($assoc[$req]==='') { set_flash('error','All fields are required'); redirect('index.php?page=dr-payments'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('dr-payments', $assoc['id']) : null;
        $needsApproval = $isEdit && !$admin && $old && ds_days($old['date']) > 3;
        $res = submit_change('dr-payments','Doctor Payments',$isEdit?'update':'create',$assoc,'Update doctor payment — '.$assoc['doctorReferral'].' ₹'.$assoc['amount'].' (edit after 3-day lock)',$old,$needsApproval);
        set_flash('success', $res==='queued' ? 'Edit sent to Admin for approval (locked after 3 days)' : ($isEdit?'Payment updated':'Payment added'));
        redirect('index.php?page=dr-payments');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('dr-payments', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=dr-payments'); }
}
$rows = entity_all('dr-payments');
$q = trim($_GET['q'] ?? '');
if ($q !== '') $rows = array_values(array_filter($rows, fn($p)=>stripos($p['doctorReferral'],$q)!==false||stripos($p['visitLocation'],$q)!==false));
$totalAmount = 0; $totalPatients = 0;
foreach ($rows as $p) { $totalAmount += (float)$p['amount']; $totalPatients += (int)$p['noOfPatients']; }
$monthly = []; $yearly = [];
foreach ($rows as $p) { $t = safe_parse_date($p['date']); if ($t) { $monthly[$t->format('M Y')] = ($monthly[$t->format('M Y')]??0)+(float)$p['amount']; $yearly[$t->format('Y')] = ($yearly[$t->format('Y')]??0)+(float)$p['amount']; } }
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Doctor Payments</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Payment</button></div>
<div class="grid grid-cols-1 gap-4 mb-5 <?= $admin ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?>">
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Payments</p><p class="text-2xl font-bold text-gray-900"><?= count($rows) ?></p></div>
  <?php if ($admin): ?><div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Amount</p><p class="text-2xl font-bold text-green-600"><?= rupees($totalAmount) ?></p></div><?php endif; ?>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Patients Covered</p><p class="text-2xl font-bold text-indigo-600"><?= $totalPatients ?></p></div>
</div>
<?php if ($admin && $monthly): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
  <div class="bg-white rounded-xl border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-700 mb-3">Monthly Breakdown</h3><div class="space-y-2"><?php foreach (array_slice($monthly,0,6,true) as $m=>$amt): ?><div class="flex justify-between items-center"><span class="text-sm text-gray-600"><?= h($m) ?></span><span class="text-sm font-medium text-green-600"><?= rupees($amt) ?></span></div><?php endforeach; ?></div></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-700 mb-3">Yearly Breakdown</h3><div class="space-y-2"><?php foreach ($yearly as $y=>$amt): ?><div class="flex justify-between items-center"><span class="text-sm text-gray-600"><?= h($y) ?></span><span class="text-sm font-medium text-green-600"><?= rupees($amt) ?></span></div><?php endforeach; ?></div></div>
</div>
<?php endif; ?>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="dr-payments"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search doctor or location..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Doctor/Referral','No. of Patients','From-To','Amount','Location','Visited By',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $p): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($p)) ?>)'>
      <td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($p['date'])) ?></td><td class="px-4 py-3 font-medium text-gray-900"><?= h($p['doctorReferral']) ?></td>
      <td class="px-4 py-3 text-center"><?= h($p['noOfPatients']) ?></td>
      <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= h(format_date($p['fromDate'],'d M')) ?> — <?= h(format_date($p['tillDate'],'d M')) ?></td>
      <td class="px-4 py-3 font-medium text-green-600 whitespace-nowrap">₹<?= h($p['amount']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($p['visitLocation']) ?></td><td class="px-4 py-3 text-gray-600"><?= h($p['visitedBy']) ?></td>
      <td class="px-4 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($p['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No payments found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Payment</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Date *','date','date') ?><?= ff('Doctor/Referral *','doctorReferral') ?><?= ff('No. of Patients *','noOfPatients','number') ?><?= ff('Amount (₹) *','amount') ?>
      <?= ff('From Date *','fromDate','date') ?><?= ff('Till Date *','tillDate','date') ?><?= ff('Visit Location *','visitLocation') ?><?= ff('Visited By *','visitedBy') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Payment'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Payment'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
