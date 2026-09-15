<?php $loggedIn = is_logged_in(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maji Hearing Aids Centre — Hear the World Better</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="assets/logo.jpg">
</head>
<body class="min-h-screen bg-white">
  <!-- Navbar -->
  <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
      <a href="index.php?page=home" class="flex items-center gap-3">
        <img src="assets/logo.jpg" alt="Maji Hearing Aids Centre" class="h-10 w-10 rounded-xl object-cover" onerror="this.style.display='none'">
        <div><p class="font-bold text-gray-900 text-sm sm:text-base leading-tight">Maji Hearing Aids Centre</p><p class="text-[10px] sm:text-xs text-gray-500 -mt-0.5">Since 1999 | Your Hearing, Our Priority</p></div>
      </a>
      <div class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
        <a href="#services" class="hover:text-indigo-600 transition-colors">Services</a>
        <a href="#locations" class="hover:text-indigo-600 transition-colors">Locations</a>
        <a href="#contact" class="hover:text-indigo-600 transition-colors">Contact</a>
      </div>
      <a href="<?= $loggedIn ? 'index.php?page=dashboard' : 'index.php?page=login' ?>" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"><?= $loggedIn ? 'Dashboard' : 'Login' ?></a>
    </div>
  </nav>

  <!-- Hero -->
  <section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 text-white">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"><div class="w-[250px] h-[250px] sm:w-[400px] sm:h-[400px] lg:w-[550px] lg:h-[550px] rounded-full border-2 border-white/15 animate-ping" style="animation-duration:3s"></div></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"><div class="w-[350px] h-[350px] sm:w-[550px] sm:h-[550px] lg:w-[750px] lg:h-[750px] rounded-full border-2 border-white/10 animate-ping" style="animation-duration:3s;animation-delay:.5s"></div></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"><div class="w-[450px] h-[450px] sm:w-[700px] sm:h-[700px] lg:w-[950px] lg:h-[950px] rounded-full border border-white/[0.06] animate-ping" style="animation-duration:3s;animation-delay:1s"></div></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center"><div class="relative"><div class="absolute inset-0 bg-white/10 rounded-full blur-2xl scale-150"></div><span class="relative block text-[7rem] sm:text-[9rem] lg:text-[11rem] leading-none opacity-90 animate-pulse select-none drop-shadow-2xl" style="animation-duration:2s">🦻</span></div></div>
      <div class="absolute top-10 right-10 w-40 h-40 sm:w-56 sm:h-56 bg-pink-500/25 rounded-full blur-3xl animate-pulse"></div>
      <div class="absolute bottom-10 left-10 w-48 h-48 sm:w-64 sm:h-64 bg-cyan-400/20 rounded-full blur-3xl animate-pulse" style="animation-delay:1.5s"></div>
      <div class="absolute top-1/3 left-1/4 w-28 h-28 bg-yellow-300/15 rounded-full blur-2xl animate-bounce" style="animation-duration:4s"></div>
      <div class="absolute bottom-1/3 right-1/4 w-24 h-24 bg-purple-400/15 rounded-full blur-2xl animate-bounce" style="animation-duration:5s;animation-delay:2s"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 lg:py-40 text-center">
      <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 px-4 py-1.5 text-sm font-medium mb-6">
        <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-400"></span></span>
        Serampore &amp; Konnagar
      </div>
      <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">Hear the World Better</h1>
      <p class="mt-4 text-lg text-white/70 max-w-xl mx-auto">Expert audiological care and hearing aid solutions.</p>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="tel:+917980344897" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-all shadow-lg hover:-translate-y-0.5">📞 Book Appointment</a>
        <a href="#locations" class="inline-flex items-center gap-2 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 px-6 py-3 text-sm font-semibold text-white hover:bg-white/20 transition-all hover:-translate-y-0.5">📍 Our Locations</a>
      </div>
      <div class="mt-14 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-2xl mx-auto">
        <?php foreach ([['10+','Years'],['5000+','Patients'],['15+','Brands'],['2','Centers']] as $s): ?>
          <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-3 border border-white/10"><p class="text-2xl font-bold"><?= $s[0] ?></p><p class="text-xs text-white/60"><?= $s[1] ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
    <svg class="absolute bottom-0 w-full" viewBox="0 0 1440 60" preserveAspectRatio="none"><path d="M0,30 C360,60 1080,0 1440,30 L1440,60 L0,60 Z" fill="white"/></svg>
  </section>

  <!-- Services -->
  <section id="services" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider mb-2 text-center">Services</p>
      <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">What We Offer</h2>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <?php foreach ([['🔊','Audiometry','PTA, impedance & OAE testing','from-blue-500 to-indigo-600'],['🦻','Hearing Aids','Fitting from top brands','from-purple-500 to-pink-600'],['🔧','Repairs','Quick device servicing','from-amber-500 to-orange-600'],['🗣️','Speech Therapy','Sessions for all ages','from-teal-500 to-emerald-600']] as $s): ?>
          <div class="group relative rounded-2xl overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br <?= $s[3] ?> opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="relative p-5 sm:p-6 border border-gray-100 rounded-2xl group-hover:border-transparent transition-colors text-center">
              <span class="text-3xl sm:text-4xl block mb-3 group-hover:scale-110 transition-transform duration-300"><?= $s[0] ?></span>
              <h3 class="font-semibold text-gray-900 group-hover:text-white transition-colors"><?= $s[1] ?></h3>
              <p class="mt-1 text-xs sm:text-sm text-gray-500 group-hover:text-white/80 transition-colors"><?= $s[2] ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Locations -->
  <section id="locations" class="py-20 bg-gradient-to-b from-gray-50 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider mb-2 text-center">Locations</p>
      <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">Visit Us</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
        <?php foreach ([['Serampore','Dr. G. C. Goswami Street, Near Station','+91 79803 44897','from-indigo-600 to-purple-600'],['Konnagar','19A, S K Deb Street, Konnagar, Hooghly','+91 74396 63366','from-purple-600 to-pink-600']] as $b): ?>
          <div class="rounded-2xl border border-gray-100 overflow-hidden hover:shadow-xl transition-shadow">
            <div class="bg-gradient-to-r <?= $b[3] ?> px-6 py-4"><h3 class="text-lg font-bold text-white flex items-center gap-2">📍 <?= $b[0] ?></h3></div>
            <div class="p-5 space-y-3 bg-white"><p class="text-sm text-gray-600"><?= $b[1] ?></p><a href="tel:<?= str_replace(' ','',$b[2]) ?>" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700">📞 <?= $b[2] ?></a><p class="text-xs text-gray-400">Mon–Sat, 10 AM – 7 PM</p></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-16"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="rounded-3xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 px-8 sm:px-14 py-12 text-center text-white relative overflow-hidden">
      <div class="absolute inset-0 pointer-events-none"><div class="absolute top-4 left-8 w-24 h-24 bg-white/10 rounded-full blur-2xl"></div><div class="absolute bottom-4 right-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div></div>
      <div class="relative"><h2 class="text-2xl sm:text-3xl font-bold">Having Trouble Hearing?</h2><p class="mt-2 text-white/70">Book a free consultation today.</p><a href="tel:+917980344897" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-8 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors shadow-lg">📞 Call Now</a></div>
    </div>
  </div></section>

  <!-- Footer -->
  <footer id="contact" class="py-8 bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-3"><img src="assets/logo.jpg" alt="Logo" class="h-8 w-8 rounded-lg object-cover" onerror="this.style.display='none'"><div><p class="font-bold text-sm">Maji Hearing Aids Centre</p><p class="text-xs text-gray-400">Your Hearing, Our Priority</p></div></div>
      <div class="flex items-center gap-6 text-sm text-gray-400"><a href="tel:+917980344897" class="hover:text-white transition-colors">📞 79803 44897</a><a href="tel:+917439663366" class="hover:text-white transition-colors">📞 74396 63366</a></div>
      <p class="text-xs text-gray-500">© <?= date('Y') ?> All rights reserved.</p>
    </div>
  </footer>
</body>
</html>
