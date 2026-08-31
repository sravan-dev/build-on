<?php
// includes/gl_functions.php

/**
 * Maps a payment method key (from expenses/payments) to a General Ledger Account Name.
 */
function get_gl_account_for_payment_method($method) {
    $map = [
        'company_cash' => 'Cash',
        'company_bank' => 'Bank – Company Account',
        'company_card' => 'Credit Card Payable',
        'company_cheque' => 'Bank – Company Account',
        'credit_card' => 'Credit Card Payable',
        'rahees_cash_card' => 'Rahees – Cash', // Assuming combined for now, or split if needed
        'salman_cash_card' => 'Salman – Cash',
        'personal' => 'Accounts Payable' // Default for 'personal' if not specified
    ];

    return $map[$method] ?? 'Cash'; // Default to Cash if unknown
}

/**
 * Creates a Journal Entry (Voucher) in the General Ledger.
 * 
 * @param PDO $pdo Database connection
 * @param string $date Transaction date (Y-m-d)
 * @param float $amount Transaction amount
 * @param string $debit_account Name of the GL Account to Debit
 * @param string $credit_account Name of the GL Account to Credit
 * @param string $description Main narration/description for the voucher
 * @param string|null $reference Reference info (e.g. Invoice #, Vehicle Reg)
 * @param string $payee Who was paid/received from
 * @return int The ID of the created voucher
 * @throws Exception If voucher creation fails
 */
function create_journal_entry($pdo, $date, $amount, $debit_account, $credit_account, $description, $reference = null, $payee = null, $voucher_reference = null) {
    if ($amount <= 0) {
        return 0;
    }

    // 1. Generate Voucher Number
    // (Logic copied from existing pages to maintain consistency)
    $v_stmt = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
    $next_num = ($v_stmt->fetch()['max_num'] ?? 0) + 1;
    $voucher_no = 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

    // 2. Insert Voucher Head
    $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, prepared_by, status, reference) VALUES (?, ?, ?, ?, ?, 'System', 'posted', ?)");
    $payee_text = $payee ?? 'System Entry';
    // Append reference to description if provided
    $full_desc = $description . ($reference ? " (Ref: $reference)" : "");
    
    $stmt->execute([$voucher_no, $date, $payee_text, $amount, $full_desc, $voucher_reference]);
    $voucher_id = $pdo->lastInsertId();

    // 3. Insert Debit Entry
    $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
    $stmt->execute([$voucher_id, $debit_account, $amount, $full_desc]);

    // 4. Insert Credit Entry
    $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
    $stmt->execute([$voucher_id, $credit_account, $amount, $full_desc]);

    return $voucher_id;
}
?>
