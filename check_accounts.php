<?php
include_once 'includes/db.php';

$stmt = $pdo->query("SELECT * FROM accounts ORDER BY account_code");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Accounts: " . count($accounts) . "\n";
foreach ($accounts as $acc) {
    echo "{$acc['account_code']} - {$acc['account_name']} ({$acc['account_type']})\n";
}
?>
