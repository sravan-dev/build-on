<?php
require_once 'includes/db.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Connected to: $driver\n\n";

if ($driver === 'mysql') {
    // Check current columns
    $result = $pdo->query("SHOW COLUMNS FROM vehicles");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);

    echo "Current columns: " . implode(', ', $columns) . "\n\n";

    if (!in_array('vehicle_number', $columns)) {
        echo "Adding vehicle_number column...\n";
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN vehicle_number VARCHAR(50) AFTER id");
        echo "✓ Added vehicle_number column\n";
    } else {
        echo "✓ vehicle_number column already exists\n";
    }
} else {
    echo "Not connected to MySQL, skipping fix\n";
}
