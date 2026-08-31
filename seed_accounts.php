<?php
include_once 'includes/db.php';

$mandatory_accounts = [
    // Assets
    ['code' => '1001', 'name' => 'Cash', 'type' => 'asset'],
    ['code' => '1002', 'name' => 'Bank – Company Account', 'type' => 'asset'],
    
    // Liabilities
    ['code' => '2100', 'name' => 'Credit Card Payable', 'type' => 'liability'],
    ['code' => '2201', 'name' => 'Rahees – Cash', 'type' => 'liability'], // Partner Loan
    ['code' => '2202', 'name' => 'Rahees – Card', 'type' => 'liability'], // Partner Loan
    ['code' => '2203', 'name' => 'Salman – Cash', 'type' => 'liability'], // Partner Loan
    ['code' => '2204', 'name' => 'Salman – Card', 'type' => 'liability'], // Partner Loan
    
    // Equity
    ['code' => '3000', 'name' => 'Opening Balance Adjustment', 'type' => 'equity'],
];

foreach ($mandatory_accounts as $acc) {
    // Check if exists by code
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE account_code = ?");
    $stmt->execute([$acc['code']]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update name
        $stmt = $pdo->prepare("UPDATE accounts SET account_name = ?, account_type = ? WHERE id = ?");
        $stmt->execute([$acc['name'], $acc['type'], $existing['id']]);
        echo "Updated: {$acc['name']}\n";
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO accounts (account_code, account_name, account_type, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$acc['code'], $acc['name'], $acc['type']]);
        echo "Created: {$acc['name']}\n";
    }
}
echo "Account seeding complete.\n";
?>
