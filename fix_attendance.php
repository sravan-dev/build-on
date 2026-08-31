<?php
require_once 'includes/db.php';

echo "=== FIXING ATTENDANCE DATA ===\n\n";

// Fix the out_time: 05:30:00 -> 17:30:00
$stmt = $pdo->prepare("UPDATE daily_attendance SET out_time = '17:30:00' WHERE id = 8 AND out_time = '05:30:00'");
$stmt->execute();
echo "Updated record ID 8: out_time 05:30:00 -> 17:30:00\n";

// Also recalculate working hours (10 hours from 07:30 to 17:30)
$stmt = $pdo->prepare("UPDATE daily_attendance SET working_hours = 10.00 WHERE id = 8");
$stmt->execute();
echo "Updated record ID 8: working_hours -> 10.00\n";

// Fix break time: 01:30:00 -> 13:30:00 (for 1 hour break from 12:30 to 13:30)
$stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = '13:30:00' WHERE daily_attendance_id = 8 AND end_time = '01:30:00' AND activity_type = 'break'");
$result = $stmt->execute();
$affected = $stmt->rowCount();
echo "Updated break log end_time 01:30:00 -> 13:30:00 (affected: $affected rows)\n";

echo "\n=== VERIFICATION ===\n";

// Verify the fix
$stmt = $pdo->query("SELECT * FROM daily_attendance WHERE id = 8");
$record = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Record ID 8:\n";
echo "  In Time: {$record['in_time']}\n";
echo "  Out Time: {$record['out_time']}\n";
echo "  Working Hours: {$record['working_hours']}\n";

// Calculate correct labour cost
$in = strtotime($record['in_time']);
$out = strtotime($record['out_time']);
$hours = ($out - $in) / 3600;
$hourly_rate = 1300 / 26 / 8;
$labour_cost = ($hours - 1) * $hourly_rate; // Minus 1 hour break

echo "  Hours (calculated): $hours\n";
echo "  Hours after break: " . ($hours - 1) . "\n";
echo "  Labour Cost: $labour_cost riyal\n";

echo "\nDone! Refresh the Projects page to see the corrected labour cost.\n";
?>