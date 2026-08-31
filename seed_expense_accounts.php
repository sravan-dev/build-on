<?php
include_once 'includes/db.php';

$expense_accounts = [
    ['code' => '5001', 'name' => 'Transport', 'type' => 'expense'],
    ['code' => '5002', 'name' => 'Food', 'type' => 'expense'],
    ['code' => '5003', 'name' => 'Labor', 'type' => 'expense'],
    ['code' => '5004', 'name' => 'Materials', 'type' => 'expense'],
    ['code' => '5005', 'name' => 'Equipment', 'type' => 'expense'],
    ['code' => '5006', 'name' => 'Communication', 'type' => 'expense'],
    ['code' => '5007', 'name' => 'Office Supplies', 'type' => 'expense'],
    ['code' => '5008', 'name' => 'Utilities', 'type' => 'expense'],
    ['code' => '5011', 'name' => 'Maintenance', 'type' => 'expense'], // 5009/5010 already seen
    ['code' => '5012', 'name' => 'Miscellaneous', 'type' => 'expense'],
];

foreach ($expense_accounts as $acc) {
    // Check if exists by code
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE account_code = ?");
    $stmt->execute([$acc['code']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE accounts SET account_name = ?, account_type = ? WHERE id = ?");
        $stmt->execute([$acc['name'], $acc['type'], $existing['id']]);
        echo "Updated: {$acc['name']}\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO accounts (account_code, account_name, account_type, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$acc['code'], $acc['name'], $acc['type']]);
        echo "Created: {$acc['name']}\n";
    }
}
echo "Expense accounts seeded.\n";
?>
