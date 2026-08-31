<?php
include_once 'includes/db.php';

echo "--- Breakdown of Total Expenses (5,560.00) ---\n";

// 1. Expenses Table
$expenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses")->fetch()['total'];
echo "1. Expenses Table: " . number_format($expenses, 2) . "\n";

// 2. Vendor Payments
$vendor_payments = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments")->fetch()['total'];
echo "2. Vendor Payments: " . number_format($vendor_payments, 2) . "\n";

// 3. Purchase Payments
$purchase_payments = 0;
try {
    $purchase_payments = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM purchase_payments")->fetch()['total'];
} catch (Exception $e) {}
echo "3. Purchase Payments: " . number_format($purchase_payments, 2) . "\n";

// 4. Labour Payments
$labour_payments = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) as total FROM labour_payments")->fetch()['total'];
echo "4. Labour Payments: " . number_format($labour_payments, 2) . "\n";

// 5. Vehicle Expenses
$vehicle_expenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vehicle_expenses")->fetch()['total'];
echo "5. Vehicle Expenses: " . number_format($vehicle_expenses, 2) . "\n";

$total = $expenses + $vendor_payments + $purchase_payments + $labour_payments + $vehicle_expenses;
echo "---------------------------------\n";
echo "TOTAL: " . number_format($total, 2) . "\n";

// Detailed list if small enough
echo "\n--- Details ---\n";
if ($expenses > 0) {
    echo "Expenses Table Items:\n";
    $stm = $pdo->query("SELECT description, amount FROM expenses");
    while ($r = $stm->fetch()) {
        echo "- {$r['description']}: {$r['amount']}\n";
    }
}
if ($vehicle_expenses > 0) {
    echo "\nVehicle Expenses Items:\n";
    $stm = $pdo->query("SELECT expense_type, amount FROM vehicle_expenses");
    while ($r = $stm->fetch()) {
        echo "- {$r['expense_type']}: {$r['amount']}\n";
    }
}
?>
