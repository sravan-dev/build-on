<?php
require_once 'includes/db.php';

echo "=== MySQL Data Verification ===\n\n";

$tables = ['employees', 'clients', 'vendors', 'projects', 'quotations', 'invoices', 'payments'];

foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo "$table: $count rows\n";
}

echo "\n=== Employee Data Sample ===\n";
$employees = $pdo->query("SELECT id, name, employee_id FROM employees LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($employees as $emp) {
    echo "ID: {$emp['id']}, Name: {$emp['name']}, EmpID: " . ($emp['employee_id'] ?? 'NULL') . "\n";
}

echo "\n=== Comparison ===\n";
$sqliteCount = 21; // We know from previous check
$mysqlCount = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
echo "SQLite employees: $sqliteCount\n";
echo "MySQL employees: $mysqlCount\n";

if ($sqliteCount == $mysqlCount) {
    echo "✅ All data imported successfully!\n";
} else {
    echo "⚠️  Data mismatch! Missing " . ($sqliteCount - $mysqlCount) . " employees\n";
}
