<?php
require_once dirname(__DIR__) . '/includes/db.php';

try {
    // Create vouchers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        voucher_no TEXT UNIQUE NOT NULL,
        voucher_date TEXT NOT NULL,
        paid_to_received_from TEXT NOT NULL,
        amount REAL NOT NULL,
        amount_in_words TEXT NOT NULL,
        description TEXT,
        voucher_type TEXT DEFAULT 'cash', -- 'cash', 'bank', 'journal'
        prepared_by TEXT,
        checked_by TEXT,
        approved_by TEXT,
        status TEXT DEFAULT 'draft', -- 'draft', 'approved', 'posted'
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ Vouchers table created successfully\n";

    // Create voucher_entries table for double-entry bookkeeping
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        voucher_id INTEGER NOT NULL,
        account_head TEXT NOT NULL,
        debit_amount REAL DEFAULT 0,
        credit_amount REAL DEFAULT 0,
        narration TEXT,
        FOREIGN KEY(voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
    )");
    echo "✓ Voucher entries table created successfully\n";

    // Create accounts table for chart of accounts
    $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_code TEXT UNIQUE NOT NULL,
        account_name TEXT NOT NULL,
        account_type TEXT NOT NULL, -- 'asset', 'liability', 'equity', 'income', 'expense'
        parent_id INTEGER,
        is_active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(parent_id) REFERENCES accounts(id)
    )");
    echo "✓ Accounts table created successfully\n";

    // Insert default accounts
    $defaultAccounts = [
        ['1001', 'Cash in Hand', 'asset', null],
        ['1002', 'Bank Account', 'asset', null],
        ['2001', 'Accounts Payable', 'liability', null],
        ['2002', 'Accounts Receivable', 'asset', null],
        ['3001', 'Capital', 'equity', null],
        ['4001', 'Sales Revenue', 'income', null],
        ['5001', 'Office Expenses', 'expense', null],
        ['5002', 'Travel Expenses', 'expense', null],
        ['5003', 'Utilities', 'expense', null],
        ['5004', 'Rent', 'expense', null]
    ];

    foreach ($defaultAccounts as $account) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO accounts (account_code, account_name, account_type, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute($account);
    }
    echo "✓ Default accounts inserted successfully\n";

    echo "✓ All voucher tables created successfully!\n";

} catch (PDOException $e) {
    echo "✗ Error creating voucher tables: " . $e->getMessage() . "\n";
}
?>
