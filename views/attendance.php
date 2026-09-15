<?php
$title = 'Attendance';
$admin = is_admin();
$me = current_user()['name'] ?? '';
$SESS = [['checkInTime','checkInLocation','checkOutTime','checkOutLocation'],['checkIn2Time','checkIn2Location','checkOut2Time','checkOut2Location'],['checkIn3Time','checkIn3Location','checkOut3Time','checkOut3Location']];

function att_sessions(array $r, array $SESS): array {
    $out = [];
    foreach ($SESS as $k) $out[] = ['in'=>$r[$k[0]]??'','inLoc'=>$r[$k[1]]??'','out'=>$r[$k[2]]??'','outLoc'=>$r[$k[3]]??''];
    return $out;
}
function att_total(array $r, array $SESS): string {
    $mins = 0; $has = false;
    foreach (att_sessions($r, $SESS) as $s) {
        if ($s['in'] && $s['out']) { [$h1,$m1]=array_map('intval',explode(':',$s['in'])); [$h2,$m2]=array_map('intval',explode(':',$s['out'])); $d=($h2*60+$m2)-($h1*60+$m1); if($d>0){$mins+=$d;$has=true;} }
    }
    return $has ? number_format($mins/60, 1) : '';
}
function att_has_open(array $r, array $SESS): bool { foreach (att_sessions($r,$SESS) as $s) if ($s['in'] && !$s['out']) return true; return false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    $today = date('Y-m-d'); $now = date('H:i');
    if ($a === 'checkin') {
        $loc = $_POST['location'] ?: 'Location unavailable';
        $rec = null; foreach (entity_all('attendance') as $r) if ($r['userName']===$me && $r['date']===$today) { $rec=$r; break; }
        if (!$rec) { entity_insert('attendance', ['date'=>$today,'userName'=>$me,'checkInTime'=>$now,'checkInLocation'=>$loc,'status'=>'Pending Admin']); }
        else { $sessions=att_sessions($rec,$SESS); foreach ($SESS as $i=>$k){ if(!$sessions[$i]['in']){ $rec[$k[0]]=$now; $rec[$k[1]]=$loc; entity_update('attendance',$rec); break; } } }
        set_flash('success','Checked in — awaiting admin approval'); redirect('index.php?page=attendance');
    }
    if ($a === 'checkout') {
        $loc = $_POST['location'] ?: 'Location unavailable';
        $rec = null; foreach (entity_all('attendance') as $r) if ($r['userName']===$me && $r['date']===$today) { $rec=$r; break; }
        if ($rec && ($rec['status']??'')==='Approved' && att_has_open($rec,$SESS)) {
            $sessions=att_sessions($rec,$SESS); foreach ($SESS as $i=>$k){ if($sessions[$i]['in'] && !$sessions[$i]['out']){ $rec[$k[2]]=$now; $rec[$k[3]]=$loc; break; } }
            $rec['totalHours']=att_total($rec,$SESS); $rec['status']='Pending Admin'; entity_update('attendance',$rec);
            set_flash('success','Checked out — awaiting admin approval');
        } else { set_flash('error','Waiting for Admin to approve your Check-In before you can Check Out'); }
        redirect('index.php?page=attendance');
    }
    if ($admin && $a === 'approve') { $r=entity_find('attendance',$_POST['id']??''); if($r){ $r['status']='Approved'; if(!$r['totalHours'])$r['totalHours']=att_total($r,$SESS); entity_update('attendance',$r); } set_flash('success','Attendance approved'); redirect('index.php?page=attendance'); }
    if ($admin && $a === 'unlock') { $r=entity_find('attendance',$_POST['id']??''); if($r){ $r['checkOutTime']=$_POST['checkOutTime']?:'18:00'; $r['checkOutLocation']='Admin override'; $r['totalHours']=att_total($r,$SESS); $r['status']='Approved'; entity_update('attendance',$r); } set_flash('success','User unlocked'); redirect('index.php?page=attendance'); }
    if ($admin && $a === 'delete') { entity_delete('attendance',$_POST['id']??''); set_flash('success','Deleted'); redirect('index.php?page=attendance'); }
}

$records = entity_all('attendance');
$today = date('Y-m-d');
$myRecords = array_values(array_filter($records, fn($r)=>$r['userName']===$me));
$todayRec = null; foreach ($myRecords as $r) if ($r['date']===$today) { $todayRec=$r; break; }
$isLocked = false; $lockedRec = null;
foreach ($myRecords as $r) if ($r['date']<$today && $r['checkInTime'] && !$r['checkOutTime']) { $isLocked=true; $lockedRec=$r; break; }
$todaySessions = $todayRec ? att_sessions($todayRec,$SESS) : [];
$checkInCount = count(array_filter($todaySessions, fn($s)=>$s['in']));
$openSession = (bool)array_filter($todaySessions, fn($s)=>$s['in'] && !$s['out']);
$checkInApproved = ($todayRec['status']??'')==='Approved';
$canCheckIn = !$isLocked && $checkInCount<3 && !$openSession;
$canCheckOut = $openSession && $checkInApproved;
$todayTotal = $todayRec ? att_total($todayRec,$SESS) : '';

$dateFrom = $_GET['from'] ?? date('Y-m-01'); $dateTo = $_GET['to'] ?? $today; $q = trim($_GET['q'] ?? '');
$visible = $admin ? $records : $myRecords;
$rows = array_values(array_filter($visible, function($r) use ($q,$dateFrom,$dateTo){ if($q!=='' && stripos($r['userName'],$q)===false && strpos($r['date'],$q)===false && stripos($r['status'],$q)===false) return false; if($r['date']<$dateFrom||$r['date']>$dateTo) return false; return true; }));
usort($rows, fn($a,$b)=>strcmp($b['date'],$a['date']));
$presentToday = count(array_filter($records, fn($r)=>$r['date']===$today));
$pendingAdmin = count(array_filter($records, fn($r)=>($r['status']??'')!=='Approved'));
$appr = array_filter($records, fn($r)=>$r['status']==='Approved' && $r['totalHours']);
$avg = $appr ? number_format(array_sum(array_map(fn($r)=>(float)$r['totalHours'],$appr))/count($appr),1) : '0.0';
require __DIR__ . '/../partials/top.php';
?>
<h1 class="text-2xl font-bold text-gray-900 mb-5">Attendance</h1>
<?php if (!$admin && $isLocked): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5"><p class="font-semibold text-red-800">Portal Locked</p><p class="text-sm text-red-600 mt-1">Missed Check-Out on <span class="font-medium"><?= h($lockedRec['date']??'') ?></span>. Contact Admin to unlock your attendance.</p></div>
<?php endif; ?>
<?php if (!$admin): ?>
<div class="bg-white rounded-2xl border border-gray-200 p-6 mb-5">
  <div class="text-center mb-6"><p class="text-sm text-gray-400">Current Time</p><p id="clock" class="text-4xl font-bold text-gray-900"><?= date('H:i') ?></p><p class="text-sm text-gray-500 mt-1"><?= date('l, F j, Y') ?></p></div>
  <?php if ($todayRec): ?>
  <div class="bg-gray-50 rounded-xl p-4 mb-6"><div class="flex items-center justify-between mb-3"><p class="text-xs font-medium text-gray-500 uppercase">Today's Sessions</p><span class="text-[10px] font-medium text-gray-500 bg-gray-200 rounded-full px-2 py-0.5"><?= count(array_filter($todaySessions, fn($s)=>$s['out'])) ?>/3 completed</span></div>
    <div class="space-y-2"><?php foreach ($todaySessions as $i=>$s): if(!$s['in'] && $i>0) continue; ?>
      <div class="grid grid-cols-3 gap-3 items-center bg-white rounded-lg px-3 py-2"><div class="text-[10px] text-gray-400 uppercase">Session <?= $i+1 ?></div><div class="text-center"><p class="text-[10px] text-gray-500">In</p><p class="text-sm font-bold text-green-600"><?= h($s['in']?:'—') ?></p></div><div class="text-center"><p class="text-[10px] text-gray-500">Out</p><p class="text-sm font-bold text-red-600"><?= h($s['out']?:'—') ?></p></div></div>
    <?php endforeach; ?></div>
    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200"><p class="text-xs text-gray-500">Total Utilized Time</p><p class="text-lg font-bold text-indigo-600"><?= $todayTotal?h($todayTotal).' hrs':'—' ?></p></div>
    <?php if (($todayRec['status']??'')!=='Approved' && $openSession): ?><p class="text-xs text-amber-600 mt-2">Check-in pending Admin approval.</p><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <form method="post" onsubmit="return captureGPS(this)"><input type="hidden" name="action" value="checkin"><input type="hidden" name="location" class="loc">
      <button type="submit" <?= $canCheckIn?'':'disabled' ?> class="w-full h-24 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 text-white font-bold shadow-lg disabled:opacity-40 disabled:cursor-not-allowed"><span class="text-2xl block">Check In</span><span class="text-xs opacity-80 block mt-1"><?= $isLocked?'Locked — Contact Admin':($openSession?'Check out first':'Tap to check in') ?></span></button></form>
    <form method="post" onsubmit="return captureGPS(this)"><input type="hidden" name="action" value="checkout"><input type="hidden" name="location" class="loc">
      <button type="submit" <?= $canCheckOut?'':'disabled' ?> class="w-full h-24 rounded-2xl bg-gradient-to-br from-red-500 to-rose-600 text-white font-bold shadow-lg disabled:opacity-40 disabled:cursor-not-allowed"><span class="text-2xl block">Check Out</span><span class="text-xs opacity-80 block mt-1"><?= $openSession?($checkInApproved?'Tap to check out with GPS':'Waiting for Admin to approve Check-In'):'Check in first' ?></span></button></form>
  </div>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
  <div class="bg-gradient-to-r from-emerald-600 to-teal-700 rounded-xl p-6 text-white"><p class="text-sm opacity-80">Present Today</p><p class="text-3xl font-bold mt-1"><?= $presentToday ?></p></div>
  <div class="bg-gradient-to-r from-amber-500 to-orange-600 rounded-xl p-6 text-white"><p class="text-sm opacity-80">Pending Admin Action</p><p class="text-3xl font-bold mt-1"><?= $pendingAdmin ?></p></div>
  <div class="bg-gradient-to-r from-indigo-600 to-purple-700 rounded-xl p-6 text-white"><p class="text-sm opacity-80">Average Hours</p><p class="text-3xl font-bold mt-1"><?= $avg ?> hrs</p></div>
</div>
<?php endif; ?>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-col sm:flex-row gap-3"><input type="hidden" name="page" value="attendance">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="<?= $admin?'Search by name, date, status...':'Search your records...' ?>" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <input type="date" name="from" value="<?= h($dateFrom) ?>" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <input type="date" name="to" value="<?= h($dateTo) ?>" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Filter</button>
</form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (array_merge(['Date','Name','Check In','Check Out','Hours','Status'], $admin?['Actions']:[]) as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($rows as $r): $pending=att_has_open($r,$SESS); $sessions=att_sessions($r,$SESS); $ins=array_filter($sessions,fn($s)=>$s['in']); $outs=array_filter($sessions,fn($s)=>$s['out']); ?>
    <tr class="hover:bg-gray-50 <?= $pending?'bg-amber-50':'' ?>">
      <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= h($r['date']) ?></td><td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap"><?= h($r['userName']) ?></td>
      <td class="px-4 py-3 text-green-600 font-medium whitespace-nowrap"><?= $ins?h(implode(', ',array_map(fn($s)=>$s['in'],$ins))):'—' ?></td>
      <td class="px-4 py-3 text-red-600 font-medium whitespace-nowrap"><?= $outs?h(implode(', ',array_map(fn($s)=>$s['out'],$outs))):'—' ?></td>
      <td class="px-4 py-3 font-medium text-indigo-600"><?= h($r['totalHours']?:'—') ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= ($r['status']??'')==='Approved'?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700' ?>"><?= h($r['status']?:'Pending Admin') ?></span></td>
      <?php if ($admin): ?>
      <td class="px-4 py-3"><div class="flex items-center gap-3">
        <?php if (($r['status']??'')!=='Approved'): ?><form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><button class="text-green-600 text-xs font-medium">Approve</button></form><?php endif; ?>
        <?php if ($pending): ?><form method="post" onsubmit="var t=prompt('Check-out time (HH:MM)','18:00'); if(!t)return false; this.checkOutTime.value=t;"><input type="hidden" name="action" value="unlock"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><input type="hidden" name="checkOutTime"><button class="text-amber-600 text-xs font-medium">Unlock</button></form><?php endif; ?>
        <form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><button class="text-red-500 text-xs font-medium">Delete</button></form>
      </div></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="<?= $admin?7:6 ?>" class="px-4 py-10 text-center text-gray-400">No attendance records found</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<script>
setInterval(function(){ var c=document.getElementById('clock'); if(c){ var d=new Date(); c.textContent=String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); } }, 30000);
function captureGPS(form){
  var btn=form.querySelector('button'); if(btn){btn.disabled=true;}
  if (!navigator.geolocation){ form.querySelector('.loc').value='Location unavailable'; form.submit(); return false; }
  navigator.geolocation.getCurrentPosition(function(pos){ form.querySelector('.loc').value=pos.coords.latitude.toFixed(6)+', '+pos.coords.longitude.toFixed(6); form.submit(); }, function(){ form.querySelector('.loc').value='Location unavailable'; form.submit(); }, {enableHighAccuracy:true,timeout:10000});
  return false;
}
</script>
<?php require __DIR__ . '/../partials/bottom.php';
