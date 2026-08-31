<?php
include_once 'includes/db.php';

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
            <p class="text-sm text-gray-500">Purchase ID</p>
            <p class="font-medium">P#<?php echo $purchase['id']; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Date</p>
            <p class="font-medium"><?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Project</p>
            <p class="font-medium"><?php echo htmlspecialchars($purchase['project_name']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Client</p>
            <p class="font-medium"><?php echo htmlspecialchars($purchase['client_name'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Vendor</p>
            <p class="font-medium"><?php echo htmlspecialchars($purchase['vendor_name'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Invoice Number</p>
            <p class="font-medium"><?php echo htmlspecialchars($purchase['invoice_number'] ?? '-'); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Status</p>
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
            <p class="text-sm text-gray-500">Description</p>
            <p class="font-medium"><?php echo htmlspecialchars($purchase['description'] ?? '-'); ?></p>
        </div>
    </div>

    <div class="mt-4">
        <h4 class="font-medium mb-2">Items</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Description</th>
                        <th class="px-3 py-2 text-right whitespace-nowrap">Qty</th>
                        <th class="px-3 py-2 text-right whitespace-nowrap">Unit Price</th>
                        <th class="px-3 py-2 text-right whitespace-nowrap">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr class="border-t">
                        <td class="px-3 py-2"><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="px-3 py-2 text-right whitespace-nowrap"><?php echo number_format($item['quantity'], 2); ?></td>
                        <td class="px-3 py-2 text-right whitespace-nowrap"><?php echo money($item['unit_price']); ?></td>
                        <td class="px-3 py-2 text-right whitespace-nowrap"><?php echo money($item['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="border-t font-medium">
                        <td colspan="3" class="px-3 py-2 text-right whitespace-nowrap">Subtotal:</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap"><?php echo money($purchase['subtotal']); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-3 py-2 text-right whitespace-nowrap">Tax:</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap"><?php echo money($purchase['tax_amount']); ?></td>
                    </tr>
                    <tr class="font-bold">
                        <td colspan="3" class="px-3 py-2 text-right whitespace-nowrap">Total:</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap"><?php echo money($purchase['total_amount']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (count($payments) > 0): ?>
    <div class="mt-4">
        <h4 class="font-medium mb-2">Payments</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Date</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Amount</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Method</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr class="border-t">
                        <td class="px-3 py-2 whitespace-nowrap"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?php echo money($payment['amount']); ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <?php if ($payment['is_reimbursable']): ?>
                                <span class="text-xs text-purple-600"><?php echo ucfirst($payment['reimbursement_status']); ?></span>
                            <?php else: ?>
                                <span class="text-xs text-green-600">Paid</span>
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
    <div class="mt-4">
        <h4 class="font-medium mb-2">Activity Log</h4>
        <div class="space-y-2">
            <?php foreach ($logs as $log): ?>
            <div class="text-sm border-l-2 border-gray-300 pl-3 py-1">
                <p class="font-medium"><?php echo ucfirst($log['action']); ?> by <?php echo htmlspecialchars($log['performed_by']); ?></p>
                <p class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($log['performed_at'])); ?></p>
                <?php if ($log['notes']): ?>
                    <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($log['notes']); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

