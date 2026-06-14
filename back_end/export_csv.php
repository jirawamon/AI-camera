<?php
/**
 * Export CSV - ดาวน์โหลดข้อมูลตาม filter/range ที่เลือก
 * ใช้ parameters เดียวกับ index.php (range, location, equipment, event_type, date_from, date_to)
 */

require_once __DIR__ . '/config.php';

$db = getDB();
$db->exec("SET time_zone = '+07:00'");

// Get parameters (same as index.php)
$location = $_GET['location'] ?? '';
$equipment = $_GET['equipment'] ?? '';
$event_type = $_GET['event_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$time_range = $_GET['range'] ?? 'daily';

if (!in_array($time_range, ['daily', 'monthly', 'yearly', 'all'])) {
    $time_range = 'daily';
}

// Time range WHERE
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

// Build WHERE
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
if ($date_from) {
    $where[] = "received_at >= :date_from";
    $params[':date_from'] = $date_from . " 00:00:00";
}
if ($date_to) {
    $where[] = "received_at <= :date_to";
    $params[':date_to'] = $date_to . " 23:59:59";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query all matching events (no LIMIT, exclude image_data for performance)
$stmt = $db->prepare("
    SELECT id, alarm_id, coordinate, equipment_id, equipment_name, 
           send_time, event_type_id, task_id, task_name, str_res,
           installation_area, installation_location, box_code,
           received_at, ip_address
    FROM webhook_events 
    $whereSQL
    ORDER BY received_at DESC
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Range label for filename
$rangeLabels = [
    'daily' => 'รายวัน_' . date('Y-m-d'),
    'monthly' => 'รายเดือน_' . date('Y-m'),
    'yearly' => 'รายปี_' . date('Y'),
    'all' => 'ทั้งหมด',
];
$filename = 'webhook_events_' . ($rangeLabels[$time_range] ?? 'export') . '.csv';

// Send CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM for Excel UTF-8
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header row
fputcsv($output, [
    'ID',
    'Alarm ID',
    'วันที่-เวลา (Received)',
    'Send Time',
    'Equipment ID',
    'ชื่ออุปกรณ์',
    'ประเภทเหตุการณ์',
    'Task ID',
    'Task Name',
    'รายละเอียด',
    'พื้นที่ติดตั้ง',
    'สถานที่ติดตั้ง',
    'Box Code',
    'พิกัด',
    'IP Address',
]);

// Event type mapping
$eventTypeNames = [
    '1' => 'บุคคลเข้าพื้นที่',
    '2' => 'สูบบุหรี่',
    '3' => 'จักรยานยนต์เข้าพื้นที่',
    '4' => 'ทะเลาะวิวาท',
    '5' => 'บุคคลเข้าพื้นที่เสี่ยง',
    '6' => 'เหตุการณ์ผิดปกติ',
];

// Data rows
foreach ($events as $event) {
    $eventTypeName = $eventTypeNames[$event['event_type_id']] ?? $event['event_type_id'];
    
    fputcsv($output, [
        $event['id'],
        $event['alarm_id'] ?? '',
        $event['received_at'],
        $event['send_time'] ?? '',
        $event['equipment_id'] ?? '',
        $event['equipment_name'] ?? '',
        $eventTypeName,
        $event['task_id'] ?? '',
        $event['task_name'] ?? '',
        $event['str_res'] ?? '',
        $event['installation_area'] ?? '',
        $event['installation_location'] ?? '',
        $event['box_code'] ?? '',
        $event['coordinate'] ?? '',
        $event['ip_address'] ?? '',
    ]);
}

fclose($output);
exit;
