<?php
/**
 * Update All Payment Tables
 * Add 'paid_by' column to all relevant payment/expense tables
 */

require_once __DIR__ . '/../includes/db.php';

$tables_to_update = [
    'vendor_payments',
    'expenses',
    'purchase_payments',
    'vehicle_expenses',
    'vehicle_fuel_records',
    'vehicle_maintenance'
];

foreach ($tables_to_update as $table) {
    try {
        // Check if table exists
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'")->fetch();

        if ($check) {
            // Check if column exists
            $columns = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);

            if (!in_array('paid_by', $columns)) {
                // Add paid_by column
                $pdo->exec("ALTER TABLE $table ADD COLUMN paid_by TEXT");
                echo "✅ 'paid_by' column added to table '$table'.\n";
            } else {
                echo "ℹ️ 'paid_by' column already exists in '$table'.\n";
            }
        } else {
            echo "⚠️ Table '$table' does not exist.\n";
        }

    } catch (PDOException $e) {
        echo "❌ Error updating '$table': " . $e->getMessage() . "\n";
    }
}
