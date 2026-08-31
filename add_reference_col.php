<?php
include_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE vouchers ADD COLUMN reference VARCHAR(100) NULL AFTER status");
    echo "Added reference column to vouchers table.\n";
} catch (PDOException $e) {
    echo "Error adding column (might already exist): " . $e->getMessage() . "\n";
}
?>
