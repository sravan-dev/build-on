<?php
require_once 'includes/db.php';

echo "Checking expenses table...\n\n";

try {
    // Count records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expenses");
    $result = $stmt->fetch();
    echo "Total expense records: " . $result['count'] . "\n";

    // Sum amounts
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses");
    $result = $stmt->fetch();
    echo "Total expense amount: " . $result['total'] . "\n\n";

    // Show sample records
    if ($result['total'] > 0) {
        echo "Sample records:\n";
        $stmt = $pdo->query("SELECT id, project_id, amount, description, payment_method FROM expenses LIMIT 5");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $rec) {
            echo "  ID: {$rec['id']}, Amount: {$rec['amount']}, Method: {$rec['payment_method']}, Desc: {$rec['description']}\n";
        }
    } else {
        echo "No expense records found in database.\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
