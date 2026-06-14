<?php

require_once __DIR__ . '/back_end/config.php';

$db = getDB();

// Get filter parameters
$page = max(1, intval($_GET['page'] ?? 1));
$location = $_GET['location'] ?? '';
$equipment = $_GET['equipment'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$offset = ($page - 1) * EVENTS_PER_PAGE;

// Build query with filters
$where = [];
$params = [];

if ($location) {
    $where[] = "installation_location LIKE :location";
    $params[':location'] = "%$location%";
}
if ($equipment) {
    $where[] = "equipment_id LIKE :equipment";
    $params[':equipment'] = "%$equipment%";
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

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM webhook_events $whereSQL");
$countStmt->execute($params);
$totalEvents = $countStmt->fetchColumn();
$totalPages = ceil($totalEvents / EVENTS_PER_PAGE);

// Get events
$stmt = $db->prepare("
    SELECT id, coordinate, equipment_id, send_time, event_type_id, 
           installation_location, image_data, received_at, ip_address
    FROM webhook_events 
    $whereSQL
    ORDER BY received_at DESC 
    LIMIT " . EVENTS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get unique locations for filter dropdown
$locations = $db->query("SELECT DISTINCT installation_location FROM webhook_events WHERE installation_location IS NOT NULL ORDER BY installation_location")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$statsToday = $db->query("SELECT COUNT(*) FROM webhook_events WHERE DATE(received_at) = CURDATE()")->fetchColumn();
$statsWeek = $db->query("SELECT COUNT(*) FROM webhook_events WHERE received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$uniqueEquipment = $db->query("SELECT COUNT(DISTINCT equipment_id) FROM webhook_events")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #334155;
        }
        
        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #f8fafc;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
        }
        
        .stat-card h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #38bdf8;
        }
        
        .filters {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
        }
        
        .filter-group input,
        .filter-group select {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 10px 12px;
            color: #e2e8f0;
            font-size: 14px;
            min-width: 150px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #38bdf8;
        }
        
        .btn {
            background: #38bdf8;
            color: #0f172a;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #7dd3fc;
        }
        
        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .events-table {
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #0f172a;
            padding: 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #334155;
            font-size: 14px;
        }
        
        tr:hover {
            background: #334155;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-location {
            background: #7c3aed20;
            color: #a78bfa;
        }
        
        .badge-equipment {
            background: #059669;
            color: #a7f3d0;
        }
        
        .image-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .image-preview:hover {
            transform: scale(1.1);
        }
        
        .no-image {
            width: 60px;
            height: 60px;
            background: #334155;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 10px 15px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: #38bdf8;
            color: #0f172a;
            border-color: #38bdf8;
        }
        
        .coordinates {
            font-family: monospace;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .timestamp {
            font-size: 12px;
            color: #64748b;
        }
        
        .ip-address {
            font-family: monospace;
            font-size: 12px;
            color: #64748b;
        }
        
        /* Modal for image preview */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 30px;
            color: #fff;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group input,
            .filter-group select {
                width: 100%;
            }
            
            .events-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📡 Webhook Dashboard</h1>
            <span class="timestamp">Last updated: <?= date('Y-m-d H:i:s') ?></span>
        </header>
        
        <!-- Stats Cards -->
        <div class="stats">
            <div class="stat-card">
                <h3>Total Events</h3>
                <div class="value"><?= number_format($totalEvents) ?></div>
            </div>
            <div class="stat-card">
                <h3>Today</h3>
                <div class="value"><?= number_format($statsToday) ?></div>
            </div>
            <div class="stat-card">
                <h3>Last 7 Days</h3>
                <div class="value"><?= number_format($statsWeek) ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Equipment</h3>
                <div class="value"><?= number_format($uniqueEquipment) ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <form class="filters" method="GET">
            <div class="filter-group">
                <label>Location</label>
                <select name="location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Equipment ID</label>
                <input type="text" name="equipment" value="<?= htmlspecialchars($equipment) ?>" placeholder="Search...">
            </div>
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <button type="submit" class="btn">Filter</button>
            <a href="dashboard.php" class="btn btn-secondary">Reset</a>
        </form>
        
        <!-- Events Table -->
        <div class="events-table">
            <?php if (empty($events)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <h3>No events found</h3>
                    <p>Webhook events will appear here when received</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Location</th>
                            <th>Equipment ID</th>
                            <th>Coordinates</th>
                            <th>Received</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <?php if ($event['image_data']): ?>
                                        <img src="data:image/jpeg;base64,<?= $event['image_data'] ?>" 
                                             class="image-preview" 
                                             onclick="showImage(this.src)"
                                             alt="Event image">
                                    <?php else: ?>
                                        <div class="no-image">No image</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-location">
                                        <?= htmlspecialchars($event['installation_location'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-size: 11px;">
                                        <?= htmlspecialchars(substr($event['equipment_id'] ?? '', 0, 16)) ?>...
                                    </code>
                                </td>
                                <td>
                                    <?php if ($event['coordinate']): ?>
                                        <a href="https://www.google.com/maps?q=<?= urlencode($event['coordinate']) ?>" 
                                           target="_blank" 
                                           class="coordinates"
                                           title="Open in Google Maps">
                                            📍 <?= htmlspecialchars(substr($event['coordinate'], 0, 25)) ?>...
                                        </a>
                                    <?php else: ?>
                                        <span class="coordinates">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($event['received_at'])) ?></div>
                                    <div class="timestamp"><?= date('H:i:s', strtotime($event['received_at'])) ?></div>
                                </td>
                                <td>
                                    <span class="ip-address"><?= htmlspecialchars($event['ip_address'] ?? 'N/A') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&location=<?= urlencode($location) ?>&equipment=<?= urlencode($equipment) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">← Prev</a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?page=<?= $i ?>&location=<?= urlencode($location) ?>&equipment=<?= urlencode($equipment) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&location=<?= urlencode($location) ?>&equipment=<?= urlencode($equipment) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Image Modal -->
    <div class="modal" id="imageModal" onclick="closeModal()">
        <span class="modal-close">&times;</span>
        <img id="modalImage" src="" alt="Full size image">
    </div>
    
    <script>
        function showImage(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('imageModal').classList.remove('active');
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
