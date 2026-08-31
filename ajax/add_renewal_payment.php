<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

function normalizeRenewalPaymentDate($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return date('Y-m-d');
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt instanceof DateTime && $dt->format($format) === $raw) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function getRenewalCreditAccount($payment_method)
{
    if (strpos($payment_method, 'card_') === 0) {
        return 'Credit Card Payable';
    }

    switch ($payment_method) {
        case 'company_cash':
            return 'Cash';
        case 'company_bank':
        case 'company_cheque':
            return 'Bank – Company Account';
        case 'company_card':
        case 'credit_card':
            return 'Credit Card Payable';
        case 'personal':
            return 'Accounts Payable';
        case 'rahees_cash_card':
            return 'Rahees – Cash';
        case 'salman_cash_card':
            return 'Salman – Cash';
        default:
            return 'Cash';
    }
}

function getRenewalCardId($pdo, $payment_method)
{
    if (strpos($payment_method, 'card_') === 0) {
        $id = intval(str_replace('card_', '', $payment_method));
        return $id > 0 ? $id : null;
    }

    if ($payment_method === 'credit_card' || $payment_method === 'company_card') {
        $stmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
        $stmt->execute();
        $card = $stmt->fetch();
        return $card ? intval($card['id']) : null;
    }

    return null;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['employee_id']) || empty($input['document_type']) || empty($input['amount'])) {
        throw new Exception('Missing required fields');
    }

    $employee_id = intval($input['employee_id']);
    $document_type = trim((string)$input['document_type']);
    $amount = floatval($input['amount']);
    $payment_date = normalizeRenewalPaymentDate($input['payment_date'] ?? '');
    $payment_method = trim((string)($input['payment_method'] ?? 'company_cash'));
    $notes = trim((string)($input['notes'] ?? ''));

    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }
    if (!$payment_date) {
        throw new Exception('Invalid payment date. Use YYYY-MM-DD.');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Generate RP ID (RP-YYYY-XXXX)
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM renewal_payments WHERE year = ?");
        $stmt->execute([$year]);
        $count = $stmt->fetchColumn() + 1;
        $rp_id = sprintf("RP-%s-%04d", $year, $count);

        // Insert renewal payment record
        $stmt = $pdo->prepare("INSERT INTO renewal_payments (employee_id, document_type, amount, payment_date, payment_method, notes, rp_id, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employee_id, $document_type, $amount, $payment_date, $payment_method, $notes, $rp_id, $year]);

        // Get employee name for voucher description
        $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee_name = $stmt->fetchColumn();

        // === GENERAL LEDGER INTEGRATION ===
        
        // 1. Create Voucher
        $voucher_no = getNextVoucherNo($pdo);
        $description = "Renewal Payment: $document_type for $employee_name ($rp_id)";
        
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([$voucher_no, $payment_date, $employee_name, $amount, $description, $rp_id]);
        $voucher_id = $pdo->lastInsertId();

        // 2. Get or create Renewal Expense account
        $stmt = $pdo->prepare("SELECT account_name FROM accounts WHERE account_name = 'Renewal Expenses' LIMIT 1");
        $stmt->execute();
        $expense_account = $stmt->fetchColumn();
        
        if (!$expense_account) {
            // Create the account if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO accounts (account_code, account_name, account_type, is_active) VALUES ('5300', 'Renewal Expenses', 'expense', 1)");
            $stmt->execute();
            $expense_account = 'Renewal Expenses';
        }

        // 3. Debit: Renewal Expense
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $expense_account, $amount, $description]);

        // 4. Credit: Payment Method Account
        $credit_account = getRenewalCreditAccount($payment_method);
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $description]);

        // Card-based methods should update only card source.
        $card_id = getRenewalCardId($pdo, $payment_method);
        if (!empty($card_id)) {
            $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
            $stmt->execute([$card_id, $payment_date, $description, $amount, $rp_id]);
            updateCardBalance($pdo, $card_id);
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'rp_id' => $rp_id
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
