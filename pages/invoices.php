<?php



include_once 'includes/db.php';
include_once 'includes/payment_methods.php';

/**
 * Invoices may be raised without a quotation ("Void Quotation" option in the
 * dropdown). The select posts "0" in that case; store it as a real NULL so the
 * LEFT JOINs on quotations resolve to NULL instead of a dangling id 0.
 */
if (!function_exists('normalizeQuotationId')) {
    function normalizeQuotationId($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === 'none' || (int) $raw <= 0) {
            return null;
        }
        return (int) $raw;
    }
}

/**
 * An invoice is attributed to a project directly (invoices.project_id) so that
 * quotation-less invoices still roll into the Projects income figures. An
 * explicit choice wins; otherwise inherit the project from the quotation.
 */
if (!function_exists('resolveInvoiceProjectId')) {
    function resolveInvoiceProjectId($pdo, $rawProjectId, $quotationId, $wasSubmitted = true)
    {
        $projectId = (int) trim((string) $rawProjectId);
        if ($projectId > 0) {
            return $projectId;
        }
        // An empty value from a form that HAS the field is a deliberate "No Project".
        if ($wasSubmitted) {
            return null;
        }
        if ($quotationId) {
            $stmt = $pdo->prepare("SELECT project_id FROM quotations WHERE id = ?");
            $stmt->execute([$quotationId]);
            $inherited = $stmt->fetchColumn();
            return $inherited ? (int) $inherited : null;
        }
        return null;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add'])) {

        $stmt = $pdo->prepare("INSERT INTO invoices (quotation_id, project_id, client_id, date, lpo_number, total_amount, gross_amount, discount, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $quotationId = normalizeQuotationId($_POST['quotation_id'] ?? null);

        // The form collects the gross amount and a discount; total_amount is stored net.
        $gross = max(0.0, floatval($_POST['total_amount'] ?? 0));
        $discount = min(max(0.0, floatval($_POST['discount'] ?? 0)), $gross);
        $netTotal = max(0.0, $gross - $discount);

        $stmt->execute([$quotationId, resolveInvoiceProjectId($pdo, $_POST['project_id'] ?? null, $quotationId, array_key_exists('project_id', $_POST)), $_POST['client_id'], $_POST['date'], $_POST['lpo_number'] ?? null, $netTotal, $gross, $discount, $netTotal]);

    } elseif (isset($_POST['update'])) {

        $stmt = $pdo->prepare("UPDATE invoices SET quotation_id=?, project_id=?, client_id=?, date=?, total_amount=?, gross_amount=?, discount=? WHERE id=?");

        $quotationId = normalizeQuotationId($_POST['quotation_id'] ?? null);

        $gross = max(0.0, floatval($_POST['total_amount'] ?? 0));
        $discount = min(max(0.0, floatval($_POST['discount'] ?? 0)), $gross);
        $netTotal = max(0.0, $gross - $discount);

        $stmt->execute([$quotationId, resolveInvoiceProjectId($pdo, $_POST['project_id'] ?? null, $quotationId, array_key_exists('project_id', $_POST)), $_POST['client_id'], $_POST['date'], $netTotal, $gross, $discount, $_POST['id']]);

    }

    // Apply discount to an invoice
    if (isset($_POST['apply_discount'])) {
        $invoice_id = (int) ($_POST['invoice_id'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        if ($invoice_id > 0) {
            // ensure discount column exists (PRAGMA is SQLite-only and fatals on MySQL)
            $hasDiscountCol = false;
            try {
                $pdo->query("SELECT discount FROM invoices LIMIT 1");
                $hasDiscountCol = true;
            } catch (PDOException $e) {
                $hasDiscountCol = false;
            }
            if (!$hasDiscountCol) {
                $pdo->exec("ALTER TABLE invoices ADD COLUMN discount DECIMAL(10,2) DEFAULT 0");
            }

            // Item presence is tested with COUNT(*), not SUM(total) > 0 — items may sum to zero.
            $sumStmt = $pdo->prepare("SELECT COUNT(*) AS item_count, COALESCE(SUM(total),0) AS item_sum FROM invoice_items WHERE invoice_id = ?");
            $sumStmt->execute([$invoice_id]);
            $itemInfo = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['item_count' => 0, 'item_sum' => 0];

            // current header figures
            $curStmt = $pdo->prepare("SELECT COALESCE(paid_amount,0) AS paid, COALESCE(total_amount,0) AS total, COALESCE(discount,0) AS discount, COALESCE(gross_amount, COALESCE(total_amount,0) + COALESCE(discount,0)) AS gross FROM invoices WHERE id = ?");
            $curStmt->execute([$invoice_id]);
            $cur = $curStmt->fetch(PDO::FETCH_ASSOC) ?: ['paid' => 0, 'total' => 0, 'discount' => 0, 'gross' => 0];
            $paid = (float) $cur['paid'];

            // Invoices raised without a quotation have no invoice_items, so the item
            // sum is 0 and discounting would wipe total_amount. Fall back to the
            // gross already on the header (total + the discount previously applied),
            // which keeps repeated discount edits idempotent.
            $gross = (int) $itemInfo['item_count'] > 0
                ? (float) $itemInfo['item_sum']
                : (float) $cur['gross'];

            // Store the discount clamped to the gross, otherwise the excess is added
            // back as gross on the next edit and inflates the invoice.
            $discount = min(max(0.0, $discount), $gross);
            $newTotal = max(0, $gross - $discount);

            $balance = $newTotal - $paid;

            // status must move with the total: a discount can settle an invoice in full.
            if ($paid <= 0) {
                $status = 'unpaid';
            } elseif ($balance <= 0) {
                $status = 'paid';
            } else {
                $status = 'partially_paid';
            }

            $u = $pdo->prepare("UPDATE invoices SET discount = ?, total_amount = ?, gross_amount = ?, balance = ?, status = ? WHERE id = ?");
            $u->execute([$discount, $newTotal, $gross, $balance, $status, $invoice_id]);
        }
        header('Location: index.php?page=invoices');
        exit;
    }

}

if (isset($_GET['delete'])) {

    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id=?");

    $stmt->execute([$_GET['delete']]);

}

// Function to update invoice payment status
function updateInvoicePaymentStatus($pdo, $invoice_id)
{
    // Calculate total paid for this invoice
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $result = $stmt->fetch();
    $total_paid = $result['total_paid'];

    // Get invoice total amount
    $stmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    $total_amount = $invoice['total_amount'];

    // Calculate balance
    $balance = $total_amount - $total_paid;

    // Determine status
    if ($total_paid == 0) {
        $status = 'unpaid';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } else {
        $status = 'partially_paid';
    }

    // Update invoice
    $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
    $stmt->execute([$total_paid, $balance, $status, $invoice_id]);
}

$invoices = $pdo->query("SELECT i.*, q.total_amount as quotation_amount, c.name as client_name,
                                COALESCE(dp.name, p.name) as project_name
                         FROM invoices i
                         LEFT JOIN quotations q ON i.quotation_id = q.id
                         LEFT JOIN clients c ON i.client_id = c.id
                         LEFT JOIN projects p ON q.project_id = p.id
                         LEFT JOIN projects dp ON i.project_id = dp.id")->fetchAll();

$quotations = $pdo->query("SELECT q.id, q.client_id, q.date, q.total_amount, q.project_id, p.name as project_name 
                           FROM quotations q 
                           LEFT JOIN projects p ON q.project_id = p.id 
                           WHERE q.status = 'approved'")->fetchAll();

$projectOptions = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

$clients = $pdo->query("SELECT id, name FROM clients")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Invoices</h1>
    <p class="text-gray-600 mt-2">Manage project invoices and track payment status</p>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Invoice Management</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add Invoice
                </button>
            </div>
        </div>

        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quotation</label>
                        <select name="quotation_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                            <option value="">Select Quotation</option>
                            <option value="0" class="font-semibold text-primary">Void Quotation — invoice without quotation</option>
                            <?php foreach ($quotations as $quotation): ?>
                                <option value="<?php echo $quotation['id']; ?>"
                                    data-client-id="<?php echo $quotation['client_id']; ?>"
                                    data-amount="<?php echo $quotation['total_amount']; ?>"
                                    data-project-id="<?php echo $quotation['project_id'] ?? ''; ?>"
                                    data-date="<?php echo $quotation['date']; ?>">
                                    Q#<?php echo $quotation['id']; ?> — <?php echo htmlspecialchars($quotation['project_name'] ?? 'No Project'); ?> — $<?php echo number_format($quotation['total_amount'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                        <select name="client_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                            <option value="">Select Client</option>
                            <option value="new_client_action" class="font-bold text-primary">+ Add New Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                        <select name="project_id" id="add-project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">No Project</option>
                            <?php foreach ($projectOptions as $projectOption): ?>
                                <option value="<?php echo $projectOption['id']; ?>">
                                    <?php echo htmlspecialchars($projectOption['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Income is credited to this project. Auto-filled from the quotation when one is selected.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LPO Number</label>
                        <input type="text" name="lpo_number" placeholder="LPO-001 (optional)"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (before discount)</label>
                        <input type="number" step="0.01" min="0" name="total_amount" id="add-total_amount" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" id="add-discount" placeholder="0.00" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Net payable: <span id="add-net-total" class="font-semibold text-gray-700">0.00</span></p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Invoice
                    </button>
                </div>
            </form>
        </div>

        <div class="p-4 md:p-6">
            <div class="overflow-x-auto -mx-4 md:mx-0">
                <table class="w-full table-auto min-w-full">

                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Quotation</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Client</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Discount</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Received</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Balance</th>
                            <th
                                class="px-2 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                onclick="showPaymentHistory(<?php echo htmlspecialchars(json_encode($invoice), ENT_QUOTES, 'UTF-8'); ?>)"
                                title="Click to view payment history">
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php if (empty($invoice['quotation_id'])): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700"
                                                title="Invoice raised without a quotation">Void Quotation</span>
                                        <?php else: ?>
                                            <?php echo money($invoice['quotation_amount'] ?? 0); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($invoice['client_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php if (!empty($invoice['project_name'])): ?>
                                            <?php echo htmlspecialchars($invoice['project_name']); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">&mdash;</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($invoice['date'])); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                        <?php
                        switch ($invoice['status']) {
                            case 'paid':
                                echo 'bg-green-100 text-green-800';
                                break;
                            case 'unpaid':
                                echo 'bg-red-100 text-red-800';
                                break;
                            case 'partially_paid':
                                echo 'bg-yellow-100 text-yellow-800';
                                break;
                            default:
                                echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $invoice['status'] ?? 'unpaid')); ?>
                                    </span>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm">
                                        <?php if ((float) ($invoice['discount'] ?? 0) > 0): ?>
                                            <span class="text-red-600 font-medium">-<?php echo money($invoice['discount']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">&mdash;</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo money($invoice['total_amount']); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo money($invoice['paid_amount'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo money($invoice['balance'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap text-center">
                                    <div class="flex flex-col space-y-1" onclick="event.stopPropagation()">
                                        <div class="flex space-x-1">
                                            <a href="pages/invoice_view.php?id=<?php echo $invoice['id']; ?>"
                                                target="_blank" class="text-gray-700 hover:text-gray-900 text-xs"
                                                title="View"><i class="fas fa-eye"></i></a>
                                            <button class="text-green-600 hover:text-green-800 text-xs"
                                                onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode($invoice), ENT_QUOTES, 'UTF-8'); ?>)"
                                                title="Record Payment"><i class="fas fa-credit-card"></i></button>
                                            <button class="text-indigo-600 hover:text-indigo-800 text-xs"
                                                onclick="openDiscountModal(<?php echo $invoice['id']; ?>)"
                                                title="Discount"><i class="fas fa-percent"></i></button>
                                        </div>
                                        <div class="flex space-x-1">
                                            <a href="pages/invoice_edit.php?id=<?php echo $invoice['id']; ?>"
                                                class="text-primary hover:text-secondary text-xs" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?page=invoices&delete=<?php echo $invoice['id']; ?>"
                                                class="text-red-600 hover:text-red-900 text-xs"
                                                onclick="return confirm('Are you sure you want to delete this invoice?')"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div id="paymentHistoryModal" class="hidden fixed z-30 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closePaymentHistoryModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-900">Payment History</h3>
                    <button onclick="closePaymentHistoryModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Invoice Summary -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 uppercase">Invoice</div>
                            <div class="text-sm font-semibold" id="history-invoice-id">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase">Client</div>
                            <div class="text-sm font-semibold" id="history-client">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase">Total Amount</div>
                            <div class="text-sm font-semibold" id="history-total">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase">Balance</div>
                            <div class="text-sm font-semibold" id="history-balance">-</div>
                        </div>
                    </div>
                </div>

                <!-- Payment History Table -->
                <div class="overflow-x-auto">
                    <div id="payment-history-loading" class="text-center py-8 hidden">
                        <i class="fas fa-spinner fa-spin text-3xl text-primary"></i>
                        <p class="text-gray-600 mt-2">Loading payment history...</p>
                    </div>
                    <div id="payment-history-empty" class="text-center py-8 hidden">
                        <i class="fas fa-receipt text-4xl text-gray-300 mb-2"></i>
                        <p class="text-gray-600">No payments recorded yet</p>
                    </div>
                    <table id="payment-history-table" class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="payment-history-body" class="bg-white divide-y divide-gray-200">
                            <!-- Payment rows will be inserted here -->
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">Total Paid:</td>
                                <td class="px-4 py-3 text-sm font-semibold text-green-600" id="history-total-paid"
                                    colspan="3">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="closePaymentHistoryModal()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="paymentModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closePaymentModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post" action="index.php?page=payments">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Record Payment</h3>
                    <input type="hidden" id="pay-invoice_id" name="invoice_id" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice</label>
                            <input type="text" id="pay-invoice_label"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" step="0.01" name="amount" id="pay-amount"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="date" id="pay-date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" id="pay-method"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php echo payment_method_options(); ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="pay-notes" rows="3"
                            placeholder="Add any additional notes about this payment..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="add"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary sm:ml-3 sm:w-auto sm:text-sm">Record</button>
                    <button type="button" onclick="closePaymentModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openPaymentModal(invoice) {
        document.getElementById('paymentModal').classList.remove('hidden');
        document.getElementById('pay-invoice_id').value = invoice.id;
        document.getElementById('pay-invoice_label').value = 'INV#' + invoice.id + ' — ' + (Number(invoice.total_amount) || 0).toFixed(2);
        document.getElementById('pay-amount').value = (Number(invoice.balance) && Number(invoice.balance) > 0) ? Number(invoice.balance).toFixed(2) : (Number(invoice.total_amount) || 0).toFixed(2);
        const today = new Date().toISOString().slice(0, 10);
        document.getElementById('pay-date').value = today;
        document.getElementById('pay-method').value = '';
        document.getElementById('pay-notes').value = '';
    }
    function closePaymentModal() { document.getElementById('paymentModal').classList.add('hidden'); }

    function showPaymentHistory(invoice) {
        // Prevent event bubbling from action buttons
        event.stopPropagation();

        // Show modal
        document.getElementById('paymentHistoryModal').classList.remove('hidden');

        // Update invoice summary
        document.getElementById('history-invoice-id').textContent = 'INV#' + invoice.id;
        document.getElementById('history-client').textContent = invoice.client_name || 'N/A';
        document.getElementById('history-total').textContent = '<?php echo currency_symbol(); ?>' + (Number(invoice.total_amount) || 0).toFixed(2);
        document.getElementById('history-balance').textContent = '<?php echo currency_symbol(); ?>' + (Number(invoice.balance) || 0).toFixed(2);

        // Show loading
        document.getElementById('payment-history-loading').classList.remove('hidden');
        document.getElementById('payment-history-empty').classList.add('hidden');
        document.getElementById('payment-history-table').classList.add('hidden');

        // Fetch payment history
        fetch('pages/get_payment_history.php?invoice_id=' + invoice.id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('payment-history-loading').classList.add('hidden');

                if (data.payments && data.payments.length > 0) {
                    // Show table
                    document.getElementById('payment-history-table').classList.remove('hidden');
                    document.getElementById('payment-history-empty').classList.add('hidden');

                    // Build table rows
                    const tbody = document.getElementById('payment-history-body');
                    tbody.innerHTML = '';

                    let totalPaid = 0;
                    data.payments.forEach(payment => {
                        totalPaid += Number(payment.amount) || 0;

                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';

                        // Format payment method
                        // Format payment method
                        const methodLabels = {
                            'company_cash': 'Company Cash',
                            'company_bank': 'Company Bank Transfer',
                            'company_card': 'Company Card',
                            'company_cheque': 'Company Cheque',
                            'credit_card': 'Credit Card',
                            'personal': 'Personal / Employee Cash',
                            'rahees_cash_card': 'Rahees Cash / Card',
                            'salman_cash_card': 'Salman Cash / Card',
                            'other': 'Other'
                        };
                        const methodLabel = methodLabels[payment.payment_method] || payment.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

                        const methodColors = {
                            'company_cash': 'bg-green-100 text-green-800',
                            'company_bank': 'bg-blue-100 text-blue-800',
                            'company_card': 'bg-purple-100 text-purple-800',
                            'company_cheque': 'bg-orange-100 text-orange-800',
                            'personal': 'bg-yellow-100 text-yellow-800',
                            'credit_card': 'bg-indigo-100 text-indigo-800',
                            'rahees_cash_card': 'bg-teal-100 text-teal-800',
                            'salman_cash_card': 'bg-teal-100 text-teal-800',
                            'other': 'bg-gray-100 text-gray-800'
                        };
                        const methodColor = methodColors[payment.payment_method] || 'bg-gray-100 text-gray-800';

                        row.innerHTML = `
                        <td class="px-4 py-3 text-sm text-gray-900">
                            ${new Date(payment.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-green-600">
                            <?php echo currency_symbol(); ?>${(Number(payment.amount) || 0).toFixed(2)}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${methodColor}">
                                ${methodLabel}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            ${payment.notes ? payment.notes : '<span class="text-gray-400">-</span>'}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex justify-center space-x-2">
                                <button onclick="sendReceipt(${payment.id}, ${invoice.id})" 
                                        class="text-blue-600 hover:text-blue-800" 
                                        title="Send as Receipt">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <button onclick="editPaymentFromHistory(${JSON.stringify(payment).replace(/"/g, '&quot;')})" 
                                        class="text-primary hover:text-secondary" 
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePaymentFromHistory(${payment.id})" 
                                        class="text-red-600 hover:text-red-800" 
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;
                        tbody.appendChild(row);
                    });

                    // Update total
                    document.getElementById('history-total-paid').textContent = '<?php echo currency_symbol(); ?>' + totalPaid.toFixed(2);
                } else {
                    // Show empty state
                    document.getElementById('payment-history-table').classList.add('hidden');
                    document.getElementById('payment-history-empty').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error fetching payment history:', error);
                document.getElementById('payment-history-loading').classList.add('hidden');
                document.getElementById('payment-history-empty').classList.remove('hidden');
            });
    }

    function closePaymentHistoryModal() {
        document.getElementById('paymentHistoryModal').classList.add('hidden');
    }

    function sendReceipt(paymentId, invoiceId) {
        // Open receipt in new window
        const receiptUrl = `pages/payment_receipt.php?payment_id=${paymentId}&invoice_id=${invoiceId}`;
        window.open(receiptUrl, '_blank');
    }

    function editPaymentFromHistory(payment) {
        // Close payment history modal
        closePaymentHistoryModal();

        // Redirect to payments page with edit modal
        window.location.href = `index.php?page=payments&edit=${payment.id}`;
    }

    function deletePaymentFromHistory(paymentId) {
        if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
            // Redirect to delete payment
            window.location.href = `index.php?page=payments&delete=${paymentId}`;
        }
    }
</script>

<div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Invoice</h3>
                    <input type="hidden" id="edit-id" name="id">
                    <div class="mb-4">
                        <label for="edit-quotation_id" class="block text-sm font-medium text-gray-700">Quotation</label>
                        <select name="quotation_id" id="edit-quotation_id"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="0" class="font-semibold text-primary">Void Quotation — invoice without quotation</option>
                            <?php foreach ($quotations as $quotation): ?>
                            <option value="<?php echo $quotation['id']; ?>"
                                data-client-id="<?php echo $quotation['client_id']; ?>"
                                data-amount="<?php echo $quotation['total_amount']; ?>"
                                data-project-id="<?php echo $quotation['project_id'] ?? ''; ?>"
                                data-date="<?php echo $quotation['date']; ?>">
                                Q#<?php echo $quotation['id']; ?> — <?php echo htmlspecialchars($quotation['project_name'] ?? 'No Project'); ?> — $<?php echo number_format($quotation['total_amount'], 2); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="edit-client_id" class="block text-sm font-medium text-gray-700">Client</label>
                        <select name="client_id" id="edit-client_id"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="new_client_action" class="font-bold text-primary">+ Add New Client</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"><?php echo $client['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="edit-project_id" class="block text-sm font-medium text-gray-700">Project</label>
                        <select name="project_id" id="edit-project_id"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">No Project</option>
                            <?php foreach ($projectOptions as $projectOption): ?>
                            <option value="<?php echo $projectOption['id']; ?>"><?php echo htmlspecialchars($projectOption['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="edit-date" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="date" id="edit-date"
                            class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label for="edit-total_amount" class="block text-sm font-medium text-gray-700">Amount (before
                            discount)</label>
                        <input type="number" step="0.01" min="0" name="total_amount" id="edit-total_amount"
                            class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label for="edit-discount" class="block text-sm font-medium text-gray-700">Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" id="edit-discount" value="0"
                            class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        <p class="text-xs text-gray-500 mt-1">Net payable: <span id="edit-net-total" class="font-semibold text-gray-700">0.00</span></p>
                    </div>
                    <div class="mb-4">
                        <label for="edit-status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="edit-status"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                            <option value="partially_paid">Partially Paid</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Update
                    </button>
                    <button type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-fill Add form fields based on selected quotation
        const qSelect = document.querySelector('select[name="quotation_id"]');
        const clientSelect = document.querySelector('select[name="client_id"]');
        const dateInput = document.querySelector('input[name="date"]');
        const amountInput = document.querySelector('input[name="total_amount"]');

        function syncFromQuotationOption(option, targets) {
            if (!option) return;
            // "Void Quotation" (value 0): nothing to copy. Leave whatever the operator
            // already entered — blanking the client select here would set
            // selectedIndex=-1 in the edit modal (it has no empty option), so the field
            // would not be submitted at all and the UPDATE would null out the client.
            if (option.value === '0') {
                if (targets.client) targets.client.focus();
                return;
            }
            const clientId = option.getAttribute('data-client-id');
            const amount = option.getAttribute('data-amount');
            const date = option.getAttribute('data-date');
            const projectId = option.getAttribute('data-project-id');
            if (clientId && targets.client) targets.client.value = clientId;
            if (amount && targets.amount) targets.amount.value = (Number(amount) || 0).toFixed(2);
            if (date && targets.date) targets.date.value = date;
            if (projectId && targets.project) targets.project.value = projectId;
        }

        if (qSelect) {
            qSelect.addEventListener('change', function () {
                syncFromQuotationOption(this.selectedOptions[0], {
                    client: clientSelect,
                    amount: amountInput,
                    date: dateInput,
                    project: document.getElementById('add-project_id')
                });
            });
        }

        // Live "net payable" preview on both forms
        function bindNetPreview(amountId, discountId, outId) {
            const amountEl = document.getElementById(amountId);
            const discountEl = document.getElementById(discountId);
            const outEl = document.getElementById(outId);
            if (!amountEl || !discountEl || !outEl) return;
            const render = function () {
                const gross = Number(amountEl.value) || 0;
                const discount = Math.min(Math.max(Number(discountEl.value) || 0, 0), gross);
                outEl.textContent = Math.max(0, gross - discount).toFixed(2);
            };
            amountEl.addEventListener('input', render);
            discountEl.addEventListener('input', render);
            render();
        }
        bindNetPreview('add-total_amount', 'add-discount', 'add-net-total');
        bindNetPreview('edit-total_amount', 'edit-discount', 'edit-net-total');
        window.renderInvoiceNetTotals = function () {
            bindNetPreview('edit-total_amount', 'edit-discount', 'edit-net-total');
        };

        // Edit modal auto-fill when quotation changes
        const qEdit = document.getElementById('edit-quotation_id');
        const clientEdit = document.getElementById('edit-client_id');
        const dateEdit = document.getElementById('edit-date');
        const amountEdit = document.getElementById('edit-total_amount');

        if (qEdit) {
            qEdit.addEventListener('change', function () {
                syncFromQuotationOption(this.selectedOptions[0], {
                    client: clientEdit,
                    amount: amountEdit,
                    date: dateEdit,
                    project: document.getElementById('edit-project_id')
                });
            });
        }
    });

    // Open edit modal and ensure the quotation is selectable even if not in the approved list
    function editInvoice(invoice) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = invoice.id;

        const qSel = document.getElementById('edit-quotation_id');
        const hasQuotation = invoice.quotation_id !== null && invoice.quotation_id !== undefined
            && String(invoice.quotation_id) !== '' && Number(invoice.quotation_id) > 0;
        if (qSel && !hasQuotation) {
            qSel.value = '0';
        } else if (qSel) {
            qSel.value = invoice.quotation_id;
            if (String(qSel.value) !== String(invoice.quotation_id)) {
                const opt = document.createElement('option');
                opt.value = invoice.quotation_id;
                const amountLabel = (invoice.quotation_amount !== undefined && invoice.quotation_amount !== null)
                    ? ' — $' + (Number(invoice.quotation_amount) || 0).toFixed(2)
                    : '';
                opt.textContent = 'Q#' + invoice.quotation_id + amountLabel;
                qSel.appendChild(opt);
                qSel.value = invoice.quotation_id;
            }
        }

        if (hasQuotation) {
            document.getElementById('edit-quotation_id').dispatchEvent(new Event('change'));
        }
        document.getElementById('edit-client_id').value = invoice.client_id;
        document.getElementById('edit-project_id').value = invoice.project_id || '';
        document.getElementById('edit-date').value = invoice.date;
        const storedDiscount = Number(invoice.discount) || 0;
        document.getElementById('edit-discount').value = storedDiscount.toFixed(2);
        document.getElementById('edit-total_amount').value = ((Number(invoice.total_amount) || 0) + storedDiscount).toFixed(2);
        if (window.renderInvoiceNetTotals) window.renderInvoiceNetTotals();
        document.getElementById('edit-status').value = invoice.status;
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>

<!-- Add Client Modal -->
<div id="addClientModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeAddClientModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="ajaxClientForm" method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Client</h3>
                        <button type="button" onclick="closeAddClientModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="ajax-client-error" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm"></div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" name="name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                                <input type="text" name="contact"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" name="phone"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Save Client
                    </button>
                    <button type="button" onclick="closeAddClientModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let activeClientSelect = null;

    function initClientDropdowns() {
        const clientSelects = document.querySelectorAll('select[name="client_id"]');
        clientSelects.forEach(select => {
            select.addEventListener('change', function() {
                if (this.value === 'new_client_action') {
                    activeClientSelect = this;
                    this.value = ''; // Reset selection
                    openAddClientModal();
                }
            });
        });
    }

    function openAddClientModal() {
        document.getElementById('addClientModal').classList.remove('hidden');
        document.getElementById('ajaxClientForm').reset();
        document.getElementById('ajax-client-error').classList.add('hidden');
    }

    function closeAddClientModal() {
        document.getElementById('addClientModal').classList.add('hidden');
    }

    document.getElementById('ajaxClientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // Show loading state if needed
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        submitBtn.disabled = true;

        fetch('ajax/add_client.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Add new client to all client dropdowns
                const clientSelects = document.querySelectorAll('select[name="client_id"]');
                clientSelects.forEach(select => {
                    const option = document.createElement('option');
                    option.value = result.client.id;
                    option.textContent = result.client.name;
                    
                    // Insert before "Add New Client" or at the end
                    const addOption = select.querySelector('option[value="new_client_action"]');
                    if (addOption) {
                        select.insertBefore(option, addOption.nextSibling);
                    } else {
                        select.appendChild(option);
                    }
                });

                // Select the new client in the active dropdown
                if (activeClientSelect) {
                    activeClientSelect.value = result.client.id;
                }

                closeAddClientModal();
                
                // Optional: Show success toast/notification
                // alert('Client added successfully!');
            } else {
                const errorDiv = document.getElementById('ajax-client-error');
                errorDiv.textContent = result.message || 'Error occurred';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('ajax-client-error');
            errorDiv.textContent = 'Network or server error occurred';
            errorDiv.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    // Initialize dropdowns on load
    document.addEventListener('DOMContentLoaded', initClientDropdowns);
</script>

<!-- Discount Modal -->
<div id="discountModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeDiscountModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Apply Discount</h3>
                    <input type="hidden" id="disc-invoice-id" name="invoice_id" value="">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Discount Amount</label>
                        <input type="number" step="0.01" name="discount" id="disc-amount"
                            class="mt-1 block w-full px-3 py-2 border rounded-md" placeholder="0.00">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="apply_discount"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary sm:ml-3 sm:w-auto sm:text-sm">Apply</button>
                    <button type="button" onclick="closeDiscountModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDiscountModal(id) { document.getElementById('disc-invoice-id').value = id; document.getElementById('disc-amount').value = ''; document.getElementById('discountModal').classList.remove('hidden'); }
    function closeDiscountModal() { document.getElementById('discountModal').classList.add('hidden'); }
</script>