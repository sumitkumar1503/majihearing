<?php
$title = 'WhatsApp';
$tab = $_GET['tab'] ?? 'reviews';
$config = get_config();
$clinicName = $config['clinicName'] ?? 'Maji Hearing Aids Centre';
$reviewLink = $config['reviewLink'] ?? '';
$q = trim($_GET['q'] ?? '');

$patients = array_values(array_filter(entity_all('patients'), fn($p)=>$p['contactNo']!==''));
usort($patients, fn($a,$b)=>strcmp($b['date'],$a['date']));
$appointments = array_values(array_filter(entity_all('appointments'), fn($a)=>$a['contactNo']!==''));
usort($appointments, fn($a,$b)=>strcmp($b['date'],$a['date']));
if ($q !== '') {
  $patients = array_filter($patients, fn($p)=>stripos($p['name'],$q)!==false||strpos($p['contactNo'],$q)!==false);
  $appointments = array_filter($appointments, fn($a)=>stripos($a['patientName'],$q)!==false||strpos($a['contactNo'],$q)!==false);
}
$patients = array_slice($patients, 0, 300);
$appointments = array_slice($appointments, 0, 300);

function review_msg($name,$link,$clinic){ return "Dear ".($name?:'Patient').",\n\nThank you for visiting $clinic! We hope you had a great experience.\n\nWe would love your feedback. Please rate us (3, 4 or 5 stars) here:\n".($link?:'[Google review link not configured]')."\n\nYour review means a lot to us. Thank you!"; }
function appt_msg($name,$date,$time,$branch,$clinic){ return "Dear ".($name?:'Patient').",\n\nYour appointment has been scheduled at ".($time?:'[Time]')." on ".($date?:'[Date]').($branch?" at our $branch branch":"").".\n\nPlease arrive 10 minutes early. Reply to this message if you need to reschedule.\n\nRegards,\n$clinic"; }
require __DIR__ . '/../partials/top.php';
?>
<div class="flex items-center gap-3 mb-5"><div class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-500 text-white text-xl">✆</div><div><h1 class="text-2xl font-bold text-gray-900">WhatsApp Center</h1><p class="text-sm text-gray-500">Send review requests, appointment confirmations &amp; messages.</p></div></div>
<?php if ($tab==='reviews' && !$reviewLink): ?><div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 mb-5 text-sm text-amber-700">No Google review link configured yet. Add it under <a href="index.php?page=settings&tab=whatsapp" class="underline font-medium">Settings → WhatsApp</a>. Messages still send, without the review URL.</div><?php endif; ?>
<div class="flex gap-1 rounded-lg bg-gray-100 p-1 w-full sm:w-fit overflow-x-auto mb-5">
  <?php foreach (['reviews'=>'Review Requests','appointments'=>'Appointment Confirmations','compose'=>'Quick Message'] as $k=>$l): ?><a href="index.php?page=whatsapp&tab=<?= $k ?>" class="rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap <?= $tab===$k?'bg-white text-green-600 shadow-sm':'text-gray-500 hover:text-gray-700' ?>"><?= $l ?></a><?php endforeach; ?>
</div>
<?php if ($tab !== 'compose'): ?>
<form method="get" class="max-w-md mb-5"><input type="hidden" name="page" value="whatsapp"><input type="hidden" name="tab" value="<?= h($tab) ?>"><input name="q" value="<?= h($q) ?>" placeholder="Search by name or phone..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></form>
<?php endif; ?>
<?php if ($tab==='reviews'): ?>
  <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
    <?php foreach ($patients as $p): $msg=review_msg($p['name'],$reviewLink,$clinicName); ?>
      <div class="flex items-center justify-between gap-3 px-4 py-3"><div class="min-w-0"><p class="font-medium text-gray-900 truncate"><?= h($p['name']) ?></p><p class="text-xs text-gray-400"><?= h($p['contactNo']) ?> · <?= h(format_date($p['date'])) ?></p></div>
      <a href="<?= h(wa_link($p['contactNo'],$msg)) ?>" target="_blank" class="rounded-lg bg-green-500 px-3 py-2 text-sm font-medium text-white hover:bg-green-600 flex-shrink-0">Send Review</a></div>
    <?php endforeach; if(!$patients): ?><p class="px-5 py-10 text-center text-gray-400">No patients with phone numbers found</p><?php endif; ?>
  </div>
<?php elseif ($tab==='appointments'): ?>
  <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
    <?php foreach ($appointments as $a): $msg=appt_msg($a['patientName'],format_date($a['date']),$a['time'],$a['branch'],$clinicName); ?>
      <div class="flex items-center justify-between gap-3 px-4 py-3"><div class="min-w-0"><p class="font-medium text-gray-900 truncate"><?= h($a['patientName']) ?></p><p class="text-xs text-gray-400"><?= h($a['contactNo']) ?> · <?= h(format_date($a['date'])) ?> <?= h($a['time']) ?></p></div>
      <a href="<?= h(wa_link($a['contactNo'],$msg)) ?>" target="_blank" class="rounded-lg bg-green-500 px-3 py-2 text-sm font-medium text-white hover:bg-green-600 flex-shrink-0">Confirm</a></div>
    <?php endforeach; if(!$appointments): ?><p class="px-5 py-10 text-center text-gray-400">No appointments with phone numbers found</p><?php endif; ?>
  </div>
<?php else: ?>
  <div class="bg-white rounded-xl border border-gray-200 p-5 max-w-lg space-y-4">
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label><input id="wphone" placeholder="e.g. 9831493073" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Message</label><textarea id="wmsg" rows="5" placeholder="Type your message..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea></div>
    <button onclick="sendWa()" class="flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-600">Open in WhatsApp</button>
    <p class="text-xs text-gray-400">This opens WhatsApp with your message pre-filled — tap Send there to deliver it.</p>
  </div>
  <script>
  function sendWa(){ var p=document.getElementById('wphone').value.replace(/\D/g,''); if(p.length===10)p='91'+p; if(p.length<11){alert('Invalid phone number');return;} var m=document.getElementById('wmsg').value||('Greetings from <?= h($clinicName) ?>.'); window.open('https://wa.me/'+p+'?text='+encodeURIComponent(m),'_blank'); }
  </script>
<?php endif; ?>
<?php require __DIR__ . '/../partials/bottom.php';
