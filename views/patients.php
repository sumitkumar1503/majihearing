<?php
$title = 'Patients';
$admin = is_admin();
$canDelete = in_array(current_role(), ['admin','doctor','staff'], true);

/* ---- POST actions (PRG) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $tests = $_POST['tests'] ?? [];
        $assoc = [
            'id' => trim($_POST['id'] ?? ''),
            'sn' => $_POST['sn'] ?? '',
            'date' => $_POST['date'] ?? '',
            'name' => trim($_POST['name'] ?? ''),
            'ageSex' => $_POST['ageSex'] ?? '',
            'contactNo' => $_POST['contactNo'] ?? '',
            'address' => $_POST['address'] ?? '',
            'referral' => $_POST['referral'] ?? '',
            'test' => implode(', ', array_map('trim', (array)$tests)),
            'branch' => $_POST['branch'] ?? '',
            'payment' => $_POST['payment'] ?? 'Pending',
            'reportStatus' => $_POST['reportStatus'] ?? 'Pending',
            'deliveryDate' => $_POST['deliveryDate'] ?? '',
            'remarks' => $_POST['remarks'] ?? '',
        ];
        if ($assoc['name'] === '' || $assoc['date'] === '') {
            set_flash('error', 'Name and date are required');
            redirect('index.php?page=patients');
        }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('patients', $assoc['id']) : null;
        $days = $old ? (int)((time() - (safe_parse_date($old['date'])?->getTimestamp() ?? time())) / 86400) : 0;
        $needsApproval = $isEdit && !$admin && $days > 10;
        $res = submit_change('patients', 'Patient Details', $isEdit ? 'update' : 'create', $assoc, 'Patient — ' . $assoc['name'], $old, $needsApproval);
        set_flash($res === 'queued' ? 'success' : 'success', $res === 'queued' ? 'Change sent for admin approval. Original stays until approved.' : ($isEdit ? 'Patient updated' : 'Patient added'));
        redirect('index.php?page=patients');
    }
    if ($action === 'delete' && $canDelete) {
        entity_delete('patients', $_POST['id'] ?? '');
        set_flash('success', 'Deleted successfully');
        redirect('index.php?page=patients');
    }
}

/* ---- Load + filter ---- */
$all = entity_all('patients');
sort_by_date_desc($all, 'date');
$q = trim($_GET['q'] ?? '');
$branchFilter = $_GET['branch'] ?? 'All';
$statusFilter = $_GET['status'] ?? 'All';
$rows = array_values(array_filter($all, function ($p) use ($q, $branchFilter, $statusFilter) {
    if ($q !== '' && stripos($p['name'], $q) === false && strpos($p['contactNo'], $q) === false) return false;
    if ($branchFilter !== 'All' && $p['branch'] !== $branchFilter) return false;
    if ($statusFilter !== 'All' && $p['reportStatus'] !== $statusFilter) return false;
    return true;
}));

$branchList = active_branch_names();
$testList = active_test_names();
$rows = paginate($rows);

require __DIR__ . '/../partials/top.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
  <h1 class="text-2xl font-bold text-gray-900">Patients</h1>
  <button onclick="openNew()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Patient</button>
</div>

<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3">
  <input type="hidden" name="page" value="patients">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name or contact..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="branch" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <option value="All">All Branches</option>
    <?php foreach ($branchList as $b): ?><option value="<?= h($b) ?>" <?= $branchFilter === $b ? 'selected' : '' ?>><?= h($b) ?></option><?php endforeach; ?>
  </select>
  <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
    <?php foreach (['All','Pending','Uploaded','Completed','Delivered','N/A'] as $s): ?><option value="<?= h($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?>
  </select>
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium hover:bg-gray-200">Filter</button>
</form>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-50"><tr>
        <?php foreach (['SN','Date','Name','Age/Sex','Contact','Referral','Test','Branch','Payment','Report','Delivery',''] as $htxt): ?>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= h($htxt) ?></th>
        <?php endforeach; ?>
      </tr></thead>
      <tbody class="divide-y divide-gray-200">
      <?php foreach ($rows as $i => $p): $j = json_encode($p); ?>
        <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h($j) ?>)'>
          <td class="px-4 py-3 text-gray-600"><?= page_offset() + $i + 1 ?></td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= h(format_date($p['date'])) ?></td>
          <td class="px-4 py-3 font-medium text-gray-900"><?= h($p['name']) ?></td>
          <td class="px-4 py-3 text-gray-600"><?= h($p['ageSex']) ?></td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
            <?= h($p['contactNo']) ?>
            <?php if ($p['contactNo']): ?><a onclick="event.stopPropagation()" href="<?= h(wa_link($p['contactNo'])) ?>" target="_blank" class="text-green-500 ml-1">WA</a><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-gray-600 truncate max-w-[120px]"><?= h($p['referral']) ?></td>
          <td class="px-4 py-3 text-gray-600 truncate max-w-[150px]"><?= h($p['test']) ?></td>
          <td class="px-4 py-3 text-gray-600"><?= h($p['branch']) ?></td>
          <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= $p['payment'] === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>"><?= h($p['payment']) ?></span></td>
          <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= $p['reportStatus'] === 'Completed' ? 'bg-green-100 text-green-700' : ($p['reportStatus'] === 'Delivered' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700') ?>"><?= h($p['reportStatus']) ?></span></td>
          <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= h(format_date($p['deliveryDate'])) ?></td>
          <td class="px-4 py-3 whitespace-nowrap">
            <button type="button" onclick='event.stopPropagation(); openReports(<?= h(json_encode($p['id'])) ?>, <?= h(json_encode($p['name'])) ?>)' class="text-indigo-600 hover:text-indigo-800 font-medium mr-3">Reports</button>
            <?php if ($canDelete): ?>
            <form method="post" class="inline" onsubmit="event.stopPropagation(); return confirm('Delete this patient?');" onclick="event.stopPropagation()">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button class="text-red-500 hover:text-red-700">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="12" class="px-4 py-10 text-center text-gray-400">No patients found</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= render_pagination() ?>

<!-- Modal -->
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6">
    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Patient</h3>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f_id">
      <input type="hidden" name="sn" id="f_sn">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Date *</label><input type="date" name="date" id="f_date" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Name *</label><input type="text" name="name" id="f_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Age/Sex</label><input type="text" name="ageSex" id="f_ageSex" placeholder="e.g. 45/M" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Contact No</label><input type="tel" name="contactNo" id="f_contactNo" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Address</label><input type="text" name="address" id="f_address" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Referral</label><input type="text" name="referral" id="f_referral" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
          <select name="branch" id="f_branch" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach ($branchList as $b): ?><option value="<?= h($b) ?>"><?= h($b) ?></option><?php endforeach; ?></select></div>
        <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Tests</label>
          <div class="flex flex-wrap gap-2" id="f_tests">
            <?php foreach ($testList as $t): ?>
              <label class="cursor-pointer"><input type="checkbox" name="tests[]" value="<?= h($t) ?>" class="hidden peer"><span class="rounded-full px-3 py-1 text-xs font-medium bg-gray-100 text-gray-600 peer-checked:bg-indigo-600 peer-checked:text-white"><?= h($t) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Payment</label>
          <select name="payment" id="f_payment" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach (['Pending','Paid','Partial'] as $o): ?><option value="<?= $o ?>"><?= $o ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Report Status</label>
          <select name="reportStatus" id="f_reportStatus" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach (['Pending','Uploaded','Completed','Delivered','N/A'] as $o): ?><option value="<?= $o ?>"><?= $o ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Delivery Date</label><input type="date" name="deliveryDate" id="f_deliveryDate" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label><input type="text" name="remarks" id="f_remarks" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Report Upload Modal (files stored locally on this device, mirrors original) -->
<div id="reportModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">Reports for <span id="rp_name"></span></h3>
      <button type="button" onclick="closeModal('reportModal')" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">✕</button>
    </div>
    <div class="flex items-center gap-3 mb-4">
      <button type="button" onclick="document.getElementById('rp_file').click()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">⬆ Upload Report</button>
      <input type="file" id="rp_file" accept="image/*,.pdf" capture="environment" class="hidden" onchange="rpUpload(event)">
      <span class="text-xs text-gray-500">JPG, PNG, or PDF (max 5MB)</span>
    </div>
    <div id="rp_list"></div>
    <p class="text-xs text-gray-400 italic mt-3">Reports are saved on the server and cached on this device.</p>
  </div>
</div>
<div id="rp_preview" class="hidden fixed inset-0 z-[60] items-center justify-center bg-black/70 p-4" onclick="this.classList.add('hidden');this.classList.remove('flex')">
  <div id="rp_preview_inner" class="max-w-4xl max-h-[90vh] w-full" onclick="event.stopPropagation()"></div>
</div>

<script>
var RP_ID=null, RP_NAME='', RP_SERVER=[];
function rpKey(id){ return 'patient_reports_'+id; }
function rpGet(id){ try{ return JSON.parse(localStorage.getItem(rpKey(id))||'[]'); }catch(e){ return []; } }
function rpSet(id, arr){ localStorage.setItem(rpKey(id), JSON.stringify(arr)); }
function rpIsPdf(n){ return String(n).toLowerCase().endsWith('.pdf'); }
function openReports(id, name){ RP_ID=id; RP_NAME=name; document.getElementById('rp_name').textContent=name; document.getElementById('rp_list').innerHTML='<p class="text-sm text-gray-400 py-6 text-center">Loading…</p>'; openModal('reportModal'); rpLoad(); }
function rpLoad(){
  RP_SERVER=[];
  fetch('index.php?page=report-list&id='+encodeURIComponent(RP_ID)).then(function(r){return r.ok?r.json():[];}).then(function(s){ RP_SERVER=Array.isArray(s)?s:[]; rpRender(); }).catch(function(){ rpRender(); });
}
// Merge server files (authoritative) with any local-only copies (by id).
function rpMerged(){
  var ids={}; var list=[];
  RP_SERVER.forEach(function(s){ ids[String(s.id)]=true; list.push({id:s.id,name:s.name,date:s.date,url:s.url,file:s.file,source:'server'}); });
  rpGet(RP_ID).forEach(function(l){ if(!ids[String(l.id)]) list.push({id:l.id,name:l.name,date:l.date,data:l.data,source:'local'}); });
  list.sort(function(a,b){ return String(b.id).localeCompare(String(a.id)); });
  return list;
}
function rpFind(id){ return rpMerged().filter(function(x){ return String(x.id)===String(id); })[0]; }
function rpRender(){
  var arr=rpMerged(), el=document.getElementById('rp_list');
  if(!arr.length){ el.innerHTML='<div class="rounded-xl border-2 border-dashed border-gray-300 p-8 text-center"><p class="text-sm text-gray-500">No reports uploaded.</p><p class="text-xs text-gray-400 mt-1">Tap \'Upload Report\' or scan using camera.</p></div>'; return; }
  var html='<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">';
  arr.forEach(function(r){
    var src = r.source==='server' ? r.url : r.data;
    var thumb = rpIsPdf(r.name) ? '<div class="w-16 h-16 rounded-md bg-red-50 flex items-center justify-center text-red-400 text-2xl">PDF</div>' : '<img src="'+src+'" class="w-16 h-16 rounded-md object-cover bg-gray-100">';
    var badge = r.source==='server' ? '<span class="ml-1 text-[10px] bg-green-100 text-green-700 rounded px-1.5 py-0.5">Server</span>' : '<span class="ml-1 text-[10px] bg-amber-100 text-amber-700 rounded px-1.5 py-0.5">Local only</span>';
    var idj = String(r.id).replace(/&/g,'&amp;').replace(/"/g,'&quot;');
    html+='<div class="rounded-lg border border-gray-200 p-3 flex gap-3 items-start">'+thumb
      +'<div class="flex-1 min-w-0"><p class="text-sm font-medium text-gray-900 truncate">'+r.name+'</p><p class="text-xs text-gray-500">'+r.date+badge+'</p>'
      +'<div class="mt-1 flex gap-3 text-xs font-medium"><button type="button" onclick="rpView(&quot;'+idj+'&quot;)" class="text-indigo-600 hover:text-indigo-800">View</button><button type="button" onclick="rpDelete(&quot;'+idj+'&quot;)" class="text-red-500 hover:text-red-700">Delete</button></div></div></div>';
  });
  html+='</div>'; el.innerHTML=html;
}
function rpUpload(e){
  var f=e.target.files&&e.target.files[0]; if(!f) return;
  if(f.size>5*1024*1024){ alert('File too large (max 5MB)'); e.target.value=''; return; }
  var id=Date.now().toString();
  var reader=new FileReader();
  reader.onload=function(){
    // 1) keep a local copy immediately
    var arr=rpGet(RP_ID); arr.push({id:id, name:f.name, data:reader.result, date:new Date().toISOString().slice(0,10)}); rpSet(RP_ID,arr); rpRender();
    // 2) upload to the server
    var fd=new FormData(); fd.append('file', f); fd.append('id', id);
    fetch('index.php?page=report-upload&id='+encodeURIComponent(RP_ID), {method:'POST', body:fd})
      .then(function(r){return r.json();})
      .then(function(res){ if(!res||!res.ok){ alert('Saved locally, but server upload failed'+(res&&res.error?': '+res.error:'')); } rpLoad(); })
      .catch(function(){ alert('Saved locally, but server upload failed (network)'); rpRender(); });
  };
  reader.onerror=function(){ alert('Failed to read file'); };
  reader.readAsDataURL(f); e.target.value='';
}
function rpDelete(id){
  if(!confirm('Remove this report?')) return;
  var r=rpFind(id);
  if(r && r.source==='server'){ var fd=new FormData(); fd.append('f', r.file); fetch('index.php?page=report-delete&id='+encodeURIComponent(RP_ID), {method:'POST', body:fd}).catch(function(){}); }
  var loc=rpGet(RP_ID).filter(function(x){ return String(x.id)!==String(id); }); rpSet(RP_ID, loc);
  setTimeout(rpLoad, 200);
}
function rpView(id){
  var r=rpFind(id); if(!r) return; var src = r.source==='server' ? r.url : r.data; var box=document.getElementById('rp_preview_inner');
  if(rpIsPdf(r.name)){ box.innerHTML='<div class="bg-white rounded-lg p-3"><div class="flex justify-between items-center mb-2"><p class="text-gray-700 font-medium truncate">'+r.name+'</p><a href="'+src+'" target="_blank" class="text-sm text-indigo-600">Open / Download</a></div><iframe src="'+src+'" class="w-full h-[80vh] rounded"></iframe></div>'; }
  else { box.innerHTML='<img src="'+src+'" class="w-full max-h-[90vh] object-contain rounded-lg">'; }
  var p=document.getElementById('rp_preview'); p.classList.remove('hidden'); p.classList.add('flex');
}

function isoDate(v){ if(!v) return ''; var m=String(v).match(/^(\d{1,4})-(\d{2})-(\d{2})/); if(m){var y=parseInt(m[1],10); if(y<100)y+=2000; return y.toString().padStart(4,'0')+'-'+m[2]+'-'+m[3];} return ''; }
function setField(id,v){ var el=document.getElementById(id); if(el) el.value=v||''; }
function fillTests(str){
  var sel=(str||'').split(',').map(s=>s.trim()).filter(Boolean);
  document.querySelectorAll('#f_tests input[type=checkbox]').forEach(cb=>{ cb.checked = sel.indexOf(cb.value)>=0; });
}
function openNew(){
  document.getElementById('modalTitle').textContent='Add Patient';
  ['f_id','f_sn','f_name','f_ageSex','f_contactNo','f_address','f_referral','f_remarks','f_deliveryDate'].forEach(i=>setField(i,''));
  setField('f_date', new Date().toISOString().slice(0,10));
  setField('f_branch', document.getElementById('f_branch').options[0]?.value||'');
  setField('f_payment','Pending'); setField('f_reportStatus','Pending');
  fillTests('');
  openModal('modal');
}
function openEdit(p){
  document.getElementById('modalTitle').textContent='Edit Patient';
  setField('f_id',p.id); setField('f_sn',p.sn); setField('f_name',p.name); setField('f_ageSex',p.ageSex);
  setField('f_contactNo',p.contactNo); setField('f_address',p.address); setField('f_referral',p.referral);
  setField('f_date',isoDate(p.date)); setField('f_deliveryDate',isoDate(p.deliveryDate));
  setField('f_branch',p.branch); setField('f_payment',p.payment||'Pending'); setField('f_reportStatus',p.reportStatus||'Pending');
  setField('f_remarks',p.remarks);
  fillTests(p.test);
  openModal('modal');
}
</script>
<?php
require __DIR__ . '/../partials/bottom.php';
