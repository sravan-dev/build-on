<?php
/**
 * Add project_id column to outside_labours table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $columns = $pdo->query("PRAGMA table_info(outside_labours)")->fetchAll();
    $project_id_exists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'project_id') {
            $project_id_exists = true;
            break;
        }
    }

    if (!$project_id_exists) {
        // Add project_id column
        $pdo->exec("ALTER TABLE outside_labours ADD COLUMN project_id INTEGER");
        echo "✅ Added project_id column to outside_labours table\n";
    } else {
        echo "ℹ️  project_id column already exists\n";
    }

    echo "✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
