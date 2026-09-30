<?php
$title = 'Daily Revenue Sheet';
$admin = is_admin();
$TESTS = ['PTA','TYMP','ENG','OAE','ABR','VEMP','SRT/SDS','TDT','SISI','ETF','SP. THX','SWALLOW THX','VOICE THX'];
$ACCESSORY_DEFAULT = ['Accessories'];
$MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

$year = (int)($_GET['year'] ?? date('Y'));
$month = isset($_GET['month']) ? (int)$_GET['month'] : ((int)date('n') - 1); // 0-based
$branch = $_GET['branch'] ?? (active_branch_names()[0] ?? 'Serampore');
$monthStr = sprintf('%04d-%02d', $year, $month + 1);
$daysCount = (int)date('t', mktime(0, 0, 0, $month + 1, 1, $year));

function ds_day_locked(int $y, int $m0, int $d, bool $admin): bool {
    if ($admin) return false;
    $entry = mktime(0, 0, 0, $m0 + 1, $d, $y);
    $diff = floor((strtotime('today') - $entry) / 86400);
    return $diff > 3;
}

$isDefault = fn($n) => in_array($n, $GLOBALS['TESTS'], true) || in_array($n, $GLOBALS['ACCESSORY_DEFAULT'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meta = json_decode($_POST['rowsmeta'] ?? '[]', true);
    if (!is_array($meta)) $meta = [];
    $amt = $_POST['amt'] ?? []; $qty = $_POST['qty'] ?? [];
    $entries = [];
    foreach ($meta as $row) {
        $name = trim($row['name'] ?? '');
        if ($name === '') continue;
        $sec = (($row['section'] ?? 'test') === 'accessory') ? 'accessory' : 'test';
        $has = false;
        for ($d = 1; $d <= $daysCount; $d++) {
            $a = (float)($amt[$name][$d] ?? 0); $qq = (float)($qty[$name][$d] ?? 0);
            if ($a > 0 || $qq > 0) { $entries[] = ['testName'=>$name,'day'=>$d,'amount'=>$a,'quantity'=>$qq,'section'=>$sec]; $has = true; }
        }
        // Persist empty custom rows via a day=0 sentinel so they reappear on reload.
        if (!$has && !$isDefault($name)) $entries[] = ['testName'=>$name,'day'=>0,'amount'=>0,'quantity'=>0,'section'=>$sec];
    }
    $payload = ['month'=>$monthStr,'branch'=>$branch,'entries'=>$entries];
    if ($admin) { save_daily_sheet($branch, $monthStr, $entries); mark_daily_dirty($branch, $monthStr); set_flash('success','Daily sheet saved'); }
    else {
        entity_insert('approvals', ['createdAt'=>date('c'),'requestedBy'=>current_user()['name']??'','requestedByRole'=>current_role(),'module'=>'daily-sheet','moduleLabel'=>'Daily Sheet','action'=>'update','targetId'=>"$branch-$monthStr",'summary'=>"Update daily sheet — $branch / $monthStr",'oldValue'=>'','newValue'=>json_encode($payload),'status'=>'pending','reviewedBy'=>'','reviewedAt'=>'']);
        set_flash('success','Sheet sent for admin approval');
    }
    redirect("index.php?page=daily-sheet&year=$year&month=$month&branch=" . urlencode($branch));
}

// Load existing grid + rebuild custom rows
$grid = []; $savedSection = [];
foreach (daily_sheet_rows($branch, $monthStr) as $r) {
    $nm = cell($r, 0); $savedSection[$nm] = cell($r, 4) ?: 'test';
    if ((int)cell($r, 1) >= 1) $grid[$nm][(int)cell($r, 1)] = ['a'=>(float)cell($r, 2), 'q'=>(float)cell($r, 3)];
}
$customTests = []; $customAcc = [];
foreach ($savedSection as $nm => $sec) {
    if (in_array($nm, $TESTS, true) || $nm === 'Accessories') continue;
    if ($sec === 'accessory') $customAcc[] = $nm; else $customTests[] = $nm;
}
$testRows = array_merge($TESTS, $customTests);
$accRows = array_merge($ACCESSORY_DEFAULT, $customAcc);
$branchList = active_branch_names();

// Server-side totals for initial render
function ds_cell($grid, $name, $d) { return $grid[$name][$d] ?? null; }
$testTotalAmt = 0; $accTotalAmt = 0; $grandQty = 0;
$rowsMeta = [];
foreach ($testRows as $n) $rowsMeta[] = ['name'=>$n,'section'=>'test'];
foreach ($accRows as $n) $rowsMeta[] = ['name'=>$n,'section'=>'accessory'];

require __DIR__ . '/../partials/top.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
  <h1 class="text-2xl font-bold text-gray-900">Daily Revenue Sheet</h1>
  <button form="dsForm" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">↻ Save Sheet</button>
</div>

<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-center"><input type="hidden" name="page" value="daily-sheet">
  <select name="month" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach ($MONTH_NAMES as $i=>$mn): ?><option value="<?= $i ?>" <?= $month===$i?'selected':'' ?>><?= $mn ?></option><?php endforeach; ?></select>
  <input type="number" name="year" value="<?= $year ?>" min="2020" max="2030" onchange="this.form.submit()" class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <select name="branch" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach ($branchList as $b): ?><option <?= $branch===$b?'selected':'' ?>><?= h($b) ?></option><?php endforeach; ?></select>
  <?php if (!$admin): ?><span class="ml-auto text-xs text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg">Entries older than 3 days are locked</span><?php endif; ?>
</form>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Tests Revenue</p><p class="text-xl font-bold text-indigo-600" id="cardTest">₹0</p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Accessories Revenue</p><p class="text-xl font-bold text-purple-600" id="cardAcc">₹0</p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Grand Total</p><p class="text-2xl font-bold text-green-600" id="cardGrand">₹0</p></div>
  <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-sm text-gray-500">Total Quantity</p><p class="text-2xl font-bold text-gray-900" id="cardQty">0</p></div>
</div>

<form method="post" id="dsForm" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
  <input type="hidden" name="rowsmeta" id="rowsmeta" value="<?= h(json_encode($rowsMeta)) ?>">
  <div class="overflow-x-auto"><table class="text-xs border-collapse" id="dsTable">
  <thead>
    <tr class="bg-gray-100"><th class="sticky left-0 z-10 bg-gray-100 px-2 py-2 text-left font-semibold border border-gray-200 min-w-[130px]">Test / Item</th><?php for ($d=1;$d<=$daysCount;$d++): $lk=ds_day_locked($year,$month,$d,$admin); ?><th colspan="2" class="px-1 py-2 text-center font-semibold border border-gray-200 min-w-[76px] <?= $lk?'bg-gray-200 text-gray-500':'' ?>"><?= $d ?></th><?php endfor; ?><th colspan="2" class="px-2 py-2 text-center font-bold border border-gray-200 bg-yellow-50">TOTAL</th></tr>
    <tr class="bg-gray-50"><th class="sticky left-0 z-10 bg-gray-50 border border-gray-200"></th><?php for ($d=1;$d<=$daysCount;$d++): ?><th class="px-1 py-1 text-center text-[10px] text-gray-500 border border-gray-200">Amt</th><th class="px-0.5 py-1 text-center text-[10px] text-gray-500 border border-gray-200">Qty</th><?php endfor; ?><th class="px-1 py-1 text-[10px] text-gray-500 border border-gray-200">AMT</th><th class="px-1 py-1 text-[10px] text-gray-500 border border-gray-200">QTY</th></tr>
  </thead>
  <tbody>
  <?php
  // Render a section (Tests / Accessories)
  $renderSection = function(array $rows, string $section, string $label, string $bg) use ($grid, $daysCount, $year, $month, $admin, $isDefault) {
      // Section header
      echo '<tr class="' . $bg . '" data-sechead="' . $section . '"><td class="sticky left-0 z-10 ' . $bg . ' px-2 py-1.5 font-bold text-xs uppercase tracking-wide border border-gray-200">' . h($label) . '</td>';
      for ($d=1;$d<=$daysCount;$d++) echo '<td colspan="2" class="border border-gray-200 ' . $bg . '"></td>';
      echo '<td class="border border-gray-200 ' . $bg . '"></td><td class="border border-gray-200 ' . $bg . '"></td></tr>';
      // Data rows
      foreach ($rows as $name) {
          $rAmt=0;$rQty=0;
          echo '<tr class="hover:bg-gray-50 group ds-row" data-row="' . h($name) . '" data-section="' . $section . '">';
          echo '<td class="sticky left-0 z-10 bg-white group-hover:bg-gray-50 px-2 py-1 font-medium border border-gray-200 whitespace-nowrap text-xs"><div class="flex items-center gap-1"><span>' . h($name) . '</span>';
          if (!$isDefault($name)) echo '<button type="button" onclick="dsDeleteRow(this,\'' . h(addslashes($name)) . '\')" class="text-red-400 hover:text-red-600 ml-1 opacity-0 group-hover:opacity-100" title="Remove row">✕</button>';
          echo '</div></td>';
          for ($d=1;$d<=$daysCount;$d++) {
              $c = ds_cell($grid,$name,$d); $lk = ds_day_locked($year,$month,$d,$admin);
              $a = $c && $c['a'] ? $c['a'] : ''; $q = $c && $c['q'] ? $c['q'] : '';
              $rAmt += $c['a']??0; $rQty += $c['q']??0;
              $hl = ($c && ($c['a']||$c['q'])) ? 'bg-green-50' : '';
              // Locked cells are READONLY (not disabled) so their values are still
              // submitted — otherwise a partial submission would wipe older data on save.
              $ro = $lk ? 'readonly' : '';
              echo '<td colspan="2" class="border border-gray-200 p-0 ' . $hl . '"><div class="flex">'
                 . '<input type="number" step="any" name="amt[' . h($name) . '][' . $d . ']" value="' . h($a) . '" ' . $ro . ' data-r="' . h($name) . '" data-d="' . $d . '" data-sec="' . $section . '" oninput="dsRecalc()" class="ds-amt w-12 px-1 py-1 text-center text-xs border-r border-gray-200 outline-none focus:bg-indigo-50 read-only:bg-gray-100 read-only:text-gray-400">'
                 . '<input type="number" step="any" name="qty[' . h($name) . '][' . $d . ']" value="' . h($q) . '" ' . $ro . ' data-r="' . h($name) . '" data-d="' . $d . '" data-sec="' . $section . '" oninput="dsRecalc()" class="ds-qty w-7 px-0.5 py-1 text-center text-xs outline-none focus:bg-indigo-50 read-only:bg-gray-100 read-only:text-gray-400">'
                 . '</div></td>';
          }
          echo '<td class="border border-gray-200 px-2 py-1 text-center font-semibold bg-yellow-50" data-total-amt="' . h($name) . '">₹' . number_format($rAmt) . '</td>';
          echo '<td class="border border-gray-200 px-2 py-1 text-center font-semibold bg-yellow-50" data-total-qty="' . h($name) . '">' . (int)$rQty . '</td>';
          echo '</tr>';
      }
      // Subtotal row
      echo '<tr class="font-bold ' . $bg . '" data-subtotal="' . $section . '"><td class="sticky left-0 z-10 ' . $bg . ' px-2 py-1.5 border border-gray-200 text-xs">' . h($label) . ' Subtotal</td>';
      for ($d=1;$d<=$daysCount;$d++) echo '<td colspan="2" class="border border-gray-200 px-1 py-1.5 text-center text-[10px] ' . $bg . '" data-sub-day="' . $section . '-' . $d . '"></td>';
      echo '<td class="border border-gray-200 px-2 py-1.5 text-center text-xs ' . $bg . '" data-subtotal-amt="' . $section . '">₹0</td>';
      echo '<td class="border border-gray-200 px-2 py-1.5 text-center text-xs ' . $bg . '" data-subtotal-qty="' . $section . '">0</td></tr>';
  };
  $renderSection($testRows, 'test', 'Tests', 'bg-indigo-50');
  $renderSection($accRows, 'accessory', 'Accessories', 'bg-purple-50');
  ?>
    <!-- Grand total row -->
    <tr class="bg-green-100 font-bold"><td class="sticky left-0 z-10 bg-green-100 px-2 py-2 border border-gray-200 text-green-900">GRAND TOTAL</td>
      <?php for ($d=1;$d<=$daysCount;$d++): ?><td colspan="2" class="border border-gray-200 px-1 py-2 text-center text-[10px] text-green-800" data-grand-day="<?= $d ?>"></td><?php endfor; ?>
      <td class="border border-gray-200 px-2 py-2 text-center bg-green-200 text-green-900" data-grandtotal-amt>₹0</td>
      <td class="border border-gray-200 px-2 py-2 text-center bg-green-200 text-green-900" data-grandtotal-qty>0</td>
    </tr>
  </tbody>
  </table></div>
  <!-- Add custom row -->
  <div class="p-3 border-t border-gray-200 flex flex-wrap items-center gap-2">
    <input type="text" id="newRowName" placeholder="Enter test / item name..." class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
    <select id="newRowSection" class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm"><option value="test">Test</option><option value="accessory">Accessory</option></select>
    <button type="button" onclick="dsAddRow()" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">+ Add Custom Row</button>
  </div>
</form>

<script>
var DS_DAYS = <?= $daysCount ?>;
function money(n){ return '₹' + (Math.round(n)||0).toLocaleString('en-IN'); }
function dsRecalc(){
  var testDayA={},testDayQ={},accDayA={},accDayQ={}, rowA={},rowQ={};
  var tTot=0,tQty=0,aTot=0,aQty=0;
  document.querySelectorAll('#dsTable input.ds-amt').forEach(function(inp){
    var r=inp.dataset.r, d=inp.dataset.d, sec=inp.dataset.sec, v=parseFloat(inp.value)||0;
    rowA[r]=(rowA[r]||0)+v;
    if(sec==='test'){ testDayA[d]=(testDayA[d]||0)+v; tTot+=v; } else { accDayA[d]=(accDayA[d]||0)+v; aTot+=v; }
  });
  document.querySelectorAll('#dsTable input.ds-qty').forEach(function(inp){
    var r=inp.dataset.r, d=inp.dataset.d, sec=inp.dataset.sec, v=parseFloat(inp.value)||0;
    rowQ[r]=(rowQ[r]||0)+v;
    if(sec==='test'){ testDayQ[d]=(testDayQ[d]||0)+v; tQty+=v; } else { accDayQ[d]=(accDayQ[d]||0)+v; aQty+=v; }
  });
  // row totals
  document.querySelectorAll('[data-total-amt]').forEach(function(td){ td.textContent=money(rowA[td.dataset.totalAmt]||0); });
  document.querySelectorAll('[data-total-qty]').forEach(function(td){ td.textContent=(rowQ[td.dataset.totalQty]||0); });
  // per-day subtotals + grand day
  for(var d=1; d<=DS_DAYS; d++){
    var ts=document.querySelector('[data-sub-day="test-'+d+'"]'); if(ts) ts.textContent=(testDayA[d]||testDayQ[d])?('₹'+(testDayA[d]||0)+' - '+(testDayQ[d]||0)):'';
    var as=document.querySelector('[data-sub-day="accessory-'+d+'"]'); if(as) as.textContent=(accDayA[d]||accDayQ[d])?('₹'+(accDayA[d]||0)+' - '+(accDayQ[d]||0)):'';
    var gd=document.querySelector('[data-grand-day="'+d+'"]'); if(gd){ var ga=(testDayA[d]||0)+(accDayA[d]||0), gq=(testDayQ[d]||0)+(accDayQ[d]||0); gd.textContent=(ga||gq)?('₹'+ga+' - '+gq):''; }
  }
  var st=document.querySelector('[data-subtotal-amt="test"]'); if(st) st.textContent=money(tTot);
  var stq=document.querySelector('[data-subtotal-qty="test"]'); if(stq) stq.textContent=tQty;
  var sa=document.querySelector('[data-subtotal-amt="accessory"]'); if(sa) sa.textContent=money(aTot);
  var saq=document.querySelector('[data-subtotal-qty="accessory"]'); if(saq) saq.textContent=aQty;
  document.querySelector('[data-grandtotal-amt]').textContent=money(tTot+aTot);
  document.querySelector('[data-grandtotal-qty]').textContent=(tQty+aQty);
  document.getElementById('cardTest').textContent=money(tTot);
  document.getElementById('cardAcc').textContent=money(aTot);
  document.getElementById('cardGrand').textContent=money(tTot+aTot);
  document.getElementById('cardQty').textContent=(tQty+aQty);
}
function dsRowsMeta(){ try{ return JSON.parse(document.getElementById('rowsmeta').value||'[]'); }catch(e){ return []; } }
function dsSetRowsMeta(m){ document.getElementById('rowsmeta').value=JSON.stringify(m); }
function dsAddRow(){
  var name=document.getElementById('newRowName').value.trim();
  var section=document.getElementById('newRowSection').value;
  if(!name){ return; }
  var meta=dsRowsMeta();
  if(meta.some(function(r){ return r.name.toLowerCase()===name.toLowerCase(); })){ alert('Row already exists'); return; }
  var esc=name.replace(/"/g,'&quot;');
  var cells='';
  for(var d=1; d<=DS_DAYS; d++){
    cells+='<td colspan="2" class="border border-gray-200 p-0"><div class="flex">'
      +'<input type="number" step="any" name="amt['+esc+']['+d+']" data-r="'+esc+'" data-d="'+d+'" data-sec="'+section+'" oninput="dsRecalc()" class="ds-amt w-12 px-1 py-1 text-center text-xs border-r border-gray-200 outline-none focus:bg-indigo-50">'
      +'<input type="number" step="any" name="qty['+esc+']['+d+']" data-r="'+esc+'" data-d="'+d+'" data-sec="'+section+'" oninput="dsRecalc()" class="ds-qty w-7 px-0.5 py-1 text-center text-xs outline-none focus:bg-indigo-50">'
      +'</div></td>';
  }
  var tr=document.createElement('tr');
  tr.className='hover:bg-gray-50 group ds-row'; tr.dataset.row=name; tr.dataset.section=section;
  tr.innerHTML='<td class="sticky left-0 z-10 bg-white group-hover:bg-gray-50 px-2 py-1 font-medium border border-gray-200 whitespace-nowrap text-xs"><div class="flex items-center gap-1"><span>'+esc+'</span><button type="button" onclick="dsDeleteRow(this,\''+name.replace(/'/g,"\\'")+'\')" class="text-red-400 hover:text-red-600 ml-1">✕</button></div></td>'
    +cells
    +'<td class="border border-gray-200 px-2 py-1 text-center font-semibold bg-yellow-50" data-total-amt="'+esc+'">₹0</td>'
    +'<td class="border border-gray-200 px-2 py-1 text-center font-semibold bg-yellow-50" data-total-qty="'+esc+'">0</td>';
  // insert before the section subtotal row
  var subtotal=document.querySelector('[data-subtotal="'+section+'"]');
  subtotal.parentNode.insertBefore(tr, subtotal);
  meta.push({name:name, section:section}); dsSetRowsMeta(meta);
  document.getElementById('newRowName').value='';
  dsRecalc();
}
function dsDeleteRow(btn, name){
  if(!confirm('Remove this row?')) return;
  var tr=btn.closest('tr'); if(tr) tr.remove();
  var meta=dsRowsMeta().filter(function(r){ return r.name!==name; }); dsSetRowsMeta(meta);
  dsRecalc();
}
dsRecalc();
</script>
<?php require __DIR__ . '/../partials/bottom.php';
