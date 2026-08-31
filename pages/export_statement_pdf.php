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

// Find company logo
$uploadDir = dirname(__DIR__) . '/uploads';
$logoFs = null;
$logoUrl = null;
$possibleExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/company_logo.' . $ext;
    if (file_exists($testPath)) {
        $logoFs = $testPath;
        $logoUrl = 'uploads/company_logo.' . $ext;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Statement - <?php echo htmlspecialchars($client['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0 !important; padding: 0 !important; }
            .container { box-shadow: none !important; margin: 0 !important; }
            @page { 
                margin: 15mm;
                size: A4;
            }
        }
        
        .company-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .statement-title {
            font-size: 1.875rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .client-info {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .table-header {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        
        .footer-info {
            border-top: 1px solid #e5e7eb;
            padding-top: 1rem;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container max-w-5xl mx-auto my-6 bg-white shadow-lg rounded-lg print:shadow-none print:my-0">
        <!-- Company Header -->
        <div class="company-header p-8">
            <div class="flex items-start justify-between">
                <!-- Company Logo -->
                <div class="w-1/3">
                    <?php if ($logoFs && file_exists($logoFs)): ?>
                        <img src="<?php echo $logoUrl; ?>?t=<?php echo filemtime($logoFs); ?>" alt="Company Logo" class="h-24 object-contain">
                    <?php endif; ?>
                </div>
                
                <!-- Company Details -->
                <div class="w-2/3 text-right">
                    <div class="statement-title">
                        <?php echo $statementType === 'activity' ? 'ACCOUNT ACTIVITY STATEMENT' : 'OUTSTANDING BALANCE STATEMENT'; ?>
                    </div>
                    <div class="space-y-0.5">
                        <div class="text-sm font-bold text-gray-900"><?php echo strtoupper(htmlspecialchars(getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L')); ?></div>
                        <?php 
                        $address = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
                        $addressLines = explode("\n", $address);
                        foreach ($addressLines as $line): 
                            if (trim($line)): ?>
                                <div class="text-xs text-gray-600"><?php echo htmlspecialchars(trim($line)); ?></div>
                            <?php endif;
                        endforeach; ?>
                        <?php if (getenv('COMPANY_PHONE')): ?>
                            <div class="text-xs text-gray-600">Mobile: <?php echo htmlspecialchars(getenv('COMPANY_PHONE')); ?></div>
                        <?php endif; ?>
                        <?php if (getenv('COMPANY_TOLL_FREE')): ?>
                            <div class="text-xs text-gray-600">Second Number: <?php echo htmlspecialchars(getenv('COMPANY_TOLL_FREE')); ?></div>
                        <?php endif; ?>
                        <?php if (getenv('COMPANY_WEBSITE')): ?>
                            <div class="text-xs text-gray-600"><?php echo htmlspecialchars(getenv('COMPANY_WEBSITE')); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statement Details -->
        <div class="px-8">
            <!-- Client Information -->
            <div class="client-info">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Statement For:</h3>
                <div class="text-sm text-gray-700">
                    <div class="font-semibold"><?php echo htmlspecialchars($client['name']); ?></div>
                    <?php if ($client['address']): ?>
                        <div class="mt-1 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($client['address'])); ?></div>
                    <?php endif; ?>
                    <?php if ($client['phone']): ?>
                        <div class="mt-1">Phone: <?php echo htmlspecialchars($client['phone']); ?></div>
                    <?php endif; ?>
                    <?php if ($client['email']): ?>
                        <div>Email: <?php echo htmlspecialchars($client['email']); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($statementType === 'activity'): ?>
                    <div class="mt-2 text-sm text-gray-600">
                        <strong>Period:</strong> <?php echo date('M d, Y', strtotime($dateFrom)); ?> - <?php echo date('M d, Y', strtotime($dateTo)); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statement Content -->
            <?php if ($statementType === 'activity'): ?>
                <!-- Full Account Activity Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="table-header">
                            <tr>
                                <th class="text-left px-4 py-2">Date</th>
                                <th class="text-left px-4 py-2">Ref No</th>
                                <th class="text-left px-4 py-2">Description</th>
                                <th class="text-right px-4 py-2">Debit</th>
                                <th class="text-right px-4 py-2">Credit</th>
                                <th class="text-right px-4 py-2">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statements as $statement): ?>
                            <tr class="border-b">
                                <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($statement['date'])); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($statement['ref_no']); ?></td>
                                <td class="px-4 py-2">
                                    <?php echo htmlspecialchars($statement['description']); ?>
                                    <?php if ($statement['lpo_number']): ?>
                                        <br><span class="text-xs text-gray-500">LPO: <?php echo htmlspecialchars($statement['lpo_number']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-right"><?php echo $statement['debit'] > 0 ? money($statement['debit']) : '-'; ?></td>
                                <td class="px-4 py-2 text-right"><?php echo $statement['credit'] > 0 ? money($statement['credit']) : '-'; ?></td>
                                <td class="px-4 py-2 text-right font-semibold"><?php echo money($statement['running_balance']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- Pending/Outstanding Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="table-header">
                            <tr>
                                <th class="text-left px-4 py-2">Invoice No</th>
                                <th class="text-left px-4 py-2">Date</th>
                                <th class="text-left px-4 py-2">Due Date</th>
                                <th class="text-right px-4 py-2">Amount</th>
                                <th class="text-right px-4 py-2">Paid</th>
                                <th class="text-right px-4 py-2">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalOutstanding = 0;
                            foreach ($statements as $statement): 
                                $totalOutstanding += $statement['outstanding'];
                            ?>
                            <tr class="border-b">
                                <td class="px-4 py-2">
                                    #<?php echo htmlspecialchars($statement['invoice_no']); ?>
                                    <?php if ($statement['lpo_number']): ?>
                                        <br><span class="text-xs text-gray-500">LPO: <?php echo htmlspecialchars($statement['lpo_number']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($statement['date'])); ?></td>
                                <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($statement['due_date'])); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo money($statement['total_amount']); ?></td>
                                <td class="px-4 py-2 text-right"><?php echo money($statement['paid']); ?></td>
                                <td class="px-4 py-2 text-right font-semibold text-red-600"><?php echo money($statement['outstanding']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-right font-bold">Total Outstanding:</td>
                                <td class="px-4 py-2 text-right font-bold text-red-600"><?php echo money($totalOutstanding); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer-info text-center">
                <p><strong><?php echo htmlspecialchars(getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L'); ?></strong></p>
                <p>
                    <?php if (getenv('COMPANY_PHONE')): ?>
                        Mobile: <?php echo htmlspecialchars(getenv('COMPANY_PHONE')); ?> | 
                    <?php endif; ?>
                    <?php if (getenv('COMPANY_TOLL_FREE')): ?>
                        Second Number: <?php echo htmlspecialchars(getenv('COMPANY_TOLL_FREE')); ?> | 
                    <?php endif; ?>
                    <?php if (getenv('COMPANY_WEBSITE')): ?>
                        <?php echo htmlspecialchars(getenv('COMPANY_WEBSITE')); ?>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Generated on <?php echo date('M d, Y \a\t g:i A'); ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when loaded
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
