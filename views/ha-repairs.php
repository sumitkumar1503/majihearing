<?php
$title = 'HA Repairs';
$canDelete = is_admin();
$SERVICE_TYPES = ['Repair Within Warranty','Repair Non-Warranty','Full Soft Mold','Half Soft Mold','Tip-Type Soft Mold','Hard Mold','Retubing','RIC Mold','Instant Fit CIC','Custom CIC','ITC/ITE','Reselling','Pulling Thread Repair','Other'];
$STATUS_OPTIONS = ['Pending','In Progress','Completed','Incomplete'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $st = $_POST['serviceType'] ?? '';
        if ($st === 'Other') $st = trim($_POST['customServiceType'] ?? '');
        $f = fn($k)=>$_POST[$k]??'';
        $assoc = ['id'=>trim($f('id')),'date'=>$f('date'),'patientName'=>trim($f('patientName')),'phoneNumber'=>$f('phoneNumber'),'brand'=>$f('brand'),'model'=>$f('model'),'ear'=>$f('ear')?:'Right','oldSerialNoRight'=>$f('oldSerialNoRight'),'newSerialNoRight'=>$f('newSerialNoRight'),'oldSerialNoLeft'=>$f('oldSerialNoLeft'),'newSerialNoLeft'=>$f('newSerialNoLeft'),'oldChargerSerial'=>$f('oldChargerSerial'),'newChargerSerial'=>$f('newChargerSerial'),'serviceType'=>$st,'cost'=>$f('cost')?:'0','status'=>$f('status')?:'Pending','remarks'=>$f('remarks'),'branch'=>$f('branch'),'courierNo'=>$f('courierNo'),'sentDate'=>$f('sentDate'),'receivedDate'=>$f('receivedDate'),'deliveryDate'=>$f('deliveryDate'),'source'=>$f('source'),'purchaseDate'=>$f('purchaseDate')];
        if ($assoc['patientName']==='') { set_flash('error','Patient Name is required'); redirect('index.php?page=ha-repairs'); }
        if ($st==='') { set_flash('error','Service Type is required'); redirect('index.php?page=ha-repairs'); }
        if ($assoc['id']==='') { entity_insert('ha-repairs', $assoc); set_flash('success','Repair added'); }
        else { entity_update('ha-repairs', $assoc); set_flash('success','Repair updated'); }
        redirect('index.php?page=ha-repairs');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('ha-repairs', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=ha-repairs'); }
}
$repairs = entity_all('ha-repairs');
$q = trim($_GET['q'] ?? ''); $branchFilter = $_GET['branchf'] ?? 'All'; $statusFilter = $_GET['status'] ?? 'All';
$rows = array_values(array_filter($repairs, function($r) use ($q,$branchFilter,$statusFilter){
  if ($q!=='' && stripos($r['patientName'],$q)===false && strpos($r['phoneNumber'],$q)===false && stripos($r['brand'],$q)===false && stripos($r['model'],$q)===false) return false;
  if ($branchFilter!=='All' && $r['branch']!==$branchFilter) return false;
  if ($statusFilter!=='All' && $r['status']!==$statusFilter) return false;
  return true;
}));
$repairsThisMonth = count(array_filter($repairs, fn($r)=>is_current_month($r['date'])));
$pendingAll = count(array_filter($repairs, fn($r)=>$r['status']==='Pending'));
$brands = active_brand_names(); $branchList = active_branch_names();
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">HA Repairs</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Repair</button></div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Repairs (This Month)</p><p class="text-2xl font-bold text-gray-900"><?= $repairsThisMonth ?></p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Pending Repairs (All Time)</p><p class="text-2xl font-bold text-yellow-600"><?= $pendingAll ?></p></div>
</div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3"><input type="hidden" name="page" value="ha-repairs">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name, phone, brand, model..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="branchf" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Branches</option><?php foreach ($branchList as $b): ?><option <?= $branchFilter===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
  <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Statuses</option><?php foreach ($STATUS_OPTIONS as $s): ?><option <?= $statusFilter===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button>
</form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Patient Name','Phone','Brand','Model','Ear','Service Type','Source','Purchase Date','Cost','Status','Delivery Date','Branch',''] as $htxt): ?><th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $r): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($r)) ?>)'>
      <td class="px-3 py-3 whitespace-nowrap"><?= h(format_date($r['date'])) ?></td><td class="px-3 py-3 font-medium text-gray-900"><?= h($r['patientName']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($r['phoneNumber']) ?></td><td class="px-3 py-3 text-gray-600"><?= h($r['brand']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($r['model']) ?></td><td class="px-3 py-3"><?= h($r['ear']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($r['serviceType']) ?></td><td class="px-3 py-3 text-gray-600"><?= h($r['source']?:'-') ?></td>
      <td class="px-3 py-3 whitespace-nowrap"><?= $r['purchaseDate']?h(format_date($r['purchaseDate'])):'-' ?></td>
      <td class="px-3 py-3 whitespace-nowrap"><?= rupees($r['cost']) ?></td>
      <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= pill($r['status']) ?>"><?= h($r['status']) ?></span></td>
      <td class="px-3 py-3 whitespace-nowrap"><?= $r['deliveryDate']?h(format_date($r['deliveryDate'])):'-' ?></td><td class="px-3 py-3"><?= h($r['branch']) ?></td>
      <td class="px-3 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="14" class="px-4 py-10 text-center text-gray-400">No repairs found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Repair</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?= ff('Date','date','date') ?><?= ff('Patient Name *','patientName') ?><?= ff('Phone Number','phoneNumber','tel') ?>
      <?= ffselect('Brand','brand', array_merge(['' => 'Select Brand'], array_combine($brands ?: [], $brands ?: []))) ?>
      <?= ff('Hearing Aid Model','model') ?><?= ffselect('Ear','ear', ['Right'=>'Right','Left'=>'Left','Both'=>'Both']) ?>
      <?= ff('Old Serial No (Right)','oldSerialNoRight') ?><?= ff('New Serial No (Right)','newSerialNoRight') ?>
      <?= ff('Old Serial No (Left)','oldSerialNoLeft') ?><?= ff('New Serial No (Left)','newSerialNoLeft') ?>
      <?= ff('Old Charger Serial','oldChargerSerial') ?><?= ff('New Charger Serial','newChargerSerial') ?>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Service Type *</label><select name="serviceType" id="f_serviceType" onchange="document.getElementById('customWrap').classList.toggle('hidden', this.value!=='Other')" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="">Select Service Type</option><?php foreach ($SERVICE_TYPES as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?></select></div>
      <div id="customWrap" class="hidden"><label class="block text-sm font-medium text-gray-700 mb-1">Custom Service Type *</label><input type="text" name="customServiceType" id="f_customServiceType" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
      <?= ff('Cost (₹)','cost','number') ?>
      <?= ffselect('Status','status', array_combine($STATUS_OPTIONS,$STATUS_OPTIONS)) ?>
      <?= ffselect('Branch','branch', array_combine($branchList,$branchList)) ?>
      <?= ff('Source','source','text','placeholder="Where the aid was purchased"') ?><?= ff('Purchase Date','purchaseDate','date') ?>
      <?= ff('Courier No','courierNo') ?><?= ff('Sent Date','sentDate','date') ?><?= ff('Received Date','receivedDate','date') ?><?= ff('Delivery Date','deliveryDate','date') ?>
      <?= ff('Remarks','remarks','text','','md:col-span-3') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
var SERVICE_TYPES=<?= json_encode($SERVICE_TYPES) ?>;
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Repair'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); document.getElementById('customWrap').classList.add('hidden'); openModal('modal'); }
function openEdit(o){ resetForm(); document.getElementById('modalTitle').textContent='Edit Repair';
  var known = SERVICE_TYPES.slice(0,-1).indexOf(o.serviceType)>=0;
  var oo = Object.assign({}, o);
  if (o.serviceType && !known){ oo.serviceType='Other'; document.getElementById('f_customServiceType').value=o.serviceType; document.getElementById('customWrap').classList.remove('hidden'); } else { document.getElementById('customWrap').classList.add('hidden'); }
  fillForm(oo); openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
