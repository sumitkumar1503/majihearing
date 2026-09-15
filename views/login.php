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
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In — Maji Hearing Aids</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-600 to-purple-700 p-4">
  <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
    <div class="text-center mb-6">
      <img src="assets/logo.jpg" class="mx-auto h-14 w-14 rounded-xl object-cover" alt="logo" onerror="this.style.display='none'">
      <h1 class="mt-3 text-xl font-bold text-gray-900">Maji Hearing Aids Centre</h1>
      <p class="text-sm text-gray-500">Clinic Management — Sign in</p>
    </div>
    <?php if ($error): ?>
      <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" action="index.php?page=login" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
      <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">Sign In</button>
    </form>
  </div>
</body>
</html>
