    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('-translate-x-full');
  document.getElementById('backdrop').classList.toggle('hidden');
}
// Generic modal open/close
function openModal(id){ var m=document.getElementById(id); if(m){m.classList.remove('hidden'); m.classList.add('flex');} }
function closeModal(id){ var m=document.getElementById(id); if(m){m.classList.add('hidden'); m.classList.remove('flex');} }
// Normalize a stored date to yyyy-mm-dd for <input type=date>
function isoDate(v){ if(!v) return ''; var m=String(v).match(/^(\d{1,4})-(\d{2})-(\d{2})/); if(m){var y=parseInt(m[1],10); if(y<100)y+=2000; return String(y).padStart(4,'0')+'-'+m[2]+'-'+m[3];} return ''; }
// Fill #f_<key> inputs from an object (id fields, dates, selects, checkboxes)
function fillForm(obj){ for(var k in obj){ var el=document.getElementById('f_'+k); if(!el) continue; var v=obj[k]; if(el.type==='date') v=isoDate(v); if(el.type==='checkbox'){ el.checked=(String(v).toLowerCase()==='true'); } else { el.value=(v==null?'':v); } } }
// Reset all #f_ fields under a modal
function resetForm(){ document.querySelectorAll('[id^=f_]').forEach(function(el){ if(el.type==='checkbox') el.checked=false; else if(el.tagName==='SELECT'){ el.selectedIndex=0; } else el.value=''; }); }
</script>
<?php if (!empty($GLOBALS['__charts'])): foreach ($GLOBALS['__charts'] as $cid => $cfgJson): ?>
<script>
(function(){
  var el = document.getElementById(<?= json_encode($cid) ?>);
  if (el) new Chart(el, <?= $cfgJson ?>);
})();
</script>
<?php endforeach; endif; ?>
</body>
</html>
