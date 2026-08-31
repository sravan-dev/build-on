<?php
require_once 'includes/db.php';

echo "=== DEBUGGING PROJECTS QUERY ===\n\n";

// Detect database driver
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Database Driver: $driver\n\n";

// Build labour cost calculation based on database type
if ($driver === 'mysql') {
    $labour_cost_calc = "(e.monthly_salary / 26 / 8) * ((TIME_TO_SEC(da.out_time) - TIME_TO_SEC(da.in_time)) / 3600)";
    $time_filter = "TIME_TO_SEC(da.out_time) > TIME_TO_SEC(da.in_time)";
} else {
    $labour_cost_calc = "(e.monthly_salary / 26 / 8) * ((strftime('%s', da.out_time) - strftime('%s', da.in_time)) / 3600.0)";
    $time_filter = "strftime('%s', da.out_time) > strftime('%s', da.in_time)";
}

echo "Labour Cost Calculation: $labour_cost_calc\n";
echo "Time Filter: $time_filter\n\n";

// Try the full query
$query = "
    SELECT p.*, 
           c.name as client_name,
           COALESCE((SELECT SUM(pm.amount) 
                     FROM payments pm
                     LEFT JOIN invoices i ON pm.invoice_id = i.id
                     LEFT JOIN quotations q ON i.quotation_id = q.id 
                     WHERE q.project_id = p.id), 0) as total_income,
           COALESCE((SELECT SUM(total_amount) 
                     FROM purchases 
                     WHERE project_id = p.id), 0) as total_expenses,
           COALESCE((SELECT SUM($labour_cost_calc)
                     FROM daily_attendance da
                     JOIN employees e ON da.employee_id = e.id
                     WHERE da.work_site = p.name
                     AND da.in_time IS NOT NULL
                     AND da.out_time IS NOT NULL
                     AND e.monthly_salary > 0
                     AND $time_filter), 0) as total_labour_cost
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id
    ORDER BY p.id DESC
";

echo "=== FULL QUERY ===\n$query\n\n";

try {
    $projects = $pdo->query($query)->fetchAll();
    echo "Projects found: " . count($projects) . "\n\n";

    foreach ($projects as $project) {
        echo "Project: " . $project['name'] . "\n";
        echo "  Client: " . ($project['client_name'] ?? 'N/A') . "\n";
        echo "  Total Income: " . $project['total_income'] . "\n";
        echo "  Total Expenses: " . $project['total_expenses'] . "\n";
        echo "  Total Labour Cost: " . $project['total_labour_cost'] . "\n\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
