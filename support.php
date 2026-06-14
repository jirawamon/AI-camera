<?php
require_once __DIR__ . '/back_end/config.php';

// Support team data — แก้ไขข้อมูลจริงที่นี่
$team = [
    [
        'name' => 'นายศรัณย์ กระจ่างแก้ว',
        'nickname' => 'ชาย',
        'role' => 'หัวหน้าทีมเทคนิค',
        'phone' => '096-675-5839',
        'email' => 'saran_krac@cmu.ac.th',
        'avatar_icon' => 'engineering',
        'color' => 'primary',
    ],
    [
        'name' => 'นายภูริวัชร สุภัคกนก',
        'nickname' => 'ชาย',
        'role' => 'ผู้ออกแบบระบบ',
        'phone' => '098-952-6051',
        'email' => 'phooriwat3011@gmail.com',
        'avatar_icon' => 'support_agent',
        'color' => 'blue',
    ],

];

$colorMap = [
    'primary' => ['bg' => 'bg-primary/10 dark:bg-primary/20', 'text' => 'text-primary', 'ring' => 'ring-primary/20'],
    'blue' => ['bg' => 'bg-blue-100 dark:bg-blue-500/20', 'text' => 'text-blue-600 dark:text-blue-400', 'ring' => 'ring-blue-200'],
    'green' => ['bg' => 'bg-green-100 dark:bg-green-500/20', 'text' => 'text-green-600 dark:text-green-400', 'ring' => 'ring-green-200'],
    'orange' => ['bg' => 'bg-orange-100 dark:bg-orange-500/20', 'text' => 'text-orange-600 dark:text-orange-400', 'ring' => 'ring-orange-200'],
];
?>
<!DOCTYPE html>
<html class="light" lang="th">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
    <title>Support - สำนักงานจังหวัดเชียงใหม่</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: { primary: "#123a7d", "background-light": "#f3f8ff", "background-dark": "#081228" },
            fontFamily: { display: ["Manrope", "sans-serif"] },
            borderRadius: { DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px" },
            screens: { 'xs': '480px' },
          },
        },
      };
    </script>
    <style>
      body { font-family: "Manrope", sans-serif; -webkit-tap-highlight-color: transparent; }
      .material-symbols-outlined { font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24; }
      #sidebarOverlay { transition: opacity 0.3s ease; }
      #sidebar { transition: transform 0.3s ease; }
      #sidebar.sidebar-closed { transform: translateX(-100%); }
      @media (min-width: 1024px) {
        #sidebar { transform: translateX(0) !important; }
        #sidebarOverlay { display: none !important; }
      }
      @media (max-width: 640px) { .tap-target { min-height: 44px; min-width: 44px; } }
      .page-enter { animation: fadeInUp 0.3s ease forwards; }
      @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
      .sidebar-brand {
        border-bottom: 1px solid rgba(255,255,255,0.08);
      }
      .brand-emblem {
        width: 40px; height: 40px; border-radius: 999px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #123a7d, #00b7ff);
        border: 1.5px solid rgba(125,230,255,0.6);
        box-shadow: 0 0 14px rgba(0,183,255,0.45);
        margin-bottom: 10px;
      }
      .brand-emblem svg { width: 20px; height: 20px; fill: #e8fbff; }
      .brand-title { font-size: 12.5px; font-weight: 700; color: #fff; line-height: 1.4; }
      .brand-sub { font-size: 10.5px; color: rgba(255,255,255,0.45); margin-top: 2px; letter-spacing: 0.04em; }
      .brand-sys { font-size: 10px; color: rgba(125,230,255,0.9); margin-top: 6px; letter-spacing: 0.06em; text-transform: uppercase; border-top: 1px solid rgba(255,255,255,0.07); padding-top: 6px; }
      .sidebar-nav { padding-top: 12px; }
      .nav-section { font-size: 9.5px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.3); padding: 10px 12px 4px; }
      .nav-item {
        display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 7px;
        color: rgba(255,255,255,0.65); font-size: 13px; font-weight: 500; transition: all .2s; margin: 0 8px 2px; text-decoration: none;
      }
      .nav-item:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.95); }
      .nav-item.active {
        background: linear-gradient(90deg, rgba(54,209,255,0.28), rgba(31,94,255,0.2));
        color: #fff; border-left: 3px solid var(--meta-cyan); padding-left: 9px; box-shadow: 0 0 12px rgba(0,183,255,0.24);
      }
      .gov-topbar{
        background: #081228;
        padding: 0 28px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 2px solid #00b7ff;
      }
      .gov-topbar-left{display:flex;align-items:center;gap:16px;}
      .gov-flag{font-size:14px;}
      .gov-tag{font-size:11px;color:rgba(255,255,255,0.7);letter-spacing:0.04em;}
      .topbar{
        background: rgba(255,255,255,0.92);
        border-bottom: 1px solid #cfe0ff;
        padding: 0 20px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        backdrop-filter: blur(10px);
      }
      .topbar-left{display:flex;align-items:center;gap:12px;min-width:0;flex:1;}
      .topbar-right{display:flex;align-items:center;gap:10px;}
      .topbar-title{font-weight:700;font-size:15px;color:#081228;white-space:nowrap;}
      .topbar-meta{font-size:12px;color:#3f5d8f;white-space:nowrap;}
      .icon-btn{
        background:none;border:1px solid #cfe0ff;border-radius:7px;
        width:34px;height:34px;display:flex;align-items:center;justify-content:center;
        cursor:pointer;color:#3f5d8f;transition:all .2s;
      }
      .icon-btn:hover{background:#f3f8ff;color:#081228;border-color:#00b7ff;}
      html.dark .topbar{
        background: rgba(8,18,40,0.9);
        border-bottom-color: rgba(125,230,255,0.16);
      }
      html.dark .topbar-title{color:#eaf8ff;}
      html.dark .topbar-meta, html.dark .icon-btn{color:#9bc9ff;}
      html.dark .icon-btn{border-color:rgba(125,230,255,0.2);}
      html.dark .icon-btn:hover{background:rgba(255,255,255,0.08);color:#eaf8ff;border-color:#00b7ff;}
      /* Fix native select dropdown visibility in dark mode (Windows/Chrome/Edge) */
      html.dark select {
        color: #eaf8ff !important;
        background-color: rgba(255, 255, 255, 0.06) !important;
      }
      html.dark select option {
        color: #eaf8ff !important;
        background-color: #0b1a36 !important;
      }
      html.dark select option:checked {
        color: #ffffff !important;
        background-color: #123a7d !important;
      }
      /* Fix native date picker popup in dark mode */
      html.dark {
        color-scheme: dark;
      }
      html.dark input[type="date"] {
        color-scheme: dark;
      }
      :root {
        --meta-cyan: #00b7ff;
        --meta-glow: #7de6ff;
      }
      body {
        background:
          radial-gradient(1200px 650px at 92% -10%, rgba(0, 183, 255, 0.16), transparent 60%),
          radial-gradient(900px 560px at -8% 108%, rgba(31, 94, 255, 0.12), transparent 58%),
          #f3f8ff;
      }
      html.dark body {
        background:
          radial-gradient(1200px 650px at 92% -10%, rgba(0, 183, 255, 0.12), transparent 60%),
          radial-gradient(900px 560px at -8% 108%, rgba(31, 94, 255, 0.1), transparent 58%),
          #081228;
      }
      #sidebar {
        background: linear-gradient(180deg, #061127 0%, #0a1e44 45%, #08224a 100%) !important;
        border-right-color: rgba(0, 183, 255, 0.26) !important;
        box-shadow: 0 0 0 1px rgba(125, 230, 255, 0.08) inset;
      }
      #sidebar > div:first-child {
        background: linear-gradient(135deg, rgba(18, 58, 125, 0.86), rgba(8, 18, 40, 0.92));
      }
      #sidebar a.bg-white\/10 {
        background: linear-gradient(90deg, rgba(54, 209, 255, 0.28), rgba(31, 94, 255, 0.2)) !important;
        border-left: 3px solid var(--meta-cyan);
        box-shadow: 0 0 12px rgba(0, 183, 255, 0.24);
      }
      #sidebar .bg-white\/10.rounded-lg {
        background: linear-gradient(135deg, #123a7d, #00b7ff) !important;
        box-shadow: 0 0 14px rgba(0, 183, 255, 0.45);
      }
      main { background: transparent !important; }
      .gov-strip {
        background: linear-gradient(90deg, #081228, #123a7d);
        border: 1px solid rgba(0, 183, 255, 0.25);
        color: rgba(255, 255, 255, 0.86);
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 11px;
        letter-spacing: 0.04em;
      }
      main .bg-white {
        background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%) !important;
      }
      html.dark main .bg-white {
        background: linear-gradient(180deg, rgba(12, 24, 52, 0.94) 0%, rgba(8, 18, 40, 0.94) 100%) !important;
        border-color: rgba(125, 230, 255, 0.16) !important;
      }
    </style>
  </head>
  <body class="bg-background-light dark:bg-background-dark text-[#121617] dark:text-white font-display">
    <div class="flex h-[100dvh] overflow-hidden">
      <!-- Sidebar overlay -->
      <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 opacity-0 pointer-events-none lg:hidden"></div>

      <!-- Sidebar -->
      <aside id="sidebar" class="sidebar-closed lg:sidebar-open fixed lg:relative z-50 lg:z-auto w-72 sm:w-72 lg:w-20 xl:w-64 h-full text-white flex flex-col shrink-0 border-r border-primary/10">
        <div class="sidebar-brand p-4 xl:p-6">
          <div class="brand-emblem">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
          </div>
          <div class="brand-title">สำนักงานจังหวัดเชียงใหม่</div>
          <div class="brand-sub">Provincial Office · Chiang Mai</div>
          <div class="brand-sys">ระบบ CCTV Webhook · 5G Metaverse</div>
          <button onclick="toggleSidebar()" class="mt-2 p-1 rounded-lg hover:bg-white/10 lg:hidden">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <nav class="sidebar-nav flex-1 overflow-y-auto">
          <div class="nav-section">Menu</div>
          <a class="nav-item" href="index.php" title="Dashboard">
            <span class="material-symbols-outlined">dashboard</span> Dashboard
          </a>
          <a class="nav-item" href="equipment.php" title="Equipment">
            <span class="material-symbols-outlined">videocam</span> Equipment
          </a>
           <a class="nav-item" href="map.php" title="Map">
             <span class="material-symbols-outlined">map</span> Map
           </a>
          <div class="nav-section">Preferences</div>
          <a class="nav-item active" href="support.php" title="Support">
            <span class="material-symbols-outlined">help</span> Support
          </a>
        </nav>
        <div class="p-4 border-t border-white/10">
          <button onclick="location.href='index.php'" class="w-full bg-white text-primary font-bold py-2.5 rounded-lg flex items-center justify-center gap-2 text-sm hover:bg-gray-100 transition-colors tap-target">
            <span class="material-symbols-outlined text-sm">dashboard</span>
            <span class="lg:hidden xl:block">Go to Dashboard</span>
          </button>
        </div>
      </aside>

      <!-- Main Content -->
      <main class="flex-1 flex flex-col overflow-y-auto bg-background-light dark:bg-background-dark w-full">
        <div class="w-full max-w-[1600px] mx-auto flex flex-col flex-1 page-enter">
          <div class="gov-topbar">
            <div class="gov-topbar-left">
              <span class="gov-flag">🇹🇭</span>
              <span class="gov-tag">เว็บไซต์อย่างเป็นทางการของสำนักงานจังหวัดเชียงใหม่ · ศูนย์ปฏิบัติการดิจิทัล 5G</span>
            </div>
          </div>
          <div class="topbar">
            <div class="topbar-left">
              <button onclick="toggleSidebar()" class="icon-btn lg:hidden tap-target shrink-0">
                <span class="material-symbols-outlined">menu</span>
              </button>
              <span class="topbar-title">Support</span>
            </div>
            <div class="topbar-right">
              <span class="topbar-meta hidden xl:inline">อัปเดต: <?= date('d/m/Y H:i') ?></span>
              <button onclick="document.documentElement.classList.toggle('dark')" class="icon-btn tap-target">
                <span class="material-symbols-outlined text-lg">dark_mode</span>
              </button>
              <a href="support.php" class="icon-btn tap-target">
                <span class="material-symbols-outlined text-lg">refresh</span>
              </a>
            </div>
          </div>
          <div class="p-4 sm:p-6 lg:p-8">

          <!-- Quick Contact Banner -->
          <div class="bg-primary rounded-2xl p-5 sm:p-8 mb-6 sm:mb-8 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
            <div class="relative z-10">
              <div class="flex items-center gap-3 mb-3">
                <span class="material-symbols-outlined text-3xl sm:text-4xl">headset_mic</span>
                <div>
                  <h3 class="text-lg sm:text-xl font-extrabold">ต้องการความช่วยเหลือ?</h3>
                  <p class="text-white/70 text-xs sm:text-sm font-medium">ติดต่อทีมสนับสนุนได้ตลอดเวลาทำการ</p>
                </div>
              </div>
              <div class="flex flex-wrap gap-3 sm:gap-4 mt-4">
                <a href="tel:0812345678" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 rounded-xl px-4 py-2.5 text-sm font-bold transition-colors">
                  <span class="material-symbols-outlined text-lg">call</span> โทรหาเรา
                </a>
                <a href="mailto:saran_krac@cmu.ac.th" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 rounded-xl px-4 py-2.5 text-sm font-bold transition-colors">
                  <span class="material-symbols-outlined text-lg">mail</span> ส่งอีเมล
                </a>
              </div>
            </div>
          </div>

          <!-- Team Cards -->
          <h3 class="text-base sm:text-lg font-extrabold mb-4 sm:mb-5">ทีมสนับสนุน</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-8">
            <?php foreach ($team as $person): 
              $c = $colorMap[$person['color']];
            ?>
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-md hover:border-primary/20 transition-all group">
              <!-- Avatar + Name -->
              <div class="flex items-center gap-4 mb-4">
                <div class="size-14 sm:size-16 rounded-2xl <?= $c['bg'] ?> flex items-center justify-center ring-4 <?= $c['ring'] ?> dark:ring-white/5 shrink-0 group-hover:scale-105 transition-transform">
                  <span class="material-symbols-outlined <?= $c['text'] ?> text-2xl sm:text-3xl"><?= $person['avatar_icon'] ?></span>
                </div>
                <div class="min-w-0">
                  <h4 class="text-sm sm:text-base font-extrabold truncate"><?= htmlspecialchars($person['name']) ?></h4>
                  <p class="text-xs text-[#687d82] dark:text-white/40 font-medium">ชื่อเล่น: <span class="font-bold <?= $c['text'] ?>"><?= htmlspecialchars($person['nickname']) ?></span></p>
                  <p class="text-[10px] sm:text-[11px] text-[#687d82] dark:text-white/40 font-medium mt-0.5"><?= htmlspecialchars($person['role']) ?></p>
                </div>
              </div>
              <!-- Contact Info -->
              <div class="space-y-2.5">
                <a href="tel:<?= str_replace('-', '', $person['phone']) ?>" class="flex items-center gap-3 p-2.5 rounded-xl bg-background-light dark:bg-white/5 hover:bg-primary/5 dark:hover:bg-primary/10 transition-colors group/link">
                  <div class="size-9 rounded-lg bg-green-100 dark:bg-green-500/20 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-lg">call</span>
                  </div>
                  <div class="min-w-0">
                    <p class="text-[10px] text-[#687d82] dark:text-white/40 font-bold uppercase">โทรศัพท์</p>
                    <p class="text-xs sm:text-sm font-bold truncate group-hover/link:text-primary transition-colors"><?= htmlspecialchars($person['phone']) ?></p>
                  </div>
                  <span class="material-symbols-outlined text-[#687d82]/30 text-lg ml-auto shrink-0 group-hover/link:text-primary transition-colors">arrow_forward</span>
                </a>
                <a href="mailto:<?= htmlspecialchars($person['email']) ?>" class="flex items-center gap-3 p-2.5 rounded-xl bg-background-light dark:bg-white/5 hover:bg-primary/5 dark:hover:bg-primary/10 transition-colors group/link">
                  <div class="size-9 rounded-lg bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-lg">mail</span>
                  </div>
                  <div class="min-w-0">
                    <p class="text-[10px] text-[#687d82] dark:text-white/40 font-bold uppercase">อีเมล</p>
                    <p class="text-xs sm:text-sm font-bold truncate group-hover/link:text-primary transition-colors"><?= htmlspecialchars($person['email']) ?></p>
                  </div>
                  <span class="material-symbols-outlined text-[#687d82]/30 text-lg ml-auto shrink-0 group-hover/link:text-primary transition-colors">arrow_forward</span>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Office Info -->
          <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-2xl p-5 sm:p-8 shadow-sm">
            <h3 class="text-base sm:text-lg font-extrabold mb-4 flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-xl">location_on</span>
              สำนักงาน
            </h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
              <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-primary/10 dark:bg-primary/20 flex items-center justify-center shrink-0 mt-0.5">
                  <span class="material-symbols-outlined text-primary">apartment</span>
                </div>
                <div>
                  <p class="text-[10px] text-[#687d82] dark:text-white/40 font-bold uppercase mb-1">ที่อยู่</p>
                  <p class="text-xs sm:text-sm font-medium leading-relaxed">มหาวิทยาลัยเชียงใหม่<br>College of Art Media and Technology<br>Digital Industry Integration - DII</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-primary/10 dark:bg-primary/20 flex items-center justify-center shrink-0 mt-0.5">
                  <span class="material-symbols-outlined text-primary">schedule</span>
                </div>
                <div>
                  <p class="text-[10px] text-[#687d82] dark:text-white/40 font-bold uppercase mb-1">เวลาทำการ</p>
                  <p class="text-xs sm:text-sm font-medium leading-relaxed">ทุกวัน<br>08:30 - 16:30 น.</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-primary/10 dark:bg-primary/20 flex items-center justify-center shrink-0 mt-0.5">
                  <span class="material-symbols-outlined text-primary">call</span>
                </div>
                <div>
                  <p class="text-[10px] text-[#687d82] dark:text-white/40 font-bold uppercase mb-1">ติดต่อสำนักงาน</p>
                  <p class="text-xs sm:text-sm font-medium leading-relaxed">โทร: 096-675-5839<br>Email: saran_krac@cmu.ac.th</p>
                </div>
              </div>
            </div>
          </div>

          </div>
        </div>
      </main>
    </div>

    <script>
      function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('sidebar-closed');
        if (sidebar.classList.contains('sidebar-closed')) {
          overlay.classList.add('opacity-0', 'pointer-events-none');
          overlay.classList.remove('opacity-100', 'pointer-events-auto');
          document.body.style.overflow = '';
        } else {
          overlay.classList.remove('opacity-0', 'pointer-events-none');
          overlay.classList.add('opacity-100', 'pointer-events-auto');
          document.body.style.overflow = 'hidden';
        }
      }
      document.addEventListener('click', function(e) {
        if (e.target.closest('a') && window.innerWidth < 1024) {
          const sidebar = document.getElementById('sidebar');
          if (!sidebar.classList.contains('sidebar-closed')) toggleSidebar();
        }
      });
      window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
          const overlay = document.getElementById('sidebarOverlay');
          overlay.classList.add('opacity-0', 'pointer-events-none');
          overlay.classList.remove('opacity-100', 'pointer-events-auto');
          document.body.style.overflow = '';
        }
      });
      let touchStartX = 0;
      document.addEventListener('touchstart', function(e) { touchStartX = e.touches[0].clientX; }, { passive: true });
      document.addEventListener('touchend', function(e) {
        const diff = touchStartX - e.changedTouches[0].clientX;
        const sidebar = document.getElementById('sidebar');
        if (diff > 80 && !sidebar.classList.contains('sidebar-closed') && window.innerWidth < 1024) toggleSidebar();
        if (diff < -80 && touchStartX < 30 && sidebar.classList.contains('sidebar-closed') && window.innerWidth < 1024) toggleSidebar();
      }, { passive: true });
    </script>
  </body>
</html>
