<?php
include_once 'includes/db.php';

echo "<h2>GL Accounts Check</h2>";

$stmt = $pdo->query("SELECT * FROM accounts WHERE account_head LIKE '%Credit%' OR account_head LIKE '%Card%'");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Account Code</th><th>Account Head</th><th>Type</th></tr>";
foreach ($accounts as $acc) {
    echo "<tr>";
    echo "<td>{$acc['id']}</td>";
    echo "<td>{$acc['account_code']}</td>";
    echo "<td>{$acc['account_head']}</td>";
    echo "<td>{$acc['type']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>All Accounts:</h3>";
$all = $pdo->query("SELECT account_head FROM accounts ORDER BY account_head")->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>" . print_r($all, true) . "</pre>";
?>
