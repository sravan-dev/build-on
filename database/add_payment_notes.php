<?php
/**
 * Migration: Add notes column to payments table
 * 
 * This migration adds a notes field to the payments table
 * to allow users to add additional information about payments
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting payments notes field migration...\n";

    // Check if column already exists
    $result = $pdo->query('PRAGMA table_info(payments)');
    $existing_columns = [];
    foreach ($result as $row) {
        $existing_columns[] = $row['name'];
    }

    if (!in_array('notes', $existing_columns)) {
        $sql = "ALTER TABLE payments ADD COLUMN notes TEXT";
        $pdo->exec($sql);
        echo "✓ Added 'notes' column to payments table\n";
    } else {
        echo "- Column 'notes' already exists in payments table\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
