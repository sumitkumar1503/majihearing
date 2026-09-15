<?php
$title = 'Invoices';
$canDelete = is_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $items = json_decode($_POST['items'] ?? '[]', true); if (!is_array($items)) $items = [];
        $subtotal = 0; foreach ($items as $it) $subtotal += (float)($it['total'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $assoc = ['id'=>trim($_POST['id']??''),'invoiceNo'=>trim($_POST['invoiceNo']??''),'date'=>$_POST['date']??'','patientName'=>trim($_POST['patientName']??''),'age'=>$_POST['age']??'','address'=>$_POST['address']??'','phoneNumber'=>$_POST['phoneNumber']??'','items'=>$items,'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$subtotal-$discount,'paymentMode'=>$_POST['paymentMode']??'Cash','branch'=>$_POST['branch']??''];
        if ($assoc['patientName']==='' || $assoc['invoiceNo']==='') { set_flash('error','Patient name and invoice no are required'); redirect('index.php?page=invoices'); }
        if ($assoc['id']==='') { entity_insert('invoices', $assoc); set_flash('success','Invoice created'); }
        else { entity_update('invoices', $assoc); set_flash('success','Invoice updated'); }
        redirect('index.php?page=invoices');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('invoices', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=invoices'); }
}
$rows = entity_all('invoices');
usort($rows, fn($a,$b)=>strcmp($b['date'],$a['date']));
$q = trim($_GET['q'] ?? '');
if ($q !== '') $rows = array_values(array_filter($rows, fn($i)=>stripos($i['patientName'],$q)!==false||stripos($i['invoiceNo'],$q)!==false));
$branchList = active_branch_names();
$rows = paginate($rows);
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Invoices</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Create Invoice</button></div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="invoices"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by patient or invoice #..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Invoice #','Date','Patient','Items','Total','Payment','Branch','Actions'] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $inv): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($inv)) ?>)'>
      <td class="px-4 py-3 font-medium text-indigo-600"><?= h($inv['invoiceNo']) ?></td><td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($inv['date'])) ?></td>
      <td class="px-4 py-3 font-medium text-gray-900"><?= h($inv['patientName']) ?></td><td class="px-4 py-3 text-gray-600"><?= count($inv['items']) ?> items</td>
      <td class="px-4 py-3 font-bold text-green-600"><?= rupees($inv['total']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium"><?= h($inv['paymentMode']) ?></span></td>
      <td class="px-4 py-3"><?= h($inv['branch']) ?></td>
      <td class="px-4 py-3"><div class="flex items-center gap-3">
        <a onclick="event.stopPropagation()" href="index.php?page=invoice-print&id=<?= h($inv['id']) ?>" target="_blank" class="text-indigo-600 text-xs">Print/PDF</a>
        <?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($inv['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No invoices found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?= render_pagination() ?>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Create Invoice</h3>
  <form method="post" onsubmit="return prepItems()"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id"><input type="hidden" name="items" id="f_items">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
      <?= ff('Invoice No *','invoiceNo') ?><?= ff('Date','date','date') ?><?= ff('Patient Name *','patientName') ?><?= ff('Age','age') ?>
      <?= ff('Address','address','text','','md:col-span-2') ?><?= ff('Phone','phoneNumber','tel') ?>
      <?= ffselect('Branch','branch', array_combine($branchList,$branchList)) ?>
    </div>
    <h4 class="text-sm font-semibold text-gray-700 mb-2">Items</h4>
    <div class="overflow-x-auto"><table class="min-w-full text-sm border border-gray-200 rounded-lg"><thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs text-gray-500">Description</th><th class="px-3 py-2 text-left text-xs text-gray-500 w-20">Qty</th><th class="px-3 py-2 text-left text-xs text-gray-500 w-28">Price</th><th class="px-3 py-2 text-left text-xs text-gray-500 w-28">Total</th><th class="w-10"></th></tr></thead><tbody id="itemsBody"></tbody></table></div>
    <button type="button" onclick="addItem()" class="mt-2 text-sm text-indigo-600 hover:underline">+ Add Item</button>
    <div class="flex flex-col items-end gap-2 mt-4">
      <div class="flex items-center gap-4"><span class="text-sm text-gray-600">Subtotal:</span><span class="font-medium" id="subT">₹0</span></div>
      <div class="flex items-center gap-4"><span class="text-sm text-gray-600">Discount (₹):</span><input type="number" name="discount" id="f_discount" value="0" oninput="recalc()" class="w-28 rounded-lg border border-gray-300 px-3 py-1 text-sm"></div>
      <div class="flex items-center gap-4"><span class="text-lg font-semibold">Grand Total:</span><span class="text-xl font-bold text-green-600" id="grandT">₹0</span></div>
    </div>
    <div class="flex items-center gap-4 mt-4"><label class="text-sm font-medium text-gray-700">Payment Mode:</label><select name="paymentMode" id="f_paymentMode" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option>Cash</option><option>Online</option><option>Cheque</option></select></div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
var SERVICES=[["Service","Pure Tone Audiometry (PTA)",600],["Service","Audiometry + Tympanometry (A+T)",1300],["Service","ENG",2250],["Service","Audiometry + Tymp + ENG",3550],["Service","SRT + SDS",700],["Service","OAE",1300],["Service","BERA (ABR)",2200],["Service","OAE + BERA",3100],["Service","VEMP",1500],["Service","Speech Therapy",500],["Service","Swallow Therapy",500],["Service","Voice Therapy",500],["Accessories","Battery 675",200],["Accessories","Battery 13",200],["Accessories","Battery 10",200],["Accessories","Battery 312",200]];
var items=[];
function renderItems(){
  var b=document.getElementById('itemsBody'); b.innerHTML='';
  items.forEach(function(it,idx){
    var tr=document.createElement('tr'); tr.className='border-t border-gray-200';
    var opts='<option value="">Type custom...</option>'+SERVICES.map(function(s){return '<option value="'+s[1]+'"'+(s[1]===it.description?' selected':'')+'>'+s[1]+'</option>';}).join('');
    tr.innerHTML='<td class="px-3 py-2"><select onchange="pick('+idx+',this.value)" class="rounded border border-gray-300 px-2 py-1 text-sm w-full mb-1">'+opts+'</select><input value="'+(it.description||'').replace(/"/g,"&quot;")+'" oninput="items['+idx+'].description=this.value" placeholder="Description" class="rounded border border-gray-300 px-2 py-1 text-sm w-full"></td>'+
      '<td class="px-3 py-2"><input type="number" min="1" value="'+it.quantity+'" oninput="items['+idx+'].quantity=+this.value||0;recalc()" class="rounded border border-gray-300 px-2 py-1 text-sm w-full"></td>'+
      '<td class="px-3 py-2"><input type="number" value="'+it.price+'" oninput="items['+idx+'].price=+this.value||0;recalc()" class="rounded border border-gray-300 px-2 py-1 text-sm w-full"></td>'+
      '<td class="px-3 py-2 font-medium">₹'+((it.quantity*it.price)||0).toLocaleString()+'</td>'+
      '<td class="px-3 py-2"><button type="button" onclick="items.splice('+idx+',1);renderItems();recalc()" class="text-red-500">✕</button></td>';
    b.appendChild(tr);
  });
}
function pick(idx,val){ var s=SERVICES.find(function(x){return x[1]===val;}); if(s){items[idx].description=s[1];items[idx].price=s[2];} else {items[idx].description='';} renderItems(); recalc(); }
function addItem(){ items.push({item:'Service',description:'',quantity:1,price:0}); renderItems(); recalc(); }
function recalc(){ var sub=0; items.forEach(function(it){it.total=(it.quantity||0)*(it.price||0); sub+=it.total;}); var disc=+document.getElementById('f_discount').value||0; document.getElementById('subT').textContent='₹'+sub.toLocaleString(); document.getElementById('grandT').textContent='₹'+(sub-disc).toLocaleString(); }
function prepItems(){ items.forEach(function(it){it.total=(it.quantity||0)*(it.price||0);}); document.getElementById('f_items').value=JSON.stringify(items); return true; }
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Create Invoice'; document.getElementById('f_invoiceNo').value='INV-'+String(Date.now()).slice(-6); document.getElementById('f_date').value=new Date().toISOString().slice(0,10); document.getElementById('f_discount').value=0; items=[{item:'Service',description:'',quantity:1,price:0}]; renderItems(); recalc(); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Invoice'; document.getElementById('f_discount').value=o.discount||0; items=(o.items&&o.items.length)?o.items.map(function(x){return {item:x.item||'Service',description:x.description||'',quantity:+x.quantity||1,price:+x.price||0};}):[{item:'Service',description:'',quantity:1,price:0}]; renderItems(); recalc(); openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
