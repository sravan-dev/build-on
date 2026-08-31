<?php
// Add LPO number column to invoices table
try {
    $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/buildon.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column exists
    $columns = $pdo->query("PRAGMA table_info(invoices)")->fetchAll(PDO::FETCH_ASSOC);
    $lpoExists = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'lpo_number') {
            $lpoExists = true;
            break;
        }
    }
    
    if (!$lpoExists) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN lpo_number TEXT");
        echo "✓ LPO number column added successfully to invoices table\n";
    } else {
        echo "✓ LPO number column already exists\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

