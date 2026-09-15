<?php
/** Shared page shell: <head>, sidebar, header. Expects $title and $page. */
$u = current_user();
$role = current_role();
$theme = role_theme($role);
$currentSlug = $_GET['page'] ?? 'dashboard';
$flashes = take_flash();
$pending = is_admin() ? pending_approvals_count() : 0;

function nav_icon(string $key): string {
    // compact inline SVGs (Heroicons outline), fallback = document icon
    $p = [
        'dashboard' => 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10',
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z',
        'patients' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z',
        'appointments' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    ];
    $d = $p[$key] ?? 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    return '<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'Maji Hearing Aids') ?> — Maji Hearing Aids</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="assets/logo.jpg">
<style>[x-cloak]{display:none}</style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen overflow-hidden">
  <!-- Sidebar -->
  <aside id="sidebar" class="fixed lg:static z-50 h-full w-64 flex-col <?= $theme['sidebar'] ?> text-white -translate-x-full lg:translate-x-0 transition-transform flex">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
      <img src="assets/logo.jpg" class="h-9 w-9 rounded-lg object-cover" alt="logo" onerror="this.style.display='none'">
      <div>
        <h1 class="text-sm font-bold leading-tight">Clinic Mgmt</h1>
        <p class="text-[10px] opacity-60">Maji Hearing Aids</p>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
      <?php foreach (nav_items() as $item): $active = $item['slug'] === $currentSlug; ?>
        <a href="index.php?page=<?= h($item['slug']) ?>"
           class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                  <?= $active ? $theme['active'] . ' text-white shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' ?>">
          <?= nav_icon($item['key']) ?>
          <span class="truncate"><?= h($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="border-t border-white/10 px-4 py-4">
      <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-semibold">
          <?= h(strtoupper(substr($u['name'] ?? 'U', 0, 1))) ?>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium"><?= h($u['name'] ?? 'User') ?></p>
          <span class="inline-block mt-0.5 rounded-full px-2 py-0.5 text-[10px] font-medium <?= $theme['badge'] ?>"><?= h(role_label($role)) ?></span>
        </div>
      </div>
      <a href="index.php?page=logout" class="mt-3 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm opacity-70 hover:bg-white/10 hover:opacity-100">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>
  <div id="backdrop" onclick="toggleSidebar()" class="fixed inset-0 z-40 bg-black/50 hidden lg:hidden"></div>

  <!-- Main -->
  <div class="flex flex-1 flex-col overflow-hidden">
    <header class="sticky top-0 z-30 <?= $theme['header'] ?> text-white shadow-md">
      <div class="flex h-16 items-center justify-between px-4 lg:px-6">
        <div class="flex items-center gap-3">
          <button onclick="toggleSidebar()" class="lg:hidden rounded-lg p-2 hover:bg-white/10">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <h1 class="text-lg font-semibold truncate"><?= h($title ?? 'Dashboard') ?></h1>
        </div>
        <div class="flex items-center gap-3">
          <?php if (is_admin()): ?>
          <a href="index.php?page=requests" class="relative rounded-lg p-2 hover:bg-white/10" title="Approval requests">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"/></svg>
            <?php if ($pending > 0): ?><span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold"><?= $pending > 99 ? '99+' : $pending ?></span><?php endif; ?>
          </a>
          <?php endif; ?>
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-xs font-semibold"><?= h(strtoupper(substr($u['name'] ?? 'U', 0, 2))) ?></div>
        </div>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-4 lg:p-6">
      <?php foreach ($flashes as $f): ?>
        <div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium <?= $f['type'] === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>"><?= h($f['msg']) ?></div>
      <?php endforeach; ?>
