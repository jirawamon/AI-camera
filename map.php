<?php
require_once __DIR__ . '/back_end/config.php';

$db = getDB();
$db->exec("SET time_zone = '+07:00'");

$stmt = $db->query("
    SELECT we.equipment_id, we.coordinate
    FROM webhook_events AS we
    INNER JOIN (
        SELECT equipment_id, MAX(id) AS latest_id
        FROM webhook_events
        WHERE equipment_id IS NOT NULL
          AND TRIM(equipment_id) != ''
          AND coordinate IS NOT NULL
          AND TRIM(coordinate) != ''
        GROUP BY equipment_id
    ) AS latest ON latest.latest_id = we.id
    ORDER BY we.equipment_id
");

function parseCoordinate($value) {
    if (!preg_match('/(-?\d+(?:\.\d+)?)\s*[,，]\s*(-?\d+(?:\.\d+)?)/u', trim((string)$value), $matches)) {
        return null;
    }
    $lat = (float)$matches[1];
    $lng = (float)$matches[2];
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return null;
    }
    return ['lat' => $lat, 'lng' => $lng];
}

$markers = [];
foreach ($stmt->fetchAll() as $row) {
    $coordinate = parseCoordinate($row['coordinate']);
    if ($coordinate === null) continue;
    $markers[] = [
        'id' => (string)$row['equipment_id'],
        'lat' => $coordinate['lat'],
        'lng' => $coordinate['lng'],
    ];
}

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แผนที่อุปกรณ์ - สำนักงานจังหวัดเชียงใหม่</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    :root{--navy:#081228;--royal:#123a7d;--blue:#1f5eff;--sky:#00b7ff}
    *{box-sizing:border-box} body{margin:0;font-family:'Sarabun',sans-serif;background:#f3f8ff;color:#121617}
    #sidebar{background:linear-gradient(180deg,#061127 0%,#0a1e44 45%,#08224a 100%)}
    .sidebar-brand{padding:24px;border-bottom:1px solid rgba(255,255,255,.1)}
    .brand-emblem{width:42px;height:42px;margin-bottom:10px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,#123a7d,#00b7ff)}
    .brand-emblem svg{width:24px;fill:none;stroke:white;stroke-width:1.7}.brand-title{font-size:15px;font-weight:700}.brand-sub,.brand-sys{color:rgba(255,255,255,.55);font-size:10px;margin-top:2px}
    .sidebar-nav{padding:12px}.nav-section{padding:12px 10px 6px;color:rgba(255,255,255,.4);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.12em}
    .nav-item{display:flex;align-items:center;gap:12px;margin:3px 0;padding:10px 12px;border-radius:9px;color:rgba(255,255,255,.72);text-decoration:none;font-size:14px}.nav-item:hover{background:rgba(255,255,255,.08);color:white}
    .nav-item.active{color:white;background:linear-gradient(90deg,rgba(54,209,255,.28),rgba(31,94,255,.2));border-left:3px solid var(--sky)}
    .gov-topbar{min-height:32px;display:flex;align-items:center;padding:0 20px;background:linear-gradient(90deg,#081228,#123a7d);color:rgba(255,255,255,.82);font-size:11px}
    .topbar{height:56px;padding:0 20px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.94);border-bottom:1px solid #dbe8ff}
    .icon-btn{width:36px;height:36px;display:grid;place-items:center;border:1px solid #cfe0ff;border-radius:8px;color:#3f5d8f;background:white}
    #map{width:100%;height:calc(100dvh - 136px);min-height:420px;border-radius:14px;border:1px solid #d6e5ff;box-shadow:0 12px 30px rgba(18,58,125,.1)}
    .device-pin{position:relative;display:block;width:24px;height:24px;background:#1f5eff;border:3px solid white;border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 3px 10px rgba(8,18,40,.4)}
    .device-pin::after{content:'';position:absolute;width:7px;height:7px;top:6px;left:6px;border-radius:50%;background:white}
    .map-empty{position:absolute;z-index:500;top:50%;left:50%;transform:translate(-50%,-50%);padding:14px 18px;border-radius:10px;background:white;box-shadow:0 8px 24px rgba(8,18,40,.16);color:#526784;font-weight:600}
    @media(max-width:1023px){#sidebar{transform:translateX(-100%);transition:transform .2s ease}#sidebar.sidebar-open{transform:translateX(0)}#map{height:calc(100dvh - 156px);min-height:360px}}
  </style>
</head>
<body>
  <div class="flex h-[100dvh] overflow-hidden">
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>
    <aside id="sidebar" class="fixed lg:relative z-50 lg:z-auto w-72 lg:w-20 xl:w-64 h-full text-white flex flex-col shrink-0">
      <div class="sidebar-brand">
        <div class="brand-emblem"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div class="brand-title">สำนักงานจังหวัดเชียงใหม่</div>
        <div class="brand-sub">Provincial Office · Chiang Mai</div>
        <div class="brand-sys">ระบบ CCTV Webhook · 5G Metaverse</div>
      </div>
      <nav class="sidebar-nav flex-1 overflow-y-auto">
        <div class="nav-section">Menu</div>
        <a class="nav-item" href="index.php"><span class="material-symbols-outlined">dashboard</span> Dashboard</a>
        <a class="nav-item" href="equipment.php"><span class="material-symbols-outlined">videocam</span> Equipment</a>
        <a class="nav-item active" href="map.php"><span class="material-symbols-outlined">map</span> Map</a>
        <div class="nav-section">Preferences</div>
        <a class="nav-item" href="support.php"><span class="material-symbols-outlined">help</span> Support</a>
      </nav>
    </aside>
    <main class="flex-1 flex flex-col min-w-0">
      <div class="gov-topbar">เว็บไซต์อย่างเป็นทางการของสำนักงานจังหวัดเชียงใหม่ · ศูนย์ปฏิบัติการดิจิทัล 5G</div>
      <div class="topbar">
        <div class="flex items-center gap-3">
          <button type="button" class="icon-btn lg:hidden" onclick="toggleSidebar()"><span class="material-symbols-outlined">menu</span></button>
          <strong>แผนที่อุปกรณ์</strong>
        </div>
        <a class="icon-btn" href="map.php" aria-label="รีเฟรช"><span class="material-symbols-outlined">refresh</span></a>
      </div>
      <section class="relative flex-1 min-h-0 p-4 sm:p-6">
        <div id="map"></div>
        <?php if (!$markers): ?><div class="map-empty">ไม่พบอุปกรณ์ที่มีพิกัดถูกต้อง</div><?php endif; ?>
      </section>
    </main>
  </div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const markers = <?= json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const map = L.map('map', { minZoom: 6, maxZoom: 19 }).setView([18.7883, 98.9853], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19, attribution:'&copy; OpenStreetMap contributors' }).addTo(map);
    const bounds = [];
    const pinIcon = L.divIcon({ className:'', html:'<span class="device-pin"></span>', iconSize:[24,32], iconAnchor:[12,30] });
    markers.forEach((item) => { L.marker([item.lat,item.lng], { icon:pinIcon, title:item.id }).addTo(map); bounds.push([item.lat,item.lng]); });
    if(bounds.length===1) map.setView(bounds[0],16);
    else if(bounds.length>1) map.fitBounds(bounds,{padding:[36,36],maxZoom:16});
    function toggleSidebar(){document.getElementById('sidebar').classList.toggle('sidebar-open');document.getElementById('sidebarOverlay').classList.toggle('hidden');setTimeout(()=>map.invalidateSize(),220)}
    window.addEventListener('resize',()=>map.invalidateSize());
  </script>
</body>
</html>

