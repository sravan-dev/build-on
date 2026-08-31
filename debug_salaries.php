<?php
require_once 'includes/db.php';

echo "=== CHECKING EMPLOYEE SALARIES ===\n";
$stmt = $pdo->query("
    SELECT e.id, e.name, e.monthly_salary, 
           COUNT(da.id) as attendance_count,
           SUM(da.working_hours) as total_hours
    FROM employees e
    LEFT JOIN daily_attendance da ON e.id = da.employee_id AND da.working_hours > 0
    GROUP BY e.id, e.name, e.monthly_salary
    HAVING attendance_count > 0
    ORDER BY e.id
    LIMIT 10
");

while ($row = $stmt->fetch()) {
    echo "Employee: " . $row['name'] .
        " | Salary: " . ($row['monthly_salary'] ?: 'NOT SET') .
        " | Attendance Records: " . $row['attendance_count'] .
        " | Total Hours: " . $row['total_hours'] . "\n";
}

echo "\n=== ATTENDANCE RECORDS WITH HOURS ===\n";
$stmt = $pdo->query("
    SELECT da.id, e.name as emp_name, da.work_site, da.working_hours, e.monthly_salary
    FROM daily_attendance da
    JOIN employees e ON da.employee_id = e.id
    WHERE da.working_hours > 0
    LIMIT 10
");

while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] .
        " | Employee: " . $row['emp_name'] .
        " | Site: " . $row['work_site'] .
        " | Hours: " . $row['working_hours'] .
        " | Salary: " . ($row['monthly_salary'] ?: 'NOT SET') . "\n";
}
