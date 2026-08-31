<?php
require_once 'includes/db.php';

echo "Checking expense records in detail...\n\n";

try {
    $stmt = $pdo->query("SELECT * FROM expenses");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total records: " . count($expenses) . "\n\n";

    foreach ($expenses as $idx => $exp) {
        echo "Record " . ($idx + 1) . ":\n";
        echo "  ID: " . ($exp['id'] ?? 'NULL') . "\n";
        echo "  Project ID: " . ($exp['project_id'] ?? 'NULL') . "\n";
        echo "  Amount: " . ($exp['amount'] ?? 'NULL') . "\n";
        echo "  Description: " . ($exp['description'] ?? 'NULL') . "\n";
        echo "  Date: " . ($exp['date'] ?? 'NULL - MISSING!') . "\n";
        echo "  Expense Type: " . ($exp['expense_type'] ?? 'NULL') . "\n";
        echo "  Payment Method: " . ($exp['payment_method'] ?? 'NULL') . "\n";
        echo "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
