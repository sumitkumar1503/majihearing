<?php
$title = 'HA Stock / Inventory';
$admin = is_admin();
$canDelete = in_array(current_role(), ['admin','staff'], true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'date'=>$_POST['date']??'','brand'=>$_POST['brand']??'','model'=>trim($_POST['model']??''),'serialNumber'=>$_POST['serialNumber']??'','mfdDate'=>$_POST['mfdDate']??'','source'=>$_POST['source']??'','branch'=>$_POST['branch']??'','soldDate'=>$_POST['soldDate']??'','remarks'=>$_POST['remarks']??'','mrp'=>$_POST['mrp']??''];
        if ($assoc['model']==='' || $assoc['brand']==='') { set_flash('error','Brand and model are required'); redirect('index.php?page=ha-stock'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('ha-stock', $assoc['id']) : null;
        $res = submit_change('ha-stock','HA Stock',$isEdit?'update':'create',$assoc,($isEdit?'Update':'Add').' HA stock — '.$assoc['brand'].' '.$assoc['model'].' (SN '.$assoc['serialNumber'].')',$old,!$admin);
        set_flash('success', $res==='queued' ? 'Change sent for admin approval' : ($isEdit?'Stock updated':'Stock item added'));
        redirect('index.php?page=ha-stock');
    }
    if ($a === 'delete') {
        $rec = entity_find('ha-stock', $_POST['id']??'');
        $res = submit_change('ha-stock','HA Stock','delete',['id'=>$_POST['id']??''],'Delete HA stock — '.($rec['brand']??'').' '.($rec['model']??''),$rec,!$admin);
        set_flash('success', $res==='queued'?'Change sent for admin approval':'Deleted');
        redirect('index.php?page=ha-stock');
    }
}
$stock = entity_all('ha-stock');
$brandFilter = $_GET['brand'] ?? 'All';
$branchFilter = $_GET['branchf'] ?? 'All';
$availFilter = $_GET['avail'] ?? 'All';
$q = trim($_GET['q'] ?? '');
$branchScoped = $branchFilter === 'All' ? $stock : array_values(array_filter($stock, fn($s)=>$s['branch']===$branchFilter));
$totalCount = count($branchScoped);
$availCount = count(array_filter($branchScoped, fn($s)=>$s['soldDate']===''));
$soldCount = count(array_filter($branchScoped, fn($s)=>$s['soldDate']!==''));
$byBrand = [];
foreach ($branchScoped as $s) { $b=$s['brand']?:'—'; if(!isset($byBrand[$b]))$byBrand[$b]=['total'=>0]; $byBrand[$b]['total']++; }
$rows = array_values(array_filter($stock, function($s) use ($q,$brandFilter,$branchFilter,$availFilter){
  if ($q!=='' && stripos($s['model'],$q)===false && stripos($s['serialNumber'],$q)===false) return false;
  if ($brandFilter!=='All' && $s['brand']!==$brandFilter) return false;
  if ($branchFilter!=='All' && $s['branch']!==$branchFilter) return false;
  if ($availFilter==='Available' && $s['soldDate']!=='') return false;
  if ($availFilter==='Sold' && $s['soldDate']==='') return false;
  return true;
}));
usort($rows, fn($a,$b)=>strcmp($b['date'],$a['date']));
$brands = active_brand_names();
$branchList = active_branch_names();
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">HA Stock / Inventory</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Stock Item</button></div>
<?php if ($admin): ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Stock</p><p class="text-2xl font-bold text-gray-900"><?= $totalCount ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Available</p><p class="text-2xl font-bold text-green-600"><?= $availCount ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Sold</p><p class="text-2xl font-bold text-red-600"><?= $soldCount ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Brands</p><p class="text-2xl font-bold text-indigo-600"><?= count($byBrand) ?></p></div>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-3 mb-5"><div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Available Stock<?= $branchFilter!=='All'?' — '.h($branchFilter):'' ?></p><p class="text-2xl font-bold text-green-600"><?= $availCount ?></p></div></div>
<?php endif; ?>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 space-y-3"><input type="hidden" name="page" value="ha-stock">
  <div class="flex gap-2 overflow-x-auto pb-1">
    <button name="brand" value="All" class="rounded-full px-4 py-1.5 text-sm font-medium whitespace-nowrap <?= $brandFilter==='All'?'bg-indigo-600 text-white':'bg-gray-100 text-gray-600' ?>">All (<?= count($stock) ?>)</button>
    <?php foreach ($brands as $b): $cnt=$byBrand[$b]['total']??0; if($cnt===0 && $brandFilter!==$b) continue; ?><button name="brand" value="<?= h($b) ?>" class="rounded-full px-4 py-1.5 text-sm font-medium whitespace-nowrap <?= $brandFilter===$b?'bg-indigo-600 text-white':'bg-gray-100 text-gray-600' ?>"><?= h($b) ?> (<?= $cnt ?>)</button><?php endforeach; ?>
  </div>
  <div class="flex flex-col sm:flex-row gap-3">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search model or serial..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <select name="branchf" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Branches</option><?php foreach ($branchList as $b): ?><option <?= $branchFilter===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
    <select name="avail" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach (['All'=>'All Status','Available'=>'Available','Sold'=>'Sold'] as $v=>$l): ?><option value="<?= $v ?>" <?= $availFilter===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button>
  </div>
</form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Brand','Model','Serial No','Mfd Date','Source','Branch','Status','Sold Date','MRP',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $s): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($s)) ?>)'>
      <td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($s['date'])) ?></td><td class="px-4 py-3 font-medium"><?= h($s['brand']) ?></td>
      <td class="px-4 py-3 text-gray-900"><?= h($s['model']) ?></td><td class="px-4 py-3 text-gray-600"><?= h($s['serialNumber']) ?></td>
      <td class="px-4 py-3 whitespace-nowrap"><?= $s['mfdDate']?h($s['mfdDate']):'-' ?></td><td class="px-4 py-3 text-gray-600"><?= h($s['source']) ?></td><td class="px-4 py-3"><?= h($s['branch']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= $s['soldDate']?'bg-red-100 text-red-700':'bg-green-100 text-green-700' ?>"><?= $s['soldDate']?'Sold':'Available' ?></span></td>
      <td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($s['soldDate'])) ?></td><td class="px-4 py-3 whitespace-nowrap"><?= $s['mrp']?'₹'.h($s['mrp']):'-' ?></td>
      <td class="px-4 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($s['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="11" class="px-4 py-10 text-center text-gray-400">No stock items found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Stock Item</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Date','date','date') ?>
      <?= ffselect('Brand *','brand', array_combine($brands ?: [''], $brands ?: [''])) ?>
      <?= ff('Model *','model') ?><?= ff('Serial Number','serialNumber') ?><?= ff('Mfd Date','mfdDate','date') ?><?= ff('Source','source') ?>
      <?= ffselect('Branch','branch', array_combine($branchList, $branchList)) ?>
      <?= ff('Sold Date','soldDate','date') ?><?= ff('MRP (₹)','mrp') ?><?= ff('Remarks','remarks') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Stock Item'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Stock Item'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
