<?php
require_once 'includes/db.php';

// First show table structure
echo "Employees table columns:\n";
$columns = $pdo->query("PRAGMA table_info(employees)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "- {$col['name']} ({$col['type']})\n";
}

// Check if emp_id or employee_id exists
$hasEmpId = false;
$empIdColumn = 'id';
foreach ($columns as $col) {
    if ($col['name'] === 'emp_id') {
        $hasEmpId = true;
        $empIdColumn = 'emp_id';
        break;
    }
}

echo "\nUsing column: $empIdColumn\n";

$search = 'BUE010';
$password = 'Qatar123';

// Get employee
$stmt = $pdo->prepare("SELECT * FROM employees WHERE $empIdColumn = ? OR name LIKE ? LIMIT 5");
$stmt->execute([$search, "%$search%"]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($employees)) {
    echo "\nNo employees found. Showing first 5:\n";
    $employees = $pdo->query("SELECT * FROM employees LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($employees as $emp) {
    echo "\n---\n";
    foreach ($emp as $key => $val) {
        if ($key !== 'password') {
            echo "$key: $val\n";
        }
    }
    if (!empty($emp['password'])) {
        echo "Has password: YES\n";
        echo "Password verify '$password': " . (password_verify($password, $emp['password']) ? 'OK' : 'FAIL') . "\n";
    } else {
        echo "Has password: NO\n";
    }
}
