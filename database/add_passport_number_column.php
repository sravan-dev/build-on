<?php
/**
 * Add passport_number column to employees table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $columns = $pdo->query("PRAGMA table_info(employees)")->fetchAll();
    $passport_number_exists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'passport_number') {
            $passport_number_exists = true;
            break;
        }
    }

    if (!$passport_number_exists) {
        // Add passport_number column
        $pdo->exec("ALTER TABLE employees ADD COLUMN passport_number TEXT");
        echo "✅ Added passport_number column to employees table\n";
    } else {
        echo "ℹ️  passport_number column already exists\n";
    }

    echo "✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
