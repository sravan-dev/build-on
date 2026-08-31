<?php

include_once 'includes/db.php';

// Handle CSV export
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $export_type . '_report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type == 'purchases_by_project') {
        fputcsv($output, ['Project', 'Client', 'Total Purchases', 'Total Paid', 'Outstanding', 'Purchase Count']);
        
        $data = $pdo->query("
            SELECT pr.name as project_name,
                   c.name as client_name,
                   COALESCE(SUM(p.total_amount), 0) as total_purchases,
                   COALESCE(SUM((SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id)), 0) as total_paid,
                   COALESCE(SUM(p.total_amount), 0) - COALESCE(SUM((SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id)), 0) as outstanding,
                   COUNT(p.id) as purchase_count
            FROM projects pr
            LEFT JOIN clients c ON pr.client_id = c.id
            LEFT JOIN purchases p ON pr.id = p.project_id
            GROUP BY pr.id
            ORDER BY total_purchases DESC
        ")->fetchAll();
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['project_name'],
                $row['client_name'] ?? 'N/A',
                number_format($row['total_purchases'], 2),
                number_format($row['total_paid'], 2),
                number_format($row['outstanding'], 2),
                $row['purchase_count']
            ]);
        }
    }
    elseif ($export_type == 'payments_by_method') {
        fputcsv($output, ['Payment Method', 'Total Amount', 'Transaction Count', 'Reimbursable Amount']);
        
        $data = $pdo->query("
            SELECT payment_method,
                   SUM(amount) as total_amount,
                   COUNT(*) as count,
                   SUM(CASE WHEN is_reimbursable = 1 THEN amount ELSE 0 END) as reimbursable_amount
            FROM purchase_payments
            GROUP BY payment_method
            ORDER BY total_amount DESC
        ")->fetchAll();
        
        foreach ($data as $row) {
            fputcsv($output, [
                ucwords(str_replace('_', ' ', $row['payment_method'])),
                number_format($row['total_amount'], 2),
                $row['count'],
                number_format($row['reimbursable_amount'], 2)
            ]);
        }
    }
    elseif ($export_type == 'pending_reimbursements') {
        fputcsv($output, ['ID', 'Employee', 'Amount', 'Request Date', 'Purchase', 'Project', 'Status']);
        
        $data = $pdo->query("
            SELECT r.id,
                   e.name as employee_name,
                   r.amount,
                   r.request_date,
                   p.invoice_number,
                   pr.name as project_name,
                   r.status
            FROM reimbursements r
            LEFT JOIN employees e ON r.employee_id = e.id
            LEFT JOIN purchase_payments pp ON r.purchase_payment_id = pp.id
            LEFT JOIN purchases p ON pp.purchase_id = p.id
            LEFT JOIN projects pr ON p.project_id = pr.id
            WHERE r.status IN ('pending', 'approved')
            ORDER BY r.request_date
        ")->fetchAll();
        
        foreach ($data as $row) {
            fputcsv($output, [
                'R#' . $row['id'],
                $row['employee_name'] ?? 'N/A',
                number_format($row['amount'], 2),
                $row['request_date'],
                $row['invoice_number'] ?? 'N/A',
                $row['project_name'] ?? 'N/A',
                ucfirst($row['status'])
            ]);
        }
    }
    elseif ($export_type == 'all_purchases') {
        fputcsv($output, ['ID', 'Date', 'Project', 'Vendor', 'Invoice #', 'Amount', 'Paid', 'Balance', 'Status']);
        
        $data = $pdo->query("
            SELECT p.id,
                   p.purchase_date,
                   pr.name as project_name,
                   v.name as vendor_name,
                   p.invoice_number,
                   p.total_amount,
                   (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id) as paid,
                   p.total_amount - (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id) as balance,
                   p.status
            FROM purchases p
            LEFT JOIN projects pr ON p.project_id = pr.id
            LEFT JOIN vendors v ON p.vendor_id = v.id
            ORDER BY p.purchase_date DESC
        ")->fetchAll();
        
        foreach ($data as $row) {
            fputcsv($output, [
                'P#' . $row['id'],
                $row['purchase_date'],
                $row['project_name'] ?? 'N/A',
                $row['vendor_name'] ?? 'N/A',
                $row['invoice_number'] ?? '-',
                number_format($row['total_amount'], 2),
                number_format($row['paid'], 2),
                number_format($row['balance'], 2),
                ucfirst($row['status'])
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// Fetch report data
$purchases_by_project = $pdo->query("
    SELECT pr.id,
           pr.name as project_name,
           c.name as client_name,
           COALESCE(SUM(p.total_amount), 0) as total_purchases,
           COALESCE(SUM((SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id)), 0) as total_paid,
           COUNT(p.id) as purchase_count
    FROM projects pr
    LEFT JOIN clients c ON pr.client_id = c.id
    LEFT JOIN purchases p ON pr.id = p.project_id
    GROUP BY pr.id
    HAVING total_purchases > 0
    ORDER BY total_purchases DESC
")->fetchAll();

$payments_by_method = $pdo->query("
    SELECT payment_method,
           SUM(amount) as total_amount,
           COUNT(*) as count,
           SUM(CASE WHEN is_reimbursable = 1 THEN amount ELSE 0 END) as reimbursable_amount
    FROM purchase_payments
    GROUP BY payment_method
    ORDER BY total_amount DESC
")->fetchAll();

$pending_reimbursements = $pdo->query("
    SELECT r.*,
           e.name as employee_name,
           p.invoice_number,
           pr.name as project_name
    FROM reimbursements r
    LEFT JOIN employees e ON r.employee_id = e.id
    LEFT JOIN purchase_payments pp ON r.purchase_payment_id = pp.id
    LEFT JOIN purchases p ON pp.purchase_id = p.id
    LEFT JOIN projects pr ON p.project_id = pr.id
    WHERE r.status IN ('pending', 'approved')
    ORDER BY r.request_date
")->fetchAll();

$payments_by_account = $pdo->query("
    SELECT 
        CASE 
            WHEN payment_method LIKE 'company%' THEN 'Company'
            WHEN payment_method = 'personal' THEN 'Personal (Reimbursable)'
            ELSE 'Other'
        END as account_type,
        SUM(amount) as total_amount,
        COUNT(*) as count
    FROM purchase_payments
    GROUP BY account_type
    ORDER BY total_amount DESC
")->fetchAll();

// Summary statistics
$total_purchases = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchases WHERE status = 'approved'")->fetchColumn();
$total_paid = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM purchase_payments")->fetchColumn();
$total_outstanding = $total_purchases - $total_paid;
$total_reimbursable = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE is_reimbursable = 1 AND reimbursement_status IN ('pending', 'approved')")->fetchColumn();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Purchase Reports</h1>
    <p class="text-gray-600 mt-2">Comprehensive reporting and analytics for purchase tracking</p>
</div>

<!-- Summary Dashboard -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-blue-50 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-blue-600 font-medium">Total Purchases</p>
                <p class="text-2xl font-bold text-blue-900 mt-1"><?php echo money($total_purchases); ?></p>
            </div>
            <div class="bg-blue-500 rounded-full p-3">
                <i class="fas fa-shopping-cart text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-green-50 rounded-lg shadow-md p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-green-600 font-medium">Total Paid</p>
                <p class="text-2xl font-bold text-green-900 mt-1"><?php echo money($total_paid); ?></p>
            </div>
            <div class="bg-green-500 rounded-full p-3">
                <i class="fas fa-check-circle text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-yellow-50 rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-yellow-600 font-medium">Outstanding</p>
                <p class="text-2xl font-bold text-yellow-900 mt-1"><?php echo money($total_outstanding); ?></p>
            </div>
            <div class="bg-yellow-500 rounded-full p-3">
                <i class="fas fa-exclamation-circle text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-purple-50 rounded-lg shadow-md p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-purple-600 font-medium">Pending Reimbursements</p>
                <p class="text-2xl font-bold text-purple-900 mt-1"><?php echo money($total_reimbursable); ?></p>
            </div>
            <div class="bg-purple-500 rounded-full p-3">
                <i class="fas fa-user-clock text-white text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Purchases by Project -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="p-6 border-b">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Purchases by Project</h2>
            <a href="?page=purchase_reports&export=purchases_by_project" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-file-excel mr-2"></i>Export to CSV
            </a>
        </div>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Purchases</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Paid</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Count</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($purchases_by_project as $row): 
                        $outstanding = $row['total_purchases'] - $row['total_paid'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['project_name']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['client_name'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900"><?php echo money($row['total_purchases']); ?></td>
                        <td class="px-4 py-3 text-sm text-right text-green-600"><?php echo money($row['total_paid']); ?></td>
                        <td class="px-4 py-3 text-sm text-right text-yellow-600"><?php echo money($outstanding); ?></td>
                        <td class="px-4 py-3 text-sm text-center text-gray-900"><?php echo $row['purchase_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payments by Method -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Payments by Method</h2>
                <a href="?page=purchase_reports&export=payments_by_method" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Count</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments_by_method as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo ucwords(str_replace('_', ' ', $row['payment_method'])); ?>
                                <?php if ($row['reimbursable_amount'] > 0): ?>
                                    <br><span class="text-xs text-purple-600">Reimbursable: <?php echo money($row['reimbursable_amount']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900"><?php echo money($row['total_amount']); ?></td>
                            <td class="px-4 py-3 text-sm text-center text-gray-900"><?php echo $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Payments by Account Type</h2>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Type</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Count</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments_by_account as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900"><?php echo $row['account_type']; ?></td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900"><?php echo money($row['total_amount']); ?></td>
                            <td class="px-4 py-3 text-sm text-center text-gray-900"><?php echo $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Pending Reimbursements -->
<?php if (count($pending_reimbursements) > 0): ?>
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="p-6 border-b">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Pending Reimbursements</h2>
            <a href="?page=purchase_reports&export=pending_reimbursements" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-file-excel mr-2"></i>Export to CSV
            </a>
        </div>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pending_reimbursements as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">R#<?php echo $row['id']; ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['employee_name'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo money($row['amount']); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['project_name'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                <?php echo $row['status'] == 'approved' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Export Options -->
<div class="bg-white rounded-lg shadow-md">
    <div class="p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-900">Export Options</h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="?page=purchase_reports&export=all_purchases" class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                    <div class="bg-green-100 rounded-full p-3 mr-4">
                        <i class="fas fa-file-excel text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">All Purchases</h3>
                        <p class="text-sm text-gray-500">Complete purchase list with details</p>
                    </div>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </a>
            
            <a href="?page=purchase_reports&export=purchases_by_project" class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                    <div class="bg-blue-100 rounded-full p-3 mr-4">
                        <i class="fas fa-file-excel text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Purchases by Project</h3>
                        <p class="text-sm text-gray-500">Project-wise purchase summary</p>
                    </div>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </a>
            
            <a href="?page=purchase_reports&export=payments_by_method" class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                    <div class="bg-purple-100 rounded-full p-3 mr-4">
                        <i class="fas fa-file-excel text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Payments by Method</h3>
                        <p class="text-sm text-gray-500">Payment method breakdown</p>
                    </div>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </a>
            
            <a href="?page=purchase_reports&export=pending_reimbursements" class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                    <div class="bg-yellow-100 rounded-full p-3 mr-4">
                        <i class="fas fa-file-excel text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Pending Reimbursements</h3>
                        <p class="text-sm text-gray-500">Outstanding employee reimbursements</p>
                    </div>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </a>
        </div>
    </div>
</div>

