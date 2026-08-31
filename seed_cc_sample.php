<?php
include_once 'includes/db.php';

echo "Debugging Vouchers Table Schema...\n";

try {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stm = $pdo->query("SHOW CREATE TABLE vouchers");
        $row = $stm->fetch(PDO::FETCH_ASSOC);
        echo "CREATE SQL:\n" . $row['Create Table'] . "\n";
    } else {
        echo "SQLite detected. Columns:\n";
        $stm = $pdo->query("PRAGMA table_info(vouchers)");
        $cols = $stm->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "{$col['name']} - {$col['type']}\n";
        }
    }

    echo "\nAttempting Insert again...\n";
    
    // 1. Get Accounts
    $cc_account = 'Credit Card Payable';
    $equity_account = 'Opening Balance Adjustment';

    // 2. Generate Voucher No
    $v_stmt = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
    $next_num = ($v_stmt->fetch()['max_num'] ?? 0) + 1;
    $voucher_no = 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    $date = date('Y-m-d');
    $amount = 1250.00;

    // Use NAMED placeholders to be sure
    $sql = "INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, amount_in_words, description, prepared_by, status) 
            VALUES (:no, :dt, :payee, :amt, :words, :desc, :by, 'posted')";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':no' => $voucher_no,
        ':dt' => $date,
        ':payee' => 'Test Payee',        // Simplified
        ':amt' => $amount,
        ':words' => 'Test Amount',       // Simplified
        ':desc' => 'Test Description',   // Simplified
        ':by' => 'System'
    ];
    
    $stmt->execute($params);
    $voucher_id = $pdo->lastInsertId();

    echo "Voucher Created ID: $voucher_id\n";

    // 4. Create Entries
    $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
    $stmt->execute([$voucher_id, $cc_account, $amount, 'Opening Balance']);

    $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
    $stmt->execute([$voucher_id, $equity_account, $amount, 'Opening Balance Adj']);

    echo "Success! Dashboard should now show Credit Card Balance: $amount\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
