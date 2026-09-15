<?php
$title = 'Hearing Aid Sales';
$admin = is_admin();
$canDelete = is_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $f = fn($k)=>$_POST[$k]??'';
        $assoc = ['id'=>trim($f('id')),'sn'=>$f('sn'),'date'=>$f('date'),'name'=>trim($f('name')),'contactNo'=>$f('contactNo'),'address'=>$f('address'),'referral'=>$f('referral'),'branch'=>$f('branch'),'haModel'=>trim($f('haModel')),'serialNumber'=>$f('serialNumber'),'side'=>$f('side'),'billNo'=>$f('billNo'),'source'=>$f('source'),'mrp'=>$f('mrp'),'sellingPrice'=>$f('sellingPrice'),'remarks'=>$f('remarks'),'warrantyCard'=>$f('warrantyCard'),'freeOfBattery'=>$f('freeOfBattery'),'model2'=>$f('model2'),'rightSerialNo'=>$f('rightSerialNo'),'leftSerialNo'=>$f('leftSerialNo'),'chargerSerialNo'=>$f('chargerSerialNo'),'paymentStatus'=>$f('paymentStatus')?:'Completed','dueAmount'=>$f('dueAmount')?:'0','advanceAmount'=>$f('advanceAmount')?:'0'];
        if ($assoc['name']==='' || $assoc['haModel']==='') { set_flash('error','Name and HA Model are required'); redirect('index.php?page=ha-sales'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('ha-sales', $assoc['id']) : null;
        $res = submit_change('ha-sales','HA Sales',$isEdit?'update':'create',$assoc,($isEdit?'Update':'Add').' HA sale — '.$assoc['name'].' / '.$assoc['haModel'].' (₹'.$assoc['sellingPrice'].')',$old,!$admin);
        // On a direct (admin) create, deduct matching serials from HA stock.
        if ($res === 'applied' && !$isEdit) {
            $stock = entity_all('ha-stock');
            foreach (array_filter([$assoc['rightSerialNo'], $assoc['leftSerialNo']]) as $serial) {
                $matched = null;
                foreach ($stock as $st) { if (strtolower(trim($st['serialNumber'])) === strtolower(trim($serial))) { $matched = $st; break; } }
                if ($matched) { if (!$matched['soldDate']) { $matched['soldDate'] = $assoc['date']; entity_update('ha-stock', $matched); } }
                else { entity_insert('ha-stock', ['date'=>$assoc['date'],'brand'=>$assoc['source'],'model'=>$assoc['haModel'],'serialNumber'=>$serial,'mfdDate'=>'','source'=>$assoc['source'],'branch'=>$assoc['branch'],'soldDate'=>$assoc['date'],'remarks'=>'Auto-added from HA Sale - '.$assoc['name'],'mrp'=>$assoc['mrp']]); }
            }
        }
        set_flash('success', $res==='queued'?'Change sent for admin approval':($isEdit?'Sale updated':'Sale recorded'));
        redirect('index.php?page=ha-sales');
    }
    if ($a === 'delete') {
        $rec = entity_find('ha-sales', $_POST['id']??'');
        $res = submit_change('ha-sales','HA Sales','delete',['id'=>$_POST['id']??''],'Delete HA sale — '.($rec['name']??''),$rec,!$admin);
        set_flash('success', $res==='queued'?'Change sent for admin approval':'Deleted');
        redirect('index.php?page=ha-sales');
    }
}
$sales = entity_all('ha-sales');
$q = trim($_GET['q'] ?? ''); $branchFilter = $_GET['branchf'] ?? 'All'; $brandFilter = $_GET['brandf'] ?? 'All';
$rows = array_values(array_filter($sales, function($s) use ($q,$branchFilter,$brandFilter){
  if ($q!=='' && stripos($s['name'],$q)===false && strpos($s['contactNo'],$q)===false && stripos($s['haModel'],$q)===false) return false;
  if ($branchFilter!=='All' && $s['branch']!==$branchFilter) return false;
  if ($brandFilter!=='All' && stripos($s['source'],$brandFilter)===false && stripos($s['haModel'],$brandFilter)===false) return false;
  return true;
}));
usort($rows, fn($a,$b)=>strcmp($b['date'],$a['date']));
// Totals (filtered)
$tCount=count($rows); $tMrp=0;$tSell=0; foreach ($rows as $r){$tMrp+=(float)$r['mrp'];$tSell+=(float)$r['sellingPrice'];}
// This month + FY
$mCount=0;$mMrp=0;$mSell=0; foreach ($sales as $r) if (is_current_month($r['date'])){$mCount++;$mMrp+=(float)$r['mrp'];$mSell+=(float)$r['sellingPrice'];}
$fyYear = (int)date('n') >= 4 ? (int)date('Y') : (int)date('Y')-1;
$fyStart = "$fyYear-04-01"; $fyEnd = ($fyYear+1)."-03-31";
$fCount=0;$fMrp=0;$fSell=0; foreach ($sales as $r){ $d=normalize_date_string($r['date']); if ($d>=$fyStart && $d<=$fyEnd){$fCount++;$fMrp+=(float)$r['mrp'];$fSell+=(float)$r['sellingPrice'];} }
$brands = active_brand_names(); $branchList = active_branch_names();
$inp = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm';
$rows = paginate($rows);
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Hearing Aid Sales</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Sale</button></div>
<?php if ($admin): ?>
<div class="space-y-4 mb-5">
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Sales (Filtered)</p><p class="text-2xl font-bold text-gray-900"><?= $tCount ?></p></div>
    <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total MRP</p><p class="text-2xl font-bold text-gray-900"><?= rupees($tMrp) ?></p></div>
    <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Selling Total</p><p class="text-2xl font-bold text-green-600"><?= rupees($tSell) ?></p></div>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase">This Month</h3><div class="grid grid-cols-3 gap-3"><div><p class="text-xs text-gray-500">Sales</p><p class="text-lg font-bold"><?= $mCount ?></p></div><div><p class="text-xs text-gray-500">MRP</p><p class="text-lg font-bold"><?= rupees($mMrp) ?></p></div><div><p class="text-xs text-gray-500">Selling</p><p class="text-lg font-bold text-green-600"><?= rupees($mSell) ?></p></div></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-4"><h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase">FY <?= $fyYear ?>-<?= substr($fyYear+1,2) ?></h3><div class="grid grid-cols-3 gap-3"><div><p class="text-xs text-gray-500">Sales</p><p class="text-lg font-bold"><?= $fCount ?></p></div><div><p class="text-xs text-gray-500">MRP</p><p class="text-lg font-bold"><?= rupees($fMrp) ?></p></div><div><p class="text-xs text-gray-500">Selling</p><p class="text-lg font-bold text-green-600"><?= rupees($fSell) ?></p></div></div></div>
  </div>
</div>
<?php endif; ?>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3"><input type="hidden" name="page" value="ha-sales">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search name, contact, model..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="branchf" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Branches</option><?php foreach ($branchList as $b): ?><option <?= $branchFilter===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
  <select name="brandf" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Brands</option><?php foreach ($brands as $b): ?><option <?= $brandFilter===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button>
</form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['SN','Date','Name','Contact','Branch','HA Model','Serial No','Side','Bill No','Source','MRP','Selling Price',''] as $htxt): ?><th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $i=>$s): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($s)) ?>)'>
      <td class="px-3 py-3"><?= page_offset() + $i+1 ?></td><td class="px-3 py-3 whitespace-nowrap"><?= h(format_date($s['date'])) ?></td>
      <td class="px-3 py-3 font-medium text-gray-900"><?= h($s['name']) ?></td><td class="px-3 py-3 text-gray-600"><?= h($s['contactNo']) ?></td>
      <td class="px-3 py-3"><?= h($s['branch']) ?></td><td class="px-3 py-3 text-gray-600"><?= h($s['haModel']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($s['serialNumber']) ?></td><td class="px-3 py-3"><?= h($s['side']) ?></td>
      <td class="px-3 py-3 text-gray-600"><?= h($s['billNo']) ?></td><td class="px-3 py-3 text-gray-600"><?= h($s['source']) ?></td>
      <td class="px-3 py-3 whitespace-nowrap"><?= rupees($s['mrp']) ?></td><td class="px-3 py-3 whitespace-nowrap font-medium text-green-600"><?= rupees($s['sellingPrice']) ?></td>
      <td class="px-3 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($s['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="13" class="px-4 py-10 text-center text-gray-400">No sales found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?= render_pagination() ?>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Record Sale</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id"><input type="hidden" name="sn" id="f_sn">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?= ff('Date','date','date') ?><?= ff('Name *','name') ?><?= ff('Contact No','contactNo','tel') ?>
      <?= ff('Address','address','text','','md:col-span-2') ?><?= ff('Referral','referral') ?>
      <?= ffselect('Branch','branch', array_combine($branchList,$branchList)) ?>
      <?= ff('HA Model *','haModel') ?><?= ff('HA Model 2','model2') ?><?= ff('Serial No','serialNumber') ?>
      <?= ff('Right Serial No','rightSerialNo') ?><?= ff('Left Serial No','leftSerialNo') ?><?= ff('Charger Serial No','chargerSerialNo') ?>
      <?= ffselect('Side','side', ['Right'=>'Right','Left'=>'Left','Both'=>'Both']) ?><?= ff('Bill No','billNo') ?>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Source/Brand</label><input list="brandList" name="source" id="f_source" class="<?= $inp ?>"><datalist id="brandList"><?php foreach ($brands as $b): ?><option value="<?= h($b) ?>"><?php endforeach; ?></datalist></div>
      <?= ff('MRP (₹)','mrp','number') ?><?= ff('Selling Price (₹)','sellingPrice','number') ?>
      <?= ffselect('Payment Status','paymentStatus', ['Completed'=>'Completed','Due'=>'Due','Advance'=>'Advance']) ?>
      <?= ff('Due Amount (₹)','dueAmount','number') ?><?= ff('Advance Amount (₹)','advanceAmount','number') ?>
      <?= ff('Warranty Card','warrantyCard') ?><?= ff('Remarks','remarks','text','','md:col-span-2') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Record Sale'; document.getElementById('f_date').value=new Date().toISOString().slice(0,10); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Sale'; openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
