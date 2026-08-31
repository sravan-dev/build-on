<?php

include_once 'includes/db.php';

// Create tables if they don't exist (MySQL syntax)
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'mysql') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        vendor_id INT,
        purchase_date DATE NOT NULL,
        description TEXT,
        invoice_number VARCHAR(100),
        attachment_path VARCHAR(255),
        subtotal DECIMAL(10,2) DEFAULT 0,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'draft',
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_by VARCHAR(100),
        approved_at TIMESTAMP NULL,
        rejection_reason TEXT,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(vendor_id) REFERENCES vendors(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        description TEXT NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(10,2) DEFAULT 0,
        total DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        return_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        invoice_number VARCHAR(100),
        reason TEXT,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        performed_by VARCHAR(100) NOT NULL,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        old_values TEXT,
        new_values TEXT,
        notes TEXT,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    // SQLite syntax
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        vendor_id INTEGER,
        purchase_date TEXT NOT NULL,
        description TEXT,
        invoice_number TEXT,
        attachment_path TEXT,
        subtotal REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        total_amount REAL DEFAULT 0,
        status TEXT DEFAULT 'draft',
        created_by TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        approved_by TEXT,
        approved_at TEXT,
        rejection_reason TEXT,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(vendor_id) REFERENCES vendors(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        description TEXT NOT NULL,
        quantity REAL DEFAULT 1,
        unit_price REAL DEFAULT 0,
        total REAL DEFAULT 0,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_returns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        return_date TEXT NOT NULL,
        amount REAL NOT NULL DEFAULT 0,
        invoice_number TEXT,
        reason TEXT,
        created_by TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        performed_by TEXT NOT NULL,
        performed_at TEXT DEFAULT CURRENT_TIMESTAMP,
        old_values TEXT,
        new_values TEXT,
        notes TEXT,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    )");
}


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_purchase'])) {
        $pdo->beginTransaction();
        try {
            // Insert purchase
            $stmt = $pdo->prepare("INSERT INTO purchases (project_id, vendor_id, purchase_date, description, invoice_number, subtotal, tax_amount, total_amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $subtotal = floatval($_POST['subtotal'] ?? 0);
            $tax = floatval($_POST['tax_amount'] ?? 0);
            $total = $subtotal + $tax;
            $stmt->execute([
                $_POST['project_id'],
                $_POST['vendor_id'] ?: null,
                $_POST['purchase_date'],
                $_POST['description'],
                $_POST['invoice_number'],
                $subtotal,
                $tax,
                $total,
                'draft',
                'Admin' // In real app, use logged-in user
            ]);

            $purchase_id = $pdo->lastInsertId();

            // Insert purchase items
            if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
                $itemStmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");

                foreach ($_POST['item_description'] as $index => $desc) {
                    if (!empty($desc)) {
                        $qty = floatval($_POST['item_quantity'][$index] ?? 1);
                        $price = floatval($_POST['item_price'][$index] ?? 0);
                        $itemTotal = $qty * $price;
                        $itemStmt->execute([$purchase_id, $desc, $qty, $price, $itemTotal]);
                    }
                }
            }

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$purchase_id, 'created', 'Admin', 'Purchase created']);

            $pdo->commit();
            $success_message = "Purchase created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating purchase: " . $e->getMessage();
        }
    }

    if (isset($_POST['update_purchase'])) {
        $pdo->beginTransaction();
        try {
            $purchase_id = $_POST['update_id'];
            
            // Update purchase details
            $stmt = $pdo->prepare("UPDATE purchases SET project_id = ?, vendor_id = ?, purchase_date = ?, description = ?, invoice_number = ?, subtotal = ?, tax_amount = ?, total_amount = ? WHERE id = ?");
            
            $subtotal = floatval($_POST['subtotal'] ?? 0);
            $tax = floatval($_POST['tax_amount'] ?? 0);
            $total = $subtotal + $tax;
            
            $stmt->execute([
                $_POST['project_id'],
                $_POST['vendor_id'] ?: null,
                $_POST['purchase_date'],
                $_POST['description'],
                $_POST['invoice_number'],
                $subtotal,
                $tax,
                $total,
                $purchase_id
            ]);

            // Delete existing items
            $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$purchase_id]);

            // Re-insert items
            if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
                $itemStmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");

                foreach ($_POST['item_description'] as $index => $desc) {
                    if (!empty($desc)) {
                        $qty = floatval($_POST['item_quantity'][$index] ?? 1);
                        $price = floatval($_POST['item_price'][$index] ?? 0);
                        $itemTotal = $qty * $price;
                        $itemStmt->execute([$purchase_id, $desc, $qty, $price, $itemTotal]);
                    }
                }
            }

            // Log action
            $logStmt = $pdo->prepare("INSERT INTO purchase_audit_log (purchase_id, action, performed_by, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$purchase_id, 'updated', 'Admin', 'Purchase updated']);

            $pdo->commit();
            $success_message = "Purchase updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating purchase: " . $e->getMessage();
        }
    }
}

// Note: Delete and approval handlers are now in index.php to prevent headers already sent error


// Fetch purchases with related data
$purchases = $pdo->query("
    SELECT p.*, 
           pr.name as project_name, 
           v.name as vendor_name,
           c.name as client_name,
           (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id) as paid_amount,
           (SELECT COALESCE(SUM(amount), 0) FROM purchase_returns WHERE purchase_id = p.id) as returned_amount
    FROM purchases p
    LEFT JOIN projects pr ON p.project_id = pr.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN clients c ON pr.client_id = c.id
    ORDER BY p.created_at DESC
")->fetchAll();

$projects = $pdo->query("SELECT p.id, p.name, c.name as client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id")->fetchAll();
$vendors = $pdo->query("SELECT id, name FROM vendors")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Project Purchases</h1>
    <p class="text-gray-600 mt-2">Track project purchases, manage approvals, and record payments</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">Operation completed successfully!</span>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Purchase Management</h2>
                </div>
                <!-- Action Buttons: Grid on mobile, Flex on desktop -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <a href="index.php?page=purchase_payments"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors text-center flex items-center justify-center">
                        <i class="fas fa-credit-card mr-2"></i>Payments
                    </a>
                    <a href="index.php?page=reimbursements"
                        class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors text-center flex items-center justify-center">
                        <i class="fas fa-money-bill-wave mr-2"></i>Reimburse
                    </a>
                    <a href="index.php?page=purchase_reports"
                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors text-center flex items-center justify-center">
                        <i class="fas fa-chart-bar mr-2"></i>Reports
                    </a>
                    <button
                        class="bg-primary hover:bg-secondary text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors text-center flex items-center justify-center"
                        onclick="resetForm(); document.getElementById('addForm').classList.remove('hidden')">
                        <i class="fas fa-plus mr-2"></i>Add
                    </button>
                </div>
            </div>
        </div>

        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4" id="purchaseForm">
                <input type="hidden" name="update_id" id="update_id">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project *</label>
                        <select name="project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                    <?php if ($project['client_name']): ?>
                                        (<?php echo htmlspecialchars($project['client_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
                        <select name="vendor_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Vendor (Optional)</option>
                            <option value="new_vendor_action" class="font-bold text-primary">+ Add New Vendor</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo $vendor['id']; ?>">
                                    <?php echo htmlspecialchars($vendor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date *</label>
                        <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number" placeholder="INV-001"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" name="description" placeholder="Purchase description"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Purchase Items</label>
                    <div id="itemsContainer">
                        <div class="item-row grid grid-cols-12 gap-2 mb-2">
                            <div class="col-span-5">
                                <input type="text" name="item_description[]" placeholder="Item description"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" required>
                            </div>
                            <div class="col-span-2">
                                <input type="number" name="item_quantity[]" placeholder="Qty" value="1" step="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm item-qty"
                                    onchange="calculateItemTotal(this)" required>
                            </div>
                            <div class="col-span-2">
                                <input type="number" name="item_price[]" placeholder="Unit Price" step="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm item-price"
                                    onchange="calculateItemTotal(this)" required>
                            </div>
                            <div class="col-span-2">
                                <input type="number" placeholder="Total" step="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100 item-total"
                                    readonly>
                            </div>
                            <div class="col-span-1">
                                <button type="button" onclick="removeItem(this)"
                                    class="w-full px-2 py-2 bg-red-500 text-white rounded-md text-sm hover:bg-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addItem()"
                        class="mt-2 text-primary hover:text-secondary text-sm font-medium">
                        <i class="fas fa-plus mr-1"></i>Add Item
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                        <input type="number" name="subtotal" id="subtotal" step="0.01" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tax Amount</label>
                        <input type="number" name="tax_amount" id="tax_amount" step="0.01" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md" onchange="calculateTotal()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                        <input type="number" id="total_amount" step="0.01" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 font-bold" readonly>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_purchase"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_purchase" id="submitBtn"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Purchase
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Vendor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Invoice #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Paid</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($purchases as $purchase):
                            $returned = (float) ($purchase['returned_amount'] ?? 0);
                            $balance = $purchase['total_amount'] - $purchase['paid_amount'] - $returned;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    P#<?php echo $purchase['id']; ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($purchase['project_name'] ?? 'N/A'); ?>
                                    <?php if ($purchase['client_name']): ?>
                                        <br><span
                                            class="text-xs text-gray-500">(<?php echo htmlspecialchars($purchase['client_name']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($purchase['vendor_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($purchase['invoice_number'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo money($purchase['total_amount']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-green-600">
                                    <?php echo money($purchase['paid_amount']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo money($balance); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch ($purchase['status']) {
                                        case 'approved':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'rejected':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        case 'draft':
                                            echo 'bg-gray-100 text-gray-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                        <?php echo ucfirst($purchase['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewPurchase(<?php echo $purchase['id']; ?>)"
                                        class="text-blue-600 hover:text-blue-900 mr-2">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <?php if ($purchase['status'] == 'draft'): ?>
                                        <button onclick="editPurchase(<?php echo $purchase['id']; ?>)"
                                            class="text-blue-600 hover:text-blue-900 mr-2">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($purchase['status'] == 'draft'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="purchase_id" value="<?php echo $purchase['id']; ?>">
                                            <button type="submit" name="submit_for_approval"
                                                class="text-yellow-600 hover:text-yellow-900 mr-2"
                                                onclick="return confirm('Submit for approval?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($purchase['status'] == 'pending'): ?>
                                        <button onclick="approvePurchase(<?php echo $purchase['id']; ?>)"
                                            class="text-green-600 hover:text-green-900 mr-2">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="rejectPurchase(<?php echo $purchase['id']; ?>)"
                                            class="text-red-600 hover:text-red-900 mr-2">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($purchase['status'] == 'approved' && $balance > 0): ?>
                                        <a href="index.php?page=purchase_payments&purchase_id=<?php echo $purchase['id']; ?>"
                                            class="text-purple-600 hover:text-purple-900 mr-2">
                                            <i class="fas fa-dollar-sign"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($returned < (float) $purchase['total_amount']): ?>
                                        <button type="button"
                                            onclick="openReturnModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => $purchase['id'],
                                                'vendor_name' => $purchase['vendor_name'] ?? '',
                                                'invoice_number' => $purchase['invoice_number'],
                                                'total_amount' => $purchase['total_amount'],
                                                'returned_amount' => $returned,
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="text-orange-600 hover:text-orange-900 mr-2" title="Return to vendor">
                                            <i class="fas fa-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($purchase['status'] == 'draft'): ?>
                                        <a href="?page=purchases&delete=<?php echo $purchase['id']; ?>"
                                            class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Delete this purchase?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Return to Vendor Modal -->
<div id="returnModal" class="hidden fixed z-30 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeReturnModal()"></div>
        </div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md">
            <form method="post" id="returnForm">
                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <div class="font-semibold text-gray-800">Return to Vendor</div>
                    <button type="button" class="text-gray-500" onclick="closeReturnModal()"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-4 space-y-3">
                    <input type="hidden" name="return_purchase_id" id="return-purchase-id">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
                        <input type="text" id="return-vendor-name" readonly
                            class="w-full px-3 py-2 border border-gray-200 bg-gray-50 text-gray-700 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                        <input type="text" id="return-invoice-number" readonly
                            class="w-full px-3 py-2 border border-gray-200 bg-gray-50 text-gray-700 rounded-md">
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm bg-gray-50 border rounded-md px-3 py-2">
                        <div>
                            <div class="text-gray-500">Purchase Amount</div>
                            <div class="font-semibold text-gray-900" id="return-total-amount">0.00</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Already Returned</div>
                            <div class="font-semibold text-gray-900" id="return-already-returned">0.00</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount to Return <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="return_amount" id="return-amount" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="text-xs text-gray-500 mt-1">Maximum returnable: <span id="return-max" class="font-semibold text-gray-700">0.00</span></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Return Date <span class="text-red-500">*</span></label>
                        <input type="date" name="return_date" id="return-date" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <textarea name="return_reason" rows="2" placeholder="Damaged goods, wrong item, over-supply..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                    <p id="return-error" class="hidden text-sm text-red-600"></p>
                </div>
                <div class="px-4 py-3 border-t flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 bg-white border rounded-md" onclick="closeReturnModal()">Cancel</button>
                    <button type="submit" name="add_purchase_return"
                        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-md">
                        <i class="fas fa-rotate-left mr-2"></i>Record Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let returnMaxAmount = 0;

    function openReturnModal(purchase) {
        const total = Number(purchase.total_amount) || 0;
        const alreadyReturned = Number(purchase.returned_amount) || 0;
        returnMaxAmount = Math.max(0, total - alreadyReturned);

        document.getElementById('return-purchase-id').value = purchase.id;
        document.getElementById('return-vendor-name').value = purchase.vendor_name || 'No vendor on record';
        document.getElementById('return-invoice-number').value = purchase.invoice_number || 'No invoice number';
        document.getElementById('return-total-amount').textContent = total.toFixed(2);
        document.getElementById('return-already-returned').textContent = alreadyReturned.toFixed(2);
        document.getElementById('return-max').textContent = returnMaxAmount.toFixed(2);

        const amountInput = document.getElementById('return-amount');
        amountInput.value = '';
        amountInput.max = returnMaxAmount.toFixed(2);
        document.getElementById('return-error').classList.add('hidden');

        document.getElementById('returnModal').classList.remove('hidden');
        amountInput.focus();
    }

    function closeReturnModal() {
        document.getElementById('returnModal').classList.add('hidden');
    }

    // The server re-checks this; the client-side copy just avoids a pointless round trip.
    document.getElementById('returnForm').addEventListener('submit', function (event) {
        const amount = Number(document.getElementById('return-amount').value) || 0;
        const error = document.getElementById('return-error');
        if (amount <= 0) {
            event.preventDefault();
            error.textContent = 'Enter a return amount greater than 0.';
            error.classList.remove('hidden');
            return;
        }
        if (amount > returnMaxAmount + 0.001) {
            event.preventDefault();
            error.textContent = 'Return amount exceeds the returnable balance of ' + returnMaxAmount.toFixed(2) + '.';
            error.classList.remove('hidden');
        }
    });
</script>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" id="approve-id" name="purchase_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Approve Purchase</h3>
                    <p>Are you sure you want to approve this purchase?</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="approve_purchase"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Approve</button>
                    <button type="button" onclick="closeApproveModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" id="reject-id" name="purchase_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Reject Purchase</h3>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason</label>
                    <textarea name="rejection_reason" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="reject_purchase"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Reject</button>
                    <button type="button" onclick="closeRejectModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Purchase Modal -->
<div id="viewModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeViewModal()"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Purchase Details</h3>
                <div id="viewContent"></div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="closeViewModal()"
                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function addItem() {
        const container = document.getElementById('itemsContainer');
        const newRow = container.firstElementChild.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value = input.classList.contains('item-qty') ? '1' : '');
        container.appendChild(newRow);
    }

    function removeItem(btn) {
        const container = document.getElementById('itemsContainer');
        if (container.children.length > 1) {
            btn.closest('.item-row').remove();
            calculateTotal();
        }
    }

    function calculateItemTotal(input) {
        const row = input.closest('.item-row');
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const total = qty * price;
        row.querySelector('.item-total').value = total.toFixed(2);
        calculateTotal();
    }

    function calculateTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-total').forEach(input => {
            subtotal += parseFloat(input.value) || 0;
        });
        document.getElementById('subtotal').value = subtotal.toFixed(2);
        const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
        document.getElementById('total_amount').value = (subtotal + tax).toFixed(2);
    }

    function approvePurchase(id) {
        document.getElementById('approve-id').value = id;
        document.getElementById('approveModal').classList.remove('hidden');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }

    function rejectPurchase(id) {
        document.getElementById('reject-id').value = id;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function viewPurchase(id) {
        fetch('ajax/purchase_view.php?id=' + id)
            .then(response => response.text())
            .then(html => {
                document.getElementById('viewContent').innerHTML = html;
                document.getElementById('viewModal').classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('viewContent').innerHTML = '<p class="text-red-500">Error loading purchase details</p>';
                document.getElementById('viewModal').classList.remove('hidden');
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    function editPurchase(id) {
        fetch(`ajax/get_purchase.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                // Populate form fields
                document.querySelector('select[name="project_id"]').value = data.project_id;
                document.querySelector('select[name="vendor_id"]').value = data.vendor_id || '';
                document.querySelector('input[name="purchase_date"]').value = data.purchase_date;
                document.querySelector('input[name="invoice_number"]').value = data.invoice_number || '';
                document.querySelector('input[name="description"]').value = data.description || '';
                
                // Set update ID
                document.getElementById('update_id').value = data.id;

                // Clear existing items
                const container = document.getElementById('itemsContainer');
                container.innerHTML = '';

                // Add items
                data.items.forEach(item => {
                    const newRow = document.createElement('div');
                    newRow.className = 'item-row grid grid-cols-12 gap-2 mb-2';
                    newRow.innerHTML = `
                        <div class="col-span-5">
                            <input type="text" name="item_description[]" placeholder="Item description" value="${item.description}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" required>
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_quantity[]" placeholder="Qty" value="${item.quantity}" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm item-qty"
                                onchange="calculateItemTotal(this)" required>
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_price[]" placeholder="Unit Price" value="${item.unit_price}" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm item-price"
                                onchange="calculateItemTotal(this)" required>
                        </div>
                        <div class="col-span-2">
                            <input type="number" placeholder="Total" value="${item.total}" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100 item-total"
                                readonly>
                        </div>
                        <div class="col-span-1">
                            <button type="button" onclick="removeItem(this)"
                                class="w-full px-2 py-2 bg-red-500 text-white rounded-md text-sm hover:bg-red-600">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    container.appendChild(newRow);
                });

                // Calculate totals
                calculateTotal();

                // Change submit button
                const btn = document.getElementById('submitBtn');
                btn.name = 'update_purchase';
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Purchase';

                // Show form
                document.getElementById('addForm').classList.remove('hidden');
                document.getElementById('addForm').scrollIntoView({ behavior: 'smooth' });
            })
            .catch(error => console.error('Error:', error));
    }

    function resetForm() {
        document.getElementById('purchaseForm').reset();
        document.getElementById('update_id').value = '';
        document.querySelector('input[name="purchase_date"]').value = new Date().toISOString().split('T')[0];
        
        // Reset items to single empty row
        const container = document.getElementById('itemsContainer');
        container.innerHTML = `
            <div class="item-row grid grid-cols-12 gap-2 mb-2">
                <div class="col-span-5">
                    <input type="text" name="item_description[]" placeholder="Item description"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" required>
                </div>
                <div class="col-span-2">
                    <input type="number" name="item_quantity[]" placeholder="Qty" value="1" step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm item-qty"
                        onchange="calculateItemTotal(this)" required>
                </div>
                <div class="col-span-2">
                    <input type="number" name="item_price[]" placeholder="Unit Price" step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm item-price"
                        onchange="calculateItemTotal(this)" required>
                </div>
                <div class="col-span-2">
                    <input type="number" placeholder="Total" step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100 item-total"
                        readonly>
                </div>
                <div class="col-span-1">
                    <button type="button" onclick="removeItem(this)"
                        class="w-full px-2 py-2 bg-red-500 text-white rounded-md text-sm hover:bg-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        calculateTotal();

        // Reset submit button
        const btn = document.getElementById('submitBtn');
        btn.name = 'add_purchase';
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Purchase';
    }
</script>

<!-- Add Vendor Modal -->
<div id="addVendorModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeAddVendorModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="ajaxVendorForm" method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Vendor</h3>
                        <button type="button" onclick="closeAddVendorModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="ajax-vendor-error" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm"></div>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="address" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Save Vendor
                    </button>
                    <button type="button" onclick="closeAddVendorModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function initVendorDropdowns() {
        const vendorSelects = document.querySelectorAll('select[name="vendor_id"]');
        vendorSelects.forEach(select => {
            select.addEventListener('change', function() {
                if (this.value === 'new_vendor_action') {
                    this.value = ''; // Reset selection
                    openAddVendorModal();
                }
            });
        });
    }

    function openAddVendorModal() {
        document.getElementById('addVendorModal').classList.remove('hidden');
        document.getElementById('ajaxVendorForm').reset();
        document.getElementById('ajax-vendor-error').classList.add('hidden');
    }

    function closeAddVendorModal() {
        document.getElementById('addVendorModal').classList.add('hidden');
    }

    document.getElementById('ajaxVendorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        submitBtn.disabled = true;

        fetch('ajax/add_vendor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const vendorSelects = document.querySelectorAll('select[name="vendor_id"]');
                vendorSelects.forEach(select => {
                    const option = document.createElement('option');
                    option.value = result.vendor.id;
                    option.textContent = result.vendor.name;
                    
                    const addOption = select.querySelector('option[value="new_vendor_action"]');
                    if (addOption) {
                        select.insertBefore(option, addOption.nextSibling);
                    } else {
                        select.appendChild(option);
                    }
                    
                    // Select the new vendor
                    select.value = result.vendor.id;
                });

                closeAddVendorModal();
            } else {
                const errorDiv = document.getElementById('ajax-vendor-error');
                errorDiv.textContent = result.message || 'Error occurred';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('ajax-vendor-error');
            errorDiv.textContent = 'Network or server error occurred';
            errorDiv.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    // Initialize dropdows
    document.addEventListener('DOMContentLoaded', initVendorDropdowns);
</script>