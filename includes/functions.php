<?php

function loadEnv($path) {

    if (!file_exists($path)) {

        return false;

    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);

    foreach ($lines as $line) {
        if ($line === null) { continue; }
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            // Skip malformed lines (e.g., leftover text from multi-line values)
            continue;
        }

        $parts = explode('=', $line, 2);
        $name = trim($parts[0] ?? '');
        $value = trim($parts[1] ?? '');

        // Strip optional quotes
        if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        // Decode escaped newlines (\n)
        $value = str_replace('\\n', "\n", $value);

        if ($name === '') { continue; }

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    return true;

}

function updateEnv($path, $changes) {
    if (!file_exists($path)) {
        return false;
    }
    $content = file_get_contents($path);
    $lines = preg_split("/(\r\n|\n|\r)/", $content);
    $map = [];
    foreach ($lines as $i => $line) {
        if ($line === '' || strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $map[$parts[0]] = $i;
        }
    }
    foreach ($changes as $name => $value) {
        $pair = $name . '=' . $value;
        if (array_key_exists($name, $map)) {
            $lines[$map[$name]] = $pair;
        } else {
            $lines[] = $pair;
        }
    }
    $newContent = implode(PHP_EOL, $lines);
    if (substr($newContent, -1) !== "\n") {
        $newContent .= PHP_EOL;
    }
    $ok = file_put_contents($path, $newContent) !== false;
    if ($ok) {
        // Reload env for current request
        loadEnv($path);
    }
    return $ok;
}

function envEnabled($key, $default = false) {
    $v = getenv($key);
    if ($v === false) return $default;
    $v = strtolower((string)$v);
    return in_array($v, ['1','true','yes','on'], true);
}

function currency_symbol() {
    $sym = getenv('CURRENCY_SYMBOL');
    if ($sym === false || $sym === '') {
        // Default to QAR for Qatar-based company
        return 'QAR ';
    }
    return $sym . ' ';
}

function money($amount, $decimals = 2) {
    if (!is_numeric($amount)) {
        $amount = 0;
    }
    return currency_symbol() . number_format((float)$amount, $decimals);
}

/**
 * Validate purchase total matches items
 */
function validate_purchase_totals($items, $subtotal, $tax, $total) {
    $calculated_subtotal = 0;
    foreach ($items as $item) {
        $calculated_subtotal += floatval($item['quantity'] ?? 0) * floatval($item['unit_price'] ?? 0);
    }
    
    $calculated_total = $calculated_subtotal + floatval($tax);
    
    return [
        'valid' => (abs($calculated_total - floatval($total)) < 0.01 && abs($calculated_subtotal - floatval($subtotal)) < 0.01),
        'calculated_subtotal' => $calculated_subtotal,
        'calculated_total' => $calculated_total
    ];
}

/**
 * Check if payment exceeds outstanding balance
 */
function validate_payment_amount($pdo, $purchase_id, $payment_amount) {
    $stmt = $pdo->prepare("
        SELECT p.total_amount, 
               (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = ?) as paid
        FROM purchases p 
        WHERE p.id = ?
    ");
    $stmt->execute([$purchase_id, $purchase_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        return ['valid' => false, 'message' => 'Purchase not found'];
    }
    
    $outstanding = $data['total_amount'] - $data['paid'];
    
    if ($payment_amount > $outstanding) {
        return [
            'valid' => false, 
            'message' => 'Payment amount exceeds outstanding balance of ' . money($outstanding),
            'outstanding' => $outstanding
        ];
    }
    
    return ['valid' => true, 'outstanding' => $outstanding];
}

/**
 * Check if attachment is required for large payment
 */
function requires_attachment($amount, $threshold = 500) {
    return floatval($amount) > floatval($threshold);
}

/**
 * Get purchase status badge HTML
 */
function purchase_status_badge($status) {
    $classes = [
        'approved' => 'bg-green-100 text-green-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'rejected' => 'bg-red-100 text-red-800',
        'draft' => 'bg-gray-100 text-gray-800'
    ];
    
    $class = $classes[$status] ?? 'bg-gray-100 text-gray-800';
    return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $class . '">' . ucfirst($status) . '</span>';
}

/**
 * Get reimbursement status badge HTML
 */
function reimbursement_status_badge($status) {
    $classes = [
        'paid' => 'bg-green-100 text-green-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'rejected' => 'bg-red-100 text-red-800'
    ];
    
    $class = $classes[$status] ?? 'bg-gray-100 text-gray-800';
    return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $class . '">' . ucfirst($status) . '</span>';
}

// Function to generate (or get) next voucher number
function getNextVoucherNo($pdo) {
    // Logic similar to vouchers.php
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
    $result = $stmt->fetch();
    $next_num = ($result['max_num'] ?? 0) + 1;
    return 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// Function to update credit card balance
function updateCardBalance($pdo, $card_id)
{
    $stmt = $pdo->prepare("SELECT opening_balance FROM credit_cards WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch();

    if (!$card) return;

    // Get total expenses (increases balance)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM credit_card_transactions WHERE card_id = ? AND transaction_type = 'expense'");
    $stmt->execute([$card_id]);
    $expenses = $stmt->fetch()['total'];

    // Get total payments (decreases balance)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM credit_card_transactions WHERE card_id = ? AND transaction_type = 'payment'");
    $stmt->execute([$card_id]);
    $payments = $stmt->fetch()['total'];

    $current_balance = $card['opening_balance'] + $expenses - $payments;

    $stmt = $pdo->prepare("UPDATE credit_cards SET current_balance = ? WHERE id = ?");
    $stmt->execute([$current_balance, $card_id]);
}

// Function to create GL Voucher for Invoice Payment
function addInvoicePaymentVoucher($pdo, $payment_id) {
    // 1. Fetch Payment Details
    $stmt = $pdo->prepare("SELECT p.*, i.client_id, c.name as client_name, i.quotation_id 
                           FROM payments p 
                           JOIN invoices i ON p.invoice_id = i.id 
                           LEFT JOIN clients c ON i.client_id = c.id 
                           WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) return;

    // 2. Handle Card-based Payments
    if (in_array($payment['payment_method'], ['credit_card', 'company_card'], true) && !empty($payment['card_id'])) {
        // Create credit card transaction (expense type - increases outstanding balance)
        try {
            $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
            $stmt->execute([
                $payment['card_id'],
                $payment['date'],
                "Invoice Payment - INV#{$payment['invoice_id']} - {$payment['client_name']}",
                $payment['amount'],
                "PAY-{$payment_id}"
            ]);
            
            // Update card balance
            updateCardBalance($pdo, $payment['card_id']);
        } catch (Exception $e) {
            // Log error silently
        }
    }

    // 3. Determine Accounts for GL Entry
    $debit_account = '';
    
    switch ($payment['payment_method']) {
        case 'company_cash':
            $debit_account = 'Cash';
            break;
        case 'company_bank':
        case 'company_cheque': // Assuming cheques deposited to bank or hand
            $debit_account = 'Bank – Company Account';
            break;
        case 'credit_card':    // Payment made via card
        case 'company_card':
            $debit_account = 'Credit Card Payable'; // Liability account
            break;
        case 'personal':
            $debit_account = 'Accounts Payable';
            break;
        case 'rahees_cash_card':
            $debit_account = 'Rahees – Cash';
            break;
        case 'salman_cash_card':
            $debit_account = 'Salman – Cash';
            break;
        case 'cash': // Generic cash
            $debit_account = 'Cash';
            break;
        default:
            // Fallback or specific logic
            if (strpos($payment['payment_method'], 'cash') !== false) {
                $debit_account = 'Cash';
            } else {
                $debit_account = 'Bank – Company Account';
            }
            break;
    }

    $credit_account = 'Sales Revenue'; // Standardized to 'Sales Revenue' which exists in accounts table
    
    $voucher_no = getNextVoucherNo($pdo);
    $amount = $payment['amount'];
    
    // Description
    $desc = "Invoice Payment - INV#{$payment['invoice_id']} - {$payment['client_name']}";
    if (!empty($payment['notes'])) {
        $desc .= " - " . $payment['notes'];
    }

    try {
        // Create Voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'receipt', ?)");
        $stmt->execute([
            $voucher_no,
            $payment['date'],
            $payment['client_name'],
            $amount,
            $desc,
            "PAY-{$payment_id}" // Reference
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry (Increase Cash/Bank/Credit Card Liability)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry (Income)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        // Log error silently or handle
    }

}
// Function to create GL Voucher for Credit Card Topup
function addCreditCardTopupVoucher($pdo, $card_id, $amount, $date, $reference_val, $payment_method) {
    // 1. Determine Cards Name
    // Get card name
    $stmt = $pdo->prepare("SELECT card_name, bank_name FROM credit_cards WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch();
    $card_name = $card ? ($card['bank_name'] . ' - ' . $card['card_name']) : 'Credit Card';

    // 2. Determine Credit Account (Source of funds)
    $credit_account = '';
    
    // Check payment method
    // If 'company_cash' -> Cash
    // If 'company_bank' -> Bank - Company Account
    if ($payment_method === 'company_cash') {
         $credit_account = 'Cash';
    } else {
         $credit_account = 'Bank – Company Account';
    }

    // 3. Determine Debit Account (Card Liability decreases)
    // We should have a Liability account for the card.
    // If specific account per card not set, use Generic "Credit Card Payable" or similar.
    // We seeded 'Credit Card Payable' (2100) in fix.php.
    $debit_account = 'Credit Card Payable';

    $voucher_no = getNextVoucherNo($pdo);
    
    // Description
    $desc = "Credit Card Topup - {$card_name} - Ref: {$reference_val}";

    try {
        // Create Voucher
        // Type 'payment' because we are paying out cash/bank to pay off card
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([
            $voucher_no,
            $date,
            "Credit Card Provider", // Or Card Name
            $amount,
            $desc,
            "CC-TOPUP-{$card_id}-" . time() // Weak ref but better than nothing
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry (Decrease Liability - i.e. Paying off debt)
        // In Accounting: Dr Liability, Cr Asset
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry (Decrease Cash/Bank)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        // Log error silently or handle
    }
}

// Function to create GL Voucher for Salary Payment
function addSalaryPaymentVoucher($pdo, $payment_id) {
    // 1. Fetch Payment Details
    $stmt = $pdo->prepare("SELECT sp.*, e.name as employee_name 
                           FROM salary_payments sp 
                           JOIN employees e ON sp.employee_id = e.id 
                           WHERE sp.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception("Salary payment not found: {$payment_id}");
    }

    // 2. Determine Credit Account (Source of funds)
    $credit_account = '';
    
    if ($payment['payment_method'] === 'company_cash') {
         $credit_account = 'Cash';
    } elseif ($payment['payment_method'] === 'company_bank' || $payment['payment_method'] === 'Bank Transfer') {
         $credit_account = 'Bank – Company Account';
    } elseif ($payment['payment_method'] === 'credit_card' || $payment['payment_method'] === 'company_card') {
         $credit_account = 'Credit Card Payable';
    } elseif ($payment['payment_method'] === 'personal') {
         $credit_account = 'Accounts Payable';
    } elseif ($payment['payment_method'] === 'rahees_cash_card') {
         $credit_account = 'Rahees – Cash';
    } elseif ($payment['payment_method'] === 'salman_cash_card') {
         $credit_account = 'Salman – Cash';
    } else {
         // Fallback
         $credit_account = 'Cash'; 
    }

    // 3. Determine Debit Account (Expense)
    // Account 5003 is 'Labor'
    $debit_account = 'Labor';

    $voucher_no = getNextVoucherNo($pdo);
    $amount = $payment['amount'];
    
    // Description
    $desc = "Salary Payment - {$payment['employee_name']} - " . date('M Y', strtotime($payment['payment_date']));
    if (!empty($payment['notes'])) {
        $desc .= " - " . $payment['notes'];
    }

    try {
        // Create Voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([
            $voucher_no,
            $payment['payment_date'],
            $payment['employee_name'],
            $amount,
            $desc,
            "SAL-PAY-{$payment_id}" // Reference
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry (Expense)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry (Decrease Cash/Bank)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        file_put_contents('gl_voucher_error.log', date('Y-m-d H:i:s') . " - Error in addSalaryPaymentVoucher: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        throw $e;
    }
}

// Function to create GL Voucher for Advance Payment
function addAdvancePaymentVoucher($pdo, $payment_id) {
    // 1. Fetch Payment Details
    $stmt = $pdo->prepare("SELECT ap.*, e.name as employee_name 
                           FROM advance_payments ap 
                           JOIN employees e ON ap.employee_id = e.id 
                           WHERE ap.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception("Advance payment not found: {$payment_id}");
    }

    // 2. Determine Credit Account (Source of funds)
    $credit_account = '';
    
    if ($payment['payment_method'] === 'company_cash') {
         $credit_account = 'Cash';
    } elseif ($payment['payment_method'] === 'company_bank' || $payment['payment_method'] === 'Bank Transfer') {
         $credit_account = 'Bank – Company Account';
    } elseif ($payment['payment_method'] === 'credit_card' || $payment['payment_method'] === 'company_card') {
         $credit_account = 'Credit Card Payable';
    } elseif ($payment['payment_method'] === 'personal') {
         $credit_account = 'Accounts Payable';
    } elseif ($payment['payment_method'] === 'rahees_cash_card') {
         $credit_account = 'Rahees – Cash';
    } elseif ($payment['payment_method'] === 'salman_cash_card') {
         $credit_account = 'Salman – Cash';
    } else {
         $credit_account = 'Cash';
    }

    // 3. Determine Debit Account
    // Default to 'Labor' (5003) as it matches Salary.
    $debit_account = 'Labor';

    $voucher_no = getNextVoucherNo($pdo);
    $amount = $payment['amount'];
    
    // Description
    $desc = "Advance Payment - {$payment['employee_name']}";
    if (!empty($payment['reason'])) {
        $desc .= " - " . $payment['reason'];
    }

    try {
        // Create Voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([
            $voucher_no,
            $payment['payment_date'],
            $payment['employee_name'],
            $amount,
            $desc,
            "ADV-PAY-{$payment_id}" // Reference
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        file_put_contents('gl_voucher_error.log', date('Y-m-d H:i:s') . " - Error in addAdvancePaymentVoucher: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        throw $e;
    }
}

// Function to create GL Voucher for Purchase Payment
function addPurchasePaymentVoucher($pdo, $payment_id) {
    // 1. Fetch Payment Details
    $stmt = $pdo->prepare("SELECT pp.*, 
                           p.id as purchase_ref,
                           p.description as purchase_description,
                           pr.name as project_name,
                           v.name as vendor_name
                           FROM purchase_payments pp
                           LEFT JOIN purchases p ON pp.purchase_id = p.id
                           LEFT JOIN projects pr ON p.project_id = pr.id
                           LEFT JOIN vendors v ON p.vendor_id = v.id
                           WHERE pp.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) return;

    // 2. Determine Credit Account (Source of funds)
    $credit_account = '';
    
    switch ($payment['payment_method']) {
        case 'company_cash':
            $credit_account = 'Cash';
            break;
        case 'company_bank':
        case 'company_cheque':
            $credit_account = 'Bank – Company Account';
            break;
        case 'company_card':
        case 'credit_card':
            $credit_account = 'Credit Card Payable';
            break;
        case 'rahees_cash_card':
            $credit_account = 'Rahees – Cash';
            break;
        case 'salman_cash_card':
            $credit_account = 'Salman – Cash';
            break;
        case 'personal':
            $credit_account = 'Accounts Payable';
            break;
        default:
            // Fallback
            if (strpos($payment['payment_method'], 'cash') !== false) {
                $credit_account = 'Cash';
            } else {
                $credit_account = 'Bank – Company Account';
            }
            break;
    }

    // 3. Determine Debit Account (Expense)
    // All purchase payments are expenses to 'Purchases' account
    $debit_account = 'Purchases';

    $voucher_no = getNextVoucherNo($pdo);
    $amount = $payment['amount'];
    
    // Description
    $desc = "Purchase Payment - P#{$payment['purchase_ref']}";
    if ($payment['vendor_name']) {
        $desc .= " - {$payment['vendor_name']}";
    }
    if ($payment['project_name']) {
        $desc .= " - {$payment['project_name']}";
    }
    if (!empty($payment['notes'])) {
        $desc .= " - " . $payment['notes'];
    }

    try {
        // Create Voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([
            $voucher_no,
            $payment['payment_date'],
            $payment['vendor_name'] ?? 'Vendor',
            $amount,
            $desc,
            "PURCH-PAY-{$payment_id}" // Reference
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry (Expense - Purchases)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry (single selected payment source only)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        file_put_contents('gl_voucher_error.log', date('Y-m-d H:i:s') . " - Error in addPurchasePaymentVoucher: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    }
}

// Remove purchase payment side effects (GL + card transactions) and refresh impacted cards.
function clearPurchasePaymentSideEffects($pdo, $payment_id) {
    $reference = "PURCH-PAY-{$payment_id}";
    deleteGlVoucher($pdo, $reference);

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $card_ids)))) as $card_id) {
            updateCardBalance($pdo, $card_id);
        }
    } catch (Exception $e) {
        // Ignore if card transaction table/columns are not present in this environment.
    }
}

// Remove subcontract side effects (GL + card transactions) and refresh impacted cards.
function clearSubcontractSideEffects($pdo, $subcontract_id) {
    $reference = "SUBCON-{$subcontract_id}";
    deleteGlVoucher($pdo, $reference);

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $card_ids)))) as $card_id) {
            updateCardBalance($pdo, $card_id);
        }
    } catch (Exception $e) {
        // Ignore if card transaction table/columns are not present in this environment.
    }
}

// Create card transaction side effect for labour payments when card mode is selected.
function addLabourPaymentCardTransaction($pdo, $payment_id) {
    $stmt = $pdo->prepare("SELECT lp.*, ol.name as labour_name, p.name as project_name
                           FROM labour_payments lp
                           LEFT JOIN outside_labours ol ON lp.labour_id = ol.id
                           LEFT JOIN projects p ON lp.project_id = p.id
                           WHERE lp.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        return;
    }

    $mode = $payment['payment_mode'] ?? '';
    if (!in_array($mode, ['credit_card', 'company_card'], true)) {
        return;
    }

    $cardStmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
    $cardStmt->execute();
    $card = $cardStmt->fetch();
    if (!$card) {
        return;
    }

    $description = "Labour Payment - " . ($payment['labour_name'] ?? 'Labour');
    if (!empty($payment['project_name'])) {
        $description .= " - " . $payment['project_name'];
    }

    $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
    $stmt->execute([
        $card['id'],
        $payment['payment_date'],
        $description,
        $payment['paid_amount'],
        "LAB-PAY-{$payment_id}"
    ]);

    updateCardBalance($pdo, $card['id']);
}

// Remove labour payment side effects (GL + card transactions) and refresh impacted cards.
function clearLabourPaymentSideEffects($pdo, $payment_id) {
    $reference = "LAB-PAY-{$payment_id}";
    deleteGlVoucher($pdo, $reference);

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $card_ids)))) as $card_id) {
            updateCardBalance($pdo, $card_id);
        }
    } catch (Exception $e) {
        // Ignore if card transaction table/columns are not present in this environment.
    }
}

// Refresh projects.total_expenses based on expenses table for one project.
function updateProjectExpenseTotal($pdo, $project_id) {
    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $total = (float) ($stmt->fetch()['total'] ?? 0);

        $stmt = $pdo->prepare("UPDATE projects SET total_expenses = ? WHERE id = ?");
        $stmt->execute([$total, $project_id]);
    } catch (Exception $e) {
        // Ignore if schema in this environment does not have expected columns.
    }
}

// Create card transaction side effect for project expenses when card mode is selected.
function addExpenseCardTransaction($pdo, $expense_id) {
    $stmt = $pdo->prepare("SELECT e.*, p.name as project_name
                           FROM expenses e
                           LEFT JOIN projects p ON e.project_id = p.id
                           WHERE e.id = ?");
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch();

    if (!$expense) {
        return;
    }

    $method = $expense['payment_method'] ?? '';
    if (!in_array($method, ['credit_card', 'company_card'], true)) {
        return;
    }

    $cardStmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
    $cardStmt->execute();
    $card = $cardStmt->fetch();
    if (!$card) {
        return;
    }

    $description = "Project Expense - " . ($expense['expense_type'] ?? 'Expense');
    if (!empty($expense['project_name'])) {
        $description .= " - " . $expense['project_name'];
    }
    if (!empty($expense['description'])) {
        $description .= " - " . substr((string) $expense['description'], 0, 120);
    }

    $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
    $stmt->execute([
        $card['id'],
        $expense['date'],
        $description,
        $expense['amount'],
        "EXP-{$expense_id}"
    ]);

    updateCardBalance($pdo, $card['id']);
}

// Remove expense side effects (GL + card transactions) and refresh impacted cards.
function clearExpenseSideEffects($pdo, $expense_id) {
    $reference = "EXP-{$expense_id}";
    deleteGlVoucher($pdo, $reference);

    // Legacy fallback: older expense vouchers may not have the EXP-{id} reference.
    try {
        $stmt = $pdo->prepare("SELECT amount, date, description, paid_by FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        $expense = $stmt->fetch();

        if ($expense) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM vouchers
                WHERE reference IS NULL
                  AND prepared_by = 'System'
                  AND voucher_date = ?
                  AND amount = ?
                  AND description = ?
                  AND (
                        paid_to_received_from = ?
                        OR paid_to_received_from = 'System Entry'
                        OR paid_to_received_from = ''
                  )
                ORDER BY id DESC
                LIMIT 2
            ");
            $stmt->execute([
                $expense['date'],
                $expense['amount'],
                $expense['description'],
                (string) ($expense['paid_by'] ?? '')
            ]);
            $matches = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

            // Delete only when the legacy match is unique to avoid false positives.
            if (count($matches) === 1) {
                $voucher_id = (int) $matches[0];
                $stmt = $pdo->prepare("DELETE FROM voucher_entries WHERE voucher_id = ?");
                $stmt->execute([$voucher_id]);
                $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
                $stmt->execute([$voucher_id]);
            }
        }
    } catch (Exception $e) {
        // Ignore fallback failures; reference-based deletion already attempted.
    }

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $card_ids)))) as $card_id) {
            updateCardBalance($pdo, $card_id);
        }
    } catch (Exception $e) {
        // Ignore if card transaction table/columns are not present in this environment.
    }
}

// Create card transaction side effect for vehicle fuel records when card mode is selected.
function addVehicleFuelCardTransaction($pdo, $fuel_id) {
    $stmt = $pdo->prepare("SELECT vfr.*, v.vehicle_number, v.make, v.model
                           FROM vehicle_fuel_records vfr
                           LEFT JOIN vehicles v ON vfr.vehicle_id = v.id
                           WHERE vfr.id = ?");
    $stmt->execute([$fuel_id]);
    $fuel = $stmt->fetch();

    if (!$fuel) {
        return;
    }

    $method = $fuel['payment_method'] ?? '';
    if (!in_array($method, ['credit_card', 'company_card'], true)) {
        return;
    }

    $cardStmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
    $cardStmt->execute();
    $card = $cardStmt->fetch();
    if (!$card) {
        return;
    }

    $description = "Vehicle Fuel - " . ($fuel['vehicle_number'] ?? 'Vehicle');
    if (!empty($fuel['fuel_station'])) {
        $description .= " - " . $fuel['fuel_station'];
    }

    $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
    $stmt->execute([
        $card['id'],
        $fuel['fuel_date'],
        $description,
        $fuel['amount'],
        "VEH-FUEL-{$fuel_id}"
    ]);

    updateCardBalance($pdo, $card['id']);
}

// Remove vehicle fuel side effects (GL + card transactions) and refresh impacted cards.
function clearVehicleFuelSideEffects($pdo, $fuel_id) {
    $reference = "VEH-FUEL-{$fuel_id}";
    deleteGlVoucher($pdo, $reference);

    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $card_ids)))) as $card_id) {
            updateCardBalance($pdo, $card_id);
        }
    } catch (Exception $e) {
        // Ignore if card transaction table/columns are not present in this environment.
    }
}

// Function to create GL Voucher for Subcontract Payment
function addSubcontractVoucher($pdo, $subcontract_id) {
    // 1. Fetch Subcontract Details
    $stmt = $pdo->prepare("SELECT s.*, 
                           pr.name as project_name,
                           c.company_name as contractor_name
                           FROM subcontracts s
                           LEFT JOIN projects pr ON s.project_id = pr.id
                           LEFT JOIN contractors c ON s.contractor_id = c.id
                           WHERE s.id = ?");
    $stmt->execute([$subcontract_id]);
    $subcontract = $stmt->fetch();

    if (!$subcontract) return;

    // 2. Determine Credit Account (Source of funds)
    $credit_account = '';
    
    switch ($subcontract['payment_method']) {
        case 'company_cash':
            $credit_account = 'Cash';
            break;
        case 'company_bank':
        case 'company_cheque':
            $credit_account = 'Bank – Company Account';
            break;
        case 'company_card':
        case 'credit_card':
            $credit_account = 'Credit Card Payable';
            break;
        case 'rahees_cash_card':
            $credit_account = 'Rahees – Cash';
            break;
        case 'salman_cash_card':
            $credit_account = 'Salman – Cash';
            break;
        case 'personal':
            $credit_account = 'Accounts Payable';
            break;
        default:
            if (strpos($subcontract['payment_method'], 'cash') !== false) {
                $credit_account = 'Cash';
            } else {
                $credit_account = 'Bank – Company Account';
            }
            break;
    }

    // 3. Determine Debit Account (Expense)
    $debit_account = 'Subcontractor Expense';

    $voucher_no = getNextVoucherNo($pdo);
    $amount = $subcontract['amount'];
    
    // Description
    $desc = "Subcontract Payment - {$subcontract['contractor_name']}";
    if ($subcontract['project_name']) {
        $desc .= " - {$subcontract['project_name']}";
    }
    if (!empty($subcontract['description'])) {
        $desc .= " - " . substr($subcontract['description'], 0, 100);
    }

    try {
        // Create Voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([
            $voucher_no,
            $subcontract['payment_date'],
            $subcontract['contractor_name'],
            $amount,
            $desc,
            "SUBCON-{$subcontract_id}"
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry (Expense - Subcontractor)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry (selected payment method only)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        file_put_contents('gl_voucher_error.log', date('Y-m-d H:i:s') . " - Error in addSubcontractVoucher: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    }
}

// Function to delete GL Voucher by reference
function deleteGlVoucher($pdo, $reference) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM vouchers WHERE reference = ?");
        $stmt->execute([$reference]);
        $voucher = $stmt->fetch();
        
        if ($voucher) {
            // Delete entries first (FK cascade usually handles this but manual is safer)
            $stmt = $pdo->prepare("DELETE FROM voucher_entries WHERE voucher_id = ?");
            $stmt->execute([$voucher['id']]);
            
            // Delete voucher
            $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
            $stmt->execute([$voucher['id']]);
        }
    } catch (Exception $e) {
        // Log error
    }
}

// Function to create GL Voucher for Labour Payment
function addLabourPaymentVoucher($pdo, $payment_id) {
    // 1. Fetch Payment Details
    $stmt = $pdo->prepare("SELECT lp.*, ol.name as labour_name, p.name as project_name
                           FROM labour_payments lp
                           LEFT JOIN outside_labours ol ON lp.labour_id = ol.id
                           LEFT JOIN projects p ON lp.project_id = p.id
                           WHERE lp.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) return;

    // 2. Determine Credit Account (Source of funds)
    $credit_account = '';
    // Determine payment mode (stored in payment_mode column)
    $mode = $payment['payment_mode'];
    
    switch ($mode) {
        case 'company_cash':
            $credit_account = 'Cash';
            break;
        case 'company_bank':
        case 'company_cheque':
            $credit_account = 'Bank – Company Account';
            break;
        case 'company_card':
        case 'credit_card':
            $credit_account = 'Credit Card Payable';
            break;
        case 'rahees_cash_card':
            $credit_account = 'Rahees – Cash'; 
            break;
        case 'salman_cash_card':
            $credit_account = 'Salman – Cash'; 
            break;
        case 'personal':
            $credit_account = 'Accounts Payable';
            break;
        default:
            $credit_account = 'Cash';
            if (strpos($mode, 'bank') !== false) {
                $credit_account = 'Bank – Company Account';
            }
            break;
    }

    // 3. Determine Debit Account (Expense)
    $debit_account = 'Labor'; 

    $voucher_no = getNextVoucherNo($pdo);
    $amount = $payment['paid_amount'];
    
    $desc = "Labour Payment - {$payment['labour_name']} - Reference: {$payment['voucher_no']}";
    if ($payment['project_name']) {
        $desc .= " - {$payment['project_name']}";
    }
    if (!empty($payment['remarks'])) {
        $desc .= " - " . $payment['remarks'];
    }

    try {
        // Create Voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, description, status, voucher_type, reference) VALUES (?, ?, ?, ?, ?, 'posted', 'payment', ?)");
        $stmt->execute([
            $voucher_no,
            $payment['payment_date'],
            $payment['labour_name'],
            $amount,
            $desc,
            "LAB-PAY-{$payment_id}"
        ]);
        $voucher_id = $pdo->lastInsertId();

        // Debit Entry (Expense)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$voucher_id, $debit_account, $amount, $desc]);

        // Credit Entry (selected payment method only)
        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
        $stmt->execute([$voucher_id, $credit_account, $amount, $desc]);
        
    } catch (Exception $e) {
        file_put_contents('gl_voucher_error.log', date('Y-m-d H:i:s') . " - Error in addLabourPaymentVoucher: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    }
}
