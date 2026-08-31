<?php
/**
 * Fix Vehicles Table Schema
 * Add missing vehicle_number column if it doesn't exist
 */

require_once 'includes/db.php';

echo "=== Vehicles Table Schema Fix ===\n\n";

try {
    // Check if vehicles table exists
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'vehicles'")->fetch();

        if ($tableExists) {
            echo "✓ Vehicles table exists\n";

            // Check if vehicle_number column exists
            $columns = $pdo->query("SHOW COLUMNS FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('vehicle_number', $columns)) {
                echo "⚠ vehicle_number column missing, adding it...\n";
                $pdo->exec("ALTER TABLE vehicles ADD COLUMN vehicle_number VARCHAR(50) AFTER id");
                echo "✓ Added vehicle_number column\n";
            } else {
                echo "✓ vehicle_number column exists\n";
            }

            // Show current columns
            echo "\nCurrent columns:\n";
            $cols = $pdo->query("DESCRIBE vehicles")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                echo "  - {$col['Field']} ({$col['Type']})\n";
            }
        } else {
            echo "✗ Vehicles table doesn't exist, creating it...\n";

            // Create vehicles table with proper schema
            $pdo->exec("
                CREATE TABLE vehicles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    vehicle_number VARCHAR(50),
                    model VARCHAR(100),
                    make VARCHAR(100),
                    year INT,
                    chassis_number VARCHAR(100),
                    engine_number VARCHAR(100),
                    fuel_type VARCHAR(50),
                    assigned_driver VARCHAR(100),
                    registration_renewal_date DATE,
                    insurance_renewal_date DATE,
                    purchase_date DATE,
                    purchase_price DECIMAL(10,2),
                    current_mileage DECIMAL(10,2),
                    vehicle_status VARCHAR(50) DEFAULT 'Active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            echo "✓ Created vehicles table\n";
        }
    }

    echo "\n✅ Schema fix complete!\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
