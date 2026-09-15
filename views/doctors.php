<?php
$title = 'Doctors';
$canDelete = is_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'name'=>trim($_POST['name']??''),'qualifications'=>$_POST['qualifications']??'','speciality'=>$_POST['speciality']??'','contactNo'=>$_POST['contactNo']??'','chamberLocations'=>$_POST['chamberLocations']??''];
        if ($assoc['name'] === '') { set_flash('error','Doctor name is required'); redirect('index.php?page=doctors'); }
        if ($assoc['id'] === '') { entity_insert('doctors', $assoc); set_flash('success','Doctor added'); }
        else { entity_update('doctors', $assoc); set_flash('success','Doctor updated'); }
        redirect('index.php?page=doctors');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('doctors', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=doctors'); }
}
$rows = entity_all('doctors');
$q = trim($_GET['q'] ?? '');
if ($q !== '') $rows = array_values(array_filter($rows, fn($d) => stripos($d['name'],$q)!==false || stripos($d['speciality'],$q)!==false));
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Doctors</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Doctor</button></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="doctors"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name or speciality..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Name','Qualifications','Speciality','Contact','Chambers',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $d): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($d)) ?>)'>
      <td class="px-4 py-3 font-medium text-gray-900"><?= h($d['name']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($d['qualifications']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full bg-indigo-100 text-indigo-700 px-2 py-1 text-xs font-medium"><?= h($d['speciality']) ?></span></td>
      <td class="px-4 py-3 text-gray-600"><?= h($d['contactNo']) ?></td>
      <td class="px-4 py-3 text-gray-600 truncate max-w-[200px]"><?= h($d['chamberLocations']) ?></td>
      <td class="px-4 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($d['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No doctors found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Doctor</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Name *','name') ?><?= ff('Qualifications','qualifications') ?><?= ff('Speciality','speciality') ?><?= ff('Contact No','contactNo','tel') ?>
      <?= ff('Chamber Locations','chamberLocations','text','','md:col-span-2') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Doctor'; openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Doctor'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
