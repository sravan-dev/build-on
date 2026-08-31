<?php
require_once 'includes/db.php';

echo "Connected to: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Total tables: " . count($tables) . "\n\n";

    // Test some key tables
    $testTables = ['clients', 'vendors', 'employees', 'payments'];
    foreach ($testTables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "$table: $count rows\n";
    }
} else {
    echo "Still using SQLite\n";
}
