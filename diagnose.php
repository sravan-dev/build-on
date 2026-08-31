<?php
/**
 * Database Diagnostic Script for Live Server
 * Run this to see what's happening with the database
 */

require_once 'includes/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🔍 Database Diagnostic</h1>
";

try {
    // Check database driver
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<div class='success'>✓ Connected to database: <strong>$driver</strong></div>";

    // Check projects table
    echo "<h2>Projects Table</h2>";

    if ($driver === 'mysql') {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'projects'")->fetch();
    } else {
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='projects'")->fetch();
    }

    if ($tableExists) {
        echo "<div class='success'>✓ Projects table exists</div>";

        // Count projects
        $count = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        echo "<div class='info'>Total projects in database: <strong>$count</strong></div>";

        // Show projects
        if ($count > 0) {
            echo "<h3>Project Data:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Client ID</th><th>Value</th></tr>";

            $projects = $pdo->query("SELECT id, name, client_id, total_value FROM projects LIMIT 10")->fetchAll();
            foreach ($projects as $p) {
                echo "<tr><td>{$p['id']}</td><td>{$p['name']}</td><td>{$p['client_id']}</td><td>{$p['total_value']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>No projects found in database!</div>";
        }
    } else {
        echo "<div class='error'>✗ Projects table does NOT exist!</div>";
    }

    // Check daily_attendance table
    echo "<h2>Daily Attendance Table</h2>";

    if ($driver === 'mysql') {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'daily_attendance'")->fetch();
    } else {
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='daily_attendance'")->fetch();
    }

    if ($tableExists) {
        echo "<div class='success'>✓ Daily attendance table exists</div>";

        $count = $pdo->query("SELECT COUNT(*) FROM daily_attendance")->fetchColumn();
        echo "<div class='info'>Total attendance records: <strong>$count</strong></div>";

        // Show sample attendance with work_site
        if ($count > 0) {
            echo "<h3>Sample Attendance Data:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Employee ID</th><th>Date</th><th>Work Site</th><th>In Time</th><th>Out Time</th></tr>";

            $records = $pdo->query("SELECT id, employee_id, attendance_date, work_site, in_time, out_time FROM daily_attendance LIMIT 5")->fetchAll();
            foreach ($records as $r) {
                echo "<tr><td>{$r['id']}</td><td>{$r['employee_id']}</td><td>{$r['attendance_date']}</td><td>" . ($r['work_site'] ?? 'NULL') . "</td><td>" . ($r['in_time'] ?? 'NULL') . "</td><td>" . ($r['out_time'] ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='error'>✗ Daily attendance table does NOT exist!</div>";
    }

    // Check employees table
    echo "<h2>Employees Table</h2>";

    if ($driver === 'mysql') {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'employees'")->fetch();
    } else {
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='employees'")->fetch();
    }

    if ($tableExists) {
        echo "<div class='success'>✓ Employees table exists</div>";

        $count = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        echo "<div class='info'>Total employees: <strong>$count</strong></div>";

        // Check if monthly_salary column exists and has values
        try {
            $salaries = $pdo->query("SELECT id, name, monthly_salary FROM employees WHERE monthly_salary > 0 LIMIT 5")->fetchAll();
            echo "<div class='info'>Employees with salary set: <strong>" . count($salaries) . "</strong></div>";
        } catch (Exception $e) {
            echo "<div class='error'>Error checking salaries: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error'>✗ Employees table does NOT exist!</div>";
    }

    // Test the actual projects query
    echo "<h2>Testing Projects Query</h2>";

    try {
        if ($driver === 'mysql') {
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
                       0 as total_labour_cost,
                       0 as profit
                FROM projects p 
                LEFT JOIN clients c ON p.client_id = c.id
                ORDER BY p.id DESC
            ";
        } else {
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
                       0 as total_labour_cost,
                       0 as profit
                FROM projects p 
                LEFT JOIN clients c ON p.client_id = c.id
                ORDER BY p.id DESC
            ";
        }

        $projects = $pdo->query($query)->fetchAll();
        echo "<div class='success'>✓ Query executed successfully!</div>";
        echo "<div class='info'>Projects returned: <strong>" . count($projects) . "</strong></div>";

        if (count($projects) > 0) {
            echo "<h3>Query Results:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Client</th><th>Value</th><th>Income</th><th>Expenses</th></tr>";
            foreach ($projects as $p) {
                echo "<tr><td>{$p['id']}</td><td>{$p['name']}</td><td>" . ($p['client_name'] ?? 'N/A') . "</td><td>{$p['total_value']}</td><td>{$p['total_income']}</td><td>{$p['total_expenses']}</td></tr>";
            }
            echo "</table>";
        }

    } catch (PDOException $e) {
        echo "<div class='error'>Query failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }

    // List all tables
    echo "<h2>All Database Tables</h2>";

    if ($driver === 'mysql') {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }

    echo "<div class='info'>Total tables: <strong>" . count($tables) . "</strong></div>";
    echo "<ul>";
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "<li>$table ($count records)</li>";
        } catch (Exception $e) {
            echo "<li>$table (error counting)</li>";
        }
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<div class='error'>
        <h3>Fatal Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
    </div>";
}

echo "
    <hr>
    <p style='text-align: center; color: #666;'>
        <strong>Delete this file after debugging!</strong><br>
        Script completed at: " . date('Y-m-d H:i:s') . "
    </p>
</body>
</html>";
?>