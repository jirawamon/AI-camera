<?php

require_once __DIR__ . '/back_end/config.php';

$db = getDB();

// Get filter parameters
$page = max(1, intval($_GET['page'] ?? 1));
$location = $_GET['location'] ?? '';
$equipment = $_GET['equipment'] ?? '';
$event_type = $_GET['event_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$time_range = $_GET['range'] ?? 'daily';

// Validate time_range
if (!in_array($time_range, ['daily', 'monthly', 'yearly', 'all'])) {
    $time_range = 'daily';
}

$offset = ($page - 1) * EVENTS_PER_PAGE;

// ============================================================
// Time-range based WHERE clause
// ============================================================
$rangeWhere = '';
switch ($time_range) {
    case 'daily':
        $rangeWhere = "DATE(received_at) = CURDATE()";
        break;
    case 'monthly':
        $rangeWhere = "YEAR(received_at) = YEAR(CURDATE()) AND MONTH(received_at) = MONTH(CURDATE())";
        break;
    case 'yearly':
        $rangeWhere = "YEAR(received_at) = YEAR(CURDATE())";
        break;
    case 'all':
    default:
        $rangeWhere = '';
        break;
}

// Build query with filters
$where = [];
$params = [];

if ($rangeWhere) {
    $where[] = $rangeWhere;
}
if ($location) {
    $where[] = "installation_location LIKE :location";
    $params[':location'] = "%$location%";
}
if ($equipment) {
    $where[] = "equipment_id LIKE :equipment";
    $params[':equipment'] = "%$equipment%";
}
if ($event_type) {
    $where[] = "task_name = :event_type";
    $params[':event_type'] = $event_type;
}
// Also pass range to search forms
$currentRange = htmlspecialchars($time_range);
if ($date_from) {
    $where[] = "received_at >= :date_from";
    $params[':date_from'] = $date_from . " 00:00:00";
}
if ($date_to) {
    $where[] = "received_at <= :date_to";
    $params[':date_to'] = $date_to . " 23:59:59";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// ============================================================
// Query 1: Get total count for filtered results
// ============================================================
$countStmt = $db->prepare("SELECT COUNT(*) FROM webhook_events $whereSQL");
$countStmt->execute($params);
$totalEvents = $countStmt->fetchColumn();
$totalPages = ceil($totalEvents / EVENTS_PER_PAGE);

// ============================================================
// Query 2: Get events WITHOUT image_data
// ============================================================
$stmt = $db->prepare("
    SELECT id, alarm_id, coordinate, equipment_id, equipment_name, 
           send_time, event_type_id, task_id, task_name, str_res,
           installation_area, installation_location, box_code,
           (image_data IS NOT NULL AND image_data != '') AS has_image,
           received_at, ip_address
    FROM webhook_events 
    $whereSQL
    ORDER BY received_at DESC 
    LIMIT " . EVENTS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// ============================================================
// Query 3: Stats based on time range
// ============================================================
switch ($time_range) {
    case 'daily':
        $statsRow = $db->query("
            SELECT 
                SUM(CASE WHEN DATE(received_at) = CURDATE() THEN 1 ELSE 0 END) AS current_period,
                SUM(CASE WHEN DATE(received_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS prev_period,
                COUNT(DISTINCT CASE WHEN DATE(received_at) = CURDATE() THEN equipment_id END) AS period_equipment,
                COUNT(DISTINCT CASE WHEN DATE(received_at) = CURDATE() AND installation_location IS NOT NULL THEN installation_location END) AS period_locations
            FROM webhook_events
            WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ")->fetch();
        $periodLabel = 'วันนี้';
        $prevLabel = 'เมื่อวาน';
        $chartTitle = 'เหตุการณ์รายชั่วโมง';
        $chartSubtitle = 'จำนวนเหตุการณ์แยกตามชั่วโมงของวันนี้';
        $pageTitle = 'สรุปเหตุการณ์รายวัน';
        $pageSubtitle = 'ข้อมูลเหตุการณ์ประจำวันที่ ' . date('d/m/Y');
        break;

    case 'monthly':
        $statsRow = $db->query("
            SELECT 
                SUM(CASE WHEN YEAR(received_at)=YEAR(CURDATE()) AND MONTH(received_at)=MONTH(CURDATE()) THEN 1 ELSE 0 END) AS current_period,
                SUM(CASE WHEN YEAR(received_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(received_at)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN 1 ELSE 0 END) AS prev_period,
                COUNT(DISTINCT CASE WHEN YEAR(received_at)=YEAR(CURDATE()) AND MONTH(received_at)=MONTH(CURDATE()) THEN equipment_id END) AS period_equipment,
                COUNT(DISTINCT CASE WHEN YEAR(received_at)=YEAR(CURDATE()) AND MONTH(received_at)=MONTH(CURDATE()) AND installation_location IS NOT NULL THEN installation_location END) AS period_locations
            FROM webhook_events
            WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
        ")->fetch();
        $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $periodLabel = $thaiMonths[(int)date('n')] . ' ' . (date('Y') + 543);
        $prevLabel = 'เดือนก่อน';
        $chartTitle = 'เหตุการณ์รายวัน';
        $chartSubtitle = 'จำนวนเหตุการณ์แยกตามวันของเดือนนี้';
        $pageTitle = 'สรุปเหตุการณ์รายเดือน';
        $pageSubtitle = 'ข้อมูลเหตุการณ์ประจำเดือน ' . $thaiMonths[(int)date('n')] . ' ' . (date('Y') + 543);
        break;

    case 'yearly':
        $statsRow = $db->query("
            SELECT 
                SUM(CASE WHEN YEAR(received_at)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS current_period,
                SUM(CASE WHEN YEAR(received_at)=YEAR(CURDATE())-1 THEN 1 ELSE 0 END) AS prev_period,
                COUNT(DISTINCT CASE WHEN YEAR(received_at)=YEAR(CURDATE()) THEN equipment_id END) AS period_equipment,
                COUNT(DISTINCT CASE WHEN YEAR(received_at)=YEAR(CURDATE()) AND installation_location IS NOT NULL THEN installation_location END) AS period_locations
            FROM webhook_events
            WHERE YEAR(received_at) >= YEAR(CURDATE())-1
        ")->fetch();
        $periodLabel = 'ปี ' . (date('Y') + 543);
        $prevLabel = 'ปีก่อน';
        $chartTitle = 'เหตุการณ์รายเดือน';
        $chartSubtitle = 'จำนวนเหตุการณ์แยกตามเดือนของปีนี้';
        $pageTitle = 'สรุปเหตุการณ์รายปี';
        $pageSubtitle = 'ข้อมูลเหตุการณ์ประจำปี ' . (date('Y') + 543);
        break;

    case 'all':
    default:
        $statsRow = $db->query("
            SELECT 
                COUNT(*) AS current_period,
                0 AS prev_period,
                COUNT(DISTINCT equipment_id) AS period_equipment,
                COUNT(DISTINCT CASE WHEN installation_location IS NOT NULL THEN installation_location END) AS period_locations,
                MIN(received_at) AS first_event,
                MAX(received_at) AS last_event
            FROM webhook_events
        ")->fetch();
        $periodLabel = 'ทั้งหมด';
        $prevLabel = '';
        $chartTitle = 'เหตุการณ์รายเดือน';
        $chartSubtitle = 'จำนวนเหตุการณ์ตลอดช่วงเวลา';
        $pageTitle = 'สรุปเหตุการณ์ทั้งหมด';
        $pageSubtitle = 'ข้อมูลเหตุการณ์ทั้งหมดในระบบ';
        break;
}

$currentPeriod = (int)($statsRow['current_period'] ?? 0);
$prevPeriod = (int)($statsRow['prev_period'] ?? 0);
$periodEquipment = (int)($statsRow['period_equipment'] ?? 0);
$periodLocations = (int)($statsRow['period_locations'] ?? 0);

// Calculate change %
$changePercent = 0;
$changeDirection = 'neutral';
if ($prevPeriod > 0 && $time_range !== 'all') {
    $changePercent = round((($currentPeriod - $prevPeriod) / $prevPeriod) * 100, 1);
    $changeDirection = $changePercent > 0 ? 'up' : ($changePercent < 0 ? 'down' : 'neutral');
} elseif ($prevPeriod === 0 && $currentPeriod > 0 && $time_range !== 'all') {
    $changePercent = 100;
    $changeDirection = 'up';
}

// Average per unit
switch ($time_range) {
    case 'daily':
        $avgValue = $currentPeriod > 0 ? number_format($currentPeriod / max((int)date('G') + 1, 1), 1) : '0';
        $avgLabel = 'เฉลี่ย/ชั่วโมง';
        break;
    case 'monthly':
        $avgValue = $currentPeriod > 0 ? number_format($currentPeriod / max((int)date('j'), 1), 1) : '0';
        $avgLabel = 'เฉลี่ย/วัน';
        break;
    case 'yearly':
        $avgValue = $currentPeriod > 0 ? number_format($currentPeriod / max((int)date('n'), 1), 1) : '0';
        $avgLabel = 'เฉลี่ย/เดือน';
        break;
    case 'all':
        $firstEvent = $statsRow['first_event'] ?? null;
        $lastEvent = $statsRow['last_event'] ?? null;
        if ($firstEvent && $lastEvent) {
            $days = max((strtotime($lastEvent) - strtotime($firstEvent)) / 86400, 1);
            $avgValue = number_format($currentPeriod / $days, 1);
        } else {
            $avgValue = '0';
        }
        $avgLabel = 'เฉลี่ย/วัน';
        break;
}

// ============================================================
// Query 4: Chart data based on time range
// ============================================================
switch ($time_range) {
    case 'daily':
        // Hourly for today
        $chartData = $db->query("
            SELECT HOUR(received_at) AS chart_label, COUNT(*) AS event_count
            FROM webhook_events
            WHERE DATE(received_at) = CURDATE()
            GROUP BY HOUR(received_at)
            ORDER BY chart_label ASC
        ")->fetchAll();
        // Fill missing hours
        $hourMap = array_column($chartData, 'event_count', 'chart_label');
        $chartData = [];
        for ($h = 0; $h <= (int)date('G'); $h++) {
            $chartData[] = [
                'chart_label' => sprintf('%02d:00', $h),
                'event_count' => (int)($hourMap[$h] ?? 0)
            ];
        }
        break;

    case 'monthly':
        // Daily for this month
        $chartData = $db->query("
            SELECT DATE(received_at) AS chart_label, COUNT(*) AS event_count
            FROM webhook_events
            WHERE YEAR(received_at) = YEAR(CURDATE()) AND MONTH(received_at) = MONTH(CURDATE())
            GROUP BY DATE(received_at)
            ORDER BY chart_label ASC
        ")->fetchAll();
        // Fill missing days
        $dayMap = [];
        foreach ($chartData as $d) { $dayMap[$d['chart_label']] = (int)$d['event_count']; }
        $chartData = [];
        $daysInMonth = (int)date('j'); // up to today
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = date('Y-m-') . sprintf('%02d', $d);
            $chartData[] = [
                'chart_label' => sprintf('%d', $d),
                'event_count' => (int)($dayMap[$dateStr] ?? 0)
            ];
        }
        break;

    case 'yearly':
        // Monthly for this year
        $thaiMonthsShort = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $chartData = $db->query("
            SELECT MONTH(received_at) AS chart_label, COUNT(*) AS event_count
            FROM webhook_events
            WHERE YEAR(received_at) = YEAR(CURDATE())
            GROUP BY MONTH(received_at)
            ORDER BY chart_label ASC
        ")->fetchAll();
        $monthMap = array_column($chartData, 'event_count', 'chart_label');
        $chartData = [];
        for ($m = 1; $m <= (int)date('n'); $m++) {
            $chartData[] = [
                'chart_label' => $thaiMonthsShort[$m],
                'event_count' => (int)($monthMap[$m] ?? 0)
            ];
        }
        break;

    case 'all':
    default:
        // Monthly for all time
        $chartData = $db->query("
            SELECT DATE_FORMAT(received_at, '%Y-%m') AS chart_label, COUNT(*) AS event_count
            FROM webhook_events
            GROUP BY DATE_FORMAT(received_at, '%Y-%m')
            ORDER BY chart_label ASC
        ")->fetchAll();
        // Format labels
        $thaiMonthsShort = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        foreach ($chartData as &$row) {
            $parts = explode('-', $row['chart_label']);
            $row['chart_label'] = $thaiMonthsShort[(int)$parts[1]] . (((int)$parts[0] + 543) % 100);
        }
        unset($row);
        break;
}

$maxCount = max(array_column($chartData, 'event_count') ?: [1]);

// Nice Y-axis max calculation
function niceAxisMax($maxVal) {
    if ($maxVal <= 0) return 10;
    if ($maxVal <= 5) return $maxVal;
    if ($maxVal <= 10) return 10;
    $rawStep = $maxVal / 4;
    $magnitude = pow(10, floor(log10($rawStep)));
    $residual = $rawStep / $magnitude;
    if ($residual <= 1) $niceStep = $magnitude;
    elseif ($residual <= 2) $niceStep = 2 * $magnitude;
    elseif ($residual <= 2.5) $niceStep = 2.5 * $magnitude;
    elseif ($residual <= 5) $niceStep = 5 * $magnitude;
    else $niceStep = 10 * $magnitude;
    return (int)($niceStep * 4);
}
$niceMax = niceAxisMax($maxCount);

// ============================================================
// Query 5: Top event types for this period
// ============================================================
$rangeWhereOnly = $rangeWhere ? "WHERE $rangeWhere" : "";
$topEvents = $db->query("
    SELECT COALESCE(task_name, event_type_id) AS event_type_id, COUNT(*) AS cnt
    FROM webhook_events
    $rangeWhereOnly
    GROUP BY COALESCE(task_name, event_type_id)
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll();

// ============================================================
// Query 6: Top equipment for this period
// ============================================================
$equipWhere = $rangeWhere
    ? "WHERE $rangeWhere AND equipment_id IS NOT NULL"
    : "WHERE equipment_id IS NOT NULL";
$topEquipments = $db->query("
    SELECT equipment_id, COALESCE(NULLIF(MAX(equipment_name), ''), equipment_id) AS equipment_label, COUNT(*) AS cnt
    FROM webhook_events
    $equipWhere
    GROUP BY equipment_id
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll();

// Get unique locations for filter dropdown
$locations = $db->query("SELECT DISTINCT installation_location FROM webhook_events WHERE installation_location IS NOT NULL ORDER BY installation_location")->fetchAll(PDO::FETCH_COLUMN);

// Get unique equipment for filter dropdown (equipment_id => equipment_name)
$equipmentOptions = $db->query("
    SELECT equipment_id, MAX(equipment_name) AS equipment_name 
    FROM webhook_events 
    WHERE equipment_id IS NOT NULL 
    GROUP BY equipment_id 
    ORDER BY MAX(equipment_name), equipment_id
")->fetchAll();

// Get unique task names for filter dropdown
$eventTypeOptions = $db->query("SELECT DISTINCT task_name FROM webhook_events WHERE task_name IS NOT NULL AND task_name != '' ORDER BY task_name")->fetchAll(PDO::FETCH_COLUMN);

// Event type mapping
function getEventBadge($eventTypeId) {
    $types = [
        '1' => ['label' => 'บุคคลเข้าพื้นที่', 'color' => 'yellow'],
        '2' => ['label' => 'สูบบุหรี่', 'color' => 'red'],
        '3' => ['label' => 'จักรยานยนต์เข้าพื้นที่', 'color' => 'yellow'],
        '4' => ['label' => 'ทะเลาะวิวาท', 'color' => 'red'],
        '5' => ['label' => 'บุคคลเข้าพื้นที่เสี่ยง', 'color' => 'orange'],
        '6' => ['label' => 'เหตุการณ์ผิดปกติ', 'color' => 'red'],
    ];
    $type = $types[$eventTypeId] ?? ['label' => 'Event #' . $eventTypeId, 'color' => 'gray'];
    $colorMap = [
        'yellow' => 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-700 dark:text-yellow-400',
        'red' => 'bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-400',
        'orange' => 'bg-orange-100 dark:bg-orange-500/20 text-orange-700 dark:text-orange-400',
        'green' => 'bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400',
        'gray' => 'bg-gray-100 dark:bg-gray-500/20 text-gray-700 dark:text-gray-400',
    ];
    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold ' . $colorMap[$type['color']] . ' uppercase">' . htmlspecialchars($type['label']) . '</span>';
}

// Build filter query string helper
function buildQuery($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return http_build_query($params);
}

$showStart = $totalEvents > 0 ? $offset + 1 : 0;
$showEnd = min($offset + EVENTS_PER_PAGE, $totalEvents);
$chartCount = count($chartData);

?>
<!DOCTYPE html>
<html class="light" lang="th">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
    <title>Webhook Dashboard - สำนักงานจังหวัดเชียงใหม่</title>
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
      body {
        font-family: "Manrope", sans-serif;
        -webkit-tap-highlight-color: transparent;
      }
      .material-symbols-outlined {
        font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
      }
      .custom-scrollbar::-webkit-scrollbar {
        height: 4px;
        width: 4px;
      }
      .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
      }
      .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #dde2e4;
        border-radius: 10px;
      }
      .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
      }
      /* Sidebar mobile overlay */
      #sidebarOverlay {
        transition: opacity 0.3s ease;
      }
      #sidebar {
        transition: transform 0.3s ease;
      }
      #sidebar.sidebar-closed {
        transform: translateX(-100%);
      }
      @media (min-width: 1024px) {
        #sidebar {
          transform: translateX(0) !important;
        }
        #sidebarOverlay {
          display: none !important;
        }
      }
      /* Touch-friendly tap targets */
      @media (max-width: 640px) {
        .tap-target {
          min-height: 44px;
          min-width: 44px;
        }
      }
      /* Smooth page transitions */
      .page-enter {
        animation: fadeInUp 0.3s ease forwards;
      }
      @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      /* Hide scrollbar on time range but keep scrollable */
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
      .nav-item svg { width: 15px; height: 15px; flex-shrink: 0; }
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
      .topbar-divider{width:1px;height:20px;background:#cfe0ff;}
      .device-wrap{position:relative;display:flex;align-items:center;min-width:220px;max-width:420px;flex:1;}
      .device-wrap .material-symbols-outlined{position:absolute;left:9px;color:#3f5d8f;pointer-events:none;font-size:18px;}
      .device-select{
        appearance:none;
        background:#f3f8ff;
        border:1px solid #cfe0ff;
        border-radius:7px;
        padding:7px 30px 7px 30px;
        font-size:13px;
        width:100%;
        outline:none;
      }
      .device-arrow{position:absolute;right:9px;color:#3f5d8f;pointer-events:none;font-size:11px;}
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
      html.dark .topbar-divider{background:rgba(125,230,255,0.16);}
      html.dark .device-select{background:rgba(255,255,255,0.06);border-color:rgba(125,230,255,0.16);color:#eaf8ff;}
      html.dark .topbar-meta, html.dark .device-wrap .material-symbols-outlined, html.dark .device-arrow, html.dark .icon-btn{color:#9bc9ff;}
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
        --meta-navy: #081228;
        --meta-royal: #123a7d;
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

      <!-- Mobile Sidebar Overlay -->
      <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 opacity-0 pointer-events-none lg:hidden" onclick="toggleSidebar()"></div>

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
          <a class="nav-item active" href="index.php" title="Dashboard">
            <span class="material-symbols-outlined">dashboard</span> Dashboard
          </a>
          <a class="nav-item" href="equipment.php" title="Equipment">
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
          <button onclick="exportCSV()" class="w-full bg-white text-primary font-bold py-2.5 rounded-lg flex items-center justify-center gap-2 text-sm hover:bg-gray-100 transition-colors tap-target">
            <span class="material-symbols-outlined text-sm">download</span>
            <span class="lg:hidden xl:block">Export Report</span>
          </button>
        </div>
      </aside>

      <!-- Main Content -->
      <main class="flex-1 flex flex-col overflow-y-auto bg-background-light dark:bg-background-dark w-full">
        <div class="w-full max-w-[1600px] mx-auto flex flex-col flex-1">
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
              <span class="topbar-title">Webhook Dashboard</span>
              <div class="topbar-divider hidden sm:block"></div>
              <form method="GET" class="device-wrap hidden sm:flex">
                <span class="material-symbols-outlined">videocam</span>
                <select name="equipment" onchange="this.form.submit()" class="device-select">
                  <option value="">ทุกอุปกรณ์</option>
                  <?php foreach ($equipmentOptions as $eq): ?>
                    <option value="<?= htmlspecialchars($eq['equipment_id']) ?>" <?= $equipment === $eq['equipment_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($eq['equipment_name'] ?: $eq['equipment_id']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="device-arrow">▾</span>
                <input type="hidden" name="range" value="<?= htmlspecialchars($time_range) ?>">
                <?php if ($location): ?><input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>"><?php endif; ?>
                <?php if ($event_type): ?><input type="hidden" name="event_type" value="<?= htmlspecialchars($event_type) ?>"><?php endif; ?>
                <?php if ($date_from): ?><input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>"><?php endif; ?>
                <?php if ($date_to): ?><input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>"><?php endif; ?>
              </form>
            </div>
            <div class="topbar-right">
              <span class="topbar-meta hidden xl:inline">อัปเดต: <?= date('d/m/Y H:i') ?></span>
              <button onclick="document.documentElement.classList.toggle('dark')" class="icon-btn tap-target">
                <span class="material-symbols-outlined text-lg">dark_mode</span>
              </button>
              <a href="index.php" class="icon-btn tap-target">
                <span class="material-symbols-outlined text-lg">refresh</span>
              </a>
            </div>
          </div>

          <!-- Content Area -->
          <div class="p-4 sm:p-6 lg:p-8 space-y-5 sm:space-y-6 lg:space-y-8 page-enter">

            <!-- Mobile Search Bar (visible only on mobile) -->
            <form method="GET" class="relative sm:hidden">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#687d82] text-xl pointer-events-none z-10">videocam</span>
              <select 
                name="equipment" 
                onchange="this.form.submit()"
                class="w-full bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent appearance-none cursor-pointer">
                <option value="">ทุกอุปกรณ์</option>
                <?php foreach ($equipmentOptions as $eq): ?>
                  <option value="<?= htmlspecialchars($eq['equipment_id']) ?>" <?= $equipment === $eq['equipment_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($eq['equipment_name'] ?: $eq['equipment_id']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="range" value="<?= htmlspecialchars($time_range) ?>">
              <?php if ($location): ?><input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>"><?php endif; ?>
              <?php if ($event_type): ?><input type="hidden" name="event_type" value="<?= htmlspecialchars($event_type) ?>"><?php endif; ?>
              <?php if ($date_from): ?><input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>"><?php endif; ?>
              <?php if ($date_to): ?><input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>"><?php endif; ?>
            </form>

            <!-- Page Title & Time Range Toggle -->
            <div class="flex flex-col gap-4 sm:gap-6">
              <div class="space-y-1">
                <p class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight"><?= htmlspecialchars($pageTitle) ?></p>
                <p class="text-xs sm:text-sm text-[#687d82] dark:text-white/60"><?= htmlspecialchars($pageSubtitle) ?></p>
              </div>
              <div class="overflow-x-auto hide-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
                <div class="flex items-center bg-[#f1f3f4] dark:bg-white/5 p-1 rounded-xl w-fit">
                  <a href="?<?= buildQuery(['range' => 'daily', 'page' => 1]) ?>" class="px-3 sm:px-4 lg:px-6 py-2 rounded-lg text-xs sm:text-sm whitespace-nowrap <?= $time_range === 'daily' ? 'bg-white dark:bg-primary shadow-sm text-[#121617] dark:text-white font-bold' : 'text-[#687d82] dark:text-white/50 hover:text-primary font-medium' ?> transition-colors tap-target">รายวัน</a>
                  <a href="?<?= buildQuery(['range' => 'monthly', 'page' => 1]) ?>" class="px-3 sm:px-4 lg:px-6 py-2 rounded-lg text-xs sm:text-sm whitespace-nowrap <?= $time_range === 'monthly' ? 'bg-white dark:bg-primary shadow-sm text-[#121617] dark:text-white font-bold' : 'text-[#687d82] dark:text-white/50 hover:text-primary font-medium' ?> transition-colors tap-target">รายเดือน</a>
                  <a href="?<?= buildQuery(['range' => 'yearly', 'page' => 1]) ?>" class="px-3 sm:px-4 lg:px-6 py-2 rounded-lg text-xs sm:text-sm whitespace-nowrap <?= $time_range === 'yearly' ? 'bg-white dark:bg-primary shadow-sm text-[#121617] dark:text-white font-bold' : 'text-[#687d82] dark:text-white/50 hover:text-primary font-medium' ?> transition-colors tap-target">รายปี</a>
                  <a href="?<?= buildQuery(['range' => 'all', 'page' => 1]) ?>" class="px-3 sm:px-4 lg:px-6 py-2 rounded-lg text-xs sm:text-sm whitespace-nowrap <?= $time_range === 'all' ? 'bg-white dark:bg-primary shadow-sm text-[#121617] dark:text-white font-bold' : 'text-[#687d82] dark:text-white/50 hover:text-primary font-medium' ?> transition-colors tap-target">ภาพรวม</a>
                </div>
              </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
              <!-- Card 1: Events in this period -->
              <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-5 lg:p-6 rounded-xl shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-2 sm:mb-4">
                  <span class="text-[10px] sm:text-xs font-semibold text-[#687d82] dark:text-white/60 uppercase tracking-wider leading-tight"><?= htmlspecialchars($periodLabel) ?></span>
                  <span class="material-symbols-outlined text-primary/40 text-lg sm:text-2xl">notifications</span>
                </div>
                <div class="flex items-baseline gap-1 sm:gap-2">
                  <h3 class="text-lg sm:text-xl lg:text-2xl font-extrabold"><?= number_format($currentPeriod) ?></h3>
                  <span class="text-[10px] sm:text-xs font-bold text-[#687d82] hidden xs:inline">รายการ</span>
                </div>
                <div class="mt-3 sm:mt-4 flex gap-0.5 sm:gap-1 items-end h-6 sm:h-8">
                  <?php 
                  $miniChart = array_slice($chartData, -8);
                  $miniMax = max(array_column($miniChart, 'event_count') ?: [1]);
                  foreach ($miniChart as $i => $bar): 
                    $barH = $miniMax > 0 ? round(($bar['event_count'] / $miniMax) * 100) : 10;
                    $isLast = ($i === count($miniChart) - 1);
                  ?>
                  <div class="w-full <?= $isLast ? 'bg-primary shadow-[0_-2px_8px_rgba(26,74,86,0.3)]' : 'bg-primary/20' ?> rounded-sm" style="height: <?= max($barH, 8) ?>%"></div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Card 2: Comparison with previous period -->
              <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-5 lg:p-6 rounded-xl shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-2 sm:mb-4">
                  <span class="text-[10px] sm:text-xs font-semibold text-[#687d82] dark:text-white/60 uppercase tracking-wider"><?= $time_range !== 'all' ? 'เทียบ' . $prevLabel : 'ช่วงเวลา' ?></span>
                  <span class="material-symbols-outlined text-primary/40 text-lg sm:text-2xl"><?= $changeDirection === 'up' ? 'trending_up' : ($changeDirection === 'down' ? 'trending_down' : 'trending_flat') ?></span>
                </div>
                <?php if ($time_range !== 'all'): ?>
                <div class="flex items-baseline gap-1.5 sm:gap-2">
                  <h3 class="text-lg sm:text-xl lg:text-2xl font-extrabold"><?= number_format($prevPeriod) ?></h3>
                  <?php if ($changePercent != 0): ?>
                  <span class="text-[10px] sm:text-xs font-bold px-1.5 py-0.5 rounded <?= $changeDirection === 'up' ? 'bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400' : 'bg-red-50 dark:bg-red-500/10 text-red-500 dark:text-red-400' ?>">
                    <?= $changeDirection === 'up' ? '+' : '' ?><?= $changePercent ?>%
                  </span>
                  <?php endif; ?>
                </div>
                <p class="text-[9px] sm:text-[10px] mt-1.5 sm:mt-2 text-[#687d82] dark:text-white/40 font-bold uppercase"><?= htmlspecialchars($prevLabel) ?> <?= number_format($prevPeriod) ?> รายการ</p>
                <?php else: ?>
                <div class="flex items-baseline gap-1 sm:gap-2">
                  <?php if (!empty($statsRow['first_event'])): ?>
                  <h3 class="text-sm sm:text-base lg:text-lg font-extrabold"><?= date('d/m/Y', strtotime($statsRow['first_event'])) ?></h3>
                  <?php else: ?>
                  <h3 class="text-lg font-extrabold">-</h3>
                  <?php endif; ?>
                </div>
                <p class="text-[9px] sm:text-[10px] mt-1.5 sm:mt-2 text-[#687d82] dark:text-white/40 font-bold uppercase">ข้อมูลตั้งแต่วันแรก</p>
                <?php endif; ?>
              </div>

              <!-- Card 3: Average -->
              <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-5 lg:p-6 rounded-xl shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-2 sm:mb-4">
                  <span class="text-[10px] sm:text-xs font-semibold text-[#687d82] dark:text-white/60 uppercase tracking-wider"><?= htmlspecialchars($avgLabel) ?></span>
                  <span class="material-symbols-outlined text-primary/40 text-lg sm:text-2xl">speed</span>
                </div>
                <div class="flex items-baseline gap-1 sm:gap-2">
                  <h3 class="text-lg sm:text-xl lg:text-2xl font-extrabold"><?= $avgValue ?></h3>
                  <span class="text-[10px] sm:text-xs font-bold text-[#687d82] hidden xs:inline">รายการ</span>
                </div>
                <div class="mt-3 sm:mt-4 w-full bg-[#f1f3f4] dark:bg-white/10 h-1.5 sm:h-2 rounded-full overflow-hidden">
                  <?php $avgPercent = $currentPeriod > 0 ? min(round(((float)$avgValue / max($currentPeriod, 1)) * 300), 100) : 0; ?>
                  <div class="bg-primary h-full transition-all" style="width: <?= $avgPercent ?>%"></div>
                </div>
                <p class="text-[9px] sm:text-[10px] mt-1.5 sm:mt-2 text-[#687d82] dark:text-white/40 font-bold uppercase">อัตราเฉลี่ยในช่วงนี้</p>
              </div>

              <!-- Card 4: Equipment & Locations -->
              <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-5 lg:p-6 rounded-xl shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-2 sm:mb-4">
                  <span class="text-[10px] sm:text-xs font-semibold text-[#687d82] dark:text-white/60 uppercase tracking-wider">อุปกรณ์</span>
                  <span class="material-symbols-outlined text-primary/40 text-lg sm:text-2xl">devices</span>
                </div>
                <div class="flex items-baseline gap-1 sm:gap-2">
                  <h3 class="text-lg sm:text-xl lg:text-2xl font-extrabold"><?= number_format($periodEquipment) ?></h3>
                  <span class="text-[10px] sm:text-xs font-bold text-[#687d82] hidden xs:inline">เครื่อง</span>
                </div>
                <div class="mt-3 sm:mt-4 flex items-center gap-1 sm:gap-2 text-[10px] sm:text-xs text-[#687d82] dark:text-white/60 font-medium">
                  <span class="material-symbols-outlined text-xs sm:text-sm text-primary">location_on</span>
                  <span><?= number_format($periodLocations) ?> สถานที่</span>
                </div>
              </div>
            </div>

            <!-- Chart + Side Panels -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 sm:gap-6">
              <!-- Chart Section -->
              <div class="xl:col-span-2 bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-6 rounded-xl shadow-sm">
                <div class="flex flex-col xs:flex-row xs:items-center justify-between gap-3 sm:gap-4 mb-5 sm:mb-8">
                  <div>
                    <h4 class="text-sm sm:text-base lg:text-lg font-bold"><?= htmlspecialchars($chartTitle) ?></h4>
                    <p class="text-xs sm:text-sm text-[#687d82] dark:text-white/60"><?= htmlspecialchars($chartSubtitle) ?></p>
                  </div>
                  <a href="?<?= buildQuery([]) ?>" class="p-2 rounded hover:bg-background-light dark:hover:bg-white/5 w-fit self-end xs:self-auto">
                    <span class="material-symbols-outlined text-lg">refresh</span>
                  </a>
                </div>
                <?php if (!empty($chartData) && $maxCount > 0): ?>
                <div class="relative h-[220px] sm:h-[280px] lg:h-[340px]">
                  <!-- Combined Y-axis labels + Grid lines (in same rows = perfect alignment) -->
                  <div class="absolute inset-0 flex flex-col justify-between pt-8 pb-6 sm:pb-7 pointer-events-none z-0">
                    <?php foreach ([1, 0.75, 0.5, 0.25, 0] as $frac): ?>
                    <div class="flex items-center">
                      <span class="w-[32px] sm:w-[44px] shrink-0 text-right pr-1 sm:pr-2 text-[10px] sm:text-xs lg:text-sm text-[#687d82] font-bold leading-none"><?= number_format($niceMax * $frac) ?></span>
                      <div class="flex-1 border-t border-dashed border-gray-200 dark:border-white/10"></div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <!-- Bars -->
                  <div class="absolute inset-0 pt-8 pb-6 sm:pb-7 pl-[36px] sm:pl-[52px] flex items-end gap-px sm:gap-1 <?= count($chartData) <= 12 ? 'lg:gap-3' : 'lg:gap-px' ?> z-[1]" style="overflow-x: clip; overflow-y: visible;">
                    <?php 
                    $barCount = count($chartData);
                    // Determine label display interval: show every Nth label to avoid crowding
                    if ($barCount <= 24) {
                        $labelEvery = 1; // show all labels (covers daily hourly view 0-23)
                    } elseif ($barCount <= 31) {
                        $labelEvery = 2; // e.g. daily view of a month → every 2 days
                    } else {
                        $labelEvery = (int)ceil($barCount / 15); // keep ~15 labels visible
                    }
                    foreach ($chartData as $i => $bar): 
                      $barH = $niceMax > 0 ? round(($bar['event_count'] / $niceMax) * 100) : 3;
                      $isLast = ($i === $barCount - 1);
                      $isMax = ($bar['event_count'] == $maxCount && $bar['event_count'] > 0);
                      $showThisLabel = ($i % $labelEvery === 0) || $isLast || $isMax;
                    ?>
                    <div class="flex-1 <?= $isMax ? 'bg-primary shadow-[0_-4px_12px_rgba(26,74,86,0.3)]' : ($bar['event_count'] > 0 ? 'bg-primary/20 hover:bg-primary/60' : 'bg-gray-100 dark:bg-white/5') ?> rounded-t-sm group relative transition-all" style="height: <?= max($barH, 2) ?>%; min-width: <?= $barCount > 20 ? '8px' : ($barCount > 12 ? '14px' : '0') ?>;">
                      <?php if ($showThisLabel): ?>
                      <span class="absolute -bottom-4 sm:-bottom-5 left-1/2 -translate-x-1/2 text-[7px] sm:text-[9px] font-bold <?= $isMax ? 'text-primary' : 'text-[#687d82]' ?> whitespace-nowrap"><?= htmlspecialchars($bar['chart_label']) ?></span>
                      <?php endif; ?>
                      <?php if ($isMax): ?>
                      <div class="absolute -top-6 sm:-top-8 left-1/2 -translate-x-1/2 bg-primary text-white text-[8px] sm:text-xs py-0.5 px-1 sm:px-2 rounded font-bold whitespace-nowrap z-20"><?= number_format($bar['event_count']) ?></div>
                      <?php endif; ?>
                      <!-- Tooltip on hover -->
                      <?php if (!$isMax): ?>
                      <div class="absolute <?= $barH > 60 ? 'top-2' : '-top-6 sm:-top-8' ?> left-1/2 -translate-x-1/2 bg-gray-800/90 text-white text-[9px] sm:text-[11px] py-0.5 px-1.5 sm:px-2 rounded font-bold whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-20"><?= number_format($bar['event_count']) ?></div>
                      <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php else: ?>
                <div class="flex items-center justify-center h-[150px] sm:h-[200px] lg:h-[300px] text-[#687d82] dark:text-white/40">
                  <div class="text-center">
                    <span class="material-symbols-outlined text-4xl sm:text-5xl mb-3 sm:mb-4 block opacity-30">bar_chart</span>
                    <p class="text-xs sm:text-sm font-medium">ยังไม่มีข้อมูลในช่วงเวลานี้</p>
                  </div>
                </div>
                <?php endif; ?>
              </div>

              <!-- Side Panels: Top Events & Top Equipments -->
              <div class="flex flex-col gap-5 sm:gap-6">
                <!-- Top Event Types -->
                <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-5 rounded-xl shadow-sm flex-1">
                  <h4 class="text-xs sm:text-sm font-bold mb-3 sm:mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-lg">warning</span>
                    ประเภทเหตุการณ์ยอดนิยม
                  </h4>
                  <?php if (!empty($topEvents)): ?>
                  <div class="space-y-2.5 sm:space-y-3">
                    <?php 
                    $topMax = (int)$topEvents[0]['cnt'];
                    foreach ($topEvents as $te): 
                      $pct = $topMax > 0 ? round(($te['cnt'] / $topMax) * 100) : 0;
                    ?>
                    <div>
                      <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] sm:text-xs font-medium truncate mr-2"><?= htmlspecialchars($te['event_type_id'] ?? '-') ?></span>
                        <span class="text-[11px] sm:text-xs font-bold text-[#687d82] dark:text-white/50 shrink-0"><?= number_format($te['cnt']) ?></span>
                      </div>
                      <div class="w-full bg-[#f1f3f4] dark:bg-white/10 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-primary/60 h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?>
                  <p class="text-xs text-[#687d82] dark:text-white/40 text-center py-4">ไม่มีข้อมูล</p>
                  <?php endif; ?>
                </div>

                <!-- Top Equipments -->
                <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 p-4 sm:p-5 rounded-xl shadow-sm flex-1">
                  <h4 class="text-xs sm:text-sm font-bold mb-3 sm:mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-lg">videocam</span>
                    อุปกรณ์ที่ detect สูงสุด
                  </h4>
                  <?php if (!empty($topEquipments)): ?>
                  <div class="space-y-2.5 sm:space-y-3">
                    <?php 
                    $equipMax = (int)$topEquipments[0]['cnt'];
                    foreach ($topEquipments as $idx => $eq): 
                      $pct = $equipMax > 0 ? round(($eq['cnt'] / $equipMax) * 100) : 0;
                    ?>
                    <div>
                      <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] sm:text-xs font-medium truncate mr-2 flex items-center gap-1">
                          <span class="text-[10px] font-bold text-primary/60 dark:text-white/30">#<?= $idx + 1 ?></span>
                          <?= htmlspecialchars($eq['equipment_label']) ?>
                        </span>
                        <span class="text-[11px] sm:text-xs font-bold text-[#687d82] dark:text-white/50 shrink-0"><?= number_format($eq['cnt']) ?></span>
                      </div>
                      <div class="w-full bg-[#f1f3f4] dark:bg-white/10 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-primary/40 h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?>
                  <p class="text-xs text-[#687d82] dark:text-white/40 text-center py-4">ไม่มีข้อมูล</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl shadow-sm overflow-hidden">
              <!-- Filter Toggle (mobile) -->
              <button onclick="toggleFilter()" class="w-full flex items-center justify-between p-4 sm:hidden tap-target">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-primary text-lg">filter_list</span>
                  <span class="text-sm font-bold">ตัวกรอง</span>
                  <?php if ($location || $equipment || $event_type || $date_from || $date_to): ?>
                    <span class="size-5 bg-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                      <?= ($location ? 1 : 0) + ($equipment ? 1 : 0) + ($event_type ? 1 : 0) + ($date_from ? 1 : 0) + ($date_to ? 1 : 0) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <span id="filterArrow" class="material-symbols-outlined text-[#687d82] text-lg transition-transform">expand_more</span>
              </button>
              <div id="filterPanel" class="hidden sm:block">
                <form method="GET" class="p-4 sm:p-6 flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-4 sm:items-end">
                  <input type="hidden" name="range" value="<?= htmlspecialchars($time_range) ?>">
                  <div class="flex flex-col gap-1.5 flex-1 sm:min-w-[160px] lg:min-w-[180px]">
                    <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">สถานที่</label>
                    <select name="location" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                      <option value="">ทุกสถานที่</option>
                      <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                          <?= htmlspecialchars($loc) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="flex flex-col gap-1.5 flex-1 sm:min-w-[160px] lg:min-w-[180px]">
                    <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">อุปกรณ์</label>
                    <select name="equipment" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                      <option value="">ทุกอุปกรณ์</option>
                      <?php foreach ($equipmentOptions as $eq): ?>
                        <option value="<?= htmlspecialchars($eq['equipment_id']) ?>" <?= $equipment === $eq['equipment_id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($eq['equipment_name'] ?: $eq['equipment_id']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="flex flex-col gap-1.5 flex-1 sm:min-w-[160px] lg:min-w-[180px]">
                    <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">ประเภทเหตุการณ์</label>
                    <select name="event_type" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                      <option value="">ทุกประเภท</option>
                      <?php foreach ($eventTypeOptions as $etId): ?>
                        <option value="<?= htmlspecialchars($etId) ?>" <?= $event_type === $etId ? 'selected' : '' ?>>
                          <?= htmlspecialchars($etId) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="grid grid-cols-2 gap-3 sm:contents">
                    <div class="flex flex-col gap-1.5 flex-1 sm:min-w-[160px] lg:min-w-[180px]">
                      <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">วันที่เริ่ม</label>
                      <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                    </div>
                    <div class="flex flex-col gap-1.5 flex-1 sm:min-w-[160px] lg:min-w-[180px]">
                      <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">วันที่สิ้นสุด</label>
                      <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 sm:py-2 text-sm focus:ring-2 focus:ring-primary focus:border-transparent tap-target">
                    </div>
                  </div>
                  <div class="flex gap-2 pt-1 sm:pt-0">
                    <button type="submit" class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-5 py-2.5 sm:py-2 text-sm font-bold bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors tap-target">
                      <span class="material-symbols-outlined text-sm">filter_list</span> กรอง
                    </button>
                    <a href="index.php" class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 text-sm font-bold bg-background-light dark:bg-white/5 rounded-lg border border-[#dde2e4] dark:border-white/10 hover:border-primary/30 transition-colors tap-target">
                      <span class="material-symbols-outlined text-sm">restart_alt</span> รีเซ็ต
                    </a>
                  </div>
                </form>
              </div>
            </div>

            <!-- Events Section -->
            <div class="bg-white dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-xl overflow-hidden shadow-sm flex flex-col">
              <div class="p-4 sm:p-6 border-b border-[#dde2e4] dark:border-white/10 flex items-center justify-between gap-3">
                <h4 class="text-sm sm:text-base lg:text-lg font-bold">Recent Events</h4>
                <div class="flex gap-2">
                  <button onclick="exportCSV()" class="flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 text-[10px] sm:text-xs font-bold bg-background-light dark:bg-white/5 rounded-lg border border-[#dde2e4] dark:border-white/10 hover:border-primary/30 transition-colors tap-target">
                    <span class="material-symbols-outlined text-sm">download</span> <span class="hidden xs:inline">CSV</span>
                  </button>
                </div>
              </div>

              <?php if (empty($events)): ?>
              <!-- Empty State -->
              <div class="flex flex-col items-center justify-center py-12 sm:py-16 lg:py-20 text-[#687d82] dark:text-white/40 px-4">
                <span class="material-symbols-outlined text-5xl sm:text-6xl mb-3 sm:mb-4 opacity-30">inbox</span>
                <h3 class="text-base sm:text-lg font-bold mb-1 sm:mb-2">ไม่พบเหตุการณ์</h3>
                <p class="text-xs sm:text-sm text-center">เหตุการณ์ Webhook จะแสดงที่นี่เมื่อได้รับข้อมูล</p>
              </div>
              <?php else: ?>

              <!-- Desktop Table (hidden on mobile) -->
              <div class="overflow-x-auto custom-scrollbar hidden md:block">
                <table class="w-full text-left min-w-[900px]">
                  <thead class="bg-background-light dark:bg-white/5 text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest">
                    <tr>
                      <th class="px-3 lg:px-4 py-3 lg:py-4">รูปภาพ</th>
                      <th class="px-3 lg:px-4 py-3 lg:py-4">อุปกรณ์</th>
                      <th class="px-3 lg:px-4 py-3 lg:py-4">สถานที่</th>
                      <th class="px-3 lg:px-4 py-3 lg:py-4">ประเภท</th>
                      <th class="px-3 lg:px-4 py-3 lg:py-4">พิกัด</th>
                      <th class="px-3 lg:px-4 py-3 lg:py-4">วันที่</th>
                      <th class="px-3 lg:px-4 py-3 lg:py-4 text-right">IP</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#dde2e4] dark:divide-white/10 text-sm">
                    <?php foreach ($events as $event): ?>
                    <tr class="hover:bg-primary/5 transition-colors group">
                      <td class="px-3 lg:px-4 py-3 lg:py-4">
                        <?php if ($event['has_image']): ?>
                          <img 
                            src="back_end/api_image.php?id=<?= $event['id'] ?>" 
                            loading="lazy"
                            class="size-12 lg:size-14 object-cover rounded-lg cursor-pointer hover:scale-105 transition-transform border border-[#dde2e4] dark:border-white/10 bg-gray-100 dark:bg-white/5" 
                            onclick="showImage(this.src)"
                            alt="Event image"
                          >
                        <?php else: ?>
                          <div class="size-12 lg:size-14 bg-background-light dark:bg-white/5 rounded-lg flex items-center justify-center border border-[#dde2e4] dark:border-white/10">
                            <span class="material-symbols-outlined text-[#687d82]/40 text-lg">image_not_supported</span>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="px-3 lg:px-4 py-3 lg:py-4">
                        <div class="max-w-[160px]">
                          <?php if ($event['equipment_name']): ?>
                            <div class="text-xs font-bold truncate" title="<?= htmlspecialchars($event['equipment_name']) ?>"><?= htmlspecialchars($event['equipment_name']) ?></div>
                          <?php endif; ?>
                          <code class="text-[9px] lg:text-[10px] text-[#687d82] dark:text-white/40 font-mono break-all" title="<?= htmlspecialchars($event['equipment_id'] ?? '') ?>">
                            <?= htmlspecialchars(substr($event['equipment_id'] ?? '', 0, 12)) ?>...
                          </code>
                          <?php if ($event['box_code']): ?>
                            <div class="text-[9px] text-[#687d82] dark:text-white/30 mt-0.5">Box: <?= htmlspecialchars($event['box_code']) ?></div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-3 lg:px-4 py-3 lg:py-4">
                        <div class="max-w-[160px]">
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/80">
                            <span class="material-symbols-outlined text-xs">location_on</span>
                            <?= htmlspecialchars($event['installation_location'] ?? 'N/A') ?>
                          </span>
                          <?php if ($event['installation_area']): ?>
                            <div class="text-[9px] text-[#687d82] dark:text-white/30 mt-1 truncate" title="<?= htmlspecialchars($event['installation_area']) ?>">
                              <span class="material-symbols-outlined text-[9px] align-middle">area_chart</span>
                              <?= htmlspecialchars($event['installation_area']) ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-3 lg:px-4 py-3 lg:py-4">
                        <div class="max-w-[160px]">
                          <?php if ($event['task_name']): ?>
                            <div class="text-xs font-bold truncate" title="<?= htmlspecialchars($event['task_name']) ?>"><?= htmlspecialchars($event['task_name']) ?></div>
                          <?php else: ?>
                            <span class="text-[10px] text-[#687d82]/30">-</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-3 lg:px-4 py-3 lg:py-4">
                        <?php if ($event['coordinate']): ?>
                          <a href="https://www.google.com/maps?q=<?= urlencode($event['coordinate']) ?>" 
                             target="_blank" 
                             class="inline-flex items-center gap-1 text-[11px] text-primary dark:text-sky-400 hover:underline font-medium">
                            <span class="material-symbols-outlined text-xs">map</span>
                            <?= htmlspecialchars(substr($event['coordinate'], 0, 20)) ?>
                          </a>
                        <?php else: ?>
                          <span class="text-xs text-[#687d82] dark:text-white/30">N/A</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-3 lg:px-4 py-3 lg:py-4">
                        <div class="text-xs lg:text-sm font-medium"><?= date('d M Y', strtotime($event['received_at'])) ?></div>
                        <div class="text-[10px] lg:text-[11px] text-[#687d82] dark:text-white/40"><?= date('H:i:s', strtotime($event['received_at'])) ?></div>
                      </td>
                      <td class="px-3 lg:px-4 py-3 lg:py-4 text-right">
                        <code class="text-[10px] lg:text-[11px] text-[#687d82] dark:text-white/40"><?= htmlspecialchars($event['ip_address'] ?? 'N/A') ?></code>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- Mobile Card View (hidden on desktop) -->
              <div class="md:hidden divide-y divide-[#dde2e4] dark:divide-white/10">
                <?php foreach ($events as $event): ?>
                <div class="p-4 hover:bg-primary/5 transition-colors">
                  <div class="flex gap-3">
                    <!-- Image -->
                    <div class="shrink-0">
                      <?php if ($event['has_image']): ?>
                        <img 
                          src="back_end/api_image.php?id=<?= $event['id'] ?>" 
                          loading="lazy"
                          class="size-16 sm:size-20 object-cover rounded-xl cursor-pointer active:scale-95 transition-transform border border-[#dde2e4] dark:border-white/10 bg-gray-100 dark:bg-white/5" 
                          onclick="showImage(this.src)"
                          alt="Event image"
                        >
                      <?php else: ?>
                        <div class="size-16 sm:size-20 bg-background-light dark:bg-white/5 rounded-xl flex items-center justify-center border border-[#dde2e4] dark:border-white/10">
                          <span class="material-symbols-outlined text-[#687d82]/40">image_not_supported</span>
                        </div>
                      <?php endif; ?>
                    </div>
                    <!-- Info -->
                    <div class="flex-1 min-w-0 space-y-1.5">
                      <div class="flex items-start justify-between gap-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/80 truncate">
                          <span class="material-symbols-outlined text-xs shrink-0">location_on</span>
                          <span class="truncate"><?= htmlspecialchars($event['installation_location'] ?? 'N/A') ?></span>
                        </span>
                        <?php if ($event['task_name']): ?>
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-blue-50 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400 truncate">
                            <span class="material-symbols-outlined text-xs shrink-0">task</span>
                            <span class="truncate"><?= htmlspecialchars($event['task_name']) ?></span>
                          </span>
                        <?php endif; ?>
                      </div>
                      <?php if ($event['equipment_name']): ?>
                        <div class="text-[11px] font-bold truncate">
                          <span class="material-symbols-outlined text-xs align-middle text-[#687d82]">videocam</span>
                          <?= htmlspecialchars($event['equipment_name']) ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($event['str_res']): ?>
                        <p class="text-[10px] text-[#687d82] dark:text-white/40 line-clamp-1"><?= htmlspecialchars($event['str_res']) ?></p>
                      <?php endif; ?>
                      <div class="flex items-center gap-1 text-[11px] text-[#687d82] dark:text-white/50">
                        <span class="material-symbols-outlined text-xs">schedule</span>
                        <?= date('d M Y H:i:s', strtotime($event['received_at'])) ?>
                      </div>
                      <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <?php if ($event['coordinate']): ?>
                          <a href="https://www.google.com/maps?q=<?= urlencode($event['coordinate']) ?>" 
                             target="_blank" 
                             class="inline-flex items-center gap-0.5 text-[11px] text-primary dark:text-sky-400 font-medium">
                            <span class="material-symbols-outlined text-xs">map</span>
                            แผนที่
                          </a>
                        <?php endif; ?>
                        <span class="text-[10px] text-[#687d82] dark:text-white/30 font-mono"><?= htmlspecialchars($event['ip_address'] ?? '') ?></span>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Pagination Footer -->
              <div class="p-3 sm:p-4 bg-background-light dark:bg-white/5 border-t border-[#dde2e4] dark:border-white/10 flex flex-col xs:flex-row items-center justify-between gap-3">
                <p class="text-[10px] sm:text-xs text-[#687d82] dark:text-white/40 font-medium">
                  แสดง <?= $showStart ?>-<?= $showEnd ?> จาก <?= number_format($totalEvents) ?>
                </p>
                <?php if ($totalPages > 1): ?>
                <div class="flex items-center gap-1 sm:gap-2">
                  <?php if ($page > 1): ?>
                    <a href="?<?= buildQuery(['page' => $page - 1]) ?>" class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 hover:bg-white dark:hover:bg-white/5 transition-colors tap-target">
                      <span class="material-symbols-outlined text-base sm:text-lg">chevron_left</span>
                    </a>
                  <?php else: ?>
                    <button class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 disabled:opacity-50" disabled>
                      <span class="material-symbols-outlined text-base sm:text-lg">chevron_left</span>
                    </button>
                  <?php endif; ?>

                  <?php 
                  $start = max(1, $page - 1);
                  $end = min($totalPages, $page + 1);
                  // Show first page if not in range
                  if ($start > 1): ?>
                    <a href="?<?= buildQuery(['page' => 1]) ?>" class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 text-[10px] sm:text-xs font-bold hover:bg-white dark:hover:bg-white/5 transition-colors tap-target">1</a>
                    <?php if ($start > 2): ?>
                      <span class="text-[10px] text-[#687d82] px-0.5">...</span>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $page): ?>
                      <button class="size-8 sm:size-9 flex items-center justify-center rounded bg-primary text-white text-[10px] sm:text-xs font-bold"><?= $i ?></button>
                    <?php else: ?>
                      <a href="?<?= buildQuery(['page' => $i]) ?>" class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 text-[10px] sm:text-xs font-bold hover:bg-white dark:hover:bg-white/5 transition-colors tap-target"><?= $i ?></a>
                    <?php endif; ?>
                  <?php endfor; ?>

                  <?php // Show last page if not in range
                  if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                      <span class="text-[10px] text-[#687d82] px-0.5">...</span>
                    <?php endif; ?>
                    <a href="?<?= buildQuery(['page' => $totalPages]) ?>" class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 text-[10px] sm:text-xs font-bold hover:bg-white dark:hover:bg-white/5 transition-colors tap-target"><?= $totalPages ?></a>
                  <?php endif; ?>

                  <?php if ($page < $totalPages): ?>
                    <a href="?<?= buildQuery(['page' => $page + 1]) ?>" class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 hover:bg-white dark:hover:bg-white/5 transition-colors tap-target">
                      <span class="material-symbols-outlined text-base sm:text-lg">chevron_right</span>
                    </a>
                  <?php else: ?>
                    <button class="size-8 sm:size-9 flex items-center justify-center rounded border border-[#dde2e4] dark:border-white/10 disabled:opacity-50" disabled>
                      <span class="material-symbols-outlined text-base sm:text-lg">chevron_right</span>
                    </button>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      </main>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black/90 z-[60] hidden items-center justify-center p-4" onclick="closeModal()">
      <button class="absolute top-4 right-4 sm:top-6 sm:right-8 text-white/80 hover:text-white transition-colors tap-target z-10" onclick="closeModal()">
        <span class="material-symbols-outlined text-3xl sm:text-4xl">close</span>
      </button>
      <img id="modalImage" src="" alt="Full size image" class="max-w-full max-h-full rounded-xl shadow-2xl object-contain" onclick="event.stopPropagation()">
    </div>

    <script>
      // Sidebar toggle
      function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const isOpen = !sidebar.classList.contains('sidebar-closed');
        
        if (isOpen) {
          sidebar.classList.add('sidebar-closed');
          overlay.classList.add('opacity-0', 'pointer-events-none');
          overlay.classList.remove('opacity-100', 'pointer-events-auto');
          document.body.style.overflow = '';
        } else {
          sidebar.classList.remove('sidebar-closed');
          overlay.classList.remove('opacity-0', 'pointer-events-none');
          overlay.classList.add('opacity-100', 'pointer-events-auto');
          document.body.style.overflow = 'hidden';
        }
      }

      // Filter toggle (mobile)
      function toggleFilter() {
        const panel = document.getElementById('filterPanel');
        const arrow = document.getElementById('filterArrow');
        const isHidden = panel.classList.contains('hidden');
        
        if (isHidden) {
          panel.classList.remove('hidden');
          arrow.style.transform = 'rotate(180deg)';
        } else {
          panel.classList.add('hidden');
          arrow.style.transform = 'rotate(0deg)';
        }
      }

      // Image modal
      function showImage(src) {
        document.getElementById('modalImage').src = src;
        const modal = document.getElementById('imageModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
      }

      function closeModal() {
        const modal = document.getElementById('imageModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
      }

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeModal();
          // Also close sidebar on mobile
          const sidebar = document.getElementById('sidebar');
          if (!sidebar.classList.contains('sidebar-closed') && window.innerWidth < 1024) {
            toggleSidebar();
          }
        }
      });

      // Close sidebar on resize to desktop
      window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
          const overlay = document.getElementById('sidebarOverlay');
          overlay.classList.add('opacity-0', 'pointer-events-none');
          overlay.classList.remove('opacity-100', 'pointer-events-auto');
          document.body.style.overflow = '';
        }
      });

      // Swipe to close sidebar on mobile
      let touchStartX = 0;
      document.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
      }, { passive: true });

      document.addEventListener('touchend', function(e) {
        const touchEndX = e.changedTouches[0].clientX;
        const diff = touchStartX - touchEndX;
        const sidebar = document.getElementById('sidebar');
        
        // Swipe left to close sidebar
        if (diff > 80 && !sidebar.classList.contains('sidebar-closed') && window.innerWidth < 1024) {
          toggleSidebar();
        }
        // Swipe right from edge to open sidebar
        if (diff < -80 && touchStartX < 30 && sidebar.classList.contains('sidebar-closed') && window.innerWidth < 1024) {
          toggleSidebar();
        }
      }, { passive: true });

      // Export CSV — show popup to choose options
      function exportCSV() {
        document.getElementById('exportModal').classList.remove('hidden');
        document.getElementById('exportModal').classList.add('flex');
        // Pre-select current range
        const currentRange = new URLSearchParams(window.location.search).get('range') || 'daily';
        document.getElementById('exportRange').value = currentRange;
      }
      function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
        document.getElementById('exportModal').classList.remove('flex');
      }
      function doExport() {
        const range = document.getElementById('exportRange').value;
        const location = document.getElementById('exportLocation').value;
        const eventType = document.getElementById('exportEventType').value;
        const equipment = document.getElementById('exportEquipment').value;
        const dateFrom = document.getElementById('exportDateFrom').value;
        const dateTo = document.getElementById('exportDateTo').value;
        const params = new URLSearchParams();
        params.set('range', range);
        if (location) params.set('location', location);
        if (eventType) params.set('event_type', eventType);
        if (equipment) params.set('equipment', equipment);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        window.location.href = 'back_end/export_csv.php?' + params.toString();
        closeExportModal();
      }
    </script>

    <!-- Export Modal -->
    <div id="exportModal" class="hidden fixed inset-0 z-[100] items-center justify-center bg-black/50 backdrop-blur-sm p-4" onclick="if(event.target===this)closeExportModal()">
      <div class="bg-white dark:bg-[#1a2028] rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden animate-[fadeInUp_0.2s_ease]">
        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-[#dde2e4] dark:border-white/10">
          <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
              <span class="material-symbols-outlined text-primary text-xl">download</span>
            </div>
            <div>
              <h3 class="text-base font-extrabold">Export CSV</h3>
              <p class="text-[11px] text-[#687d82] dark:text-white/40 font-medium">เลือกช่วงเวลาและตัวกรอง</p>
            </div>
          </div>
          <button onclick="closeExportModal()" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
            <span class="material-symbols-outlined text-[#687d82]">close</span>
          </button>
        </div>
        <!-- Body -->
        <div class="p-5 space-y-4">
          <!-- Range -->
          <div>
            <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest mb-1.5 block">ช่วงเวลา</label>
            <div class="grid grid-cols-4 gap-1.5 bg-[#f1f3f4] dark:bg-white/5 p-1 rounded-xl">
              <?php foreach (['daily' => 'รายวัน', 'monthly' => 'รายเดือน', 'yearly' => 'รายปี', 'all' => 'ทั้งหมด'] as $rv => $rl): ?>
              <label class="cursor-pointer">
                <input type="radio" name="exportRangeRadio" value="<?= $rv ?>" class="hidden peer" onchange="document.getElementById('exportRange').value='<?= $rv ?>'" <?= $rv === $time_range ? 'checked' : '' ?>>
                <div class="text-center py-2 rounded-lg text-[11px] sm:text-xs font-bold transition-colors peer-checked:bg-white dark:peer-checked:bg-primary peer-checked:shadow-sm peer-checked:text-[#121617] dark:peer-checked:text-white text-[#687d82] dark:text-white/50 hover:text-primary">
                  <?= $rl ?>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
            <input type="hidden" id="exportRange" value="<?= htmlspecialchars($time_range) ?>">
          </div>
          <!-- Location -->
          <div>
            <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest mb-1.5 block">สถานที่</label>
            <select id="exportLocation" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
              <option value="">ทุกสถานที่</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Event Type -->
          <div>
            <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest mb-1.5 block">ประเภทเหตุการณ์</label>
            <select id="exportEventType" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
              <option value="">ทุกประเภท</option>
              <?php foreach ($eventTypeOptions as $etId): ?>
              <option value="<?= htmlspecialchars($etId) ?>" <?= $event_type === $etId ? 'selected' : '' ?>><?= htmlspecialchars($etId) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Equipment -->
          <div>
            <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest mb-1.5 block">อุปกรณ์</label>
            <select id="exportEquipment" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
              <option value="">ทุกอุปกรณ์</option>
              <?php foreach ($equipmentOptions as $eq): ?>
              <option value="<?= htmlspecialchars($eq['equipment_id']) ?>" <?= $equipment === $eq['equipment_id'] ? 'selected' : '' ?>><?= htmlspecialchars($eq['equipment_name'] ?: $eq['equipment_id']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Date range -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest mb-1.5 block">วันที่เริ่ม</label>
              <input id="exportDateFrom" type="date" value="<?= htmlspecialchars($date_from) ?>" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
              <label class="text-[10px] font-bold text-[#687d82] dark:text-white/40 uppercase tracking-widest mb-1.5 block">วันที่สิ้นสุด</label>
              <input id="exportDateTo" type="date" value="<?= htmlspecialchars($date_to) ?>" class="w-full bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
          </div>
        </div>
        <!-- Footer -->
        <div class="flex gap-3 p-5 pt-0">
          <button onclick="closeExportModal()" class="flex-1 py-2.5 rounded-lg text-sm font-bold bg-background-light dark:bg-white/5 border border-[#dde2e4] dark:border-white/10 hover:border-primary/30 transition-colors">
            ยกเลิก
          </button>
          <button onclick="doExport()" class="flex-1 py-2.5 rounded-lg text-sm font-bold bg-primary text-white hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">download</span>
            ดาวน์โหลด CSV
          </button>
        </div>
      </div>
    </div>
  </body>
</html>
