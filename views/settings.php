<?php
$title = 'Settings';
$tab = $_GET['tab'] ?? 'branches';
$sid = spreadsheet_id('master');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Branches
    if ($action === 'branch_add') { entity_insert('branches', ['name'=>trim($_POST['name']??''),'address'=>$_POST['address']??'','phone'=>$_POST['phone']??'','active'=>'true']); set_flash('success','Branch added'); redirect('index.php?page=settings&tab=branches'); }
    if ($action === 'branch_toggle') { $b=entity_find('branches',$_POST['id']??''); if($b){$b['active']=strtolower($b['active'])==='false'?'true':'false'; entity_update('branches',$b);} set_flash('success','Branch updated'); redirect('index.php?page=settings&tab=branches'); }
    if ($action === 'branch_delete') { entity_delete('branches',$_POST['id']??''); set_flash('success','Branch deleted'); redirect('index.php?page=settings&tab=branches'); }
    // Brands
    if ($action === 'brand_add') { $n=trim($_POST['name']??''); if($n!=='' && !array_filter(entity_all('brands'),fn($b)=>strtolower($b['name'])===strtolower($n))){ entity_insert('brands',['name'=>$n,'active'=>'true']); set_flash('success','Brand added'); } redirect('index.php?page=settings&tab=brands'); }
    if ($action === 'brand_toggle') { $b=entity_find('brands',$_POST['id']??''); if($b){$b['active']=strtolower($b['active'])==='false'?'true':'false'; entity_update('brands',$b);} redirect('index.php?page=settings&tab=brands'); }
    // Tests
    if ($action === 'test_add') { $n=trim($_POST['name']??''); if($n!=='' && !array_filter(entity_all('tests'),fn($t)=>strtolower($t['name'])===strtolower($n))){ entity_insert('tests',['name'=>$n,'active'=>'true']); set_flash('success','Test added'); } redirect('index.php?page=settings&tab=tests'); }
    if ($action === 'test_toggle') { $t=entity_find('tests',$_POST['id']??''); if($t){$t['active']=strtolower($t['active'])==='false'?'true':'false'; entity_update('tests',$t);} redirect('index.php?page=settings&tab=tests'); }
    // Services (no id: match category+description)
    if ($action === 'service_save') {
        $cat=$_POST['category']??'Service'; $desc=trim($_POST['description']??''); $price=$_POST['price']??'0';
        $oc=$_POST['originalCategory']??''; $od=$_POST['originalDescription']??'';
        if ($desc==='') { set_flash('error','Description required'); redirect('index.php?page=settings&tab=services'); }
        service_save($cat,$desc,$price,$oc,$od);
        set_flash('success','Service saved'); redirect('index.php?page=settings&tab=services');
    }
    // WhatsApp config
    if ($action === 'config_save') { set_config_value('reviewLink',$_POST['reviewLink']??''); set_config_value('clinicName',$_POST['clinicName']??'Maji Hearing Aids Centre'); set_flash('success','WhatsApp settings saved'); redirect('index.php?page=settings&tab=whatsapp'); }
    // Sync interval
    if ($action === 'sync_interval') { set_config_value('sync_interval_minutes', (string)max(5,(int)($_POST['interval']??60))); set_flash('success','Sync interval updated'); redirect('index.php?page=settings&tab=database'); }
}

$branches = entity_all('branches');
$brands = entity_all('brands');
$tests = entity_all('tests');
$services = array_map(fn($r)=>['category'=>$r['category']?:'Service','description'=>$r['description'],'price'=>$r['price']], services_all());
$config = get_config();
$CATS = ['Service','Hearing Aids','Accessories'];
$tabs = ['branches'=>'Branches','services'=>'Service Catalog','brands'=>'HA Brands','tests'=>'Medical Tests','whatsapp'=>'WhatsApp','database'=>'Database & Sync','general'=>'General'];
require __DIR__ . '/../partials/top.php';
?>
<h1 class="text-2xl font-bold text-gray-900 mb-5">Settings</h1>
<div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-5 overflow-x-auto">
  <?php foreach ($tabs as $k=>$l): ?><a href="index.php?page=settings&tab=<?= $k ?>" class="rounded-md px-4 py-2 text-sm font-medium flex-1 text-center whitespace-nowrap <?= $tab===$k?'bg-white text-indigo-600 shadow-sm':'text-gray-600 hover:text-gray-900' ?>"><?= $l ?></a><?php endforeach; ?>
</div>

<?php if ($tab === 'branches'): ?>
  <div class="flex justify-between items-center mb-4"><h2 class="text-lg font-semibold text-gray-900">Branch Management</h2><button onclick="openModal('brModal')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">+ Add Branch</button></div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($branches as $b): $act=strtolower($b['active'])!=='false'; ?>
      <div class="rounded-xl border p-5 <?= $act?'bg-white border-gray-200':'bg-gray-50 border-gray-100' ?>">
        <div class="flex justify-between items-start"><div><h3 class="font-semibold text-lg <?= $act?'text-gray-900':'text-gray-400 line-through' ?>"><?= h($b['name']) ?></h3><?php if($b['address']): ?><p class="text-sm text-gray-500 mt-1"><?= h($b['address']) ?></p><?php endif; ?><?php if($b['phone']): ?><p class="text-sm text-gray-500">Phone: <?= h($b['phone']) ?></p><?php endif; ?></div><span class="rounded-full px-2 py-1 text-xs font-medium <?= $act?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= $act?'Active':'Discontinued' ?></span></div>
        <div class="mt-4 flex items-center gap-4 border-t border-gray-100 pt-3">
          <form method="post"><input type="hidden" name="action" value="branch_toggle"><input type="hidden" name="id" value="<?= h($b['id']) ?>"><button class="text-xs font-medium <?= $act?'text-red-500':'text-green-600' ?>"><?= $act?'Discontinue':'Reactivate' ?></button></form>
          <form method="post" onsubmit="return confirm('Delete branch?')"><input type="hidden" name="action" value="branch_delete"><input type="hidden" name="id" value="<?= h($b['id']) ?>"><button class="text-xs font-medium text-gray-500 hover:text-red-700">Delete</button></form>
        </div>
      </div>
    <?php endforeach; if(!$branches): ?><div class="col-span-2 text-center py-8 text-gray-500">No branches configured</div><?php endif; ?>
  </div>
  <div id="brModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-md p-6"><h3 class="text-lg font-semibold mb-4">Add Branch</h3>
    <form method="post"><input type="hidden" name="action" value="branch_add"><div class="space-y-4"><?= ff('Branch Name *','name') ?><?= ff('Address','address') ?><?= ff('Phone','phone','tel') ?></div>
      <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('brModal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Add</button></div>
    </form></div></div>

<?php elseif ($tab === 'services'): ?>
  <div class="flex justify-between items-center mb-4"><h2 class="text-lg font-semibold text-gray-900">Service Catalog</h2><button onclick="svcNew()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">+ Add Service</button></div>
  <?php foreach ($CATS as $cat): $cs=array_filter($services,fn($s)=>$s['category']===$cat); if(!$cs) continue; ?>
    <div class="mb-4"><h3 class="text-sm font-semibold text-gray-500 uppercase mb-2"><?= $cat ?></h3><div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th><th class="px-4 py-3 w-20"></th></tr></thead><tbody class="divide-y divide-gray-200">
      <?php foreach ($cs as $svc): ?><tr class="hover:bg-gray-50"><td class="px-4 py-3 text-gray-900"><?= h($svc['description']) ?></td><td class="px-4 py-3 font-medium"><?= rupees($svc['price']) ?></td><td class="px-4 py-3"><button onclick='svcEdit(<?= h(json_encode($svc)) ?>)' class="text-indigo-600 hover:underline text-xs">Edit</button></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <?php endforeach; ?>
  <div id="svcModal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4"><div class="bg-white rounded-2xl w-full max-w-md p-6"><h3 id="svcTitle" class="text-lg font-semibold mb-4">Add Service</h3>
    <form method="post"><input type="hidden" name="action" value="service_save"><input type="hidden" name="originalCategory" id="s_oc"><input type="hidden" name="originalDescription" id="s_od">
      <div class="space-y-4"><?= ffselect('Category','category', array_combine($CATS,$CATS)) ?><?= ff('Description *','description') ?><?= ff('Price (₹)','price','number') ?></div>
      <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('svcModal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Save</button></div>
    </form></div></div>
  <script>
  function svcNew(){ document.getElementById('svcTitle').textContent='Add Service'; document.getElementById('f_category').value='Service'; document.getElementById('f_description').value=''; document.getElementById('f_price').value=''; document.getElementById('s_oc').value=''; document.getElementById('s_od').value=''; openModal('svcModal'); }
  function svcEdit(s){ document.getElementById('svcTitle').textContent='Edit Service'; document.getElementById('f_category').value=s.category; document.getElementById('f_description').value=s.description; document.getElementById('f_price').value=s.price; document.getElementById('s_oc').value=s.category; document.getElementById('s_od').value=s.description; openModal('svcModal'); }
  </script>

<?php elseif ($tab === 'brands' || $tab === 'tests'): $isBrand=$tab==='brands'; $list=$isBrand?$brands:$tests; $pref=$isBrand?'brand':'test'; ?>
  <h2 class="text-lg font-semibold text-gray-900"><?= $isBrand?'Hearing Aid Brands':'Medical Tests' ?></h2>
  <p class="text-sm text-gray-500 mb-4"><?= $isBrand?'Add or discontinue brands. Discontinued brands are hidden from stock/sales dropdowns.':'Manage tests shown on the Patient &amp; Appointment dashboards. Discontinued tests are hidden.' ?></p>
  <form method="post" class="flex gap-2 max-w-md mb-4"><input type="hidden" name="action" value="<?= $pref ?>_add"><input type="text" name="name" placeholder="New <?= $pref ?> name" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">+ Add</button></form>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
    <?php foreach ($list as $b): $act=strtolower($b['active'])!=='false'; ?>
      <div class="flex items-center justify-between gap-2 rounded-lg border p-3 <?= $act?'border-gray-200 bg-white':'border-gray-100 bg-gray-50' ?>">
        <span class="text-sm font-medium truncate <?= $act?'text-gray-900':'text-gray-400 line-through' ?>"><?= h($b['name']) ?></span>
        <form method="post"><input type="hidden" name="action" value="<?= $pref ?>_toggle"><input type="hidden" name="id" value="<?= h($b['id']) ?>"><button class="text-xs font-medium <?= $act?'text-red-500':'text-green-600' ?>"><?= $act?'Discontinue':'Reactivate' ?></button></form>
      </div>
    <?php endforeach; if(!$list): ?><p class="col-span-full text-center py-8 text-gray-500">None configured</p><?php endif; ?>
  </div>

<?php elseif ($tab === 'whatsapp'): ?>
  <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-6 max-w-2xl">
    <div><h2 class="text-lg font-semibold text-gray-900">WhatsApp &amp; Reviews</h2><p class="text-sm text-gray-500">Used by the WhatsApp Center to send review requests and messages.</p></div>
    <form method="post" class="space-y-6"><input type="hidden" name="action" value="config_save">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Google Review Link</label><input type="url" name="reviewLink" value="<?= h($config['reviewLink']??'') ?>" placeholder="https://g.page/r/....../review" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Clinic Name (in messages)</label><input type="text" name="clinicName" value="<?= h($config['clinicName']??'Maji Hearing Aids Centre') ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
      <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save WhatsApp Settings</button>
    </form>
  </div>

<?php elseif ($tab === 'database'):
  $dbOk = db_available();
  $lastSync = config_value('last_sync_at','');
  $interval = (int)config_value('sync_interval_minutes','60');
  $lastResult = json_decode(config_value('last_sync_result','') ?: 'null', true);
  $counts = [];
  if ($dbOk) { foreach (entities() as $k=>$c) { $r=db_row("SELECT COUNT(*) n FROM `".entity_table($k)."`"); $counts[$k]=['label'=>$c['sheet'],'n'=>(int)($r['n']??0)]; } }
  $nextSync = $lastSync ? date('d M Y, h:i A', strtotime($lastSync)+$interval*60) : 'On next run';
?>
  <div class="space-y-5">
    <!-- Status -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-2xl border p-5 <?= $dbOk?'bg-emerald-50 border-emerald-200':'bg-red-50 border-red-200' ?>">
        <p class="text-xs font-medium <?= $dbOk?'text-emerald-700':'text-red-700' ?> uppercase tracking-wide">Database</p>
        <p class="text-lg font-bold <?= $dbOk?'text-emerald-800':'text-red-800' ?> mt-1"><?= $dbOk?'MySQL Connected':'Not Connected' ?></p>
        <p class="text-xs text-gray-500 mt-1">Website reads &amp; writes MySQL</p>
      </div>
      <div class="rounded-2xl border border-gray-200 bg-white p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Sync</p>
        <p class="text-lg font-bold text-gray-900 mt-1"><?= $lastSync ? h(date('d M Y, h:i A', strtotime($lastSync))) : 'Never' ?></p>
        <p class="text-xs text-gray-500 mt-1">Next auto: <?= h($nextSync) ?></p>
      </div>
      <div class="rounded-2xl border border-gray-200 bg-white p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Auto-Sync Every</p>
        <p class="text-lg font-bold text-gray-900 mt-1"><?= $interval ?> min</p>
        <p class="text-xs text-gray-500 mt-1">Google Sheets stays in sync</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5">
      <div class="flex flex-col sm:flex-row sm:items-end gap-4">
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-gray-900">Sync with Google Sheets</h2>
          <p class="text-sm text-gray-500 mt-1">Pushes MySQL data to the spreadsheets and imports any rows added directly in Sheets. Both ends end up identical.</p>
        </div>
        <a href="index.php?page=sync-now" onclick="this.classList.add('opacity-60','pointer-events-none');this.textContent='Syncing…';" class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 text-center">↻ Sync Now</a>
      </div>
      <form method="post" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
        <input type="hidden" name="action" value="sync_interval">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Auto-sync interval (minutes)</label>
          <input type="number" name="interval" min="5" max="1440" value="<?= $interval ?>" class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Save interval</button>
        <span class="text-xs text-gray-400">Requires the hourly cron job (see notes below).</span>
      </form>
    </div>

    <!-- Downloads -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5">
      <h2 class="text-lg font-semibold text-gray-900">Download Data (CSV Backup)</h2>
      <p class="text-sm text-gray-500 mt-1 mb-4">Export any table as a spreadsheet-friendly CSV.</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
        <?php foreach ($counts as $k=>$info): ?>
          <a href="index.php?page=export&entity=<?= h($k) ?>" class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
            <span class="truncate"><?= h($info['label']) ?></span>
            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"><?= $info['n'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Notes -->
    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5">
      <h2 class="text-base font-semibold text-indigo-900">How this works &amp; setup notes</h2>
      <ul class="mt-2 space-y-1.5 text-sm text-indigo-900/80 list-disc list-inside">
        <li>The website now runs entirely on <b>MySQL</b> — fast, and free of Google's per-minute API limits (the earlier 500 errors).</li>
        <li>Google Sheets is kept as a <b>live backup</b>. Every sync makes both sides identical: MySQL changes are pushed to Sheets, and rows you add directly in a Sheet are imported back.</li>
        <li><b>Automatic sync</b> needs one Hostinger cron job. In hPanel → <i>Advanced → Cron Jobs</i>, add a job that runs every 15 minutes:</li>
      </ul>
      <pre class="mt-2 overflow-x-auto rounded-lg bg-indigo-900 text-indigo-50 text-xs p-3">*/15 * * * * /usr/bin/php <?= h(rtrim(str_replace('\\','/',dirname(__DIR__)),'/')) ?>/cron_sync.php</pre>
      <p class="mt-2 text-xs text-indigo-900/70">The cron runs often, but only actually syncs once your chosen interval (<?= $interval ?> min) has elapsed — so you can change the frequency here without editing the cron line.</p>
    </div>

    <?php if (is_array($lastResult) && !empty($lastResult['log'])): ?>
    <details class="rounded-2xl border border-gray-200 bg-white p-5">
      <summary class="cursor-pointer text-sm font-medium text-gray-700">Last sync log (<?= h(date('d M, h:i A', strtotime($lastResult['at']??'now'))) ?>)</summary>
      <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 text-gray-100 text-xs p-3"><?= h(implode("\n", $lastResult['log'])) ?></pre>
    </details>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4 max-w-md">
    <h2 class="text-lg font-semibold text-gray-900">General Settings</h2>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Clinic Name</label><input type="text" value="Maji Hearing Aids Centre" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-gray-700 mb-1">Working Hours</label><input type="text" value="10:30 AM - 2:00 PM" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../partials/bottom.php';
