<?php

include_once 'includes/db.php';
include_once 'includes/payment_methods.php';

// Create table if doesn't exist (MySQL syntax)
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'mysql') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_account VARCHAR(100),
        cheque_number VARCHAR(50),
        bank_name VARCHAR(100),
        paid_by VARCHAR(100),
        employee_id INT,
        is_reimbursable TINYINT DEFAULT 0,
        reimbursement_status VARCHAR(50) DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reimbursements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_payment_id INT NOT NULL,
        employee_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        request_date DATE NOT NULL,
        approval_date DATE,
        payment_date DATE,
        status VARCHAR(50) DEFAULT 'pending',
        approved_by VARCHAR(100),
        payment_method VARCHAR(50),
        rejection_reason TEXT,
        notes TEXT,
        FOREIGN KEY(purchase_payment_id) REFERENCES purchase_payments(id) ON DELETE CASCADE,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    // SQLite syntax
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        payment_date TEXT NOT NULL,
        amount REAL NOT NULL,
        payment_method TEXT NOT NULL,
        payment_account TEXT,
        cheque_number TEXT,
        bank_name TEXT,
        paid_by TEXT,
        employee_id INTEGER,
        is_reimbursable INTEGER DEFAULT 0,
        reimbursement_status TEXT DEFAULT 'pending',
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reimbursements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_payment_id INTEGER NOT NULL,
        employee_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        request_date TEXT NOT NULL,
        approval_date TEXT,
        payment_date TEXT,
        status TEXT DEFAULT 'pending',
        approved_by TEXT,
        payment_method TEXT,
        rejection_reason TEXT,
        notes TEXT,
        FOREIGN KEY(purchase_payment_id) REFERENCES purchase_payments(id) ON DELETE CASCADE,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    )");
}


// Minimum amount requiring attachment
$ATTACHMENT_THRESHOLD = 500;

// Note: Payment addition handler is now in index.php to prevent headers already sent error


// Get filter parameters
$purchase_filter = $_GET['purchase_id'] ?? '';

// Fetch payments
$query = "
    SELECT pp.*, 
           p.id as purchase_ref,
           p.invoice_number,
           p.description as purchase_description,
           pr.name as project_name,
           v.name as vendor_name,
           e.name as employee_name,
           (SELECT GROUP_CONCAT(description, ' (Qty: ' || quantity || ')') FROM purchase_items WHERE purchase_id = p.id) as items_list
    FROM purchase_payments pp
    LEFT JOIN purchases p ON pp.purchase_id = p.id
    LEFT JOIN projects pr ON p.project_id = pr.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN employees e ON pp.employee_id = e.id
    WHERE 1=1
";

if ($purchase_filter) {
    $query .= " AND pp.purchase_id = " . intval($purchase_filter);
}

$query .= " ORDER BY pp.payment_date DESC";

$payments = $pdo->query($query)->fetchAll();

// Fetch purchases for dropdown (approved, pending, and draft)
$purchases = $pdo->query("
    SELECT p.id, p.invoice_number, p.description, p.status, p.total_amount, pr.name as project_name,
           (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id) as paid_amount,
           (SELECT GROUP_CONCAT(description, ' (Qty: ' || quantity || ')') FROM purchase_items WHERE purchase_id = p.id) as items_list
    FROM purchases p
    LEFT JOIN projects pr ON p.project_id = pr.id
    WHERE p.status IN ('approved', 'pending', 'draft')
    ORDER BY p.id DESC
")->fetchAll();

$employees = $pdo->query("SELECT id, name FROM employees")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Purchase Payments</h1>
    <p class="text-gray-600 mt-2">Record and manage purchase payments with multiple payment methods</p>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
    <span class="block sm:inline">Payment recorded successfully!</span>
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
                    <h2 class="text-xl font-semibold text-gray-900">Payment Records</h2>
                </div>
                <!-- Action Buttons: Stack on mobile -->
                <div class="flex flex-col sm:flex-row gap-2">
                    <a href="index.php?page=purchases" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors text-center flex items-center justify-center">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Purchases
                    </a>
                    <button class="bg-primary hover:bg-secondary text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors text-center flex items-center justify-center" 
                            onclick="document.getElementById('addForm').classList.toggle('hidden')">
                        <i class="fas fa-plus mr-2"></i>Record Payment
                    </button>
                </div>
            </div>
        </div>

        <div id="addForm" class="<?php echo $purchase_filter ? '' : 'hidden'; ?> p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase *</label>
                        <select name="purchase_id" id="purchase_select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required onchange="updateBalance()">
                            <option value="">Select Purchase</option>
                            <?php foreach ($purchases as $purchase): 
                                $balance = $purchase['total_amount'] - $purchase['paid_amount'];
                                if ($balance > 0):
                            ?>
                            <option value="<?php echo $purchase['id']; ?>" 
                                    data-total="<?php echo $purchase['total_amount']; ?>"
                                    data-paid="<?php echo $purchase['paid_amount']; ?>"
                                    data-balance="<?php echo $balance; ?>"
                                    <?php echo ($purchase_filter && $purchase['id'] == $purchase_filter) ? 'selected' : ''; ?>>
                                P#<?php echo $purchase['id']; ?> - <?php echo htmlspecialchars($purchase['project_name']); ?>
                                <?php if ($purchase['description']): ?>
                                    - <?php echo htmlspecialchars($purchase['description']); ?>
                                <?php endif; ?>
                                <?php if ($purchase['items_list']): ?>
                                    | Items: <?php echo htmlspecialchars($purchase['items_list']); ?>
                                <?php endif; ?>
                                [<?php echo strtoupper($purchase['status']); ?>] (Balance: <?php echo money($balance); ?>)
                            </option>
                            <?php endif; endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Outstanding: <span id="balance_display" class="font-medium">-</span></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                        <input type="number" step="0.01" name="amount" id="payment_amount" placeholder="0.00" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                        <select name="payment_method" id="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required onchange="togglePersonalFields()">
                            <?php echo payment_method_options(); ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account/Card Details</label>
                        <input type="text" name="payment_account" placeholder="e.g., Account #1234 or Card ending 5678" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div id="cheque_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cheque Number</label>
                        <input type="text" name="cheque_number" placeholder="Enter cheque number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                        <input type="text" name="bank_name" placeholder="Enter bank name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Paid By (Name)</label>
                        <input type="text" name="paid_by" placeholder="Name of person who made the payment" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div id="personal_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Employee (for Reimbursement) *</label>
                        <select name="employee_id" id="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Payment notes or reference" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        Note: Payments over <?php echo money($ATTACHMENT_THRESHOLD); ?> require attachment documentation (to be implemented).
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors" onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_payment" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <a href="index.php?page=purchases&purchase_id=<?php echo $payment['purchase_id']; ?>" class="text-primary hover:underline">
                                    P#<?php echo $payment['purchase_ref']; ?>
                                </a>
                                <?php if ($payment['invoice_number']): ?>
                                    <br><span class="text-xs text-gray-500"><?php echo htmlspecialchars($payment['invoice_number']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium"><?php echo htmlspecialchars($payment['project_name'] ?? 'N/A'); ?></div>
                                <?php if ($payment['vendor_name']): ?>
                                    <div class="text-xs text-gray-500">Vendor: <?php echo htmlspecialchars($payment['vendor_name']); ?></div>
                                <?php endif; ?>
                                <?php if ($payment['purchase_description']): ?>
                                    <div class="text-xs text-gray-600 mt-1">Desc: <?php echo htmlspecialchars($payment['purchase_description']); ?></div>
                                <?php endif; ?>
                                <?php if ($payment['items_list']): ?>
                                    <div class="text-xs text-blue-600 mt-1" title="<?php echo htmlspecialchars($payment['items_list']); ?>">
                                        Items: <?php echo htmlspecialchars(strlen($payment['items_list']) > 50 ? substr($payment['items_list'], 0, 50) . '...' : $payment['items_list']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo money($payment['amount']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex flex-col">
                                    <span><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                                    <?php if ($payment['payment_account']): ?>
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($payment['payment_account']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_method'] === 'company_cheque' && ($payment['cheque_number'] || $payment['bank_name'])): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php if ($payment['cheque_number']): ?>
                                                <div>Cheque #: <?php echo htmlspecialchars($payment['cheque_number']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($payment['bank_name']): ?>
                                                <div>Bank: <?php echo htmlspecialchars($payment['bank_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($payment['employee_name']): ?>
                                    <?php echo htmlspecialchars($payment['employee_name']); ?>
                                <?php elseif ($payment['payment_method'] === 'rahees_cash_card'): ?>
                                    Rahees
                                <?php elseif ($payment['payment_method'] === 'salman_cash_card'): ?>
                                    Salman
                                <?php elseif ($payment['paid_by'] && $payment['paid_by'] !== '0.00'): ?>
                                    <?php echo htmlspecialchars($payment['paid_by']); ?>
                                <?php else: ?>
                                    Company
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if ($payment['is_reimbursable']): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        switch($payment['reimbursement_status']) {
                                            case 'paid': echo 'bg-green-100 text-green-800'; break;
                                            case 'approved': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                        }
                                        ?>">
                                        <?php echo ucfirst($payment['reimbursement_status']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Paid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2">
                                <a href="pages/payment_receipt.php?id=<?php echo $payment['id']; ?>" target="_blank" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
                                <button onclick="editPayment(<?php echo htmlspecialchars(json_encode($payment), ENT_QUOTES, 'UTF-8'); ?>)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deletePayment(<?php echo $payment['id']; ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php if ($payment['is_reimbursable']): ?>
                                <a href="index.php?page=reimbursements&payment_id=<?php echo $payment['id']; ?>" class="text-purple-600 hover:text-purple-900">
                                    <i class="fas fa-money-bill-wave"></i>
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


<!-- Edit Payment Modal -->
<div id="editModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" onclick="closeEditModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full" onclick="event.stopPropagation()">
            <form method="post">
                <input type="hidden" name="edit_payment_id" id="edit_payment_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Edit Payment</h3>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                            <input type="date" name="edit_payment_date" id="edit_payment_date" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                            <input type="number" step="0.01" name="edit_amount" id="edit_amount" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select name="edit_payment_method" id="edit_payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php echo payment_method_options(null, false); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account/Card Details</label>
                            <input type="text" name="edit_payment_account" id="edit_payment_account" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cheque Number</label>
                            <input type="text" name="edit_cheque_number" id="edit_cheque_number" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                            <input type="text" name="edit_bank_name" id="edit_bank_name" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                            <input type="text" name="edit_paid_by" id="edit_paid_by" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Employee (for Reimbursement)</label>
                            <select name="edit_employee_id" id="edit_employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="edit_notes" id="edit_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="update_payment" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">
                        <i class="fas fa-save mr-2"></i>Update Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePersonalFields() {
    const method = document.getElementById('payment_method').value;
    const personalFields = document.getElementById('personal_fields');
    const chequeFields = document.getElementById('cheque_fields');
    const employeeId = document.getElementById('employee_id');
    
    if (method === 'personal') {
        personalFields.classList.remove('hidden');
        chequeFields.classList.add('hidden');
        employeeId.required = false;
    } else if (method === 'company_cheque') {
        personalFields.classList.add('hidden');
        chequeFields.classList.remove('hidden');
        employeeId.required = false;
    } else {
        personalFields.classList.add('hidden');
        chequeFields.classList.add('hidden');
        employeeId.required = false;
    }
}

function updateBalance() {
    const select = document.getElementById('purchase_select');
    const option = select.options[select.selectedIndex];
    const balance = option.getAttribute('data-balance');
    
    if (balance) {
        document.getElementById('balance_display').textContent = '<?php echo currency_symbol(); ?>' + parseFloat(balance).toFixed(2);
        document.getElementById('payment_amount').value = parseFloat(balance).toFixed(2);
    } else {
        document.getElementById('balance_display').textContent = '-';
        document.getElementById('payment_amount').value = '';
    }
}

function editPayment(payment) {
    document.getElementById('edit_payment_id').value = payment.id;
    document.getElementById('edit_payment_date').value = payment.payment_date;
    document.getElementById('edit_amount').value = payment.amount;
    document.getElementById('edit_payment_method').value = payment.payment_method;
    document.getElementById('edit_payment_account').value = payment.payment_account || '';
    document.getElementById('edit_cheque_number').value = payment.cheque_number || '';
    document.getElementById('edit_bank_name').value = payment.bank_name || '';
    document.getElementById('edit_paid_by').value = payment.paid_by || '';
    document.getElementById('edit_employee_id').value = payment.employee_id || '';
    document.getElementById('edit_notes').value = payment.notes || '';
    
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function deletePayment(paymentId) {
    if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
        document.getElementById('delete_payment_id').value = paymentId;
        document.getElementById('deletePaymentForm').submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($purchase_filter): ?>
    updateBalance();
    <?php endif; ?>
});
</script>

<!-- Hidden Delete Form -->
<form id="deletePaymentForm" method="post" style="display:none;">
    <input type="hidden" name="delete_payment" value="1">
    <input type="hidden" name="delete_payment_id" id="delete_payment_id">
</form>
