<?php
include_once 'includes/db.php';

echo "<h2>Debug Balance Calculation</h2>";

// 1. Check Account Heads in Voucher Entries
echo "<h3>Distinct Account Heads in Ledger</h3>";
$heads = $pdo->query("SELECT DISTINCT account_head FROM voucher_entries")->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($heads as $head) {
    echo "<li>['" . $head . "'] (Hex: " . bin2hex($head) . ")</li>";
}
echo "</ul>";

// 2. Check Recent Vouchers
echo "<h3>Last 5 Vouchers</h3>";
$vouchers = $pdo->query("SELECT * FROM vouchers ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>No</th><th>Desc</th><th>Type</th><th>Ref</th></tr>";
foreach ($vouchers as $v) {
    echo "<tr>";
    echo "<td>{$v['id']}</td>";
    echo "<td>{$v['voucher_no']}</td>";
    echo "<td>{$v['description']}</td>";
    echo "<td>{$v['voucher_type']}</td>";
    echo "<td>{$v['reference']}</td>";
    echo "</tr>";
    
    // Entries
    $entries = $pdo->query("SELECT * FROM voucher_entries WHERE voucher_id = {$v['id']}")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($entries as $e) {
        echo "<tr><td colspan='5' style='background:#f9f9f9; padding-left:20px;'>";
        echo "{$e['account_head']} | Dr: {$e['debit_amount']} | Cr: {$e['credit_amount']}";
        echo "</td></tr>";
    }
}
echo "</table>";

// 3. Check Dashboard Logic for Cash
echo "<h3>Dashboard Logic Check: 'Cash'</h3>";
$stmt = $pdo->prepare("SELECT SUM(debit_amount) - SUM(credit_amount) as balance FROM voucher_entries WHERE account_head = ?");
$stmt->execute(['Cash']);
$bal = floatval($stmt->fetch()['balance'] ?? 0);
echo "Balance for 'Cash': $bal<br>";

// 4. Check for 'Company Cash' explicitly just in case
$stmt->execute(['Company Cash']);
$bal2 = floatval($stmt->fetch()['balance'] ?? 0);
echo "Balance for 'Company Cash': $bal2<br>";

?>
