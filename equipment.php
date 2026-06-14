<?php

require_once __DIR__ . '/back_end/config.php';

$db = getDB();
$db->exec("SET time_zone = '+07:00'");

// Get parameters
$page = max(1, intval($_GET['page'] ?? 1));
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$sortBy = $_GET['sort'] ?? 'last_event';
$sortDir = $_GET['dir'] ?? 'DESC';

// Validate sort
$allowedSorts = ['equipment_id', 'installation_location', 'event_count', 'last_event', 'first_event'];
if (!in_array($sortBy, $allowedSorts)) $sortBy = 'last_event';
if (!in_array(strtoupper($sortDir), ['ASC', 'DESC'])) $sortDir = 'DESC';

$perPage = 20;
$offset = ($page - 1) * $perPage;

// ============================================================
// Stats: overall equipment summary
// ============================================================
$stats = $db->query("
    SELECT 
        COUNT(DISTINCT equipment_id) AS total_equipment,
        COUNT(DISTINCT CASE WHEN DATE(received_at) = CURDATE() THEN equipment_id END) AS active_today,
        COUNT(DISTINCT CASE WHEN received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN equipment_id END) AS active_week,
        COUNT(DISTINCT installation_location) AS total_locations
    FROM webhook_events
    WHERE equipment_id IS NOT NULL
")->fetch();

// ============================================================
// Equipment list with aggregated data
// ============================================================
$havingClauses = [];
$whereSearch = "";
$searchParams = [];

if ($search) {
    $whereSearch = "AND (equipment_id LIKE :search OR installation_location LIKE :search2 OR equipment_name LIKE :search3)";
    $searchParams[':search'] = "%$search%";
    $searchParams[':search2'] = "%$search%";
    $searchParams[':search3'] = "%$search%";
}
if ($locationFilter) {
    $whereSearch .= " AND installation_location = :loc_filter";
    $searchParams[':loc_filter'] = $locationFilter;
}

// Status filter applied as HAVING
$statusHaving = "";
if ($statusFilter === 'online') {
    $statusHaving = "HAVING last_event >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
} elseif ($statusFilter === 'today') {
    $statusHaving = "HAVING last_event >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND last_event < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
} elseif ($statusFilter === 'week') {
    $statusHaving = "HAVING last_event >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND last_event < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
} elseif ($statusFilter === 'inactive') {
    $statusHaving = "HAVING last_event < DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// Count total
$countSQL = "
    SELECT COUNT(*) FROM (
        SELECT equipment_id 
        FROM webhook_events 
        WHERE equipment_id IS NOT NULL $whereSearch
        GROUP BY equipment_id
        $statusHaving
    ) AS sub
";
$countStmt = $db->prepare($countSQL);
$countStmt->execute($searchParams);
$totalEquipment = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalEquipment / $perPage));

// Get equipment list
$listSQL = "
    SELECT 
        equipment_id,
        MAX(equipment_name) AS equipment_name,
        MAX(installation_location) AS installation_location,
        MAX(installation_area) AS installation_area,
        MAX(coordinate) AS coordinate,
        MAX(box_code) AS box_code,
        COUNT(*) AS event_count,
        SUM(CASE WHEN DATE(received_at) = CURDATE() THEN 1 ELSE 0 END) AS today_count,
        SUM(CASE WHEN received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS week_count,
        MIN(received_at) AS first_event,
        MAX(received_at) AS last_event,
        MAX(ip_address) AS last_ip
    FROM webhook_events
    WHERE equipment_id IS NOT NULL $whereSearch
    GROUP BY equipment_id
    $statusHaving
    ORDER BY $sortBy $sortDir
    LIMIT $perPage OFFSET $offset
";
$listStmt = $db->prepare($listSQL);
$listStmt->execute($searchParams);
$equipmentList = $listStmt->fetchAll();

// Get unique locations for filter
$locations = $db->query("SELECT DISTINCT installation_location FROM webhook_events WHERE installation_location IS NOT NULL ORDER BY installation_location")->fetchAll(PDO::FETCH_COLUMN);

// Get unique equipment names for filter dropdown
$equipmentOptions = $db->query("
    SELECT equipment_id, MAX(equipment_name) AS equipment_name 
    FROM webhook_events 
    WHERE equipment_id IS NOT NULL 
    GROUP BY equipment_id 
    ORDER BY MAX(equipment_name), equipment_id
")->fetchAll();

// Helper: build query string
function buildQuery($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

// Helper: sort link
function sortLink($column, $label, $currentSort, $currentDir) {
    $newDir = ($currentSort === $column && $currentDir === 'DESC') ? 'ASC' : 'DESC';
    $icon = '';
    if ($currentSort === $column) {
        $icon = $currentDir === 'ASC' ? ' ↑' : ' ↓';
    }
    $query = buildQuery(['sort' => $column, 'dir' => $newDir, 'page' => 1]);
    return '<a href="?' . htmlspecialchars($query) . '" class="hover:text-primary transition-colors whitespace-nowrap">' . $label . $icon . '</a>';
}

// Status helper
function getStatus($lastEvent) {
    if (!$lastEvent) return ['label' => 'ไม่ทราบ', 'color' => 'gray', 'dot' => 'bg-gray-400'];
    $diff = time() - strtotime($lastEvent);
    if ($diff < 3600) return ['label' => 'ออนไลน์', 'color' => 'green', 'dot' => 'bg-green-500 animate-pulse'];
    if ($diff < 86400) return ['label' => 'วันนี้', 'color' => 'blue', 'dot' => 'bg-blue-500'];
    if ($diff < 604800) return ['label' => 'สัปดาห์นี้', 'color' => 'yellow', 'dot' => 'bg-yellow-500'];
    return ['label' => 'ไม่ได้ใช้งาน', 'color' => 'red', 'dot' => 'bg-red-400'];
}

$showStart = $totalEquipment > 0 ? $offset + 1 : 0;
$showEnd = min($offset + $perPage, $totalEquipment);
?>
<!DOCTYPE html>
<html class="light" lang="th">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
    <title>Equipment - สำนักงานจังหวัดเชียงใหม่</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#123a7d",
              "background-light": "#f3f8ff",
              "background-dark": "#081228",
            },
            fontFamily: {
              display: ["Manrope", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "0.25rem",
              lg: "0.5rem",
              xl: "0.75rem",
              full: "9999px",
            },
            screens: {
              'xs': '480px',
            },
          },
        },
      };
    </script>
    <style>
      body { font-family: "Manrope", sans-serif; -webkit-tap-highlight-color: transparent; }
      .material-symbols-outlined { font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24; }
      .custom-scrollbar::-webkit-scrollbar { height: 4px; width: 4px; }
      .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
      .custom-scrollbar::-webkit-scrollbar-thumb { background: #dde2e4; border-radius: 10px; }
      .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }
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
      .hide-scrollbar::-webkit-scrollbar { display: none; }
      .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
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
      <!-- Sidebar overlay (mobile) -->
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
          <a class="nav-item active" href="equipment.php" title="Equipment">
            <span class="material-symbols-outlined">videocam</span> Equipment
          </a>
           <a class="nav-item" href="map.php" title="Map">
             <span class="material-symbols-outlined">map</span> Map
           </a>
          <div class="nav-section">Preferences</div>
          <a class="nav-item" href="support.php" title="Support">
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
              <span class="topbar-title">Equipment</span>
            </div>
            <div class="topbar-right">
              <span class="topbar-meta hidden xl:inline">อัปเดต: <?= date('d/m/Y H:i') ?></span>
              <button onclick="document.documentElement.classList.toggle('dark')" class="icon-btn tap-target">
                <span class="material-symbols-outlined text-lg">dark_mode</span>
              </button>
              <a href="equipment.php" class="icon-btn tap-target">
                <span class="material-symbols-outlined text-lg">refresh</span>
              </a>
            </div>
          </div>
          <div class="p-4 sm:p-6 lg:p-8">

          <!-- Stat Cards -->
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-5 mb-6 sm:mb-8">
            <!-- Total Equipment -->
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl p-4 sm:p-5 shadow-sm">
              <div class="flex items-center gap-2 mb-2">
                <div class="size-8 sm:size-9 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
                  <span class="material-symbols-outlined text-primary text-lg sm:text-xl">videocam</span>
                </div>
                <span class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-bold uppercase">ทั้งหมด</span>
              </div>
              <h3 class="text-2xl sm:text-3xl font-extrabold"><?= number_format($stats['total_equipment'] ?? 0) ?></h3>
              <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium mt-1">อุปกรณ์ที่ลงทะเบียน</p>
            </div>

            <!-- Active Today -->
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl p-4 sm:p-5 shadow-sm">
              <div class="flex items-center gap-2 mb-2">
                <div class="size-8 sm:size-9 rounded-lg bg-green-100 dark:bg-green-500/20 flex items-center justify-center">
                  <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-lg sm:text-xl">wifi</span>
                </div>
                <span class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-bold uppercase">ใช้งานวันนี้</span>
              </div>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-green-600 dark:text-green-400"><?= number_format($stats['active_today'] ?? 0) ?></h3>
              <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium mt-1">ส่งข้อมูลวันนี้</p>
            </div>

            <!-- Active This Week -->
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl p-4 sm:p-5 shadow-sm">
              <div class="flex items-center gap-2 mb-2">
                <div class="size-8 sm:size-9 rounded-lg bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center">
                  <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-lg sm:text-xl">date_range</span>
                </div>
                <span class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-bold uppercase">ใช้งาน 7 วัน</span>
              </div>
              <h3 class="text-2xl sm:text-3xl font-extrabold"><?= number_format($stats['active_week'] ?? 0) ?></h3>
              <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium mt-1">ใช้งานสัปดาห์นี้</p>
            </div>

            <!-- Total Locations -->
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl p-4 sm:p-5 shadow-sm">
              <div class="flex items-center gap-2 mb-2">
                <div class="size-8 sm:size-9 rounded-lg bg-orange-100 dark:bg-orange-500/20 flex items-center justify-center">
                  <span class="material-symbols-outlined text-orange-600 dark:text-orange-400 text-lg sm:text-xl">location_on</span>
                </div>
                <span class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-bold uppercase">สถานที่</span>
              </div>
              <h3 class="text-2xl sm:text-3xl font-extrabold"><?= number_format($stats['total_locations'] ?? 0) ?></h3>
              <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium mt-1">สถานที่ติดตั้ง</p>
            </div>
          </div>

          <!-- Filters -->
          <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl shadow-sm mb-6 sm:mb-8">
            <form method="GET" class="p-4 sm:p-6 flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-4 sm:items-end">
              <div class="flex flex-col gap-1.5 flex-1 sm:min-w-[180px]">
                <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">อุปกรณ์</label>
                <select name="search" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                  <option value="">ทุกอุปกรณ์</option>
                  <?php foreach ($equipmentOptions as $eq): ?>
                    <option value="<?= htmlspecialchars($eq['equipment_id']) ?>" <?= $search === $eq['equipment_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($eq['equipment_name'] ?: $eq['equipment_id']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="flex flex-col gap-1.5 sm:min-w-[160px]">
                <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">สถานะ</label>
                <select name="status" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                  <option value="">ทั้งหมด</option>
                  <option value="online" <?= $statusFilter === 'online' ? 'selected' : '' ?>>🟢 ออนไลน์ (< 1 ชม.)</option>
                  <option value="today" <?= $statusFilter === 'today' ? 'selected' : '' ?>>🔵 วันนี้ (1-24 ชม.)</option>
                  <option value="week" <?= $statusFilter === 'week' ? 'selected' : '' ?>>🟡 สัปดาห์นี้ (1-7 วัน)</option>
                  <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>🔴 ไม่ได้ใช้งาน (> 7 วัน)</option>
                </select>
              </div>
              <div class="flex flex-col gap-1.5 sm:min-w-[160px]">
                <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">สถานที่</label>
                <select name="location" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                  <option value="">ทุกสถานที่</option>
                  <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $locationFilter === $loc ? 'selected' : '' ?>>
                      <?= htmlspecialchars($loc) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="flex gap-2 pt-1 sm:pt-0">
                <button type="submit" class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-5 py-2.5 sm:py-2 text-sm font-bold bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors tap-target">
                  <span class="material-symbols-outlined text-sm">filter_list</span> กรอง
                </button>
                <a href="equipment.php" class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 text-sm font-bold bg-background-light dark:bg-white/5 rounded-lg border border-[#dde2e4] dark:border-white/10 hover:border-primary/30 transition-colors tap-target">
                  <span class="material-symbols-outlined text-sm">restart_alt</span> รีเซ็ต
                </a>
              </div>
            </form>
          </div>

          <!-- Equipment Table -->
          <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl overflow-hidden shadow-sm">
            <div class="p-4 sm:p-6 border-b border-[#dde2e4] dark:border-white/10 flex items-center justify-between gap-3">
              <div>
                <h4 class="text-sm sm:text-base lg:text-lg font-bold">รายการอุปกรณ์</h4>
                <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium mt-0.5">
                  <?php if ($totalEquipment > 0): ?>
                    แสดง <?= $showStart ?>-<?= $showEnd ?> จาก <?= number_format($totalEquipment) ?> อุปกรณ์
                  <?php else: ?>
                    ไม่พบอุปกรณ์
                  <?php endif; ?>
                </p>
              </div>
            </div>

            <?php if ($totalEquipment > 0): ?>
            <!-- Desktop Table -->
            <div class="hidden sm:block overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-[#f1f3f4] dark:bg-white/5">
                  <tr>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">สถานะ</th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest"><?= sortLink('equipment_id', 'ชื่ออุปกรณ์', $sortBy, $sortDir) ?></th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest"><?= sortLink('installation_location', 'สถานที่', $sortBy, $sortDir) ?></th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest"><?= sortLink('event_count', 'เหตุการณ์', $sortBy, $sortDir) ?></th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">วันนี้</th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest"><?= sortLink('last_event', 'ล่าสุด', $sortBy, $sortDir) ?></th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest"><?= sortLink('first_event', 'ลงทะเบียน', $sortBy, $sortDir) ?></th>
                    <th class="px-4 lg:px-6 py-3 text-[10px] lg:text-xs font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">IP</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#dde2e4] dark:divide-white/5">
                  <?php foreach ($equipmentList as $eq): 
                    $status = getStatus($eq['last_event']);
                  ?>
                  <tr class="hover:bg-[#f7f7f7] dark:hover:bg-white/[0.02] transition-colors">
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <div class="flex items-center gap-2">
                        <div class="size-2.5 rounded-full <?= $status['dot'] ?>"></div>
                        <span class="text-xs font-bold"><?= $status['label'] ?></span>
                      </div>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <div>
                        <?php if ($eq['equipment_name']): ?>
                          <div class="text-xs lg:text-sm font-bold mb-0.5"><?= htmlspecialchars($eq['equipment_name']) ?></div>
                        <?php endif; ?>
                        <a href="index.php?equipment=<?= urlencode($eq['equipment_id']) ?>&range=all" class="text-[10px] lg:text-xs text-primary hover:underline font-mono">
                          <?= htmlspecialchars(mb_strimwidth($eq['equipment_id'], 0, 20, '...')) ?>
                        </a>
                        <?php if ($eq['box_code']): ?>
                          <div class="text-[9px] text-[#687d82] dark:text-white/30 mt-0.5">Box: <?= htmlspecialchars($eq['box_code']) ?></div>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <div>
                        <div class="flex items-center gap-1.5">
                          <span class="material-symbols-outlined text-[#687d82] text-sm">location_on</span>
                          <span class="text-xs lg:text-sm font-medium"><?= htmlspecialchars($eq['installation_location'] ?? 'N/A') ?></span>
                        </div>
                        <?php if ($eq['installation_area']): ?>
                          <div class="text-[9px] text-[#687d82] dark:text-white/30 mt-0.5 ml-5"><?= htmlspecialchars($eq['installation_area']) ?></div>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <span class="text-xs lg:text-sm font-extrabold"><?= number_format($eq['event_count']) ?></span>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] lg:text-xs font-bold <?= $eq['today_count'] > 0 ? 'bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-white/30' ?>">
                        <?= number_format($eq['today_count']) ?>
                      </span>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <div class="text-xs lg:text-sm font-medium"><?= date('d/m/Y', strtotime($eq['last_event'])) ?></div>
                      <div class="text-[10px] lg:text-[11px] text-[#687d82] dark:text-white/40"><?= date('H:i:s', strtotime($eq['last_event'])) ?></div>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <div class="text-xs text-[#687d82] dark:text-white/40"><?= date('d/m/Y', strtotime($eq['first_event'])) ?></div>
                    </td>
                    <td class="px-4 lg:px-6 py-3 lg:py-4">
                      <span class="text-[10px] lg:text-xs font-mono text-[#687d82] dark:text-white/40"><?= htmlspecialchars($eq['last_ip'] ?? '-') ?></span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Mobile Cards -->
            <div class="sm:hidden divide-y divide-[#dde2e4] dark:divide-white/5">
              <?php foreach ($equipmentList as $eq): 
                $status = getStatus($eq['last_event']);
              ?>
              <a href="index.php?equipment=<?= urlencode($eq['equipment_id']) ?>&range=all" class="block p-4 hover:bg-[#f7f7f7] dark:hover:bg-white/[0.02] active:bg-gray-100 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-2">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="size-2 rounded-full <?= $status['dot'] ?> shrink-0"></div>
                      <span class="text-[10px] font-bold uppercase <?= $status['color'] === 'green' ? 'text-green-600' : ($status['color'] === 'red' ? 'text-red-500' : 'text-[#687d82]') ?>"><?= $status['label'] ?></span>
                    </div>
                    <?php if ($eq['equipment_name']): ?>
                      <p class="text-sm font-bold truncate"><?= htmlspecialchars($eq['equipment_name']) ?></p>
                      <p class="text-[10px] text-primary font-mono truncate"><?= htmlspecialchars($eq['equipment_id']) ?></p>
                    <?php else: ?>
                      <p class="text-sm font-bold text-primary truncate"><?= htmlspecialchars($eq['equipment_id']) ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="text-right shrink-0">
                    <span class="text-lg font-extrabold"><?= number_format($eq['event_count']) ?></span>
                    <p class="text-[10px] text-[#687d82] dark:text-white/40">เหตุการณ์</p>
                  </div>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-[#687d82] dark:text-white/50">
                  <span class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">location_on</span>
                    <?= htmlspecialchars($eq['installation_location'] ?? 'N/A') ?>
                    <?php if ($eq['installation_area']): ?>
                      <span class="text-[#687d82]/50">(<?= htmlspecialchars($eq['installation_area']) ?>)</span>
                    <?php endif; ?>
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">schedule</span>
                    ล่าสุด <?= date('d/m/Y H:i', strtotime($eq['last_event'])) ?>
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">today</span>
                    วันนี้: <?= number_format($eq['today_count']) ?>
                  </span>
                </div>
              </a>
              <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="flex flex-col items-center justify-center py-16 sm:py-20 text-[#687d82] dark:text-white/40">
              <span class="material-symbols-outlined text-5xl mb-4 opacity-30">videocam_off</span>
              <p class="text-sm font-bold mb-1">ไม่พบอุปกรณ์</p>
              <p class="text-xs">ลองเปลี่ยนเงื่อนไขการค้นหา</p>
            </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="p-4 sm:p-6 border-t border-[#dde2e4] dark:border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3">
              <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium">
                หน้า <?= $page ?> จาก <?= $totalPages ?>
              </p>
              <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                  <a href="?<?= buildQuery(['page' => $page - 1]) ?>" class="flex items-center justify-center size-8 sm:size-9 rounded-lg bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 hover:border-primary/30 transition-colors tap-target">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                  </a>
                <?php endif; ?>
                <?php
                  $startPage = max(1, $page - 2);
                  $endPage = min($totalPages, $page + 2);
                  for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                  <a href="?<?= buildQuery(['page' => $p]) ?>" class="flex items-center justify-center size-8 sm:size-9 rounded-lg text-xs font-bold transition-colors <?= $p === $page ? 'bg-primary text-white' : 'bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 hover:border-primary/30' ?>">
                    <?= $p ?>
                  </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                  <a href="?<?= buildQuery(['page' => $page + 1]) ?>" class="flex items-center justify-center size-8 sm:size-9 rounded-lg bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 hover:border-primary/30 transition-colors tap-target">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
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
          if (!sidebar.classList.contains('sidebar-closed')) {
            toggleSidebar();
          }
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
