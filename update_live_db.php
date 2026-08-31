<?php
/**
 * Update live database with payment_method column
 */
require_once 'includes/db.php';

echo "<!DOCTYPE html><html><head><title>Update Database</title></head><body>";
echo "<h1>Database Update</h1>";

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<p>Database Driver: $driver</p>";

    // Add payment_method column to expenses
    echo "<h2>Adding payment_method column to expenses table...</h2>";

    if ($driver === 'mysql') {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN payment_method VARCHAR(50) DEFAULT 'company_cash'");
    } else {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN payment_method VARCHAR(50) DEFAULT 'company_cash'");
    }

    echo "<p style='color: green;'>✅ SUCCESS: payment_method column added to expenses table</p>";
    echo "<p>Default value: 'company_cash'</p>";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "<p style='color: blue;'>✓ Column already exists - no change needed</p>";
    } else {
        echo "<p style='color: red;'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<hr><p><a href='index.php?page=expenses'>Go to Add Expense</a> | <a href='index.php?page=expense_list'>View Expense List</a></p>";
echo "</body></html>";
