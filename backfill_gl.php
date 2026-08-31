<?php
// Script to backfill missing GL vouchers for existing Invoice Payments
// Usage: php backfill_gl.php

include_once 'includes/db.php';
// Functions are now in includes/functions.php which is loaded by db.php

echo "Starting GL Backfill for Payments...\n";

// 1. Get all payments
try {
    $payments = $pdo->query("SELECT id, invoice_id, amount, date FROM payments ORDER BY id ASC")->fetchAll();
    
    $count = 0;
    $created = 0;
    $skipped = 0;

    foreach ($payments as $payment) {
        $count++;
        $payment_id = $payment['id'];
        
        // 2. Check if voucher already exists for this payment reference
        // Reference format: "PAY-{payment_id}"
        $ref = "PAY-{$payment_id}";
        $stmt = $pdo->prepare("SELECT id FROM vouchers WHERE reference = ?");
        $stmt->execute([$ref]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $skipped++;
            // echo "Skipping Payment #{$payment_id} - Voucher exists (ID: {$existing['id']})\n";
            continue;
        }

        // 3. Create Voucher if missing
        echo "Creating Voucher for Payment #{$payment_id} (Invoice #{$payment['invoice_id']}, Amount: {$payment['amount']})...\n";
        addInvoicePaymentVoucher($pdo, $payment_id);
        $created++;
    }

    echo "\nBackfill Complete!\n";
    echo "Total Payments Processed: {$count}\n";
    echo "Vouchers Created: {$created}\n";
    echo "Skipped (Already Existed): {$skipped}\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
