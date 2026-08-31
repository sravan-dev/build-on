<?php
require_once 'includes/db.php';

echo "=== Adding vehicle_number column ===\n\n";

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Connected to: $driver\n";

    if ($driver === 'mysql') {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_number'");
        $exists = $stmt->fetch();

        if (!$exists) {
            echo "Adding vehicle_number column...\n";
            $pdo->exec("ALTER TABLE vehicles ADD COLUMN vehicle_number VARCHAR(50) AFTER id");
            echo "✓ Successfully added vehicle_number column\n";
        } else {
            echo "✓ vehicle_number column already exists\n";
        }

        // Show all columns
        echo "\nCurrent columns in vehicles table:\n";
        $cols = $pdo->query("SHOW COLUMNS FROM vehicles")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
