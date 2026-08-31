<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = null;
$error = null;

// Function to generate next LPO number
function generateLPONumber($pdo)
{
    // Detect database driver for cross-database compatibility
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Initialize sequence if not exists (cross-database compatible)
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO lpo_sequence (id, current_number, prefix, year) VALUES (1, 1000, 'LPO', ?)");
    } else {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO lpo_sequence (id, current_number, prefix, year) VALUES (1, 1000, 'LPO', ?)");
    }
    $stmt->execute([date('Y')]);

    $stmt = $pdo->prepare("SELECT * FROM lpo_sequence WHERE id = 1");
    $stmt->execute();
    $sequence = $stmt->fetch(PDO::FETCH_ASSOC);

    $current_year = date('Y');
    if ($sequence['year'] != $current_year) {
        // Reset counter for new year
        $pdo->exec("UPDATE lpo_sequence SET current_number = 1000, year = $current_year WHERE id = 1");
        $next_number = 1001;
    } else {
        $next_number = $sequence['current_number'] + 1;
    }

    return $sequence['prefix'] . '-' . $current_year . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lpo'])) {
    $lpo_number = trim($_POST['lpo_number']);
    $date = $_POST['date'];
    $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $department = trim($_POST['department'] ?? '');
    $payment_terms = $_POST['payment_terms'];
    $delivery_date = $_POST['delivery_date'];
    $reference = trim($_POST['reference'] ?? '');
    $tax_percentage = (float) ($_POST['tax_percentage'] ?? 0);
    $discount_percentage = (float) ($_POST['discount_percentage'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Validate required fields
    if (empty($lpo_number) || empty($date)) {
        $error = 'LPO Number and Date are required';
    } else {
        try {
            $pdo->beginTransaction();

            // Calculate totals from items
            $subtotal = 0;
            $items = [];

            if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
                foreach ($_POST['item_description'] as $index => $desc) {
                    if (!empty($desc)) {
                        $qty = (float) ($_POST['item_quantity'][$index] ?? 1);
                        $unit_price = (float) ($_POST['item_unit_price'][$index] ?? 0);
                        $total_price = $qty * $unit_price;
                        $subtotal += $total_price;

                        $items[] = [
                            'description' => trim($desc),
                            'quantity' => $qty,
                            'unit_of_measure' => trim($_POST['item_unit'][$index] ?? 'pcs'),
                            'unit_price' => $unit_price,
                            'total_price' => $total_price,
                            'notes' => trim($_POST['item_notes'][$index] ?? '')
                        ];
                    }
                }
            }

            if (empty($items)) {
                throw new Exception('At least one item is required');
            }

            // Calculate tax and discount
            $discount_amount = ($subtotal * $discount_percentage) / 100;
            $taxable_amount = $subtotal - $discount_amount;
            $tax_amount = ($taxable_amount * $tax_percentage) / 100;
            $grand_total = $taxable_amount + $tax_amount;

            // Insert LPO
            $stmt = $pdo->prepare("INSERT INTO lpos 
                (lpo_number, date, supplier_id, supplier_name, project_id, department, payment_terms, 
                 delivery_date, reference, subtotal, tax_amount, tax_percentage, discount_amount, 
                 discount_percentage, grand_total, created_by, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $lpo_number,
                $date,
                $supplier_id ?: null,
                $supplier_name,
                $project_id ?: null,
                $department,
                $payment_terms,
                $delivery_date,
                $reference,
                $subtotal,
                $tax_amount,
                $tax_percentage,
                $discount_amount,
                $discount_percentage,
                $grand_total,
                'Admin',
                $notes
            ]);

            $lpo_id = $pdo->lastInsertId();

            // Insert LPO items
            $itemStmt = $pdo->prepare("INSERT INTO lpo_items 
                (lpo_id, item_description, quantity, unit_of_measure, unit_price, total_price, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $itemStmt->execute([
                    $lpo_id,
                    $item['description'],
                    $item['quantity'],
                    $item['unit_of_measure'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['notes']
                ]);
            }

            // Update LPO sequence
            $pdo->exec("UPDATE lpo_sequence SET current_number = current_number + 1 WHERE id = 1");

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO lpo_audit_log (lpo_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$lpo_id, 'created', 'Admin', 'LPO created']);

            $pdo->commit();
            $message = 'LPO created successfully';

            // Clear form data
            $_POST = [];

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to create LPO: ' . $e->getMessage();
        }
    }
}

// Handle status updates
if (isset($_GET['action']) && isset($_GET['id'])) {
    $lpo_id = (int) $_GET['id'];
    $action = $_GET['action'];

    try {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE lpos SET status = 'approved', approved_by = ?, approved_at = ? WHERE id = ?");
                $stmt->execute(['Admin', date('Y-m-d H:i:s'), $lpo_id]);

                $logStmt = $pdo->prepare("INSERT INTO lpo_audit_log (lpo_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$lpo_id, 'approved', 'Admin', 'LPO approved']);

                $message = 'LPO approved successfully';
                break;

            case 'issue':
                $stmt = $pdo->prepare("UPDATE lpos SET status = 'issued', issued_by = ?, issued_at = ? WHERE id = ?");
                $stmt->execute(['Admin', date('Y-m-d H:i:s'), $lpo_id]);

                $logStmt = $pdo->prepare("INSERT INTO lpo_audit_log (lpo_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$lpo_id, 'issued', 'Admin', 'LPO issued to supplier']);

                $message = 'LPO issued successfully';
                break;

            case 'close':
                $stmt = $pdo->prepare("UPDATE lpos SET status = 'closed' WHERE id = ?");
                $stmt->execute([$lpo_id]);

                $logStmt = $pdo->prepare("INSERT INTO lpo_audit_log (lpo_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$lpo_id, 'closed', 'Admin', 'LPO closed']);

                $message = 'LPO closed successfully';
                break;

            case 'delete':
                // Delete LPO items first (due to foreign key)
                $stmt = $pdo->prepare("DELETE FROM lpo_items WHERE lpo_id = ?");
                $stmt->execute([$lpo_id]);

                // Delete audit logs
                $stmt = $pdo->prepare("DELETE FROM lpo_audit_log WHERE lpo_id = ?");
                $stmt->execute([$lpo_id]);

                // Delete the LPO
                $stmt = $pdo->prepare("DELETE FROM lpos WHERE id = ?");
                $stmt->execute([$lpo_id]);

                $message = 'LPO deleted successfully';
                break;
        }
    } catch (Exception $e) {
        $error = 'Failed to update LPO: ' . $e->getMessage();
    }
}

// Get data for dropdowns
$suppliers = [];
$contractors = [];
$projects = [];
$units = [];

try {
    $suppliers = $pdo->query("SELECT id, name FROM vendors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $contractors = $pdo->query("SELECT id, company_name FROM contractors ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
    $projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $units = $pdo->query("SELECT unit_code, unit_name FROM units_of_measure WHERE is_active = 1 ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load dropdown data: ' . $e->getMessage();
    $suppliers = [];
    $contractors = [];
    $projects = [];
    $units = [];
}

// Get LPOs for listing
$lpos = [];
try {
    $lpos = $pdo->query("
        SELECT l.*, v.name as supplier_name_ref, p.name as project_name
        FROM lpos l
        LEFT JOIN vendors v ON l.supplier_id = v.id
        LEFT JOIN projects p ON l.project_id = p.id
        ORDER BY l.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load LPOs: ' . $e->getMessage();
}

// Generate next LPO number for new form
$next_lpo_number = generateLPONumber($pdo);

?>

<div class="lpo-management-page">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">LPO Management</h1>
        <p class="text-gray-600 mt-2">Create and manage Local Purchase Orders</p>

        <?php if ($message): ?>
            <div class="mt-4 rounded-lg border border-green-300 bg-green-50 text-green-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- LPO Creation Form -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Create New LPO</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('lpoForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>New LPO
                </button>
            </div>
        </div>

        <div id="lpoForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-6">
                <!-- Header Information -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">LPO Number *</label>
                        <input type="text" name="lpo_number"
                            value="<?php echo htmlspecialchars($next_lpo_number, ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Date</label>
                        <input type="date" name="delivery_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Terms</label>
                        <select name="payment_terms"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Credit">Credit</option>
                            <option value="Cash">Cash</option>
                            <option value="Advance">Advance</option>
                            <option value="COD">Cash on Delivery</option>
                            <option value="Net 30">Net 30 Days</option>
                            <option value="Net 60">Net 60 Days</option>
                        </select>
                    </div>
                </div>

                <!-- Supplier and Project Information -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                        <select name="supplier_id" id="supplier_select" onchange="toggleSupplierName()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>">
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other">Other (Enter manually)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contractor</label>
                        <select name="contractor_id" id="contractor_select"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Contractor</option>
                            <?php foreach ($contractors as $contractor): ?>
                                <option value="<?php echo $contractor['id']; ?>">
                                    <?php echo htmlspecialchars($contractor['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="supplier_name_div" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Name *</label>
                        <input type="text" name="supplier_name" placeholder="Enter supplier name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                        <select name="project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" placeholder="e.g., Construction, Admin, etc."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference (Quotation/PR Number)</label>
                    <input type="text" name="reference" placeholder="Reference number or description"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <!-- Items Section -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Items</h3>
                        <button type="button" onclick="addItem()"
                            class="text-primary hover:text-secondary text-sm font-medium">
                            <i class="fas fa-plus mr-1"></i>Add Item
                        </button>
                    </div>

                    <div id="itemsContainer">
                        <div class="item-row grid grid-cols-12 gap-2 mb-3 p-3 bg-white border rounded-lg">
                            <div class="col-span-4">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Description *</label>
                                <input type="text" name="item_description[]" placeholder="Item description"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Qty *</label>
                                <input type="number" name="item_quantity[]" placeholder="1" value="1" step="0.01"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm item-qty"
                                    onchange="calculateItemTotal(this)" required>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Unit</label>
                                <select name="item_unit[]"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                    <?php if (!empty($units)): ?>
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo htmlspecialchars($unit['unit_code']); ?>">
                                                <?php echo htmlspecialchars($unit['unit_code']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="Pcs">Pcs</option>
                                        <option value="Kg">Kg</option>
                                        <option value="Ltr">Ltr</option>
                                        <option value="Mtr">Mtr</option>
                                        <option value="Set">Set</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Unit Price *</label>
                                <input type="number" name="item_unit_price[]" placeholder="0.00" step="0.01"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm item-price"
                                    onchange="calculateItemTotal(this)" required>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Total</label>
                                <input type="number" placeholder="0.00" step="0.01"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100 item-total"
                                    readonly>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                                <input type="text" name="item_notes[]" placeholder="Notes"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                            </div>
                            <div class="col-span-1 flex items-end">
                                <button type="button" onclick="removeItem(this)"
                                    class="w-full px-2 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totals Section -->
                <div class="border-t pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                            <input type="number" id="subtotal" step="0.01" value="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Discount %</label>
                            <input type="number" name="discount_percentage" id="discount_percentage" step="0.01"
                                value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                onchange="calculateGrandTotal()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tax/VAT %</label>
                            <input type="number" name="tax_percentage" id="tax_percentage" step="0.01" value="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                onchange="calculateGrandTotal()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grand Total</label>
                            <input type="number" id="grand_total" step="0.01" value="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 font-bold"
                                readonly>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes or instructions"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('lpoForm').classList.add('hidden')">
                        Cancel
                    </button>
                    <button type="submit" name="save_lpo"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Create LPO
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LPO Listing -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">LPO Records</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LPO
                            Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Project</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($lpos)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No LPOs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lpos as $lpo): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($lpo['lpo_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($lpo['date'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($lpo['supplier_name_ref'] ?: $lpo['supplier_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($lpo['project_name'] ?: '-'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo money($lpo['grand_total']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        switch ($lpo['status']) {
                                            case 'approved':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'issued':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'closed':
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                            case 'draft':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($lpo['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="?page=lpo_view&id=<?php echo $lpo['id']; ?>"
                                            class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="lpo_print_standalone.php?id=<?php echo $lpo['id']; ?>" target="_blank"
                                            class="text-green-600 hover:text-green-900" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($lpo['status'] === 'draft'): ?>
                                            <a href="?page=lpos&action=approve&id=<?php echo $lpo['id']; ?>"
                                                class="text-green-600 hover:text-green-900" title="Approve"
                                                onclick="return confirm('Approve this LPO?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($lpo['status'] === 'approved'): ?>
                                            <a href="?page=lpos&action=issue&id=<?php echo $lpo['id']; ?>"
                                                class="text-blue-600 hover:text-blue-900" title="Issue"
                                                onclick="return confirm('Issue this LPO to supplier?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($lpo['status'] === 'issued'): ?>
                                            <a href="?page=lpos&action=close&id=<?php echo $lpo['id']; ?>"
                                                class="text-gray-600 hover:text-gray-900" title="Close"
                                                onclick="return confirm('Close this LPO?')">
                                                <i class="fas fa-lock"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?page=lpos&action=delete&id=<?php echo $lpo['id']; ?>"
                                            class="text-red-600 hover:text-red-900" title="Delete"
                                            onclick="return confirm('Are you sure you want to delete this LPO? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleSupplierName() {
        const select = document.getElementById('supplier_select');
        const nameDiv = document.getElementById('supplier_name_div');

        if (select.value === 'other') {
            nameDiv.classList.remove('hidden');
            nameDiv.querySelector('input').required = true;
        } else {
            nameDiv.classList.add('hidden');
            nameDiv.querySelector('input').required = false;
        }
    }

    function addItem() {
        const container = document.getElementById('itemsContainer');
        const newRow = container.firstElementChild.cloneNode(true);

        // Clear values
        newRow.querySelectorAll('input').forEach(input => {
            if (input.classList.contains('item-qty')) {
                input.value = '1';
            } else if (!input.readOnly) {
                input.value = '';
            }
        });

        container.appendChild(newRow);
    }

    function removeItem(btn) {
        const container = document.getElementById('itemsContainer');
        if (container.children.length > 1) {
            btn.closest('.item-row').remove();
            calculateGrandTotal();
        }
    }

    function calculateItemTotal(input) {
        const row = input.closest('.item-row');
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const total = qty * price;
        row.querySelector('.item-total').value = total.toFixed(2);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-total').forEach(input => {
            subtotal += parseFloat(input.value) || 0;
        });

        document.getElementById('subtotal').value = subtotal.toFixed(2);

        const discountPercent = parseFloat(document.getElementById('discount_percentage').value) || 0;
        const taxPercent = parseFloat(document.getElementById('tax_percentage').value) || 0;

        const discountAmount = (subtotal * discountPercent) / 100;
        const taxableAmount = subtotal - discountAmount;
        const taxAmount = (taxableAmount * taxPercent) / 100;
        const grandTotal = taxableAmount + taxAmount;

        document.getElementById('grand_total').value = grandTotal.toFixed(2);
    }
</script>