<?php
/**
 * One-Time Timezone Migration: UTC → Bangkok (UTC+7)
 * 
 * แปลงค่า received_at ที่เก็บเป็น UTC ให้เป็นเวลากรุงเทพ (+7 ชม.)
 * ⚠️ รันครั้งเดียวเท่านั้น! ถ้ารันซ้ำจะบวกเวลาอีก 7 ชม.
 * 
 * วิธีใช้: เปิดผ่าน browser → https://yourdomain.com/back_end/fix_timezone.php
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<h2>🕐 Timezone Migration: UTC → Asia/Bangkok (+07:00)</h2>";
echo "<pre>\n";

// Step 1: Show current data sample
echo "=== ตัวอย่างข้อมูลก่อนแปลง ===\n";
try {
    $sample = $db->query("SELECT id, received_at FROM webhook_events ORDER BY id DESC LIMIT 5")->fetchAll();
    foreach ($sample as $row) {
        echo "  ID {$row['id']}: {$row['received_at']}\n";
    }
} catch (Exception $e) {
    echo "  Error reading data: " . $e->getMessage() . "\n";
}

echo "\n";

// Step 2: Show MySQL server time info
echo "=== ข้อมูล Timezone ===\n";
$nowUtc = $db->query("SELECT UTC_TIMESTAMP() AS utc_now")->fetchColumn();
$nowLocal = $db->query("SELECT NOW() AS local_now")->fetchColumn();
echo "  UTC_TIMESTAMP(): $nowUtc\n";
echo "  NOW() (session): $nowLocal\n";
echo "\n";

// Step 3: Convert old UTC data to Bangkok time
// Skip recent entries (last 30 min) that might already be in Bangkok time
echo "=== กำลังแปลงข้อมูล ===\n";
try {
    $cutoff = $db->query("SELECT DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetchColumn();
    echo "  จะแปลงข้อมูลที่ received_at < $cutoff\n";
    echo "  (ข้อมูลล่าสุด 30 นาทีจะข้ามไป เพราะอาจเป็น Bangkok time แล้ว)\n\n";
    
    $stmt = $db->prepare("UPDATE webhook_events SET received_at = DATE_ADD(received_at, INTERVAL 7 HOUR) WHERE received_at < ?");
    $stmt->execute([$cutoff]);
    $count = $stmt->rowCount();
    
    echo "  ✅ แปลงสำเร็จ: $count รายการ (+7 ชั่วโมง)\n";
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Step 4: Show data after conversion
echo "=== ตัวอย่างข้อมูลหลังแปลง ===\n";
try {
    $sample = $db->query("SELECT id, received_at FROM webhook_events ORDER BY id DESC LIMIT 5")->fetchAll();
    foreach ($sample as $row) {
        echo "  ID {$row['id']}: {$row['received_at']}\n";
    }
} catch (Exception $e) {
    echo "  Error reading data: " . $e->getMessage() . "\n";
}

echo "\n-----------------------------\n";
echo "✅ เสร็จสิ้น! ข้อมูลทั้งหมดเป็นเวลากรุงเทพแล้ว\n";
echo "⚠️  ลบไฟล์นี้หลังรันเสร็จ เพื่อความปลอดภัย\n";
echo "</pre>";
?>
