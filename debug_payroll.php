<?php
include_once 'includes/db.php';

echo "<h2>Debug Payroll Payments</h2>";

echo "<h3>Last 5 Salary Payments</h3>";
$res = $pdo->query("SELECT * FROM salary_payments ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>EmpID</th><th>Date</th><th>Amount</th><th>Method</th><th>Notes</th></tr>";
foreach ($res as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['employee_id']}</td><td>{$r['payment_date']}</td><td>{$r['amount']}</td><td>{$r['payment_method']}</td><td>{$r['notes']}</td></tr>";
}
echo "</table>";

echo "<h3>Last 5 Advance Payments</h3>";
$res = $pdo->query("SELECT * FROM advance_payments ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>EmpID</th><th>Date</th><th>Amount</th><th>Method</th><th>Reason</th></tr>";
foreach ($res as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['employee_id']}</td><td>{$r['payment_date']}</td><td>{$r['amount']}</td><td>{$r['payment_method']}</td><td>{$r['reason']}</td></tr>";
}
echo "</table>";

echo "<h3>Last 5 Vouchers</h3>";
$res = $pdo->query("SELECT * FROM vouchers ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>No</th><th>Desc</th><th>Type</th><th>Ref</th></tr>";
foreach ($res as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['voucher_no']}</td><td>{$r['description']}</td><td>{$r['voucher_type']}</td><td>{$r['reference']}</td></tr>";
}
echo "</table>";
?>
