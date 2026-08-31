<?php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = null;
$error = null;
$statements = [];
$selectedClient = null;
$statementType = 'activity'; // 'activity' or 'pending'
$dateFrom = '';
$dateTo = '';

// Get all clients
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get currency symbol
$currencySymbol = getenv('CURRENCY_SYMBOL') ?: 'ريال';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_statement'])) {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $statementType = $_POST['statement_type'] ?? 'activity';
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    
    if (!$clientId) {
        $error = 'Please select a client';
    } else {
        $selectedClient = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $selectedClient->execute([$clientId]);
        $selectedClient = $selectedClient->fetch(PDO::FETCH_ASSOC);
        
        if ($statementType === 'activity') {
            // Full Account Activity
            if (!$dateFrom || !$dateTo) {
                $error = 'Date range is required for account activity';
            } else {
                $statements = generateAccountActivity($pdo, $clientId, $dateFrom, $dateTo);
            }
        } else {
            // Pending/Outstanding Only
            $statements = generatePendingOutstanding($pdo, $clientId);
        }
    }
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
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Account Statements</h1>
            <p class="text-gray-600">Generate client account statements and outstanding reports</p>
        </div>
    </div>

    <!-- Filters Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="post" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                    <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statement Type *</label>
                    <select name="statement_type" id="statement_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        <option value="activity" <?php echo ($statementType === 'activity') ? 'selected' : ''; ?>>Full Account Activity</option>
                        <option value="pending" <?php echo ($statementType === 'pending') ? 'selected' : ''; ?>>Pending/Outstanding Only</option>
                    </select>
                </div>
                
                <div id="date_from_field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div id="date_to_field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="generate_statement" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Generate Statement
                </button>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (!empty($statements) && $selectedClient): ?>
    <div class="bg-white rounded-lg shadow">
        <!-- Statement Header -->
        <div class="p-6 border-b">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">
                        <?php echo $statementType === 'activity' ? 'Account Activity Statement' : 'Outstanding Balance Statement'; ?>
                    </h2>
                    <p class="text-gray-600">Client: <?php echo htmlspecialchars($selectedClient['name']); ?></p>
                    <?php if ($statementType === 'activity'): ?>
                    <p class="text-sm text-gray-500">Period: <?php echo date('M d, Y', strtotime($dateFrom)); ?> - <?php echo date('M d, Y', strtotime($dateTo)); ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex space-x-2">
                    <button onclick="exportToPDF()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm">
                        <i class="fas fa-file-pdf mr-1"></i>Export PDF
                    </button>
                    <button onclick="exportToExcel()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm">
                        <i class="fas fa-file-excel mr-1"></i>Export Excel
                    </button>
                    <button onclick="window.print()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md text-sm">
                        <i class="fas fa-print mr-1"></i>Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Statement Content -->
        <div class="p-6" id="statement-content">
            <?php if ($statementType === 'activity'): ?>
                <!-- Full Account Activity Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
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
                        <thead class="bg-gray-50">
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
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Show/hide date fields based on statement type
document.getElementById('statement_type').addEventListener('change', function() {
    const dateFields = document.querySelectorAll('#date_from_field, #date_to_field');
    if (this.value === 'activity') {
        dateFields.forEach(field => field.style.display = 'block');
        document.querySelector('input[name="date_from"]').required = true;
        document.querySelector('input[name="date_to"]').required = true;
    } else {
        dateFields.forEach(field => field.style.display = 'none');
        document.querySelector('input[name="date_from"]').required = false;
        document.querySelector('input[name="date_to"]').required = false;
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('statement_type').dispatchEvent(new Event('change'));
});

function exportToPDF() {
    const clientId = <?php echo json_encode($_POST['client_id'] ?? ''); ?>;
    const statementType = <?php echo json_encode($statementType); ?>;
    const dateFrom = <?php echo json_encode($dateFrom); ?>;
    const dateTo = <?php echo json_encode($dateTo); ?>;
    
    if (!clientId) {
        alert('Please generate a statement first');
        return;
    }
    
    const url = `pages/export_statement_pdf.php?client_id=${clientId}&type=${statementType}&date_from=${dateFrom}&date_to=${dateTo}`;
    window.open(url, '_blank');
}

function exportToExcel() {
    const clientId = <?php echo json_encode($_POST['client_id'] ?? ''); ?>;
    const statementType = <?php echo json_encode($statementType); ?>;
    const dateFrom = <?php echo json_encode($dateFrom); ?>;
    const dateTo = <?php echo json_encode($dateTo); ?>;
    
    if (!clientId) {
        alert('Please generate a statement first');
        return;
    }
    
    const url = `pages/export_statement_excel.php?client_id=${clientId}&type=${statementType}&date_from=${dateFrom}&date_to=${dateTo}`;
    window.location.href = url;
}
</script>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .print-border-none { border: none !important; }
    @page { margin: 15mm; }
}
</style>
