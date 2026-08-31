<?php
/**
 * Add missing columns to employees table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Get existing columns
    $columns = $pdo->query("PRAGMA table_info(employees)")->fetchAll();
    $existing_columns = array_column($columns, 'name');

    // Define columns to add
    $columns_to_add = [
        'visa_expiry' => 'DATE',
        'ticket_frequency_years' => 'INTEGER DEFAULT 2',
        'last_ticket_date' => 'DATE',
        'next_ticket_date' => 'DATE',
        'room_allowance' => 'REAL DEFAULT 0',
        'food_allowance' => 'REAL DEFAULT 0',
        'telephone_allowance' => 'REAL DEFAULT 0',
    ];

    $added_count = 0;

    foreach ($columns_to_add as $column_name => $column_type) {
        if (!in_array($column_name, $existing_columns)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN $column_name $column_type");
            echo "✅ Added $column_name column\n";
            $added_count++;
        } else {
            echo "ℹ️  $column_name column already exists\n";
        }
    }

    if ($added_count > 0) {
        echo "\n✅ Migration completed! Added $added_count column(s) to employees table.\n";
    } else {
        echo "\n✅ All columns already exist. No changes needed.\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
