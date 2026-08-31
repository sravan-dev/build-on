<?php
include_once __DIR__ . '/../includes/db.php';

echo "Updating database schema for Employee Allowances...\n";

// Add new columns to employees table
$columns = [
    'room_allowance' => 'REAL DEFAULT 0',
    'food_allowance' => 'REAL DEFAULT 0',
    'telephone_allowance' => 'REAL DEFAULT 0'
];

foreach ($columns as $col => $type) {
    try {
        $pdo->exec("ALTER TABLE employees ADD COLUMN $col $type");
        echo "Added column: $col\n";
    } catch (PDOException $e) {
        // Column likely exists
        echo "Column $col might already exist or error: " . $e->getMessage() . "\n";
    }
}

echo "Database update complete.\n";
?>