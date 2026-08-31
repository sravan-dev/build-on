<?php
/**
 * Add passport_expiry column to employees table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $columns = $pdo->query("PRAGMA table_info(employees)")->fetchAll();
    $passport_expiry_exists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'passport_expiry') {
            $passport_expiry_exists = true;
            break;
        }
    }

    if (!$passport_expiry_exists) {
        // Add passport_expiry column
        $pdo->exec("ALTER TABLE employees ADD COLUMN passport_expiry DATE");
        echo "✅ Added passport_expiry column to employees table\n";
    } else {
        echo "ℹ️  passport_expiry column already exists\n";
    }

    echo "✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
