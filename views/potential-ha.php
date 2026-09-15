<?php
$title = 'Potential HA';
$canDelete = in_array(current_role(), ['admin','marketing','staff','doctor'], true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'date'=>$_POST['date']??'','name'=>trim($_POST['name']??''),'ageSex'=>$_POST['ageSex']??'','contactNo'=>$_POST['contactNo']??'','referral'=>$_POST['referral']??'','branch'=>$_POST['branch']??'','diagnosisRight'=>$_POST['diagnosisRight']??'','diagnosisLeft'=>$_POST['diagnosisLeft']??'','remarks'=>$_POST['remarks']??'','followUp1Date'=>$_POST['followUp1Date']??'','followUp2Date'=>$_POST['followUp2Date']??''];
        if ($assoc['name'] === '') { set_flash('error','Name is required'); redirect('index.php?page=potential-ha'); }
        if ($assoc['id'] === '') { entity_insert('potential-ha', $assoc); set_flash('success','Added'); }
        else { entity_update('potential-ha', $assoc); set_flash('success','Updated'); }
        redirect('index.php?page=potential-ha');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('potential-ha', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=potential-ha'); }
}
$rows = entity_all('potential-ha');
usort($rows, fn($a,$b)=>strcmp($b['date'],$a['date']));
$q = trim($_GET['q'] ?? '');
if ($q !== '') $rows = array_values(array_filter($rows, fn($p) => stripos($p['name'],$q)!==false || strpos($p['contactNo'],$q)!==false));
$branchList = active_branch_names();
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Potential Hearing Aid</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add</button></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="potential-ha"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search name or contact..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Name','Age/Sex','Contact','Referral','Branch','Dx (R)','Dx (L)','Follow-up 1','Follow-up 2',''] as $htxt): ?><th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $p): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($p)) ?>)'>
      <td class="px-3 py-3 whitespace-nowrap"><?= h(format_date($p['date'])) ?></td>
      <td class="px-3 py-3 font-medium text-gray-900"><?= h($p['name']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($p['ageSex']) ?></td>
      <td class="px-3 py-3 text-gray-600 whitespace-nowrap"><?= h($p['contactNo']) ?><?php if ($p['contactNo']): ?> <a onclick="event.stopPropagation()" href="<?= h(wa_link($p['contactNo'])) ?>" target="_blank" class="text-green-500">WA</a><?php endif; ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($p['referral']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($p['branch']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($p['diagnosisRight']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($p['diagnosisLeft']) ?></td>
      <td class="px-3 py-3 whitespace-nowrap"><?= h(format_date($p['followUp1Date'])) ?></td>
      <td class="px-3 py-3 whitespace-nowrap"><?= h(format_date($p['followUp2Date'])) ?></td>
      <td class="px-3 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($p['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="11" class="px-4 py-10 text-center text-gray-400">No records found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Potential HA</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Date','date','date') ?><?= ff('Name *','name') ?><?= ff('Age/Sex','ageSex') ?><?= ff('Contact No','contactNo','tel') ?>
      <?= ff('Referral','referral') ?><?= ffselect('Branch','branch',$branchList) ?>
      <?= ff('Diagnosis (Right)','diagnosisRight') ?><?= ff('Diagnosis (Left)','diagnosisLeft') ?>
      <?= ff('Follow-up 1 Date','followUp1Date','date') ?><?= ff('Follow-up 2 Date','followUp2Date','date') ?>
      <?= ff('Remarks','remarks','text','','md:col-span-2') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Potential HA'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Potential HA'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
