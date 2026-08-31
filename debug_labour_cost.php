<?php
require_once 'includes/db.php';

echo "=== WORK SITES IN ATTENDANCE ===\n";
$stmt = $pdo->query("SELECT DISTINCT work_site FROM daily_attendance WHERE work_site IS NOT NULL AND work_site != '' LIMIT 10");
while ($row = $stmt->fetch()) {
    echo "'" . $row['work_site'] . "'\n";
}

echo "\n=== PROJECT NAMES ===\n";
$stmt = $pdo->query("SELECT id, name FROM projects LIMIT 10");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " - '" . $row['name'] . "'\n";
}

echo "\n=== SAMPLE ATTENDANCE WITH LABOUR COST ===\n";
$stmt = $pdo->query("
    SELECT da.work_site, e.name as emp_name, e.monthly_salary, da.working_hours,
           (e.monthly_salary / 26 / 8) * da.working_hours as labour_cost
    FROM daily_attendance da
    JOIN employees e ON da.employee_id = e.id
    WHERE da.working_hours > 0 AND e.monthly_salary > 0
    LIMIT 5
");
while ($row = $stmt->fetch()) {
    echo "Site: '" . $row['work_site'] . "' | Employee: " . $row['emp_name'] .
        " | Salary: " . $row['monthly_salary'] . " | Hours: " . $row['working_hours'] .
        " | Cost: " . round($row['labour_cost'], 2) . "\n";
}
