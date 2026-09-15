<?php
$title = 'User Management';

$ASSIGNABLE = [];
foreach (ALL_NAV as $key => $item) { if (!in_array($key, ADMIN_ONLY_KEYS, true)) $ASSIGNABLE[$key] = $item[0]; }
$DEFAULT_ROLES = ['admin','doctor','staff','marketing'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = trim($_POST['id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || $email === '') { set_flash('error', 'Name and email are required'); redirect('index.php?page=users'); }
        $modules = implode(',', array_map('trim', (array)($_POST['modules'] ?? [])));
        $role = trim($_POST['role'] ?? 'staff');
        if ($id === '') {
            $pw = $_POST['password'] ?? '';
            if ($pw === '') { set_flash('error', 'Password is required for new users'); redirect('index.php?page=users'); }
            entity_insert('users', ['name'=>$name,'email'=>$email,'password'=>password_hash($pw, PASSWORD_BCRYPT),'role'=>$role,'branch'=>$_POST['branch']??'','phone'=>$_POST['phone']??'','active'=>'true','createdAt'=>date('c'),'modules'=>$modules]);
            set_flash('success', 'User created');
        } else {
            $existing = entity_find('users', $id);
            $pw = $_POST['password'] ?? '';
            $hash = $pw !== '' ? password_hash($pw, PASSWORD_BCRYPT) : ($existing['password'] ?? '');
            entity_update('users', ['id'=>$id,'name'=>$name,'email'=>$email,'password'=>$hash,'role'=>$role,'branch'=>$_POST['branch']??'','phone'=>$_POST['phone']??'','active'=>(isset($_POST['active'])?'true':'false'),'createdAt'=>$existing['createdAt']??'','modules'=>$modules]);
            set_flash('success', 'User updated');
        }
        redirect('index.php?page=users');
    }
    if ($action === 'toggle') {
        $u = entity_find('users', $_POST['id'] ?? '');
        if ($u && $u['role'] !== 'admin') { $u['active'] = strtolower($u['active']) === 'true' ? 'false' : 'true'; entity_update('users', $u); set_flash('success', 'User updated'); }
        redirect('index.php?page=users');
    }
}

$users = entity_all('users');
// Split the CSV modules field into an array for the edit modal JSON.
foreach ($users as &$_u) { $_u['modules'] = array_values(array_filter(array_map('trim', explode(',', (string)$_u['modules'])))); }
unset($_u);
$q = trim($_GET['q'] ?? '');
if ($q !== '') $users = array_values(array_filter($users, fn($u) => stripos($u['name'],$q)!==false || stripos($u['email'],$q)!==false));
$customRoles = array_values(array_unique(array_filter(array_map(fn($u)=>$u['role'], entity_all('users')), fn($r)=>$r && !in_array($r,$DEFAULT_ROLES,true))));
$roleColors = ['admin'=>'bg-indigo-100 text-indigo-700','doctor'=>'bg-teal-100 text-teal-700','staff'=>'bg-blue-100 text-blue-700','marketing'=>'bg-amber-100 text-amber-700'];
$users = paginate($users);

require __DIR__ . '/../partials/top.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
  <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
  <button onclick="openNew()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">+ Add User</button>
</div>
<form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3">
  <input type="hidden" name="page" value="users">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by name or email..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
  <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium hover:bg-gray-200">Search</button>
</form>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
  <thead class="bg-gray-50"><tr><?php foreach (['Name','Email','Role','Branch','Phone','Status','Actions'] as $htxt): ?><th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $htxt ?></th><?php endforeach; ?></tr></thead>
  <tbody class="divide-y divide-gray-200">
  <?php foreach ($users as $u): $active = strtolower($u['active']) === 'true'; ?>
    <tr class="hover:bg-gray-50">
      <td class="px-4 py-3 font-medium text-gray-900 cursor-pointer" onclick='openEdit(<?= h(json_encode($u)) ?>)'><?= h($u['name']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($u['email']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize <?= $roleColors[$u['role']] ?? 'bg-gray-100 text-gray-700' ?>"><?= h($u['role']) ?></span></td>
      <td class="px-4 py-3 text-gray-600"><?= h($u['branch']) ?></td>
      <td class="px-4 py-3 text-gray-600"><?= h($u['phone']) ?></td>
      <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium <?= $active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td>
      <td class="px-4 py-3 flex gap-3">
        <button onclick='openEdit(<?= h(json_encode($u)) ?>)' class="text-indigo-600 hover:underline text-xs">Edit</button>
        <?php if ($u['role'] !== 'admin'): ?><form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= h($u['id']) ?>"><button class="text-xs <?= $active ? 'text-red-600' : 'text-green-600' ?> hover:underline"><?= $active ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?= render_pagination() ?>

<div id="modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-4">Add User</h3>
    <form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Name *</label><input type="text" name="name" id="f_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Email *</label><input type="email" name="email" id="f_email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Password <span id="pwHint" class="text-gray-400"></span></label><input type="password" name="password" id="f_password" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Role</label><input type="text" name="role" id="f_role" list="roleOpts" placeholder="Type or select (e.g. Audiologist)" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><datalist id="roleOpts"><?php foreach (array_merge($DEFAULT_ROLES,$customRoles) as $r): ?><option value="<?= h($r) ?>"><?php endforeach; ?></datalist></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Branch</label><select name="branch" id="f_branch" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?php foreach (active_branch_names() as $b): ?><option value="<?= h($b) ?>"><?= h($b) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Phone</label><input type="tel" name="phone" id="f_phone" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></div>
        <div class="md:col-span-2"><label class="flex items-center gap-2"><input type="checkbox" name="active" id="f_active" checked class="rounded border-gray-300 text-indigo-600"> <span class="text-sm text-gray-700">Active</span></label></div>
      </div>
      <div class="mt-5 border-t border-gray-200 pt-4">
        <label class="block text-sm font-semibold text-gray-800 mb-1">Panel Access</label>
        <p class="text-xs text-gray-400 mb-2">Requests &amp; Reports remain Admin-only.</p>
        <div id="modWrap" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <?php foreach ($ASSIGNABLE as $key => $label): ?>
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50"><input type="checkbox" name="modules[]" value="<?= h($key) ?>" class="mod rounded border-gray-300 text-indigo-600"> <span class="text-sm text-gray-700"><?= h($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3"><button type="button" onclick="closeModal('modal')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button></div>
    </form>
  </div>
</div>
<script>
var ROLE_DEFAULTS = {
  admin: <?= json_encode(array_keys($ASSIGNABLE)) ?>,
  doctor: <?= json_encode(ROLE_NAV['doctor']) ?>,
  staff: <?= json_encode(ROLE_NAV['staff']) ?>,
  marketing: <?= json_encode(ROLE_NAV['marketing']) ?>
};
function setF(id,v){var e=document.getElementById(id); if(e)e.value=v||'';}
function setMods(list){ document.querySelectorAll('.mod').forEach(cb=>cb.checked=(list||[]).indexOf(cb.value)>=0); }
document.getElementById('f_role').addEventListener('input',function(){ var d=ROLE_DEFAULTS[this.value.trim()]; if(d) setMods(d); });
function openNew(){ document.getElementById('modalTitle').textContent='Add User'; ['f_id','f_name','f_email','f_password','f_phone'].forEach(i=>setF(i,'')); setF('f_role','staff'); document.getElementById('f_active').checked=true; document.getElementById('pwHint').textContent='*'; setMods(ROLE_DEFAULTS.staff); openModal('modal'); }
function openEdit(u){ document.getElementById('modalTitle').textContent='Edit User'; setF('f_id',u.id); setF('f_name',u.name); setF('f_email',u.email); setF('f_password',''); setF('f_role',u.role); setF('f_branch',u.branch); setF('f_phone',u.phone); document.getElementById('f_active').checked=(String(u.active).toLowerCase()==='true'); document.getElementById('pwHint').textContent='(leave blank to keep)'; var mods=(u.modules&&u.modules.length)?u.modules:(ROLE_DEFAULTS[u.role]||[]); setMods(mods); openModal('modal'); }
</script>
<?php require __DIR__ . '/../partials/bottom.php';
