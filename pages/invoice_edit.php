<?php
/**
 * Invoice Edit Page
 * Edit invoice details and manage invoice items (add, edit, delete)
 */

include_once '../includes/db.php';
include_once '../includes/functions.php';

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($invoice_id <= 0) {
    header('Location: ../index.php?page=invoices');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Add invoice item
    if (isset($_POST['add_item'])) {
        $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
        $total = $_POST['quantity'] * $_POST['price'];
        $stmt->execute([$invoice_id, $_POST['description'], $_POST['quantity'], $_POST['price'], $total]);

        // Recalculate invoice total
        updateInvoiceTotalFromItems($pdo, $invoice_id);
        header("Location: invoice_edit.php?id=$invoice_id&success=item_added");
        exit;
    }

    // Update invoice item
    if (isset($_POST['update_item'])) {
        $stmt = $pdo->prepare("UPDATE invoice_items SET description=?, quantity=?, price=?, total=? WHERE id=?");
        $total = $_POST['quantity'] * $_POST['price'];
        $stmt->execute([$_POST['description'], $_POST['quantity'], $_POST['price'], $total, $_POST['item_id']]);

        // Recalculate invoice total
        updateInvoiceTotalFromItems($pdo, $invoice_id);
        header("Location: invoice_edit.php?id=$invoice_id&success=item_updated");
        exit;
    }

    // Update invoice header
    if (isset($_POST['update_invoice'])) {
        // "Void Quotation" posts 0 — store a real NULL so the quotation joins resolve to NULL.
        $rawQuotation = trim((string) ($_POST['quotation_id'] ?? ''));
        $quotation_id = ($rawQuotation === '' || (int) $rawQuotation <= 0) ? null : (int) $rawQuotation;

        // Project attribution. This form always submits the field and the quotation
        // select syncs it client-side, so an empty value is a deliberate "No Project"
        // and must not be silently overwritten with the quotation's project.
        $project_id = (int) trim((string) ($_POST['project_id'] ?? ''));
        if ($project_id <= 0) {
            $project_id = null;
            if (!array_key_exists('project_id', $_POST) && $quotation_id) {
                $pq = $pdo->prepare("SELECT project_id FROM quotations WHERE id = ?");
                $pq->execute([$quotation_id]);
                $inherited = $pq->fetchColumn();
                $project_id = $inherited ? (int) $inherited : null;
            }
        }

        // Reconstruct the gross from the CURRENT stored figures before the new
        // discount overwrites them: total_amount is stored net, so gross is
        // total + the discount that produced it (or the item sum when there are items).
        $gross = invoiceGrossAmount($pdo, $invoice_id);
        $discount = min(max(0.0, floatval($_POST['discount'] ?? 0)), $gross);
        $newTotal = max(0.0, $gross - $discount);

        $stmt = $pdo->prepare("UPDATE invoices SET quotation_id=?, project_id=?, client_id=?, date=?, lpo_number=?, discount=?, total_amount=?, gross_amount=? WHERE id=?");
        $stmt->execute([$quotation_id, $project_id, $_POST['client_id'], $_POST['date'], $_POST['lpo_number'], $discount, $newTotal, $gross, $invoice_id]);

        // Keep paid_amount / balance / status in step with the new total.
        updateInvoicePaymentStatus($pdo, $invoice_id);
        header("Location: invoice_edit.php?id=$invoice_id&success=invoice_updated");
        exit;
    }
}

// Handle delete item
if (isset($_GET['delete_item'])) {
    $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE id=? AND invoice_id=?");
    $stmt->execute([$_GET['delete_item'], $invoice_id]);

    // Recalculate invoice total
    updateInvoiceTotalFromItems($pdo, $invoice_id);
    header("Location: invoice_edit.php?id=$invoice_id&success=item_deleted");
    exit;
}

/**
 * The gross (pre-discount) amount of an invoice. Invoices with line items are
 * driven by the item sum; header-only invoices (raised without a quotation)
 * keep their gross on the header as total_amount + discount.
 *
 * Item presence is tested with COUNT(*), not SUM(total) > 0 — an invoice can
 * legitimately hold items that sum to zero.
 */
function invoiceGrossAmount($pdo, $invoice_id)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS item_count, COALESCE(SUM(total), 0) AS item_sum FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetch();

    if ((int) $items['item_count'] > 0) {
        return (float) $items['item_sum'];
    }

    // No items: the gross lives on the header. It is stored, never reconstructed
    // from the net total, so an invoice whose items were all deleted correctly
    // falls to 0 instead of freezing at its last item-derived value.
    $stmt = $pdo->prepare("SELECT COALESCE(gross_amount, COALESCE(total_amount, 0) + COALESCE(discount, 0)) AS gross FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);

    return (float) $stmt->fetchColumn();
}

// Function to update invoice total from items
function updateInvoiceTotalFromItems($pdo, $invoice_id)
{
    $dStmt = $pdo->prepare("SELECT COALESCE(discount, 0) AS discount FROM invoices WHERE id = ?");
    $dStmt->execute([$invoice_id]);
    $discount = (float) ($dStmt->fetchColumn() ?: 0);

    // Called from the item add / update / delete paths, where the items are
    // authoritative — deleting the last line legitimately takes the gross to 0.
    $iStmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = ?");
    $iStmt->execute([$invoice_id]);
    $gross = (float) $iStmt->fetchColumn();

    $discount = min($discount, $gross);
    $new_total = max(0, $gross - $discount);

    // Update invoice total
    $stmt = $pdo->prepare("UPDATE invoices SET total_amount = ?, gross_amount = ?, discount = ? WHERE id = ?");
    $stmt->execute([$new_total, $gross, $discount, $invoice_id]);

    // Update payment status
    updateInvoicePaymentStatus($pdo, $invoice_id);
}

// Function to update invoice payment status
function updateInvoicePaymentStatus($pdo, $invoice_id)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $result = $stmt->fetch();
    $total_paid = $result['total_paid'];

    $stmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    $total_amount = $invoice['total_amount'];

    $balance = $total_amount - $total_paid;

    if ($total_paid == 0) {
        $status = 'unpaid';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } else {
        $status = 'partially_paid';
    }

    $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
    $stmt->execute([$total_paid, $balance, $status, $invoice_id]);
}

// Fetch invoice details
$stmt = $pdo->prepare("
    SELECT i.*, c.name as client_name, c.email as client_email 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE i.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: ../index.php?page=invoices');
    exit;
}

// Fetch invoice items
$stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// Fetch clients for dropdown
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

// Quotations for the dropdown: approved ones, plus whichever is already linked
// (it may have since left 'approved' status and must stay selectable).
$qStmt = $pdo->prepare("SELECT q.id, q.total_amount, q.project_id, p.name AS project_name
                        FROM quotations q
                        LEFT JOIN projects p ON q.project_id = p.id
                        WHERE q.status = 'approved' OR q.id = ?
                        ORDER BY q.id DESC");
$qStmt->execute([$invoice['quotation_id']]);
$quotations = $qStmt->fetchAll();

$projectOptions = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit Invoice #<?php echo $invoice_id; ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: '#f07d00',
                            secondary: '#e67e22'
                        }
                    }
                }
            }
        </script>
    </head>

    <body class="bg-gray-50">
        <div class="max-w-6xl mx-auto p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Invoice #<?php echo $invoice_id; ?></h1>
                    <p class="text-gray-600 mt-1">Client: <?php echo htmlspecialchars($invoice['client_name']); ?></p>
                </div>
                <a href="../index.php?page=invoices"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Invoices
                </a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php
                    $messages = [
                        'item_added' => 'Item added successfully!',
                        'item_updated' => 'Item updated successfully!',
                        'item_deleted' => 'Item deleted successfully!',
                        'invoice_updated' => 'Invoice updated successfully!'
                    ];
                    echo $messages[$_GET['success']] ?? 'Success!';
                    ?>
                </div>
            <?php endif; ?>

            <!-- Invoice Details Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Invoice Details</h2>
                <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quotation</label>
                        <?php
                        // The linked quotation may have been deleted. Without this the select
                        // would silently fall back to "Void Quotation" and the next save would
                        // quietly de-link the invoice.
                        $linkedQuotationMissing = !empty($invoice['quotation_id'])
                            && !in_array((int) $invoice['quotation_id'], array_map(function ($q) { return (int) $q['id']; }, $quotations), true);
                        ?>
                        <select name="quotation_id" id="invoice-quotation_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="0" <?php echo empty($invoice['quotation_id']) ? 'selected' : ''; ?>>
                                Void Quotation — invoice without quotation
                            </option>
                            <?php if ($linkedQuotationMissing): ?>
                                <option value="<?php echo (int) $invoice['quotation_id']; ?>" selected>
                                    Q#<?php echo (int) $invoice['quotation_id']; ?> — quotation no longer exists
                                </option>
                            <?php endif; ?>
                            <?php foreach ($quotations as $quotation): ?>
                                <option value="<?php echo $quotation['id']; ?>"
                                    data-project-id="<?php echo $quotation['project_id'] ?? ''; ?>"
                                    <?php echo $quotation['id'] == $invoice['quotation_id'] ? 'selected' : ''; ?>>
                                    Q#<?php echo $quotation['id']; ?> — <?php echo htmlspecialchars($quotation['project_name'] ?? 'No Project'); ?> — <?php echo money($quotation['total_amount']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                        <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client['id'] == $invoice['client_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                        <select name="project_id" id="invoice-project_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">No Project</option>
                            <?php foreach ($projectOptions as $projectOption): ?>
                                <option value="<?php echo $projectOption['id']; ?>" <?php echo $projectOption['id'] == ($invoice['project_id'] ?? 0) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($projectOption['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="date" value="<?php echo $invoice['date']; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                        <input type="number" step="0.01" min="0" name="discount"
                            value="<?php echo number_format((float) ($invoice['discount'] ?? 0), 2, '.', ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="text-xs text-gray-500 mt-1">Subtracted from the item total. Saved with Update Invoice Details.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LPO Number</label>
                        <input type="text" name="lpo_number"
                            value="<?php echo htmlspecialchars($invoice['lpo_number'] ?? ''); ?>" placeholder="Optional"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" name="update_invoice"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md">
                            <i class="fas fa-save mr-2"></i>Update Invoice Details
                        </button>
                    </div>
                </form>
                <script>
                    // Relinking to another quotation must move the project with it,
                    // otherwise the invoice keeps crediting income to the old project.
                    (function () {
                        const quotationSelect = document.getElementById('invoice-quotation_id');
                        const projectSelect = document.getElementById('invoice-project_id');
                        if (!quotationSelect || !projectSelect) return;
                        quotationSelect.addEventListener('change', function () {
                            const option = this.selectedOptions[0];
                            if (!option) return;
                            const projectId = option.getAttribute('data-project-id');
                            // "Void Quotation" carries no project — leave the operator's choice alone.
                            if (option.value === '0') return;
                            projectSelect.value = projectId || '';
                        });
                    })();
                </script>
            </div>

            <!-- Invoice Items Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Invoice Items</h2>
                    <button onclick="document.getElementById('addItemForm').classList.toggle('hidden')"
                        class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-plus mr-2"></i>Add Item
                    </button>
                </div>

                <!-- Add Item Form -->
                <div id="addItemForm" class="hidden bg-gray-50 p-4 rounded-lg mb-4">
                    <h3 class="font-semibold mb-3">Add New Item</h3>
                    <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="description" placeholder="Item description"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                            <input type="number" step="0.01" name="quantity" placeholder="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                            <input type="number" step="0.01" name="price" placeholder="0.00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div class="md:col-span-4 flex space-x-2">
                            <button type="submit" name="add_item"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                                <i class="fas fa-plus mr-2"></i>Add Item
                            </button>
                            <button type="button"
                                onclick="document.getElementById('addItemForm').classList.add('hidden')"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Items Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2"></i>
                                        <p>No items added yet. Click "Add Item" to get started.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50" id="item-<?php echo $item['id']; ?>">
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($item['description']); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-900"><?php echo $item['quantity']; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-900">
                                            <?php echo money($item['price']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">
                                            <?php echo money($item['total']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                onclick="editItem(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)"
                                                class="text-primary hover:text-secondary mr-2" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?id=<?php echo $invoice_id; ?>&delete_item=<?php echo $item['id']; ?>"
                                                onclick="return confirm('Delete this item?')"
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <?php
                            $invoiceDiscount = (float) ($invoice['discount'] ?? 0);
                            $itemsSubtotal = 0.0;
                            foreach ($items as $footItem) {
                                $itemsSubtotal += (float) $footItem['total'];
                            }
                            if (!$items) {
                                $itemsSubtotal = (float) ($invoice['gross_amount'] ?? ((float) $invoice['total_amount'] + $invoiceDiscount));
                            }
                            ?>
                            <?php if ($invoiceDiscount > 0): ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-sm text-gray-600">Subtotal:</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold text-gray-900">
                                        <?php echo money($itemsSubtotal); ?>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-sm text-gray-600">Discount:</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold text-red-600">
                                        -<?php echo money($invoiceDiscount); ?>
                                    </td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-900">Total:</td>
                                <td class="px-4 py-3 text-right font-bold text-lg text-primary">
                                    <?php echo money($invoice['total_amount']); ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Invoice Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Invoice Summary</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Total Amount</div>
                        <div class="text-lg font-semibold"><?php echo money($invoice['total_amount']); ?></div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Paid</div>
                        <div class="text-lg font-semibold text-green-600">
                            <?php echo money($invoice['paid_amount'] ?? 0); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Balance</div>
                        <div class="text-lg font-semibold text-orange-600">
                            <?php echo money($invoice['balance'] ?? $invoice['total_amount']); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Status</div>
                        <div class="text-lg font-semibold">
                            <span class="inline-flex px-2 py-1 text-xs rounded-full
                            <?php
                            switch ($invoice['status'] ?? 'unpaid') {
                                case 'paid':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'unpaid':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                case 'partially_paid':
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                            }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $invoice['status'] ?? 'unpaid')); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Item Modal -->
        <div id="editItemModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20">
                <div class="fixed inset-0 transition-opacity" onclick="closeEditItemModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <div class="inline-block bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                    <form method="post">
                        <div class="bg-white px-6 pt-5 pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Item</h3>
                            <input type="hidden" id="edit-item-id" name="item_id">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <input type="text" id="edit-description" name="description"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                        <input type="number" step="0.01" id="edit-quantity" name="quantity"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                                        <input type="number" step="0.01" id="edit-price" name="price"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-2">
                            <button type="button" onclick="closeEditItemModal()"
                                class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded-md">Cancel</button>
                            <button type="submit" name="update_item"
                                class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-md">
                                <i class="fas fa-save mr-2"></i>Update Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function editItem(item) {
                document.getElementById('editItemModal').classList.remove('hidden');
                document.getElementById('edit-item-id').value = item.id;
                document.getElementById('edit-description').value = item.description;
                document.getElementById('edit-quantity').value = item.quantity;
                document.getElementById('edit-price').value = item.price;
            }

            function closeEditItemModal() {
                document.getElementById('editItemModal').classList.add('hidden');
            }
        </script>
    </body>

    </html>