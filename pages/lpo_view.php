<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$lpo_id = (int)($_GET['id'] ?? 0);

if (!$lpo_id) {
    header('Location: ?page=lpos');
    exit;
}

// Get LPO details
try {
    $stmt = $pdo->prepare("
        SELECT l.*, v.name as supplier_name_ref, v.address as supplier_address, 
               v.phone as supplier_phone, v.email as supplier_email,
               p.name as project_name
        FROM lpos l
        LEFT JOIN vendors v ON l.supplier_id = v.id
        LEFT JOIN projects p ON l.project_id = p.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lpo_id]);
    $lpo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lpo) {
        header('Location: ?page=lpos');
        exit;
    }
    
    // Get LPO items
    $itemsStmt = $pdo->prepare("SELECT * FROM lpo_items WHERE lpo_id = ? ORDER BY id");
    $itemsStmt->execute([$lpo_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get audit log
    $logStmt = $pdo->prepare("SELECT * FROM lpo_audit_log WHERE lpo_id = ? ORDER BY performed_at DESC");
    $logStmt->execute([$lpo_id]);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Error loading LPO: ' . $e->getMessage());
}

?>

<div class="lpo-view-page">
    <div class="mb-6">
        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">LPO Details</h1>
                <p class="text-gray-600 mt-2">Local Purchase Order #<?php echo htmlspecialchars($lpo['lpo_number']); ?></p>
            </div>
            <div class="flex space-x-2">
                <a href="?page=lpos" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-1 md:mr-2"></i>Back to LPOs
                </a>
                <a href="lpo_print_standalone.php?id=<?php echo $lpo['id']; ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors">
                    <i class="fas fa-print mr-1 md:mr-2"></i>Print
                </a>
            </div>
        </div>
    </div>

    <!-- LPO Status -->
    <div class="bg-white rounded-lg shadow-md mb-6 p-6">
        <div class="flex justify-between items-center">
            <div>
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                    <?php
                    switch($lpo['status']) {
                        case 'approved': echo 'bg-green-100 text-green-800'; break;
                        case 'issued': echo 'bg-blue-100 text-blue-800'; break;
                        case 'closed': echo 'bg-gray-100 text-gray-800'; break;
                        case 'draft': echo 'bg-yellow-100 text-yellow-800'; break;
                        default: echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                    <?php echo ucfirst($lpo['status']); ?>
                </span>
            </div>
            <div class="flex space-x-2">
                <?php if ($lpo['status'] === 'draft'): ?>
                    <a href="?page=lpos&action=approve&id=<?php echo $lpo['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors" onclick="return confirm('Approve this LPO?')">
                        <i class="fas fa-check mr-2"></i>Approve
                    </a>
                <?php endif; ?>
                <?php if ($lpo['status'] === 'approved'): ?>
                    <a href="?page=lpos&action=issue&id=<?php echo $lpo['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors" onclick="return confirm('Issue this LPO to supplier?')">
                        <i class="fas fa-paper-plane mr-2"></i>Issue
                    </a>
                <?php endif; ?>
                <?php if ($lpo['status'] === 'issued'): ?>
                    <a href="?page=lpos&action=close&id=<?php echo $lpo['id']; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors" onclick="return confirm('Close this LPO?')">
                        <i class="fas fa-lock mr-2"></i>Close
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LPO Information -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- LPO Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">LPO Details</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">LPO Number:</span>
                    <span class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($lpo['lpo_number']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Date:</span>
                    <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($lpo['date'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Delivery Date:</span>
                    <span class="text-sm text-gray-900"><?php echo $lpo['delivery_date'] ? date('M d, Y', strtotime($lpo['delivery_date'])) : 'Not specified'; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Payment Terms:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['payment_terms']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Project:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['project_name'] ?: 'Not specified'); ?></span>
                </div>
                <?php if ($lpo['department']): ?>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Department:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['department']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lpo['reference']): ?>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Reference:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['reference']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Supplier Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Supplier Details</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Name:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['supplier_name_ref'] ?: $lpo['supplier_name']); ?></span>
                </div>
                <?php if ($lpo['supplier_address']): ?>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Address:</span>
                    <span class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($lpo['supplier_address'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lpo['supplier_phone']): ?>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Phone:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['supplier_phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lpo['supplier_email']): ?>
                <div class="flex justify-between">
                    <span class="text-sm font-medium text-gray-600">Email:</span>
                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($lpo['supplier_email']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Items</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $index => $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $index + 1; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($item['item_description']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($item['quantity'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo money($item['unit_price']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo money($item['total_price']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($item['notes'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Totals -->
    <div class="bg-white rounded-lg shadow-md mb-6 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900"><?php echo money($lpo['subtotal']); ?></div>
                <div class="text-sm text-gray-600">Subtotal</div>
            </div>
            <?php if ($lpo['discount_amount'] > 0): ?>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600">-<?php echo money($lpo['discount_amount']); ?></div>
                <div class="text-sm text-gray-600">Discount (<?php echo $lpo['discount_percentage']; ?>%)</div>
            </div>
            <?php endif; ?>
            <?php if ($lpo['tax_amount'] > 0): ?>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo money($lpo['tax_amount']); ?></div>
                <div class="text-sm text-gray-600">Tax/VAT (<?php echo $lpo['tax_percentage']; ?>%)</div>
            </div>
            <?php endif; ?>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600"><?php echo money($lpo['grand_total']); ?></div>
                <div class="text-sm text-gray-600">Grand Total</div>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <?php if ($lpo['notes']): ?>
    <div class="bg-white rounded-lg shadow-md mb-6 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Notes</h3>
        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($lpo['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Activity Log -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Activity Log</h3>
        </div>
        <div class="p-6">
            <?php if (empty($logs)): ?>
                <p class="text-gray-500 text-center py-4">No activity recorded</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($logs as $log): ?>
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-history text-blue-600 text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M d, Y H:i', strtotime($log['performed_at'])); ?>
                                </p>
                            </div>
                            <p class="text-sm text-gray-600">
                                By <?php echo htmlspecialchars($log['performed_by']); ?>
                                <?php if ($log['notes']): ?>
                                    - <?php echo htmlspecialchars($log['notes']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
