<?php
/**
 * One-time Script: Recalculate All Invoice Payment Totals
 * 
 * This script recalculates paid_amount, balance, and status for all invoices
 * based on their actual payment records.
 * 
 * Run this once to fix existing data.
 */

require_once __DIR__ . '/../includes/db.php';

echo "Starting invoice payment recalculation...\n\n";

try {
    // Get all invoices
    $invoices = $pdo->query("SELECT id, total_amount FROM invoices")->fetchAll();

    $updated_count = 0;

    foreach ($invoices as $invoice) {
        $invoice_id = $invoice['id'];
        $total_amount = $invoice['total_amount'];

        // Calculate total paid for this invoice
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $result = $stmt->fetch();
        $total_paid = $result['total_paid'];

        // Calculate balance
        $balance = $total_amount - $total_paid;

        // Determine status
        if ($total_paid == 0) {
            $status = 'unpaid';
        } elseif ($balance <= 0) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }

        // Update invoice
        $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
        $stmt->execute([$total_paid, $balance, $status, $invoice_id]);

        echo "Invoice #$invoice_id:\n";
        echo "  Total: $total_amount\n";
        echo "  Paid: $total_paid\n";
        echo "  Balance: $balance\n";
        echo "  Status: $status\n\n";

        $updated_count++;
    }

    echo "\n✓ Successfully recalculated $updated_count invoices!\n";
    echo "\nYou can now delete this script as it's no longer needed.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
