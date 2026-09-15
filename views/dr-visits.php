<?php
$title = 'Doctor Visits';
$admin = is_admin();
$canDelete = in_array(current_role(), ['admin','marketing'], true);
function days_since_str(?string $d): int { $t = safe_parse_date($d); return $t ? (int)((time() - $t->getTimestamp())/86400) : 0; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $assoc = ['id'=>trim($_POST['id']??''),'dateOfVisit'=>$_POST['dateOfVisit']??'','doctorName'=>$_POST['doctorName']??'','speciality'=>$_POST['speciality']??'','locationOfVisit'=>$_POST['locationOfVisit']??'','discussion'=>$_POST['discussion']??'','remarks'=>$_POST['remarks']??'','tentativeFollowUp'=>$_POST['tentativeFollowUp']??''];
        if ($assoc['doctorName']==='' || $assoc['dateOfVisit']==='') { set_flash('error','Doctor and date are required'); redirect('index.php?page=dr-visits'); }
        $isEdit = $assoc['id'] !== '';
        $old = $isEdit ? entity_find('dr-visits', $assoc['id']) : null;
        // Date locks 3 days after entry: a non-admin date change after that needs approval.
        $dateChanged = $old && $old['dateOfVisit'] !== $assoc['dateOfVisit'];
        $needsApproval = $isEdit && !$admin && $dateChanged && days_since_str($old['dateOfVisit']) > 3;
        $res = submit_change('dr-visits','Doctor Visit',$isEdit?'update':'create',$assoc,'Update doctor visit — '.$assoc['doctorName'].' (date change after 3-day lock)',$old,$needsApproval);
        set_flash('success', $res==='queued' ? 'Date change sent to Admin for approval' : ($isEdit?'Visit updated':'Visit added'));
        redirect('index.php?page=dr-visits');
    }
    if ($a === 'delete' && $canDelete) { entity_delete('dr-visits', $_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=dr-visits'); }
}
$visits = entity_all('dr-visits');
$cur = current_month_key();
$countByDoc = []; $monthByDoc = [];
foreach ($visits as $v) { if ($v['doctorName']) { $countByDoc[$v['doctorName']] = ($countByDoc[$v['doctorName']]??0)+1; if (month_key_of($v['dateOfVisit'])===$cur) $monthByDoc[$v['doctorName']] = ($monthByDoc[$v['doctorName']]??0)+1; } }
$today = new DateTime('today');
$isPast = function($d) use ($today){ $t = safe_parse_date($d); return $t && $t < $today; };
$overdue = array_values(array_filter($visits, function($v) use ($isPast,$visits){ if (!$v['tentativeFollowUp'] || !$isPast($v['tentativeFollowUp'])) return false; foreach ($visits as $o) if ($o['id']!==$v['id'] && $o['doctorName']===$v['doctorName'] && $o['dateOfVisit']>$v['dateOfVisit']) return false; return true; }));
$upcoming = array_values(array_filter($visits, fn($v)=>$v['tentativeFollowUp'] && !$isPast($v['tentativeFollowUp'])));
$q = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($visits, fn($v)=>$q===''||stripos($v['doctorName'],$q)!==false||stripos($v['locationOfVisit'],$q)!==false));
usort($rows, fn($a,$b)=>strcmp($b['dateOfVisit'],$a['dateOfVisit']));
$doctors = entity_all('doctors');
$specialties = array_values(array_unique(array_filter(array_map(fn($d)=>$d['speciality'],$doctors))));
$rows = paginate($rows);
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center justify-between gap-4 mb-5"><h1 class="text-2xl font-bold text-gray-900">Doctor Visits</h1><button onclick="openNew()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add Visit</button></div>
<?php if ($overdue): ?><div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4"><h3 class="font-semibold text-red-700 mb-2">Overdue Follow-ups (<?= count($overdue) ?>)</h3><div class="flex flex-wrap gap-2"><?php foreach ($overdue as $v): ?><button onclick='openEdit(<?= h(json_encode($v)) ?>)' class="bg-white border border-red-200 rounded-lg px-3 py-1.5 text-xs"><span class="font-medium"><?= h($v['doctorName']) ?></span> — <?= h(format_date($v['tentativeFollowUp'],'d M')) ?></button><?php endforeach; ?></div></div><?php endif; ?>
<?php if ($upcoming): ?><div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4"><h3 class="font-semibold text-blue-700 mb-2">Upcoming Follow-ups (<?= count($upcoming) ?>)</h3><div class="flex flex-wrap gap-2"><?php foreach ($upcoming as $v): ?><button onclick='openEdit(<?= h(json_encode($v)) ?>)' class="bg-white border border-blue-200 rounded-lg px-3 py-1.5 text-xs"><span class="font-medium"><?= h($v['doctorName']) ?></span> — <?= h(format_date($v['tentativeFollowUp'],'d M')) ?></button><?php endforeach; ?></div></div><?php endif; ?>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3"><input type="hidden" name="page" value="dr-visits"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Search doctor or location..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button></form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Date','Doctor','Speciality','Location','Discussion','Remarks','Follow-up','Total Visits','This Month',''] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $v): $od = $v['tentativeFollowUp'] && $isPast($v['tentativeFollowUp']); ?>
    <tr class="hover:bg-gray-50 cursor-pointer" onclick='openEdit(<?= h(json_encode($v)) ?>)'>
      <td class="px-4 py-3 whitespace-nowrap"><?= h(format_date($v['dateOfVisit'])) ?></td>
      <td class="px-4 py-3 font-medium text-gray-900"><?= h($v['doctorName']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full bg-indigo-100 text-indigo-700 px-2 py-1 text-xs font-medium"><?= h($v['speciality']) ?></span></td>
      <td class="px-4 py-3 text-gray-600"><?= h($v['locationOfVisit']) ?></td>
      <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate"><?= h($v['discussion']) ?></td>
      <td class="px-4 py-3 text-gray-600 max-w-[150px] truncate"><?= h($v['remarks']) ?></td>
      <td class="px-4 py-3 whitespace-nowrap <?= $od?'text-red-600 font-semibold':'text-gray-600' ?>"><?= h(format_date($v['tentativeFollowUp'])) ?></td>
      <td class="px-4 py-3 text-center font-semibold text-indigo-600"><?= (int)($countByDoc[$v['doctorName']]??0) ?></td>
      <td class="px-4 py-3 text-center font-semibold text-teal-600"><?= (int)($monthByDoc[$v['doctorName']]??0) ?></td>
      <td class="px-4 py-3"><?php if ($canDelete): ?><form method="post" onsubmit="event.stopPropagation();return confirm('Delete?')" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($v['id']) ?>"><button class="text-red-500 text-xs">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="10" class="px-4 py-10 text-center text-gray-400">No visits found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?= render_pagination() ?>
<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
  <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add Visit</h3>
  <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Date of Visit *</label><input type="date" name="dateOfVisit" id="f_dateOfVisit" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><p id="lockNote" class="text-[11px] text-amber-600 mt-1 hidden">Locked after 3 days — date change will be sent to Admin for approval.</p></div>
      <?= ffselect('Speciality *','speciality', array_merge(['' => 'Select specialty'], array_combine($specialties, $specialties))) ?>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Doctor Name *</label><select name="doctorName" id="f_doctorName" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="">Select doctor</option><?php foreach ($doctors as $d): ?><option value="<?= h($d['name']) ?>" data-spec="<?= h($d['speciality']) ?>"><?= h($d['name']) ?></option><?php endforeach; ?></select></div>
      <?= ff('Location *','locationOfVisit') ?>
      <?= ff('Discussion *','discussion','text','','md:col-span-2') ?>
      <?= ff('Remarks','remarks') ?><?= ff('Follow-up Date *','tentativeFollowUp','date') ?>
    </div>
    <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button></div>
  </form>
</div></div>
<script>
var ADMIN=<?= $admin?'true':'false' ?>;
function openNew(){ resetForm(); document.getElementById('modalTitle').textContent='Add Visit'; document.getElementById('f_dateOfVisit').value=new Date().toISOString().slice(0,10); document.getElementById('lockNote').classList.add('hidden'); openModal('modal'); }
function openEdit(o){ resetForm(); fillForm(o); document.getElementById('modalTitle').textContent='Edit Visit';
  var days = o.dateOfVisit ? Math.floor((Date.now()-new Date(isoDate(o.dateOfVisit)).getTime())/86400000) : 0;
  document.getElementById('lockNote').classList.toggle('hidden', !(!ADMIN && o.id && days>3));
  openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
