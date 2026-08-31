<?php
// Migration to add missing columns to expenses table
require_once dirname(__DIR__) . '/includes/db.php';

try {
    // Add expense_type column
    $pdo->exec("ALTER TABLE expenses ADD COLUMN expense_type TEXT");
    echo "Added expense_type column\n";
} catch (Exception $e) {
    echo "expense_type column may already exist: " . $e->getMessage() . "\n";
}

try {
    // Add remarks column
    $pdo->exec("ALTER TABLE expenses ADD COLUMN remarks TEXT");
    echo "Added remarks column\n";
} catch (Exception $e) {
    echo "remarks column may already exist: " . $e->getMessage() . "\n";
}

try {
    // Add paid_by column
    $pdo->exec("ALTER TABLE expenses ADD COLUMN paid_by TEXT");
    echo "Added paid_by column\n";
} catch (Exception $e) {
    echo "paid_by column may already exist: " . $e->getMessage() . "\n";
}

try {
    // Add attachment_path column
    $pdo->exec("ALTER TABLE expenses ADD COLUMN attachment_path TEXT");
    echo "Added attachment_path column\n";
} catch (Exception $e) {
    echo "attachment_path column may already exist: " . $e->getMessage() . "\n";
}

echo "\nMigration complete!\n";
