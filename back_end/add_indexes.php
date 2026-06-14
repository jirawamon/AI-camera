<?php
/**
 * Database Index Migration
 * 
 * Run this file ONCE to add indexes that dramatically speed up queries.
 * Access via browser: https://yourdomain.com/back_end/add_indexes.php
 * 
 * Safe to run multiple times - uses IF NOT EXISTS.
 */

require_once __DIR__ . '/config.php';

$db = getDB();

// ============================================================
// Step 1: Add image_hash column (for deduplication)
// ============================================================
echo "<h2>Schema Migration</h2><pre>\n";
try {
    $db->exec("ALTER TABLE webhook_events ADD COLUMN image_hash VARCHAR(32) DEFAULT NULL");
    echo "[OK] Added column: image_hash\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "[SKIP] Column image_hash already exists\n";
    } else {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}
echo "</pre>";

// ============================================================
// Step 2: Add indexes
// ============================================================
$indexes = [
    // Main sorting/pagination index - speeds up ORDER BY received_at DESC with LIMIT
    "CREATE INDEX IF NOT EXISTS idx_received_at ON webhook_events (received_at DESC)",
    
    // Filter indexes - speeds up WHERE clauses
    "CREATE INDEX IF NOT EXISTS idx_installation_location ON webhook_events (installation_location)",
    "CREATE INDEX IF NOT EXISTS idx_equipment_id ON webhook_events (equipment_id)",
    
    // Composite index for the most common query pattern (filter + sort + paginate)
    "CREATE INDEX IF NOT EXISTS idx_received_location ON webhook_events (received_at DESC, installation_location)",
    
    // Index for date-based stats queries
    "CREATE INDEX IF NOT EXISTS idx_date_received ON webhook_events (received_at)",

    // Index for dedup: quickly find latest event by equipment + compare hash
    "CREATE INDEX IF NOT EXISTS idx_equip_hash ON webhook_events (equipment_id, image_hash)",
];

echo "<h2>Adding Database Indexes</h2><pre>\n";

$success = 0;
$errors = 0;

foreach ($indexes as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] $sql\n";
        $success++;
    } catch (PDOException $e) {
        // MySQL doesn't support IF NOT EXISTS for indexes, handle duplicate
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "[SKIP] Already exists: $sql\n";
            $success++;
        } else {
            echo "[ERROR] $sql\n  -> " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n-----------------------------\n";
echo "Done: $success OK, $errors errors\n";
echo "</pre>";

// Show current indexes
echo "<h3>Current Indexes on webhook_events:</h3><pre>\n";
try {
    $result = $db->query("SHOW INDEX FROM webhook_events");
    $currentIndexes = $result->fetchAll();
    foreach ($currentIndexes as $idx) {
        echo "  - {$idx['Key_name']} on ({$idx['Column_name']}) " . ($idx['Non_unique'] ? '' : '[UNIQUE]') . "\n";
    }
} catch (Exception $e) {
    echo "Could not list indexes: " . $e->getMessage() . "\n";
}
echo "</pre>";
