<?php
/**
 * Database Migration: Add missing columns to webhook_events
 * 
 * Adds columns for all API parameters that weren't previously stored:
 *   alarm_id, equipment_name, task_id, task_name, str_res, installation_area, box_code
 * 
 * Safe to run multiple times - skips columns that already exist.
 * 
 * Run ONCE via browser: https://yourdomain.com/back_end/add_columns.php
 * Then DELETE this file.
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<h2>Add Missing Columns to webhook_events</h2><pre>\n";

$columns = [
    "ALTER TABLE webhook_events ADD COLUMN alarm_id VARCHAR(255) DEFAULT NULL COMMENT 'Alarm record ID from API' AFTER id",
    "ALTER TABLE webhook_events ADD COLUMN equipment_name VARCHAR(255) DEFAULT NULL COMMENT 'Device Name' AFTER equipment_id",
    "ALTER TABLE webhook_events ADD COLUMN task_id VARCHAR(255) DEFAULT NULL COMMENT 'Task ID' AFTER event_type_id",
    "ALTER TABLE webhook_events ADD COLUMN task_name VARCHAR(255) DEFAULT NULL COMMENT 'Task Name' AFTER task_id",
    "ALTER TABLE webhook_events ADD COLUMN str_res TEXT DEFAULT NULL COMMENT 'Alarm Event Description' AFTER task_name",
    "ALTER TABLE webhook_events ADD COLUMN installation_area VARCHAR(255) DEFAULT NULL COMMENT 'Installation area' AFTER str_res",
    "ALTER TABLE webhook_events ADD COLUMN box_code VARCHAR(255) DEFAULT NULL COMMENT 'Box coding' AFTER coordinate",
];

$ok = 0;
$skip = 0;
$err = 0;

foreach ($columns as $sql) {
    try {
        $db->exec($sql);
        // Extract column name for display
        preg_match('/ADD COLUMN (\w+)/', $sql, $m);
        echo "[OK]   Added column: {$m[1]}\n";
        $ok++;
    } catch (PDOException $e) {
        preg_match('/ADD COLUMN (\w+)/', $sql, $m);
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[SKIP] Column {$m[1]} already exists\n";
            $skip++;
        } else {
            echo "[ERROR] Column {$m[1]}: " . $e->getMessage() . "\n";
            $err++;
        }
    }
}

// Add useful indexes for new columns
echo "\n--- Adding indexes ---\n";
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_alarm_id ON webhook_events (alarm_id)",
    "CREATE INDEX IF NOT EXISTS idx_equipment_name ON webhook_events (equipment_name)",
    "CREATE INDEX IF NOT EXISTS idx_task_id ON webhook_events (task_id)",
    "CREATE INDEX IF NOT EXISTS idx_installation_area ON webhook_events (installation_area)",
    "CREATE INDEX IF NOT EXISTS idx_box_code ON webhook_events (box_code)",
];

foreach ($indexes as $sql) {
    try {
        $db->exec($sql);
        preg_match('/idx_(\w+)/', $sql, $m);
        echo "[OK]   Index: idx_{$m[1]}\n";
    } catch (PDOException $e) {
        preg_match('/idx_(\w+)/', $sql, $m);
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "[SKIP] Index idx_{$m[1]} already exists\n";
        } else {
            echo "[ERROR] Index idx_{$m[1]}: " . $e->getMessage() . "\n";
            $err++;
        }
    }
}

echo "\n=============================\n";
echo "Done: $ok added, $skip skipped, $err errors\n";

// Show final table structure
echo "\n--- Current Table Structure ---\n";
try {
    $cols = $db->query("DESCRIBE webhook_events")->fetchAll();
    foreach ($cols as $col) {
        echo sprintf("  %-25s %-20s %s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL', $col['Default'] !== null ? "DEFAULT '{$col['Default']}'" : '');
    }
} catch (Exception $e) {
    echo "Could not describe table: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "\n<p style='color: red; font-weight: bold;'>⚠️ ลบไฟล์นี้หลังจาก run สำเร็จแล้ว!</p>";
?>
