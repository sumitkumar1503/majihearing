<?php
if (is_logged_in()) redirect('index.php?page=dashboard');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Email and password are required';
    } elseif (attempt_login($email, $password)) {
        redirect('index.php?page=dashboard');
    } else {
        $error = 'Invalid email or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In — Maji Hearing Aids Centre</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="assets/logo.jpg">
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
  <!-- Navbar (same as homepage) -->
  <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
      <a href="index.php?page=home" class="flex items-center gap-3">
        <img src="assets/logo.jpg" alt="Maji Hearing Aids Centre" class="h-10 w-10 rounded-xl object-cover" onerror="this.style.display='none'">
        <div><p class="font-bold text-gray-900 text-sm sm:text-base leading-tight">Maji Hearing Aids Centre</p><p class="text-[10px] sm:text-xs text-gray-500 -mt-0.5">Since 1999 | Your Hearing, Our Priority</p></div>
      </a>
      <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
        <a href="index.php?page=home#services" class="hover:text-indigo-600 transition-colors">Services</a>
        <a href="index.php?page=home#locations" class="hover:text-indigo-600 transition-colors">Locations</a>
        <a href="index.php?page=home#contact" class="hover:text-indigo-600 transition-colors">Contact</a>
      </div>
      <a href="index.php?page=login" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Login</a>
    </div>
  </nav>

  <div class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <img src="assets/logo.jpg" alt="Maji Hearing Aids Centre" class="h-16 w-16 rounded-xl object-cover mx-auto" onerror="this.style.display='none'">
        <h2 class="mt-4 text-2xl font-bold text-gray-900">Welcome back</h2>
        <p class="mt-1 text-sm text-gray-500">Sign in to your account</p>
      </div>
      <div class="rounded-2xl bg-white p-8 shadow-xl shadow-gray-200/50 border border-gray-100">
        <?php if ($error): ?>
          <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-600 flex items-center gap-2">
            <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
            <?= h($error) ?>
          </div>
        <?php endif; ?>
        <form method="post" action="index.php?page=login" class="space-y-5">
          <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg></div>
              <input id="email" type="email" name="email" required placeholder="you@clinic.com" class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-colors">
            </div>
          </div>
          <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">Password</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg></div>
              <input id="password" type="password" name="password" required placeholder="••••••••" class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-colors">
            </div>
          </div>
          <button type="submit" class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 text-sm transition-colors">Sign In</button>
        </form>
      </div>
      <p class="mt-6 text-center text-xs text-gray-400">© <?= date('Y') ?> Maji Hearing Aids Centre</p>
    </div>
  </div>
</body>
</html>
