<?php
/**
 * Add work_site column to daily_attendance table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $columns = $pdo->query("PRAGMA table_info(daily_attendance)")->fetchAll();
    $work_site_exists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'work_site') {
            $work_site_exists = true;
            break;
        }
    }

    if (!$work_site_exists) {
        // Add work_site column
        $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN work_site TEXT");
        echo "✅ Added work_site column to daily_attendance table\n";
    } else {
        echo "ℹ️  work_site column already exists\n";
    }

    echo "✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
