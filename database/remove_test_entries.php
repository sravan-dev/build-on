<?php
/**
 * Remove Test Entry from Invoice Items
 */

require_once __DIR__ . '/../includes/db.php';

echo "Searching for test entries in invoice_items...\n\n";

try {
    // Find test entries
    $stmt = $pdo->query("SELECT * FROM invoice_items WHERE description LIKE '%test%'");
    $test_items = $stmt->fetchAll();

    if (empty($test_items)) {
        echo "No test entries found.\n";
        exit;
    }

    echo "Found " . count($test_items) . " test entry/entries:\n\n";

    foreach ($test_items as $item) {
        echo "ID: {$item['id']}\n";
        echo "Invoice ID: {$item['invoice_id']}\n";
        echo "Description: {$item['description']}\n";
        echo "Quantity: {$item['quantity']}\n";
        echo "Price: {$item['price']}\n";
        echo "Amount: {$item['total']}\n";
        echo "---\n";
    }

    echo "\nDeleting test entries...\n";

    foreach ($test_items as $item) {
        $invoice_id = $item['invoice_id'];

        // Delete the test item
        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE id = ?");
        $stmt->execute([$item['id']]);

        echo "✓ Deleted item ID {$item['id']}\n";

        // Recalculate invoice total
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as new_total FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $result = $stmt->fetch();
        $new_total = $result['new_total'];

        // Update invoice total
        $stmt = $pdo->prepare("UPDATE invoices SET total_amount = ? WHERE id = ?");
        $stmt->execute([$new_total, $invoice_id]);

        echo "✓ Updated invoice #{$invoice_id} total to {$new_total}\n";

        // Recalculate invoice payment status
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $result = $stmt->fetch();
        $total_paid = $result['total_paid'];

        $balance = $new_total - $total_paid;

        if ($total_paid == 0) {
            $status = 'unpaid';
        } elseif ($balance <= 0) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }

        $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
        $stmt->execute([$total_paid, $balance, $status, $invoice_id]);

        echo "✓ Updated invoice payment status\n";
    }

    echo "\n✓ All test entries removed successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
