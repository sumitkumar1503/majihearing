<?php
$title = 'Requests';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $id = $_POST['id'] ?? '';
    $decision = $_POST['decision'] ?? '';
    $appr = entity_find('approvals', $id);
    if ($appr && ($appr['status'] ?? '') === 'pending' && in_array($decision, ['approved','rejected'], true)) {
        try {
            if ($decision === 'approved') apply_approval($appr);
            $appr['status'] = $decision;
            $appr['reviewedBy'] = current_user()['name'] ?? '';
            $appr['reviewedAt'] = date('c');
            entity_update('approvals', $appr);
            set_flash('success', $decision === 'approved' ? 'Approved & applied' : 'Rejected');
        } catch (Exception $e) {
            set_flash('error', 'Failed: ' . $e->getMessage());
        }
    }
    redirect('index.php?page=requests');
}

$tab = $_GET['tab'] ?? 'pending';
$all = approvals_for_current_user();
$list = $tab === 'all' ? $all : array_values(array_filter($all, fn($r) => $r['status'] === $tab));
$pendingCount = count(array_filter($all, fn($r) => $r['status'] === 'pending'));

require __DIR__ . '/../partials/top.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
  <div>
    <h1 class="text-2xl font-bold text-gray-900"><?= is_admin() ? 'Approval Requests' : 'My Change Requests' ?></h1>
    <p class="text-sm text-gray-500"><?= is_admin() ? 'Review and approve or reject pending changes.' : 'Track status of your submitted changes.' ?></p>
  </div>
  <?php if ($pendingCount): ?><span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700 w-fit"><?= $pendingCount ?> pending</span><?php endif; ?>
</div>
<div class="flex gap-1 rounded-lg bg-gray-100 p-1 w-fit mb-5">
  <?php foreach (['pending','approved','rejected','all'] as $t): ?>
    <a href="index.php?page=requests&tab=<?= $t ?>" class="rounded-md px-3 py-1.5 text-sm font-medium capitalize <?= $tab === $t ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>"><?= $t ?></a>
  <?php endforeach; ?>
</div>

<div class="space-y-3">
<?php foreach ($list as $r):
  $old = json_decode($r['oldValue'] ?: 'null', true);
  $new = json_decode($r['newValue'] ?: 'null', true);
  $actClr = ['create'=>'bg-green-100 text-green-700','update'=>'bg-amber-100 text-amber-700','delete'=>'bg-red-100 text-red-700'][$r['action']] ?? 'bg-gray-100 text-gray-700';
  $stClr = ['pending'=>'bg-amber-100 text-amber-700','approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700'][$r['status']] ?? 'bg-gray-100';
?>
  <div class="bg-white rounded-xl border border-gray-200 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase <?= $actClr ?>"><?= h($r['action']) ?></span>
        <span class="font-semibold text-gray-900"><?= h($r['moduleLabel']) ?></span>
        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $stClr ?>"><?= h($r['status']) ?></span>
      </div>
      <p class="text-xs text-gray-400"><?= h(format_date($r['createdAt'], 'd M Y, H:i')) ?></p>
    </div>
    <p class="text-sm text-gray-700 mb-1"><?= h($r['summary']) ?></p>
    <p class="text-xs text-gray-400 mb-3">By <span class="font-medium text-gray-600"><?= h($r['requestedBy']) ?></span> (<?= h($r['requestedByRole']) ?>)</p>
    <details><summary class="cursor-pointer text-xs font-medium text-indigo-600">View details</summary>
      <div class="mt-2 rounded-lg bg-gray-50 p-3 overflow-x-auto"><table class="w-full text-xs"><thead><tr class="text-gray-400"><th class="text-left px-2 py-1">Field</th><?php if ($r['action']==='update'): ?><th class="text-left px-2 py-1">Old</th><?php endif; ?><th class="text-left px-2 py-1">New</th></tr></thead><tbody>
        <?php $keys = array_unique(array_merge(array_keys($old ?: []), array_keys($new ?: []))); foreach ($keys as $k): if (in_array($k,['id','sn'],true)) continue; $o=$old[$k]??''; $n=$new[$k]??''; $chg=$r['action']==='update' && (string)(is_array($o)?json_encode($o):$o)!==(string)(is_array($n)?json_encode($n):$n); ?>
          <tr class="<?= $chg?'bg-amber-50':'' ?>"><td class="px-2 py-1 font-medium text-gray-600"><?= h($k) ?></td><?php if ($r['action']==='update'): ?><td class="px-2 py-1 text-gray-400 line-through"><?= h(is_array($o)?json_encode($o):(string)$o) ?></td><?php endif; ?><td class="px-2 py-1 <?= $chg?'text-amber-700 font-semibold':'text-gray-700' ?>"><?= h(is_array($n)?json_encode($n):(string)$n) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </details>
    <?php if (is_admin() && $r['status'] === 'pending'): ?>
    <div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">
      <form method="post"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><input type="hidden" name="decision" value="approved"><button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Accept</button></form>
      <form method="post" onsubmit="return confirm('Reject and discard this change?')"><input type="hidden" name="id" value="<?= h($r['id']) ?>"><input type="hidden" name="decision" value="rejected"><button class="rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">Reject</button></form>
    </div>
    <?php elseif ($r['status'] !== 'pending' && $r['reviewedBy']): ?>
    <p class="text-xs text-gray-400 mt-3 pt-3 border-t border-gray-100"><?= ucfirst($r['status']) ?> by <?= h($r['reviewedBy']) ?><?= $r['reviewedAt'] ? ' · ' . h(format_date($r['reviewedAt'], 'd M, H:i')) : '' ?></p>
    <?php endif; ?>
  </div>
<?php endforeach; if (!$list): ?>
  <div class="text-center py-16 bg-white rounded-xl border border-gray-200 text-gray-400">No <?= $tab !== 'all' ? h($tab) : '' ?> requests</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/bottom.php';
