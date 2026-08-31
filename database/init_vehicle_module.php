<?php
/**
 * Vehicle Module Database Initialization
 * Run this file once to create/update vehicle management tables
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Read the vehicle schema SQL file
    $sql = file_get_contents(__DIR__ . '/vehicle_schema.sql');

    // Execute the SQL
    $pdo->exec($sql);

    echo "✅ Vehicle Management Module database tables created successfully!\n";
    echo "The following tables have been created/updated:\n";
    echo "  - vehicles (master data)\n";
    echo "  - vehicle_daily_logs (daily KM tracking)\n";
    echo "  - vehicle_expenses (all expense types)\n";
    echo "  - vehicle_fuel_records (detailed fuel tracking)\n";
    echo "  - vehicle_maintenance (maintenance history & schedule)\n";
    echo "  - vehicle_alerts (reminders & notifications)\n";
    echo "  - vehicle_income (commercial vehicle income)\n";

} catch (PDOException $e) {
    echo "❌ Error creating vehicle tables: " . $e->getMessage() . "\n";
    exit(1);
}
