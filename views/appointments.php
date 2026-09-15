<?php
$title = 'Appointments';
$canDelete = in_array(current_role(), ['admin','doctor','staff'], true);
$TIME_SLOTS = ['10:00 AM','10:30 AM','11:00 AM','11:30 AM','12:00 PM','12:30 PM','01:00 PM','01:30 PM','02:00 PM','02:30 PM','03:00 PM','03:30 PM','04:00 PM','04:30 PM','05:00 PM','05:30 PM','06:00 PM','06:30 PM','07:00 PM','07:30 PM','08:00 PM'];
$TEST_STATUS = ['Appointment Given','Rescheduled','Completed','Incomplete','Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $tests = implode(', ', array_map('trim', (array)($_POST['tests'] ?? [])));
        $assoc = ['id'=>trim($_POST['id']??''),'date'=>$_POST['date']??'','time'=>$_POST['time']??'','patientName'=>trim($_POST['patientName']??''),'ageSex'=>$_POST['ageSex']??'','contactNo'=>$_POST['contactNo']??'','address'=>$_POST['address']??'','referral'=>$_POST['referral']??'','test'=>$tests,'remarks'=>$_POST['remarks']??'','branch'=>$_POST['branch']??'','payment'=>$_POST['payment']??'Pending','reportStatus'=>$_POST['reportStatus']??'Appointment Given'];
        if ($assoc['patientName']==='' || $assoc['date']==='' || $assoc['time']==='') { set_flash('error','Please fill required fields'); redirect('index.php?page=appointments'); }
        $isEdit = $assoc['id'] !== '';
        if ($isEdit) entity_update('appointments', $assoc); else entity_insert('appointments', $assoc);
        // Auto-migrate to Patients when finished & paid (de-duplicated)
        if ($assoc['reportStatus']==='Completed' && $assoc['payment']==='Paid') {
            $dup = false;
            foreach (entity_all('patients') as $p) if (strtolower($p['name'])===strtolower($assoc['patientName']) && $p['contactNo']===$assoc['contactNo'] && normalize_date_string($p['date'])===normalize_date_string($assoc['date'])) { $dup=true; break; }
            if (!$dup) { entity_insert('patients', ['date'=>$assoc['date'],'name'=>$assoc['patientName'],'ageSex'=>$assoc['ageSex'],'contactNo'=>$assoc['contactNo'],'address'=>$assoc['address'],'referral'=>$assoc['referral'],'test'=>$assoc['test'],'branch'=>$assoc['branch'],'payment'=>'Paid','reportStatus'=>'Completed','deliveryDate'=>'','remarks'=>$assoc['remarks']?:'Auto-migrated from appointment']); set_flash('success', 'Saved & migrated to Patients'); redirect('index.php?page=appointments&date='.urlencode($assoc['date'])); }
        }
        set_flash('success', $isEdit?'Appointment updated':'Appointment booked');
        redirect('index.php?page=appointments&date='.urlencode($assoc['date']));
    }
    if ($a === 'delete' && $canDelete) { entity_delete('appointments', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=appointments'); }
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$branchFilter = $_GET['branchf'] ?? 'All';
$all = entity_all('appointments');
$dayRows = array_values(array_filter($all, fn($ap)=>normalize_date_string($ap['date'])===normalize_date_string($selectedDate) && ($branchFilter==='All'||$ap['branch']===$branchFilter)));
$slotMap = [];
foreach ($dayRows as $ap) $slotMap[$ap['time']] = $ap;
$branchList = active_branch_names();
$testList = active_test_names();
$weekStart = (clone (new DateTime($selectedDate)))->modify(('Monday this week'));
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Appointments</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Book Appointment</button></div>
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-5 space-y-4">
  <form method="get" class="flex flex-col sm:flex-row sm:items-center gap-4"><input type="hidden" name="page" value="appointments">
    <div class="flex items-center gap-2">
      <a href="index.php?page=appointments&date=<?= date('Y-m-d', strtotime($selectedDate.' -1 day')) ?>&branchf=<?= h($branchFilter) ?>" class="p-2 rounded-lg hover:bg-gray-100">‹</a>
      <input type="date" name="date" value="<?= h($selectedDate) ?>" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
      <a href="index.php?page=appointments&date=<?= date('Y-m-d', strtotime($selectedDate.' +1 day')) ?>&branchf=<?= h($branchFilter) ?>" class="p-2 rounded-lg hover:bg-gray-100">›</a>
      <a href="index.php?page=appointments&date=<?= date('Y-m-d') ?>&branchf=<?= h($branchFilter) ?>" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium">Today</a>
    </div>
    <select name="branchf" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="All">All Branches</option><?php foreach ($branchList as $b): ?><option <?= $branchFilter===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
  </form>
  <div class="flex gap-1 overflow-x-auto pb-1">
    <?php for ($i=0;$i<7;$i++): $day=(clone $weekStart)->modify("+$i day"); $ds=$day->format('Y-m-d'); $sel=$ds===$selectedDate; $cnt=count(array_filter($all, fn($ap)=>normalize_date_string($ap['date'])===$ds && ($branchFilter==='All'||$ap['branch']===$branchFilter))); ?>
      <a href="index.php?page=appointments&date=<?= $ds ?>&branchf=<?= h($branchFilter) ?>" class="flex flex-col items-center min-w-[4rem] rounded-lg px-3 py-2 text-xs font-medium <?= $sel?'bg-indigo-600 text-white':'bg-gray-50 text-gray-700 hover:bg-gray-100' ?>"><span><?= $day->format('D') ?></span><span class="text-lg font-bold"><?= $day->format('j') ?></span><?php if($cnt): ?><span class="text-[10px] <?= $sel?'text-indigo-200':'text-indigo-600' ?>"><?= $cnt ?> apt</span><?php endif; ?></a>
    <?php endfor; ?>
  </div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
  <?php foreach ($TIME_SLOTS as $t): $ap=$slotMap[$t]??null; $clr=$ap?(['Completed'=>'bg-green-50 border-green-300','Cancelled'=>'bg-red-50 border-red-300','Incomplete'=>'bg-orange-50 border-orange-300','Rescheduled'=>'bg-amber-50 border-amber-300'][$ap['reportStatus']]??'bg-blue-50 border-blue-300'):'bg-gray-50 border-gray-200'; ?>
    <button onclick='<?= $ap?("openEdit(".json_encode($ap).")"):("openSlot(".json_encode($t).")") ?>' class="text-left rounded-xl border-2 p-4 <?= $clr ?> hover:brightness-95">
      <p class="text-sm font-bold text-gray-700"><?= h($t) ?></p>
      <?php if ($ap): ?><p class="text-sm font-semibold text-gray-900 truncate mt-1"><?= h($ap['patientName']) ?></p><p class="text-xs text-gray-500 truncate"><?= h($ap['test']?:'No test specified') ?></p><span class="inline-block mt-1 rounded-full px-2 py-0.5 text-[10px] font-medium <?= $ap['payment']==='Paid'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>"><?= h($ap['payment']) ?></span><?php else: ?><p class="mt-2 text-xs text-gray-400">Available</p><?php endif; ?>
    </button>
  <?php endforeach; ?>
</div>
<?php if ($dayRows): ?>
<div class="bg-white rounded-xl border border-gray-200 mt-5"><div class="px-4 py-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-700">All Appointments for <?= h(format_date($selectedDate)) ?> (<?= count($dayRows) ?>)</h3></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Time','Patient','Age/Sex','Contact','Test','Branch','Payment','Test Status',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($dayRows as $ap): ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($ap)) ?>)'>
      <td class="px-4 py-3 font-medium"><?= h($ap['time']) ?></td><td class="px-4 py-3 font-medium text-gray-900"><?= h($ap['patientName']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($ap['ageSex']) ?></td><td class="px-4 py-3 text-gray-600"><?= h($ap['contactNo']) ?><?php if($ap['contactNo']): ?> <a onclick="event.stopPropagation()" href="<?= h(wa_link($ap['contactNo'])) ?>" target="_blank" class="text-green-500">WA</a><?php endif; ?></td>
      <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate"><?= h($ap['test']) ?></td><td class="px-4 py-3"><?= h($ap['branch']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= $ap['payment']==='Paid'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700' ?>"><?= h($ap['payment']) ?></span></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= pill($ap['reportStatus']) ?>"><?= h($ap['reportStatus']) ?></span></td>
      <td class="px-4 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($ap['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php endif; ?>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Book Appointment</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?= ff('Date *','date','date') ?><?= ffselect('Time *','time', array_merge(['' => 'Select time'], array_combine($TIME_SLOTS,$TIME_SLOTS))) ?>
      <?= ff('Patient Name *','patientName','text','','md:col-span-2') ?>
      <?= ff('Age/Sex','ageSex','text','placeholder="e.g. 45/M"') ?><?= ff('Contact No','contactNo','tel') ?>
      <?= ff('Address','address','text','','md:col-span-2') ?><?= ff('Referral (Doctor)','referral') ?>
      <?= ffselect('Branch','branch', array_combine($branchList,$branchList)) ?>
      <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Tests</label><div class="flex flex-wrap gap-2" id="f_tests"><?php foreach ($testList as $t): ?><label class="cursor-pointer"><input type="checkbox" name="tests[]" value="<?= h($t) ?>" class="hidden peer"><span class="rounded-full px-3 py-1 text-xs font-medium bg-gray-100 text-gray-600 peer-checked:bg-indigo-600 peer-checked:text-white"><?= h($t) ?></span></label><?php endforeach; ?></div></div>
      <?= ffselect('Payment Status','payment', ['Pending'=>'Pending','Paid'=>'Paid','Partial'=>'Partial']) ?>
      <?= ffselect('Test Status','reportStatus', array_combine($TEST_STATUS,$TEST_STATUS)) ?>
      <?= ff('Remarks','remarks','text','','md:col-span-2') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
var SEL_DATE=<?= json_encode($selectedDate) ?>;
function fillTests(str){ var sel=(str||'').split(',').map(s=>s.trim()).filter(Boolean); document.querySelectorAll('#f_tests input').forEach(cb=>cb.checked=sel.indexOf(cb.value)>=0); }
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Book Appointment'; document.getElementById('f_date').value=SEL_DATE; fillTests(''); openModal('modal'); }
function openSlot(t){ openNew(); document.getElementById('f_time').value=t; }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Appointment'; fillTests(o.test); openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
