<?php
session_start();
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$id = $_GET['id'] ?? 0;

$purchase = $pdo->prepare("
    SELECT p.*, 
           pr.name as project_name, 
           v.name as vendor_name,
           c.name as client_name
    FROM purchases p
    LEFT JOIN projects pr ON p.project_id = pr.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN clients c ON pr.client_id = c.id
    WHERE p.id = ?
");
$purchase->execute([$id]);
$purchase = $purchase->fetch();

if (!$purchase) {
    echo '<p class="text-red-500">Purchase not found</p>';
    exit;
}

$items = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $pdo->prepare("SELECT * FROM purchase_payments WHERE purchase_id = ? ORDER BY payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

$logs = $pdo->prepare("SELECT * FROM purchase_audit_log WHERE purchase_id = ? ORDER BY performed_at DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();

?>

<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-600">Purchase ID</p>
            <p class="font-semibold text-gray-900">P#<?php echo $purchase['id']; ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Date</p>
            <p class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Project</p>
            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($purchase['project_name']); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Client</p>
            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($purchase['client_name'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Vendor</p>
            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($purchase['vendor_name'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Invoice Number</p>
            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($purchase['invoice_number'] ?? '-'); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Status</p>
            <p>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                    <?php
                    switch($purchase['status']) {
                        case 'approved': echo 'bg-green-100 text-green-800'; break;
                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'rejected': echo 'bg-red-100 text-red-800'; break;
                        case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                    }
                    ?>">
                    <?php echo ucfirst($purchase['status']); ?>
                </span>
            </p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Description</p>
            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($purchase['description'] ?? '-'); ?></p>
        </div>
    </div>

    <div class="mt-6">
        <h4 class="font-semibold text-gray-900 mb-3">Items</h4>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Description</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Qty</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Unit Price</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-4 py-3 text-gray-900"><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="px-4 py-3 text-right text-gray-900"><?php echo number_format($item['quantity'], 2); ?></td>
                        <td class="px-4 py-3 text-right text-gray-900"><?php echo money($item['unit_price']); ?></td>
                        <td class="px-4 py-3 text-right text-gray-900"><?php echo money($item['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-gray-50">
                        <td colspan="3" class="px-4 py-3 text-right font-medium text-gray-700">Subtotal:</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900"><?php echo money($purchase['subtotal']); ?></td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td colspan="3" class="px-4 py-3 text-right font-medium text-gray-700">Tax:</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900"><?php echo money($purchase['tax_amount']); ?></td>
                    </tr>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="px-4 py-3 text-right font-bold text-gray-900">Total:</td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900 text-lg"><?php echo money($purchase['total_amount']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (count($payments) > 0): ?>
    <div class="mt-6">
        <h4 class="font-semibold text-gray-900 mb-3">Payments</h4>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Amount</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Method</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="px-4 py-3 text-gray-900"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td class="px-4 py-3 font-semibold text-gray-900"><?php echo money($payment['amount']); ?></td>
                        <td class="px-4 py-3 text-gray-900"><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td class="px-4 py-3">
                            <?php if ($payment['is_reimbursable']): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800"><?php echo ucfirst($payment['reimbursement_status']); ?></span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($logs) > 0): ?>
    <div class="mt-6">
        <h4 class="font-semibold text-gray-900 mb-3">Activity Log</h4>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="space-y-3">
                <?php foreach ($logs as $log): ?>
                <div class="border-l-4 border-blue-400 bg-white pl-4 py-3 rounded-r-lg shadow-sm">
                    <p class="font-semibold text-gray-900"><?php echo ucfirst($log['action']); ?> by <?php echo htmlspecialchars($log['performed_by']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($log['performed_at'])); ?></p>
                    <?php if ($log['notes']): ?>
                        <p class="text-sm text-gray-700 mt-2 bg-gray-50 p-2 rounded"><?php echo htmlspecialchars($log['notes']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
