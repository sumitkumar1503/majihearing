    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
// Chart.js styling to mirror the Recharts look from the Next.js app
if (window.Chart) {
  Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
  Chart.defaults.font.size = 11;
  Chart.defaults.color = '#94a3b8';
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.font = { size: 11 };
  Chart.defaults.plugins.tooltip.backgroundColor = '#1e1b4b';
  Chart.defaults.plugins.tooltip.titleColor = '#c7d2fe';
  Chart.defaults.plugins.tooltip.bodyColor = '#e0e7ff';
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 12;
  Chart.defaults.plugins.tooltip.displayColors = false;
  Chart.defaults.elements.bar.borderRadius = 6;
  Chart.defaults.elements.bar.borderSkipped = false;
  Chart.defaults.elements.point.radius = 3;
  Chart.defaults.elements.point.hoverRadius = 5;
  Chart.defaults.elements.line.tension = 0.35;
  Chart.defaults.elements.line.borderWidth = 2.5;
  Chart.defaults.elements.arc.borderWidth = 0;
  if (Chart.defaults.datasets && Chart.defaults.datasets.doughnut) Chart.defaults.datasets.doughnut.cutout = '62%';
  Chart.defaults.scales = Chart.defaults.scales || {};
}
// Common scale styling helper applied by charts
window.__chartScales = {
  x: { grid: { display: false }, border: { display: false }, ticks: { color: '#94a3b8' } },
  y: { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { color: '#94a3b8' }, beginAtZero: true }
};
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

// ---- Toasts ----
function toast(msg, type){ var w=document.getElementById('toastWrap'); if(!w){w=document.createElement('div'); w.id='toastWrap'; w.style.cssText='position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px'; document.body.appendChild(w);} var t=document.createElement('div'); t.textContent=msg; t.style.cssText='background:'+(type==='error'?'#dc2626':'#059669')+';color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.15);max-width:340px'; w.appendChild(t); setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .4s'; setTimeout(function(){t.remove();},400);}, 4000); }

// ---- Notifications ----
var NOTIF_ADMIN = <?= is_admin() ? 'true' : 'false' ?>;
function relTime(ts){ if(!ts) return ''; var diff=Date.now()-new Date(ts).getTime(); var m=Math.floor(diff/60000); if(m<1)return 'just now'; if(m<60)return m+'m ago'; var hh=Math.floor(m/60); if(hh<24)return hh+'h ago'; return Math.floor(hh/24)+'d ago'; }
function toggleNotif(){ var p=document.getElementById('notifPanel'); if(!p)return; var open=p.classList.toggle('hidden')===false; if(open){ try{localStorage.setItem('notifLastSeen', String(Date.now()));}catch(e){} var b=document.getElementById('notifBadge'); if(b)b.classList.add('hidden'); } }
document.addEventListener('click', function(e){ var w=document.getElementById('notifWrap'); var p=document.getElementById('notifPanel'); if(w && p && !w.contains(e.target)) p.classList.add('hidden'); });
function loadNotif(){
  fetch('index.php?page=notifications-json').then(function(r){return r.ok?r.json():[];}).then(function(rows){
    if(!Array.isArray(rows)) return;
    rows.sort(function(a,b){ return new Date(b.when)-new Date(a.when); });
    var items=rows.slice(0,25);
    var list=document.getElementById('notifList'); if(list){
      if(!items.length){ list.innerHTML='<p class="px-4 py-8 text-center text-sm text-gray-400">No notifications yet</p>'; }
      else { list.innerHTML=items.map(function(n){
        var title=n.status==='approved'?('Request Approved — '+n.moduleLabel):(n.status==='rejected'?('Request Rejected — '+n.moduleLabel):('Request Pending — '+n.moduleLabel));
        var dot=n.status==='approved'?'#22c55e':(n.status==='rejected'?'#ef4444':'#f59e0b');
        var detail=NOTIF_ADMIN?(n.requestedBy+': '+n.summary):n.summary;
        return '<div class="px-4 py-3 hover:bg-gray-50"><div class="flex items-center gap-2"><span style="height:8px;width:8px;border-radius:9999px;background:'+dot+'"></span><p class="text-sm font-medium text-gray-800">'+title.replace(/</g,'&lt;')+'</p></div><p class="ml-4 text-xs text-gray-500 truncate">'+String(detail).replace(/</g,'&lt;')+'</p><p class="ml-4 text-[11px] text-gray-400 mt-0.5">'+relTime(n.when)+'</p></div>';
      }).join(''); }
    }
    var lastSeen=0; try{ lastSeen=Number(localStorage.getItem('notifLastSeen')||'0'); }catch(e){}
    var unseen=items.filter(function(n){ return new Date(n.when).getTime()>lastSeen; }).length;
    var badge=document.getElementById('notifBadge'); if(badge){ if(unseen>0){ badge.textContent=unseen>99?'99+':unseen; badge.classList.remove('hidden'); } else badge.classList.add('hidden'); }
    if(!NOTIF_ADMIN){ var seen={}; try{ seen=JSON.parse(localStorage.getItem('seenApprovalStatus')||'{}'); }catch(e){} var first=Object.keys(seen).length===0; var next={}; rows.forEach(function(r){ next[r.id]=r.status; if(!first && seen[r.id]!==r.status && r.status!=='pending'){ if(r.status==='approved') toast('This change has been approved by the Admin — '+r.moduleLabel); else toast('Your '+r.moduleLabel+' change was rejected by the Admin','error'); } }); try{ localStorage.setItem('seenApprovalStatus', JSON.stringify(next)); }catch(e){} }
  }).catch(function(){});
}
loadNotif(); setInterval(loadNotif, 30000);
</script>
<?php if (!empty($GLOBALS['__charts'])): foreach ($GLOBALS['__charts'] as $cid => $cfgJson): ?>
<script>
(function(){
  var el = document.getElementById(<?= json_encode($cid) ?>);
  if (!el) return;
  var cfg = <?= $cfgJson ?>;
  cfg.options = cfg.options || {};
  cfg.options.responsive = true;
  cfg.options.maintainAspectRatio = true;
  if ((cfg.type === 'bar' || cfg.type === 'line') && !cfg.options.scales) {
    if (cfg.options.indexAxis === 'y') {
      cfg.options.scales = { y: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b' } }, x: { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { color: '#94a3b8' }, beginAtZero: true } };
    } else {
      cfg.options.scales = window.__chartScales;
    }
  }
  new Chart(el, cfg);
})();
</script>
<?php endforeach; endif; ?>
</body>
</html>
