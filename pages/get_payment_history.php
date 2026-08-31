<?php
/**
 * Get Payment History for an Invoice
 * Returns JSON data with all payments for a specific invoice
 */

header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../includes/db.php';

// Get invoice ID from query parameter
$invoice_id = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;

if ($invoice_id <= 0) {
    echo json_encode(['error' => 'Invalid invoice ID', 'payments' => []]);
    exit;
}

try {
    // Fetch all payments for this invoice
    $stmt = $pdo->prepare("
        SELECT 
            id,
            invoice_id,
            amount,
            date,
            payment_method,
            cheque_number,
            bank_name,
            notes
        FROM payments 
        WHERE invoice_id = ? 
        ORDER BY date DESC, id DESC
    ");

    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'invoice_id' => $invoice_id,
        'payments' => $payments,
        'count' => count($payments)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'payments' => []
    ]);
}
