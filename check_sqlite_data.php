<?php
$sqlite = new PDO('sqlite:buildon.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = ['employees', 'clients', 'vendors', 'projects', 'quotations', 'invoices', 'payments'];

echo "=== SQLite Data Count ===\n";
foreach ($tables as $table) {
    $count = $sqlite->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "$table: $count rows\n";
}

echo "\n=== Sample Employee Data ===\n";
$employees = $sqlite->query("SELECT id, name, employee_id FROM employees LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($employees as $emp) {
    echo "ID: {$emp['id']}, Name: {$emp['name']}, EmpID: {$emp['employee_id']}\n";
}
