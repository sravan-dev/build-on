<?php
/**
 * Update Payments Table
 * Add 'paid_by' column
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column exists
    $columns = $pdo->query("PRAGMA table_info(payments)")->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('paid_by', $columns)) {
        // Add paid_by column
        $pdo->exec("ALTER TABLE payments ADD COLUMN paid_by TEXT");
        echo "✅ 'paid_by' column added to payments table.\n";
    } else {
        echo "ℹ️ 'paid_by' column already exists.\n";
    }

} catch (PDOException $e) {
    echo "❌ Error updating payments table: " . $e->getMessage() . "\n";
    exit(1);
}
