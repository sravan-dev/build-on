<?php
require_once 'includes/db.php';

echo "=== DAILY ATTENDANCE SAMPLE ===\n";
$stmt = $pdo->query("SELECT * FROM daily_attendance LIMIT 3");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "No attendance records found!\n";
} else {
    foreach ($rows as $row) {
        echo "\nRecord ID: " . $row['id'] . "\n";
        foreach ($row as $key => $value) {
            echo "  $key: " . ($value ?? 'NULL') . "\n";
        }
    }
}

echo "\n=== EMPLOYEE SAMPLE ===\n";
$stmt = $pdo->query("SELECT id, name, monthly_salary FROM employees LIMIT 3");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Salary: " . ($row['monthly_salary'] ?? 'NULL') . "\n";
}
