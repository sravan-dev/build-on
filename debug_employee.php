<?php
require_once 'includes/db.php';

$employee_id = 16;
$month = 12;
$year = 2025;

echo "=== EMPLOYEE ID: $employee_id | MONTH: $month | YEAR: $year ===\n\n";

// Get employee details
$stmt = $pdo->prepare("SELECT id, name, monthly_salary FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

echo "Employee: " . $employee['name'] . "\n";
echo "Monthly Salary: QAR " . $employee['monthly_salary'] . "\n\n";

// Get attendance records
echo "=== ATTENDANCE RECORDS ===\n";
$stmt = $pdo->prepare("
    SELECT attendance_date, in_time, out_time, work_site, status
    FROM daily_attendance
    WHERE employee_id = ?
    AND MONTH(attendance_date) = ?
    AND YEAR(attendance_date) = ?
    ORDER BY attendance_date
");
$stmt->execute([$employee_id, $month, $year]);
$records = $stmt->fetchAll();

foreach ($records as $record) {
    echo "\nDate: " . $record['attendance_date'] . " (" . $record['status'] . ")\n";
    echo "  Work Site: " . ($record['work_site'] ?? 'NULL') . "\n";
    echo "  In Time: " . ($record['in_time'] ?? 'NULL') . "\n";
    echo "  Out Time: " . ($record['out_time'] ?? 'NULL') . "\n";

    if ($record['in_time'] && $record['out_time']) {
        // Calculate using TIMESTAMPDIFF (SQL way)
        $stmt2 = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, ?) / 3600 as hours");
        $stmt2->execute([$record['in_time'], $record['out_time']]);
        $sql_hours = $stmt2->fetchColumn();

        // Calculate using PHP (attendance report way)
        $t1 = strtotime($record['in_time']);
        $t2 = strtotime($record['out_time']);
        $php_hours = ($t2 - $t1) / 3600;

        echo "  SQL Hours (TIMESTAMPDIFF): " . round($sql_hours, 2) . "\n";
        echo "  PHP Hours (strtotime): " . round($php_hours, 2) . "\n";

        $hourly_rate = $employee['monthly_salary'] / 26 / 8;
        echo "  Hourly Rate: QAR " . round($hourly_rate, 2) . "\n";
        echo "  SQL Labour Cost: QAR " . round($sql_hours * $hourly_rate, 2) . "\n";
        echo "  PHP Labour Cost: QAR " . round($php_hours * $hourly_rate, 2) . "\n";
    }
}
