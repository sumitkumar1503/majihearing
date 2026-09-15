<?php
$title = 'Enquiries';
$canDelete = in_array(current_role(), ['admin','marketing','staff'], true);
$STATUSES = ['New','On Follow Up','No Answer','Completed','Rejected'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'enquiryDate'=>$_POST['enquiryDate']??'','patientName'=>trim($_POST['patientName']??''),'contactNo'=>$_POST['contactNo']??'','address'=>$_POST['address']??'','source'=>$_POST['source']??'','enquiryDetails'=>$_POST['enquiryDetails']??'','tentativeAppointmentDate'=>$_POST['tentativeAppointmentDate']??'','status'=>$_POST['status']??'New','statusDate'=>$_POST['statusDate']??date('Y-m-d')];
        if ($assoc['patientName'] === '') { set_flash('error','Patient name required'); redirect('index.php?page=enquiries'); }
        if ($assoc['id'] === '') { entity_insert('enquiries', $assoc); set_flash('success','Enquiry added'); }
        else { entity_update('enquiries', $assoc); set_flash('success','Enquiry updated'); }
        redirect('index.php?page=enquiries');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('enquiries', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=enquiries'); }
}
$rows = entity_all('enquiries');
usort($rows, fn($a,$b)=>strcmp($b['enquiryDate'],$a['enquiryDate']));
$q = trim($_GET['q'] ?? ''); $sf = $_GET['status'] ?? 'All';
$rows = array_values(array_filter($rows, function($e) use ($q,$sf){ if ($q!=='' && stripos($e['patientName'],$q)===false && stripos($e['source'],$q)===false) return false; if ($sf!=='All' && $e['status']!==$sf) return false; return true; }));
$rows = paginate($rows);
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Enquiries</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Enquiry</button></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="enquiries"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search name or source..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option>All</option><?php foreach ($STATUSES as $s): ?><option <?= $sf===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Patient','Contact','Source','Details','Appt Date','Status',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $e): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($e)) ?>)'>
      <td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($e['enquiryDate'])) ?></td>
      <td class="px-4 py-3 font-medium text-gray-900"><?= h($e['patientName']) ?></td>
      <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= h($e['contactNo']) ?><?php if ($e['contactNo']): ?> <a onclick="event.stopPropagation()" href="<?= h(wa_link($e['contactNo'])) ?>" target="_blank" class="text-green-500">WA</a><?php endif; ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($e['source']) ?></td>
      <td class="px-4 py-3 text-gray-600 truncate max-w-[180px]"><?= h($e['enquiryDetails']) ?></td>
      <td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($e['tentativeAppointmentDate'])) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= pill($e['status']) ?>"><?= h($e['status']) ?></span></td>
      <td class="px-4 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($e['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No enquiries found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?= render_pagination() ?>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Enquiry</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Enquiry Date','enquiryDate','date') ?><?= ff('Patient Name *','patientName') ?><?= ff('Contact No','contactNo','tel') ?><?= ff('Source','source') ?>
      <?= ff('Address','address','text','','md:col-span-2') ?><?= ff('Enquiry Details','enquiryDetails','text','','md:col-span-2') ?>
      <?= ff('Tentative Appointment Date','tentativeAppointmentDate','date') ?><?= ffselect('Status','status',$STATUSES) ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Enquiry'; document.getElementById('f_enquiryDate').value=new Date().toISOString().slice(0,10); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Enquiry'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
