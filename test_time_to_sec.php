<?php
require_once 'includes/db.php';

echo "=== TESTING UPDATED LABOUR COST CALCULATION ===\n\n";

// Test the new query with TIME_TO_SEC
$stmt = $pdo->query("
    SELECT p.name as project_name,
           COALESCE((SELECT SUM((e.monthly_salary / 26 / 8) * 
                                ((TIME_TO_SEC(da.out_time) - TIME_TO_SEC(da.in_time)) / 3600))
                     FROM daily_attendance da
                     JOIN employees e ON da.employee_id = e.id
                     WHERE da.work_site = p.name
                     AND da.in_time IS NOT NULL
                     AND da.out_time IS NOT NULL
                     AND e.monthly_salary > 0
                     AND TIME_TO_SEC(da.out_time) > TIME_TO_SEC(da.in_time)), 0) as total_labour_cost
    FROM projects p
    LIMIT 5
");

while ($row = $stmt->fetch()) {
    echo "Project: " . $row['project_name'] . "\n";
    echo "Total Labour Cost: QAR " . number_format($row['total_labour_cost'], 2) . "\n\n";
}

echo "=== DETAILED BREAKDOWN ===\n";
$stmt = $pdo->query("
    SELECT da.work_site, e.name as emp_name, e.monthly_salary,
           da.in_time, da.out_time,
           (TIME_TO_SEC(da.out_time) - TIME_TO_SEC(da.in_time)) / 3600 as calculated_hours,
           (e.monthly_salary / 26 / 8) * ((TIME_TO_SEC(da.out_time) - TIME_TO_SEC(da.in_time)) / 3600) as labour_cost
    FROM daily_attendance da
    JOIN employees e ON da.employee_id = e.id
    WHERE da.in_time IS NOT NULL
    AND da.out_time IS NOT NULL
    AND e.monthly_salary > 0
    AND TIME_TO_SEC(da.out_time) > TIME_TO_SEC(da.in_time)
    LIMIT 5
");

while ($row = $stmt->fetch()) {
    echo "Employee: " . $row['emp_name'] . "\n";
    echo "  Site: " . $row['work_site'] . "\n";
    echo "  In: " . $row['in_time'] . " | Out: " . $row['out_time'] . "\n";
    echo "  Hours: " . round($row['calculated_hours'], 2) . "\n";
    echo "  Labour Cost: QAR " . round($row['labour_cost'], 2) . "\n\n";
}
