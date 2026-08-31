<?php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$clientId = (int)($_GET['client_id'] ?? 0);
$statementType = $_GET['type'] ?? 'activity';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if (!$clientId) {
    die('Invalid client ID');
}

// Get client details
$client = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$client->execute([$clientId]);
$client = $client->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die('Client not found');
}

// Get currency symbol
$currencySymbol = getenv('CURRENCY_SYMBOL') ?: 'ريال';

// Generate statement data
if ($statementType === 'activity') {
    $statements = generateAccountActivity($pdo, $clientId, $dateFrom, $dateTo);
} else {
    $statements = generatePendingOutstanding($pdo, $clientId);
}

function generateAccountActivity($pdo, $clientId, $dateFrom, $dateTo) {
    $activities = [];
    
    // Get all invoices for the client
    $invoices = $pdo->prepare("
        SELECT 
            'invoice' as type,
            i.id as ref_no,
            i.date,
            ('Invoice #' || i.id) as description,
            0 as debit,
            i.total_amount as credit,
            i.total_amount as amount,
            i.lpo_number
        FROM invoices i 
        WHERE i.client_id = ? AND i.date BETWEEN ? AND ?
        ORDER BY i.date, i.id
    ");
    $invoices->execute([$clientId, $dateFrom, $dateTo]);
    $invoiceData = $invoices->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all payments for the client
    $payments = $pdo->prepare("
        SELECT 
            'payment' as type,
            p.id as ref_no,
            p.date,
            ('Payment #' || p.id || ' - ' || p.payment_method) as description,
            p.amount as debit,
            0 as credit,
            p.amount as amount,
            NULL as lpo_number
        FROM payments p 
        INNER JOIN invoices i ON p.invoice_id = i.id
        WHERE i.client_id = ? AND p.date BETWEEN ? AND ?
        ORDER BY p.date, p.id
    ");
    $payments->execute([$clientId, $dateFrom, $dateTo]);
    $paymentData = $payments->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine and sort by date
    $allTransactions = array_merge($invoiceData, $paymentData);
    usort($allTransactions, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    
    // Calculate running balance
    $runningBalance = 0;
    foreach ($allTransactions as $transaction) {
        $runningBalance += $transaction['credit'] - $transaction['debit'];
        $transaction['running_balance'] = $runningBalance;
        $activities[] = $transaction;
    }
    
    return $activities;
}

function generatePendingOutstanding($pdo, $clientId) {
    $pending = [];
    
    $stmt = $pdo->prepare("
        SELECT 
            i.id as invoice_no,
            i.date,
            i.date as due_date,
            i.total_amount,
            COALESCE(SUM(p.amount), 0) as paid,
            (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding,
            i.lpo_number
        FROM invoices i
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.client_id = ?
        GROUP BY i.id, i.date, i.total_amount, i.lpo_number
        HAVING outstanding > 0
        ORDER BY i.date
    ");
    $stmt->execute([$clientId]);
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $pending;
}

// Using money() function from includes/functions.php

// Set headers for Excel download
$filename = 'Account_Statement_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $client['name']) . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Company Header
fputcsv($output, [getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L']);
fputcsv($output, [getenv('COMPANY_ADDRESS') ?: '158176, Al Majed Centre, Jabr Bin Mohamed St., DOHA, Ar Rayyan, Qatar']);
fputcsv($output, ['Mobile: ' . (getenv('COMPANY_PHONE') ?: '+947 30659993')]);
fputcsv($output, ['Second Number: ' . (getenv('COMPANY_TOLL_FREE') ?: '77721423')]);
fputcsv($output, [getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com']);
fputcsv($output, []); // Empty row

// Statement Title
$statementTitle = $statementType === 'activity' ? 'ACCOUNT ACTIVITY STATEMENT' : 'OUTSTANDING BALANCE STATEMENT';
fputcsv($output, [$statementTitle]);
fputcsv($output, []); // Empty row

// Client Information
fputcsv($output, ['Statement For:']);
fputcsv($output, ['Client Name:', $client['name']]);
if ($client['address']) {
    fputcsv($output, ['Address:', $client['address']]);
}
if ($client['phone']) {
    fputcsv($output, ['Phone:', $client['phone']]);
}
if ($client['email']) {
    fputcsv($output, ['Email:', $client['email']]);
}
if ($statementType === 'activity') {
    fputcsv($output, ['Period:', date('M d, Y', strtotime($dateFrom)) . ' - ' . date('M d, Y', strtotime($dateTo))]);
}
fputcsv($output, []); // Empty row

// Data Headers
if ($statementType === 'activity') {
    fputcsv($output, ['Date', 'Ref No', 'Description', 'Debit', 'Credit', 'Running Balance']);
} else {
    fputcsv($output, ['Invoice No', 'Date', 'Due Date', 'Amount', 'Paid', 'Outstanding']);
}

// Data Rows
if ($statementType === 'activity') {
    foreach ($statements as $statement) {
        $description = $statement['description'];
        if ($statement['lpo_number']) {
            $description .= ' (LPO: ' . $statement['lpo_number'] . ')';
        }
        
        fputcsv($output, [
            date('M d, Y', strtotime($statement['date'])),
            $statement['ref_no'],
            $description,
            $statement['debit'] > 0 ? money($statement['debit']) : '-',
            $statement['credit'] > 0 ? money($statement['credit']) : '-',
            money($statement['running_balance'])
        ]);
    }
} else {
    $totalOutstanding = 0;
    foreach ($statements as $statement) {
        $totalOutstanding += $statement['outstanding'];
        
        $invoiceNo = '#' . $statement['invoice_no'];
        if ($statement['lpo_number']) {
            $invoiceNo .= ' (LPO: ' . $statement['lpo_number'] . ')';
        }
        
        fputcsv($output, [
            $invoiceNo,
            date('M d, Y', strtotime($statement['date'])),
            date('M d, Y', strtotime($statement['due_date'])),
            money($statement['total_amount']),
            money($statement['paid']),
            money($statement['outstanding'])
        ]);
    }
    
    // Total Outstanding
    fputcsv($output, []); // Empty row
    fputcsv($output, ['', '', '', '', 'Total Outstanding:', money($totalOutstanding)]);
}

fputcsv($output, []); // Empty row
fputcsv($output, ['Generated on: ' . date('M d, Y \a\t g:i A')]);

fclose($output);
exit;
?>
