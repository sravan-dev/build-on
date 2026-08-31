<?php
/**
 * Migration: Add extended employee fields
 * 
 * This migration adds additional fields to the employees table
 * to support comprehensive employee management including:
 * - Employee ID (employee number/code)
 * - Qatar ID and expiry
 * - Contact information (email, phone, address)
 * - Position and department
 * - Hire date and status
 * - Emergency contact details
 * - Bank account information
 * - Notes and timestamps
 */

require_once __DIR__ . '/../includes/db.php';

try {
    echo "Starting employee fields migration...\n";

    // Check if columns already exist
    $result = $pdo->query('PRAGMA table_info(employees)');
    $existing_columns = [];
    foreach ($result as $row) {
        $existing_columns[] = $row['name'];
    }

    // List of columns to add
    $columns_to_add = [
        'employee_id' => 'TEXT',
        'qatar_id' => 'TEXT',
        'qatar_id_expiry' => 'TEXT',
        'email' => 'TEXT',
        'phone' => 'TEXT',
        'address' => 'TEXT',
        'position' => 'TEXT',
        'department' => 'TEXT',
        'hire_date' => 'TEXT',
        'status' => 'TEXT DEFAULT "active"',
        'emergency_contact' => 'TEXT',
        'emergency_phone' => 'TEXT',
        'bank_account' => 'TEXT',
        'bank_name' => 'TEXT',
        'notes' => 'TEXT',
        'created_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP'
    ];

    $added_count = 0;

    foreach ($columns_to_add as $column_name => $column_type) {
        if (!in_array($column_name, $existing_columns)) {
            $sql = "ALTER TABLE employees ADD COLUMN $column_name $column_type";
            $pdo->exec($sql);
            echo "✓ Added column: $column_name\n";
            $added_count++;
        } else {
            echo "- Column already exists: $column_name\n";
        }
    }

    echo "\n";
    echo "Migration completed successfully!\n";
    echo "Added $added_count new columns to employees table.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
