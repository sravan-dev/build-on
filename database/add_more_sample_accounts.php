<?php
require_once dirname(__DIR__) . '/includes/db.php';

// Additional sample accounts for more realistic ledger
$additionalAccounts = [
    ['1003', 'Petty Cash', 'asset', null],
    ['1004', 'Accounts Receivable', 'asset', null],
    ['2003', 'Accrued Expenses', 'liability', null],
    ['2004', 'Tax Payable', 'liability', null],
    ['3002', 'Retained Earnings', 'equity', null],
    ['4002', 'Service Revenue', 'income', null],
    ['5005', 'Construction Materials', 'expense', null],
    ['5006', 'Transportation Expenses', 'expense', null],
    ['5007', 'Equipment Maintenance', 'expense', null],
    ['5008', 'Salary Expenses', 'expense', null],
    ['5009', 'Professional Services', 'expense', null],
    ['5010', 'Marketing Expenses', 'expense', null]
];

try {
    echo "Adding additional sample accounts...\n";
    
    foreach ($additionalAccounts as $account) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO accounts (account_code, account_name, account_type, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute($account);
        echo "✓ Added account: {$account[0]} - {$account[1]} ({$account[2]})\n";
    }
    
    echo "\n✓ Successfully added " . count($additionalAccounts) . " additional accounts!\n";
    echo "✓ Your chart of accounts is now more comprehensive\n";
    echo "✓ Ready to create more detailed vouchers!\n";
    
} catch (Exception $e) {
    echo "✗ Error adding additional accounts: " . $e->getMessage() . "\n";
}
?>
