<?php
include_once __DIR__ . '/../includes/db.php';

echo "Updating database schema for Visa & ID Tracking...\n";

// Add new columns to employees table
$columns = [
    'passport_number' => 'TEXT',
    'passport_expiry' => 'TEXT',
    'visa_expiry' => 'TEXT',
    'ticket_frequency_years' => 'INTEGER DEFAULT 2',
    'last_ticket_date' => 'TEXT',
    'next_ticket_date' => 'TEXT'
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