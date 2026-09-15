<?php
$title = 'Accessories';
$admin = is_admin();
$COMMON = ['Hearing Aid Batteries','Molds','Earplugs','Wax Guards','Cleaning Kit','Domes','Receiver','Tubing','Dry Box','Charger'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'date'=>$_POST['date']??'','name'=>trim($_POST['name']??''),'model'=>$_POST['model']??'','quantity'=>$_POST['quantity']??'1','source'=>$_POST['source']??'','branch'=>$_POST['branch']??'','remarks'=>$_POST['remarks']??'','price'=>$_POST['price']??'0','type'=>$_POST['type']??'Stock In'];
        if ($assoc['date']==='' || $assoc['name']==='') { set_flash('error','Date and accessory name are required'); redirect('index.php?page=accessories'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('accessories', $assoc['id']) : null;
        $res = submit_change('accessories','Accessories',$isEdit?'update':'create',$assoc,($isEdit?'Update':'Add').' accessory — '.$assoc['name'].' ×'.$assoc['quantity'],$old,!$admin);
        set_flash('success', $res==='queued'?'Change sent for admin approval':($isEdit?'Accessory updated':'Accessory added'));
        redirect('index.php?page=accessories');
    }
    if ($a === 'delete') {
        $rec = entity_find('accessories', $_POST['id']??'');
        $res = submit_change('accessories','Accessories','delete',['id'=>$_POST['id']??''],'Delete accessory — '.($rec['name']??''),$rec,!$admin);
        set_flash('success', $res==='queued'?'Delete sent for admin approval':'Deleted');
        redirect('index.php?page=accessories');
    }
}
$items = entity_all('accessories');
$q = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($items, fn($i)=>$q===''||stripos($i['name'],$q)!==false||stripos($i['model'],$q)!==false||stripos($i['branch'],$q)!==false));
usort($rows, fn($a,$b)=>strcmp($b['date'],$a['date']));
$inv = [];
foreach ($items as $i) { $n=$i['name']; if(!isset($inv[$n]))$inv[$n]=['in'=>0,'sold'=>0,'price'=>0]; if(($i['type']??'')==='Sold')$inv[$n]['sold']+=(int)$i['quantity']; else $inv[$n]['in']+=(int)$i['quantity']; if($i['price'])$inv[$n]['price']=$i['price']; }
ksort($inv);
$branchList = active_branch_names();
require __DIR__ . '/../partials/top.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
  <div><h1 class="text-2xl font-bold text-gray-900">Other Accessories</h1><p class="text-sm text-gray-500">Batteries, molds, earplugs and other stock items.</p></div>
  <div class="flex gap-2"><button onclick="openSale()" class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700">+ Record Sale</button><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Stock</button></div>
</div>
<?php if ($inv): ?>
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-5"><h3 class="text-sm font-semibold text-gray-700 mb-3">Stock Levels</h3><div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
  <?php foreach ($inv as $n=>$v): $avail=$v['in']-$v['sold']; ?><div class="rounded-lg border border-gray-200 p-3"><p class="text-sm font-medium text-gray-900 truncate" title="<?= h($n) ?>"><?= h($n) ?></p><div class="mt-1 flex items-baseline gap-2"><span class="text-xl font-bold <?= $avail>0?'text-green-600':'text-red-600' ?>"><?= $avail ?></span><span class="text-xs text-gray-400">available</span></div><p class="text-[11px] text-gray-400 mt-0.5">In <?= $v['in'] ?> · Sold <?= $v['sold'] ?><?= $v['price']?' · ₹'.h($v['price']):'' ?></p></div><?php endforeach; ?>
</div></div>
<?php endif; ?>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="accessories"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name, model or branch..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Accessory','Type','Qty','Price','Branch',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $i): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($i)) ?>)'>
      <td class="px-4 py-3 text-gray-600"><?= h(format_date($i['date'])) ?></td>
      <td class="px-4 py-3 font-medium text-gray-900"><?= h($i['name']) ?><?= $i['model']?' ('.h($i['model']).')':'' ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= ($i['type']??'')==='Sold'?'bg-amber-100 text-amber-700':'bg-green-100 text-green-700' ?>"><?= h($i['type']?:'Stock In') ?></span></td>
      <td class="px-4 py-3 font-medium text-indigo-600"><?= h($i['quantity']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= $i['price']?'₹'.h($i['price']):'—' ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($i['branch']?:'—') ?></td>
      <td class="px-4 py-3"><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($i['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No accessories found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Accessory Stock</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Date *','date','date') ?>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Accessory Name *</label><input list="accNames" name="name" id="f_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><datalist id="accNames"><?php foreach ($COMMON as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist></div>
      <?= ffselect('Entry Type','type', ['Stock In'=>'Stock In (add to inventory)','Sold'=>'Sold (deduct from inventory)']) ?>
      <?= ff('Quantity','quantity','number','min="0"') ?><?= ff('Unit Price (₹)','price','number','min="0"') ?>
      <?= ff('Model Number','model') ?><?= ff('Source / Destination','source') ?>
      <?= ffselect('Branch','branch', array_merge(['' => 'Select branch'], array_combine($branchList,$branchList))) ?>
      <?= ff('Remarks','remarks','text','','md:col-span-2') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Accessory Stock'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); document.getElementById('f_type').value='Stock In'; openModal('modal'); }
function openSale(){ resetForm(); document.getElementById('modalTitle').textContent='Record Accessory Sale'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); document.getElementById('f_type').value='Sold'; openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Accessory'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
