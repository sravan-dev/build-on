<?php
require_once dirname(__DIR__) . '/includes/db.php';

try {
    // Add cheque_number column
    $pdo->exec("ALTER TABLE purchase_payments ADD COLUMN cheque_number TEXT");
    echo "✓ Cheque number column added successfully to purchase_payments table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "✓ Cheque number column already exists, skipping.\n";
    } else {
        echo "✗ Error adding cheque number column: " . $e->getMessage() . "\n";
    }
}

try {
    // Add bank_name column
    $pdo->exec("ALTER TABLE purchase_payments ADD COLUMN bank_name TEXT");
    echo "✓ Bank name column added successfully to purchase_payments table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "✓ Bank name column already exists, skipping.\n";
    } else {
        echo "✗ Error adding bank name column: " . $e->getMessage() . "\n";
    }
}

echo "✓ Database migration completed successfully!\n";
?>
