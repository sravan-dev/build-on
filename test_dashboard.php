<?php
require_once 'includes/db.php';

echo "Testing dashboard expense queries...\n\n";

try {
    // Test query 1: Recent expenses
    echo "1. Testing recent expenses query:\n";
    $recent_expenses = $pdo->query("
        SELECT e.*, p.name as project_name 
        FROM expenses e 
        LEFT JOIN projects p ON e.project_id = p.id 
        ORDER BY e.date DESC, e.id DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($recent_expenses) . " records\n";

    // Test query 2: Total from expenses table only
    echo "\n2. Testing total expenses (expenses table only):\n";
    $expenses_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses")->fetch()['total'] ?? 0;
    echo "   Expenses table: " . $expenses_total . "\n";

    // Test query 3: Total from vendor_payments
    echo "\n3. Testing vendor payments:\n";
    $vendor_payments_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments")->fetch()['total'] ?? 0;
    echo "   Vendor payments: " . $vendor_payments_total . "\n";

    // Test query 4: Total from purchase_payments
    echo "\n4. Testing purchase payments:\n";
    $purchase_payments_total = $pdo->query("SELECT COALESCE(SUM(amount), 0)as total FROM purchase_payments")->fetch()['total'] ?? 0;
    echo "   Purchase payments: " . $purchase_payments_total . "\n";

    // Combined total
    echo "\n5. Combined total for dashboard:\n";
    $combined = $expenses_total + $vendor_payments_total + $purchase_payments_total;
    echo "   Total: " . $combined . "\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
