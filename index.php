<?php


session_start();

// DEBUG: Log all POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    file_put_contents('global_debug_post.log', date('Y-m-d H:i:s') . " - URL: " . $_SERVER['REQUEST_URI'] . " - POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

loadEnv('.env');

function normalizePostDate($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
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

// Handle Excel template download before any output
if (isset($_GET['download_template']) && isset($_SESSION['logged_in'])) {
    require_once __DIR__ . '/includes/excel_import.php';
    generateEmployeeSampleExcel();
    exit;
}

// Handle Client Excel template download
if (isset($_GET['download_client_template']) && isset($_SESSION['logged_in'])) {
    require_once __DIR__ . '/includes/excel_import.php';
    generateClientSampleExcel();
    exit;
}

// Handle Vendor Excel template download
if (isset($_GET['download_vendor_template']) && isset($_SESSION['logged_in'])) {
    require_once __DIR__ . '/includes/excel_import.php';
    generateVendorSampleExcel();
    exit;
}

// Handle quotation approval redirect BEFORE any output
if (isset($_GET['approve']) && isset($_GET['page']) && $_GET['page'] === 'quotations' && isset($_SESSION['logged_in'])) {
    $aid = (int) $_GET['approve'];
    if ($aid > 0) {
        $stmt = $pdo->prepare("UPDATE quotations SET status = 'approved' WHERE id = ?");
        $stmt->execute([$aid]);
    }
    header('Location: index.php?page=quotations');
    exit;
}

// Handle quotation to invoice conversion BEFORE any output
if (isset($_GET['convert_to_invoice']) && isset($_GET['page']) && $_GET['page'] === 'quotations' && isset($_SESSION['logged_in'])) {
    $qid = (int) $_GET['convert_to_invoice'];
    $q = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $q->execute([$qid]);
    $quotation = $q->fetch(PDO::FETCH_ASSOC);
    if ($quotation && ($quotation['status'] ?? 'pending') === 'approved') {
        $ins = $pdo->prepare("INSERT INTO invoices (quotation_id, client_id, date, total_amount) VALUES (?, ?, ?, ?)");
        $ins->execute([$qid, $quotation['client_id'], $quotation['date'], $quotation['total_amount']]);
        $iid = $pdo->lastInsertId();

        $items = $pdo->prepare("SELECT description, quantity, price, total FROM quotation_items WHERE quotation_id = ?");
        $items->execute([$qid]);
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);
        if ($iid && $rows) {
            $iStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
            foreach ($rows as $r) {
                $iStmt->execute([$iid, $r['description'], $r['quantity'], $r['price'], $r['total']]);
            }
        }
        header('Location: index.php?page=invoices');
        exit;
    } else {
        header('Location: index.php?page=quotations');
        exit;
    }
}

// Handle purchase delete BEFORE any output
if (isset($_GET['delete']) && isset($_GET['page']) && $_GET['page'] === 'purchases' && isset($_SESSION['logged_in'])) {
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: index.php?page=purchases');
    exit;
}

// Handle purchase submit for approval BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_for_approval']) && isset($_SESSION['logged_in'])) {
    $stmt = $pdo->prepare("UPDATE purchases SET status = 'pending' WHERE id = ?");
    $stmt->execute([$_POST['purchase_id']]);

    $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$_POST['purchase_id'], 'submitted', 'Admin', 'Submitted for approval']);

    header('Location: index.php?page=purchases&success=1');
    exit;
}

// Handle purchase approval BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_purchase']) && isset($_SESSION['logged_in'])) {
    $stmt = $pdo->prepare("UPDATE purchases SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute(['Admin', $_POST['purchase_id']]);

    $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$_POST['purchase_id'], 'approved', 'Admin', 'Purchase approved']);

    header('Location: index.php?page=purchases&success=1');
    exit;
}

// Handle purchase rejection BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_purchase']) && isset($_SESSION['logged_in'])) {
    $stmt = $pdo->prepare("UPDATE purchases SET status = 'rejected', rejection_reason = ? WHERE id = ?");
    $stmt->execute([$_POST['rejection_reason'], $_POST['purchase_id']]);

    $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
    $logStmt->execute([$_POST['purchase_id'], 'rejected', 'Admin', $_POST['rejection_reason']]);

    header('Location: index.php?page=purchases&success=1');
    exit;
}

// Handle Payroll Payment Submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment']) && isset($_POST['payment_employee_id']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();
        
        $employee_id = $_POST['payment_employee_id'];
        $payment_date = normalizePostDate($_POST['payment_date'] ?? '');
        $advance_amount = floatval($_POST['advance_amount'] ?? 0);
        $salary_amount = floatval($_POST['salary_amount'] ?? 0);
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';

        if (!$payment_date) {
            throw new Exception('Invalid payment date. Use YYYY-MM-DD.');
        }
        if ($advance_amount <= 0 && $salary_amount <= 0) {
            throw new Exception('Enter salary amount or advance amount greater than 0.');
        }
        
        // 1. Handle Advance Payment
        if ($advance_amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO advance_payments (employee_id, payment_date, amount, reason, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $payment_date, $advance_amount, $notes ?: 'Advance Payment', $payment_method]);
            $adv_pay_id = $pdo->lastInsertId();

            // Update employees.advances
            $upd = $pdo->prepare("UPDATE employees SET advances = COALESCE(advances, 0) + ? WHERE id = ?");
            $upd->execute([$advance_amount, $employee_id]);

            // Create GL Voucher for Advance
            addAdvancePaymentVoucher($pdo, $adv_pay_id);
        }
        
        // 2. Handle Salary Payment
        if ($salary_amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO salary_payments (employee_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $payment_date, $salary_amount, $payment_method, $notes]);
            $sal_pay_id = $pdo->lastInsertId();

            // Create GL Voucher for Salary
            addSalaryPaymentVoucher($pdo, $sal_pay_id);
        }
        
        // 3. Handle Credit Card Transactions
        if (($payment_method === 'credit_card' || $payment_method === 'company_card') && ($advance_amount > 0 || $salary_amount > 0)) {
            // Get the first active credit card
            $cardStmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
            $cardStmt->execute();
            $card = $cardStmt->fetch();
            
            if ($card) {
                $total_amount = $advance_amount + $salary_amount;
                
                // Get employee name for description
                $empStmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
                $empStmt->execute([$employee_id]);
                $employee = $empStmt->fetch();
                $emp_name = $employee['name'] ?? 'Employee';
                
                $description = "Payroll Payment - {$emp_name}";
                if ($advance_amount > 0) $description .= " (Advance: {$advance_amount})";
                if ($salary_amount > 0) $description .= " (Salary: {$salary_amount})";
                
                // Create credit card transaction
                $ccStmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
                $ccStmt->execute([
                    $card['id'],
                    $payment_date,
                    $description,
                    $total_amount,
                    'PAYROLL-' . date('YmdHis')
                ]);
                
                // Update card balance
                $balanceStmt = $pdo->prepare("SELECT opening_balance FROM credit_cards WHERE id = ?");
                $balanceStmt->execute([$card['id']]);
                $cardData = $balanceStmt->fetch();
                
                // Get total expenses
                $expStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM credit_card_transactions WHERE card_id = ? AND transaction_type = 'expense'");
                $expStmt->execute([$card['id']]);
                $expenses = $expStmt->fetch()['total'];
                
                // Get total payments
                $payStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM credit_card_transactions WHERE card_id = ? AND transaction_type = 'payment'");
                $payStmt->execute([$card['id']]);
                $payments = $payStmt->fetch()['total'];
                
                $current_balance = $cardData['opening_balance'] + $expenses - $payments;
                
                $updateStmt = $pdo->prepare("UPDATE credit_cards SET current_balance = ? WHERE id = ?");
                $updateStmt->execute([$current_balance, $card['id']]);
            }
        }
        
        $pdo->commit();
        header('Location: index.php?page=payroll&success=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: index.php?page=payroll&error=' . urlencode($e->getMessage()));
        exit;
    }
}

function addPurchasePaymentCardTransaction($pdo, $purchase_id, $payment_id, $payment_date, $amount) {
    if ($amount <= 0) {
        return;
    }

    $cardStmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
    $cardStmt->execute();
    $card = $cardStmt->fetch();
    if (!$card) {
        return;
    }

    $purchaseDescStmt = $pdo->prepare("SELECT p.description, pr.name as project_name, v.name as vendor_name 
                                       FROM purchases p 
                                       LEFT JOIN projects pr ON p.project_id = pr.id
                                       LEFT JOIN vendors v ON p.vendor_id = v.id
                                       WHERE p.id = ?");
    $purchaseDescStmt->execute([$purchase_id]);
    $purchaseInfo = $purchaseDescStmt->fetch();

    $description = "Purchase Payment - P#{$purchase_id}";
    if (!empty($purchaseInfo['vendor_name'])) {
        $description .= " - {$purchaseInfo['vendor_name']}";
    }
    if (!empty($purchaseInfo['project_name'])) {
        $description .= " - {$purchaseInfo['project_name']}";
    }

    $ccStmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
    $ccStmt->execute([
        $card['id'],
        $payment_date,
        $description,
        $amount,
        'PURCH-PAY-' . $payment_id
    ]);

    updateCardBalance($pdo, $card['id']);
}

function addSubcontractCardTransaction($pdo, $subcontract_id, $payment_date, $amount) {
    if ($amount <= 0) {
        return;
    }

    $cardStmt = $pdo->prepare("SELECT id FROM credit_cards WHERE status = 'active' ORDER BY id LIMIT 1");
    $cardStmt->execute();
    $card = $cardStmt->fetch();
    if (!$card) {
        return;
    }

    $subStmt = $pdo->prepare("SELECT s.description, c.company_name as contractor_name
                              FROM subcontracts s
                              LEFT JOIN contractors c ON s.contractor_id = c.id
                              WHERE s.id = ?");
    $subStmt->execute([$subcontract_id]);
    $sub = $subStmt->fetch();

    $description = "Subcontract Payment - ID#{$subcontract_id}";
    if (!empty($sub['contractor_name'])) {
        $description .= " - {$sub['contractor_name']}";
    }
    if (!empty($sub['description'])) {
        $description .= " - " . substr($sub['description'], 0, 100);
    }

    $ccStmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, 'expense', ?)");
    $ccStmt->execute([
        $card['id'],
        $payment_date,
        $description,
        $amount,
        'SUBCON-' . $subcontract_id
    ]);

    updateCardBalance($pdo, $card['id']);
}

// Handle purchase return to vendor BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_purchase_return']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();

        $purchase_id = (int) ($_POST['return_purchase_id'] ?? 0);
        $amount = floatval($_POST['return_amount'] ?? 0);
        $return_date = normalizePostDate($_POST['return_date'] ?? '');
        $reason = trim((string) ($_POST['return_reason'] ?? ''));

        if ($purchase_id <= 0) {
            throw new Exception('Purchase not found.');
        }
        if (!$return_date) {
            throw new Exception('Invalid return date. Use YYYY-MM-DD.');
        }
        if ($amount <= 0) {
            throw new Exception('Return amount must be greater than 0.');
        }

        // A return can never exceed what is left of the purchase after earlier returns.
        $stmt = $pdo->prepare("SELECT p.total_amount, p.invoice_number,
                                      (SELECT COALESCE(SUM(amount), 0) FROM purchase_returns WHERE purchase_id = p.id) AS returned
                               FROM purchases p WHERE p.id = ?");
        $stmt->execute([$purchase_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$purchase) {
            throw new Exception('Purchase not found.');
        }

        $returnable = (float) $purchase['total_amount'] - (float) $purchase['returned'];
        if ($amount > $returnable + 0.001) {
            throw new Exception('Return amount exceeds the returnable balance of ' . number_format($returnable, 2) . '.');
        }

        $ins = $pdo->prepare("INSERT INTO purchase_returns (purchase_id, return_date, amount, invoice_number, reason, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $purchase_id,
            $return_date,
            $amount,
            $purchase['invoice_number'],
            $reason !== '' ? $reason : null,
            $_SESSION['username'] ?? 'Admin',
        ]);

        $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
        $logStmt->execute([
            $purchase_id,
            'returned',
            $_SESSION['username'] ?? 'Admin',
            'Returned ' . number_format($amount, 2) . ' to vendor' . ($reason !== '' ? ' - ' . $reason : ''),
        ]);

        $pdo->commit();
        header('Location: index.php?page=purchases&success=1&returned=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: index.php?page=purchases&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle purchase payment addition BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment']) && isset($_POST['purchase_id']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();
        
        $amount = floatval($_POST['amount']);
        $purchase_id = intval($_POST['purchase_id']);
        $payment_method = $_POST['payment_method'];

        // Get purchase total and paid amount
        $purchaseStmt = $pdo->prepare("SELECT total_amount, (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = ?) as paid FROM purchases WHERE id = ?");
        $purchaseStmt->execute([$purchase_id, $purchase_id]);
        $purchaseData = $purchaseStmt->fetch();

        if ($purchaseData) {
            $outstanding = $purchaseData['total_amount'] - $purchaseData['paid'];

            // Validate payment amount
            if ($amount > 0 && $amount <= $outstanding) {
                // Determine if reimbursable
                $is_reimbursable = ($payment_method == 'personal') ? 1 : 0;
                $employee_id = $is_reimbursable ? ($_POST['employee_id'] ?: null) : null;

                $stmt = $pdo->prepare("INSERT INTO purchase_payments 
                    (purchase_id, payment_date, amount, payment_method, payment_account, cheque_number, bank_name, paid_by, employee_id, is_reimbursable, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $purchase_id,
                    $_POST['payment_date'],
                    $amount,
                    $payment_method,
                    $_POST['payment_account'] ?? null,
                    $_POST['cheque_number'] ?? null,
                    $_POST['bank_name'] ?? null,
                    $_POST['paid_by'] ?? null,
                    $employee_id,
                    $is_reimbursable,
                    $_POST['notes'] ?? null
                ]);

                $payment_id = $pdo->lastInsertId();

                // Create GL Voucher for Purchase Payment
                addPurchasePaymentVoucher($pdo, $payment_id);

                // Handle Credit Card Transactions
                if ($payment_method === 'credit_card' || $payment_method === 'company_card') {
                    addPurchasePaymentCardTransaction($pdo, $purchase_id, $payment_id, $_POST['payment_date'], $amount);
                }

                // Create reimbursement request if personal payment
                if ($is_reimbursable && $employee_id) {
                    $reimbStmt = $pdo->prepare("INSERT INTO reimbursements 
                        (purchase_payment_id, employee_id, amount, request_date, notes) 
                        VALUES (?, ?, ?, ?, ?)");
                    $reimbStmt->execute([
                        $payment_id,
                        $employee_id,
                        $amount,
                        $_POST['payment_date'],
                        'Auto-created from personal payment'
                    ]);
                }

                // Log action
                $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
                $logStmt->execute([
                    $purchase_id,
                    'payment_added',
                    'Admin',
                    'Payment of ' . $amount . ' via ' . $payment_method
                ]);
            }
        }
        
        $pdo->commit();
        header('Location: index.php?page=purchase_payments&success=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        file_put_contents('purchase_payment_error.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        header('Location: index.php?page=purchase_payments&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle contractor addition BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_contractor']) && isset($_SESSION['logged_in'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO contractors (company_name, phone_number, email, address, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['company_name'],
            $_POST['phone_number'],
            $_POST['email'] ?? null,
            $_POST['address'] ?? null,
            $_POST['notes'] ?? null
        ]);
        
        header('Location: index.php?page=subcontracts&success=1');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?page=subcontracts&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle subcontract addition BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subcontract']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();

        $payment_method = $_POST['payment_method'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = normalizePostDate($_POST['payment_date'] ?? '');
        if (!$payment_date) {
            throw new Exception('Invalid payment date. Use YYYY-MM-DD.');
        }
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0.');
        }
        $is_cheque = ($payment_method === 'company_cheque');
        
        $stmt = $pdo->prepare("INSERT INTO subcontracts 
            (project_id, contractor_id, payment_method, description, amount, payment_date, payment_account, cheque_number, bank_name, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['project_id'],
            $_POST['contractor_id'],
            $payment_method,
            $_POST['description'] ?? null,
            $amount,
            $payment_date,
            $_POST['payment_account'] ?? null,
            $is_cheque ? ($_POST['cheque_number'] ?? null) : null,
            $is_cheque ? ($_POST['bank_name'] ?? null) : null,
            $_POST['notes'] ?? null
        ]);
        
        $subcontract_id = $pdo->lastInsertId();
        
        // Create GL Voucher
        addSubcontractVoucher($pdo, $subcontract_id);
        
        // Handle Credit Card Transactions
        if ($payment_method === 'credit_card' || $payment_method === 'company_card') {
            addSubcontractCardTransaction($pdo, $subcontract_id, $payment_date, $amount);
        }
        
        $pdo->commit();
        header('Location: index.php?page=subcontracts&success=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        file_put_contents('subcontract_error.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        header('Location: index.php?page=subcontracts&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle subcontract update BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_subcontract']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();

        $subcontract_id = intval($_POST['subcontract_id'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = normalizePostDate($_POST['payment_date'] ?? '');

        if (!$payment_date) {
            throw new Exception('Invalid payment date. Use YYYY-MM-DD.');
        }
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0.');
        }
        $is_cheque = ($payment_method === 'company_cheque');

        $stmt = $pdo->prepare("UPDATE subcontracts SET
            project_id = ?,
            contractor_id = ?,
            payment_method = ?,
            description = ?,
            amount = ?,
            payment_date = ?,
            payment_account = ?,
            cheque_number = ?,
            bank_name = ?,
            notes = ?
            WHERE id = ?");
        
        $stmt->execute([
            $_POST['project_id'],
            $_POST['contractor_id'],
            $payment_method,
            $_POST['description'] ?? null,
            $amount,
            $payment_date,
            $_POST['payment_account'] ?? null,
            $is_cheque ? ($_POST['cheque_number'] ?? null) : null,
            $is_cheque ? ($_POST['bank_name'] ?? null) : null,
            $_POST['notes'] ?? null,
            $subcontract_id
        ]);

        // Rebuild side effects using the updated selected method only.
        clearSubcontractSideEffects($pdo, $subcontract_id);
        addSubcontractVoucher($pdo, $subcontract_id);
        if ($payment_method === 'credit_card' || $payment_method === 'company_card') {
            addSubcontractCardTransaction($pdo, $subcontract_id, $payment_date, $amount);
        }

        $pdo->commit();
        
        header('Location: index.php?page=subcontracts&success=1');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: index.php?page=subcontracts&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle subcontract delete BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_subcontract']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();
        
        $subcontract_id = intval($_POST['delete_id'] ?? 0);
        clearSubcontractSideEffects($pdo, $subcontract_id);

        $stmt = $pdo->prepare("DELETE FROM subcontracts WHERE id = ?");
        $stmt->execute([$subcontract_id]);
        
        $pdo->commit();
        
        header('Location: index.php?page=subcontracts&success=1');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: index.php?page=subcontracts&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle purchase payment UPDATE BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();

        $payment_id = intval($_POST['edit_payment_id']);
        $payment_date = $_POST['edit_payment_date'];
        $amount = floatval($_POST['edit_amount']);
        $payment_method = $_POST['edit_payment_method'];
        $payment_account = $_POST['edit_payment_account'] ?? null;
        $cheque_number = $_POST['edit_cheque_number'] ?? null;
        $bank_name = $_POST['edit_bank_name'] ?? null;
        $paid_by = $_POST['edit_paid_by'] ?? null;
        $employee_id = !empty($_POST['edit_employee_id']) ? intval($_POST['edit_employee_id']) : null;
        $notes = $_POST['edit_notes'] ?? null;
        $is_reimbursable = ($payment_method == 'personal') ? 1 : 0;

        $oldStmt = $pdo->prepare("SELECT purchase_id FROM purchase_payments WHERE id = ?");
        $oldStmt->execute([$payment_id]);
        $oldPayment = $oldStmt->fetch();
        if (!$oldPayment) {
            throw new Exception('Payment not found.');
        }

        // Validate updated amount against current outstanding (excluding this payment)
        $purchase_id = intval($oldPayment['purchase_id']);
        $purchaseStmt = $pdo->prepare("SELECT total_amount, 
            (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = ? AND id <> ?) as paid
            FROM purchases WHERE id = ?");
        $purchaseStmt->execute([$purchase_id, $payment_id, $purchase_id]);
        $purchaseData = $purchaseStmt->fetch();
        if (!$purchaseData) {
            throw new Exception('Purchase not found.');
        }
        $outstanding = $purchaseData['total_amount'] - $purchaseData['paid'];
        if ($amount <= 0 || $amount > $outstanding) {
            throw new Exception('Updated payment amount exceeds outstanding balance.');
        }

        if ($payment_method !== 'company_cheque') {
            $cheque_number = null;
            $bank_name = null;
        }
        if ($payment_method !== 'personal') {
            $employee_id = null;
        }

        $stmt = $pdo->prepare("UPDATE purchase_payments SET
            payment_date = ?,
            amount = ?,
            payment_method = ?,
            payment_account = ?,
            cheque_number = ?,
            bank_name = ?,
            paid_by = ?,
            employee_id = ?,
            is_reimbursable = ?,
            notes = ?
            WHERE id = ?");

        $stmt->execute([
            $payment_date,
            $amount,
            $payment_method,
            $payment_account,
            $cheque_number,
            $bank_name,
            $paid_by,
            $employee_id,
            $is_reimbursable,
            $notes,
            $payment_id
        ]);

        clearPurchasePaymentSideEffects($pdo, $payment_id);
        addPurchasePaymentVoucher($pdo, $payment_id);

        if ($payment_method === 'credit_card' || $payment_method === 'company_card') {
            addPurchasePaymentCardTransaction($pdo, $purchase_id, $payment_id, $payment_date, $amount);
        }

        $pdo->commit();
        header('Location: index.php?page=purchase_payments&success=1&updated=1');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: index.php?page=purchase_payments&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle purchase payment DELETE BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_payment']) && isset($_SESSION['logged_in'])) {
    try {
        $pdo->beginTransaction();
        
        $payment_id = intval($_POST['delete_payment_id']);
        
        // Get payment details before deleting
        $paymentStmt = $pdo->prepare("SELECT id FROM purchase_payments WHERE id = ?");
        $paymentStmt->execute([$payment_id]);
        $paymentData = $paymentStmt->fetch();
        
        if ($paymentData) {
            clearPurchasePaymentSideEffects($pdo, $payment_id);
        }
        
        // Delete the payment record
        $stmt = $pdo->prepare("DELETE FROM purchase_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        $pdo->commit();
        header('Location: index.php?page=purchase_payments&success=1&deleted=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: index.php?page=purchase_payments&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Handle quotation logic (Add/Edit/Delete/Upload) BEFORE output
if (isset($_GET['page']) && $_GET['page'] === 'quotations' && isset($_SESSION['logged_in'])) {
    
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }
    $companyLogoPath = $uploadDir . '/company_logo.png';

    // Handle delete
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM quotations WHERE id=?");
        $stmt->execute([$_GET['delete']]);
        header('Location: index.php?page=quotations');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Remove logo
        if (isset($_POST['remove_logo'])) {
            if (file_exists($companyLogoPath)) {
                @unlink($companyLogoPath);
            }
        }

        // Handle logo upload if provided
        if (!empty($_FILES['company_logo']['tmp_name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
            @move_uploaded_file($_FILES['company_logo']['tmp_name'], $companyLogoPath);
        }

        // Quick-create client from a vendor
        if (isset($_POST['create_client_from_vendor']) && isset($_POST['vendor_id'])) {
            $vid = (int) $_POST['vendor_id'];
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
            $stmt->execute([$vid]);
            if ($v = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ins = $pdo->prepare("INSERT INTO clients (name, contact, email, phone, address) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([
                    $v['name'] ?? '',
                    $v['contact'] ?? null,
                    $v['email'] ?? null,
                    $v['phone'] ?? null,
                    $v['address'] ?? null,
                ]);
                $newId = $pdo->lastInsertId();
                header('Location: index.php?page=quotations&new_client_id=' . urlencode($newId));
                exit;
            }
        }

        if (isset($_POST['add']) && empty($_POST['edit_id'])) {

            // Build total from item rows
            $descs = $_POST['item_description'] ?? [];
            $qtys = $_POST['item_quantity'] ?? [];
            $prices = $_POST['item_price'] ?? [];
            $discount = floatval($_POST['discount'] ?? 0);
            $subtotal = 0.0;
            $n = max(count($descs), count($qtys), count($prices));
            for ($i = 0; $i < $n; $i++) {
                $q = isset($qtys[$i]) ? floatval($qtys[$i]) : 0;
                $p = isset($prices[$i]) ? floatval($prices[$i]) : 0;
                $d = isset($descs[$i]) ? trim($descs[$i]) : '';
                if ($d === '' && $q <= 0 && $p <= 0)
                    continue;
                $subtotal += ($q * $p);
            }
            $totalAmount = max(0, $subtotal - $discount);

            $discount = min(max(0.0, $discount), $subtotal);

            $stmt = $pdo->prepare("INSERT INTO quotations (client_id, project_id, date, total_amount, discount) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['client_id'], $_POST['project_id'], $_POST['date'], $totalAmount, $discount]);
            $qid = $pdo->lastInsertId();

            // Insert quotation items
            if ($qid) {
                $iStmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, description, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < $n; $i++) {
                    $d = isset($descs[$i]) ? trim($descs[$i]) : '';
                    $q = isset($qtys[$i]) ? floatval($qtys[$i]) : 0;
                    $p = isset($prices[$i]) ? floatval($prices[$i]) : 0;
                    if ($d === '' && $q <= 0 && $p <= 0)
                        continue;
                    $iStmt->execute([$qid, $d, $q, $p, $q * $p]);
                }
            }
             header('Location: index.php?page=quotations');
             exit;

        } elseif ((isset($_POST['add']) && !empty($_POST['edit_id'])) || isset($_POST['save'])) {

            // Update an existing quotation and its items
            $qid = (int) ($_POST['edit_id'] ?? 0);
            if ($qid > 0) {
                $descs = $_POST['item_description'] ?? [];
                $qtys = $_POST['item_quantity'] ?? [];
                $prices = $_POST['item_price'] ?? [];
                $discount = floatval($_POST['discount'] ?? 0);
                $subtotal = 0.0;
                $n = max(count($descs), count($qtys), count($prices));
                for ($i = 0; $i < $n; $i++) {
                    $qv = isset($qtys[$i]) ? floatval($qtys[$i]) : 0;
                    $pv = isset($prices[$i]) ? floatval($prices[$i]) : 0;
                    $dv = isset($descs[$i]) ? trim($descs[$i]) : '';
                    if ($dv === '' && $qv <= 0 && $pv <= 0)
                        continue;
                    $subtotal += ($qv * $pv);
                }
                $totalAmount = max(0, $subtotal - $discount);

                $discount = min(max(0.0, $discount), $subtotal);

                $stmt = $pdo->prepare("UPDATE quotations SET client_id=?, project_id=?, date=?, total_amount=?, discount=? WHERE id=?");
                $stmt->execute([$_POST['client_id'], $_POST['project_id'], $_POST['date'], $totalAmount, $discount, $qid]);

                // Replace items
                $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$qid]);
                $iStmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, description, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
                for ($i = 0; $i < $n; $i++) {
                    $d = isset($descs[$i]) ? trim($descs[$i]) : '';
                    $qv = isset($qtys[$i]) ? floatval($qtys[$i]) : 0;
                    $pv = isset($prices[$i]) ? floatval($prices[$i]) : 0;
                    if ($d === '' && $qv <= 0 && $pv <= 0)
                        continue;
                    $iStmt->execute([$qid, $d, $qv, $pv, $qv * $pv]);
                }
            }

            header('Location: index.php?page=quotations');
            exit;

        } elseif (isset($_POST['update'])) {

            $stmt = $pdo->prepare("UPDATE quotations SET client_id=?, project_id=?, date=?, total_amount=? WHERE id=?");

            $stmt->execute([$_POST['client_id'], $_POST['project_id'], $_POST['date'], $_POST['total_amount'], $_POST['id']]);
             header('Location: index.php?page=quotations');
             exit;

        }
    }
}


// Quick login (dev aid) if enabled via env flag
// Quick login (dev aid) if enabled via env flag
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_login']) && envEnabled('ENABLE_QUICK_LOGIN', true)) {
    // Set session as superadmin
    $_SESSION['logged_in'] = true;
    $_SESSION['role'] = 'superadmin';
    $_SESSION['user_id'] = 1; // Assuming superadmin user ID is 1
    $_SESSION['username'] = 'admin';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (attempt_login($pdo, $username, $password)) {
        // Log Login Activity
        try {
            $logStmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, username, login_time, ip_address, user_agent, status) VALUES (?, ?, ?, NOW(), ?, ?, 'success')");
            $logUserId = $_SESSION['user_id'] ?? 0;
            $logUserType = $_SESSION['role'] ?? 'unknown';
            $logUsername = $_SESSION['username'] ?? $username;
            $logIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $logUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $logStmt->execute([$logUserId, $logUserType, $logUsername, $logIp, $logUa]);
        } catch (Exception $e) {
            // Siltently fail logging
        }
        if ($_SESSION['role'] === 'driver') {
             header('Location: index.php?page=vehicles');
        } elseif ($_SESSION['role'] === 'supervisor') {
             header('Location: index.php?page=supervisor_dashboard');
        } elseif ($_SESSION['role'] === 'accounts_manager') {
             header('Location: index.php?page=vouchers');
        } else {
             header('Location: index.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

if (!isset($_SESSION['logged_in'])) {
    include 'includes/header.php';
    include 'pages/login.php';
    include 'includes/footer.php';
} else {
    $is_ajax = isset($_GET['ajax_partial']);
    $page = $_GET['page'] ?? 'dashboard';

    // If employee lands on dashboard (default), redirect to attendance
    if (($_SESSION['role'] ?? '') === 'employee' && $page === 'dashboard') {
        $page = 'attendance';
    } elseif (($_SESSION['role'] ?? '') === 'driver' && $page === 'dashboard') {
        $page = 'vehicles';
    } elseif (($_SESSION['role'] ?? '') === 'supervisor' && $page === 'dashboard') {
        $page = 'supervisor_dashboard';
    } elseif (($_SESSION['role'] ?? '') === 'accounts_manager' && $page === 'dashboard') {
        $page = 'vouchers';
    }

    if (!file_exists("pages/$page.php")) {
        $page = 'dashboard';
        // Fallback safety
        if (($_SESSION['role'] ?? '') === 'employee') {
            $page = 'attendance';
        }
    }

    $no_layout_pages = ['attendance_report_export', 'voucher_print', 'voucher_pdf'];
    $is_export = in_array($page, $no_layout_pages);

    if (!$is_ajax && !$is_export) {
        include 'includes/header.php';
        echo '<div class="flex h-screen bg-gray-100 overflow-hidden">';
        if (($_SESSION['role'] ?? '') !== 'employee') {
            echo '<div class="w-64 bg-white shadow-lg overflow-y-auto fixed md:relative z-[60] inset-y-0 left-0 -translate-x-full md:translate-x-0 sidebar transition-transform duration-300 transform">';
            include 'includes/nav.php';
            echo '</div>';
        }
        echo '<div class="flex-1 p-4 md:p-6 overflow-y-auto w-full">';
    }

    include "pages/$page.php";
    if (!$is_ajax && !$is_export) {
        echo '</div>';
        echo '</div>';
        include 'includes/footer.php';
    }
}
