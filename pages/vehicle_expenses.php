<?php
/**
 * Vehicle Expenses Module
 * Track all types of vehicle-related expenses
 */

include_once 'includes/db.php';

// Auto-add payment_method column if it doesn't exist
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
try {
    if ($driver === 'mysql') {
        $check = $pdo->query("SHOW COLUMNS FROM vehicle_expenses LIKE 'payment_method'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE vehicle_expenses ADD COLUMN payment_method VARCHAR(100) DEFAULT 'company_cash'");
        }
    } else {
        $pdo->exec("ALTER TABLE vehicle_expenses ADD COLUMN payment_method TEXT DEFAULT 'company_cash'");
    }
} catch (Exception $e) {
}

// Include centralized payment methods
include_once 'includes/payment_methods.php';
$payment_methods = get_payment_methods();

// Handle form submissions
$error = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_expense'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicle_expenses (
                    vehicle_id, expense_date, expense_type, amount,
                    vendor_garage, invoice_number, description, odometer_reading, paid_by, payment_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_POST['vehicle_id'],
                $_POST['expense_date'],
                $_POST['expense_type'],
                $_POST['amount'],
                $_POST['vendor_garage'] ?? null,
                $_POST['invoice_number'] ?? null,
                $_POST['description'] ?? null,
                !empty($_POST['odometer_reading']) ? $_POST['odometer_reading'] : null,
                $_POST['paid_by'] ?? null,
                $_POST['payment_method'] ?? 'company_cash'
            ]);
            
            $expense_id = $pdo->lastInsertId();

            // --- GENERAL LEDGER INTEGRATION ---
            include_once 'includes/gl_functions.php';
            
            // 1. Identify Accounts
            $debit_account = $_POST['expense_type']; // Use Type as Account Name (e.g., 'Fuel', 'Repair')
            $credit_account = get_gl_account_for_payment_method($_POST['payment_method'] ?? 'company_cash');
            
            // 2. Prepare Description
            // Get Vehicle Number for reference
            $veh = $pdo->prepare("SELECT vehicle_number FROM vehicles WHERE id = ?");
            $veh->execute([$_POST['vehicle_id']]);
            $veh_num = $veh->fetchColumn() ?: 'Unknown Vehicle';
            
            $narration = "Vehicle Expense: $debit_account for $veh_num";
            if (!empty($_POST['paid_by'])) {
                $narration .= " (Paid by " . $_POST['paid_by'] . ")";
            }

            // 3. Post to GL with reference for deletion tracking
            create_journal_entry(
                $pdo,
                $_POST['expense_date'],
                $_POST['amount'],
                $debit_account,
                $credit_account,
                $narration,
                $_POST['invoice_number'] ?? null,
                $_POST['vendor_garage'] ?? null,
                "VEH-EXP-{$expense_id}" // Reference for deletion
            );
            // ----------------------------------

            $success = "Expense added successfully!";
        } catch (PDOException $e) {
            $error = "Failed to add expense: " . $e->getMessage();
        }
    
    } elseif (isset($_POST['update_expense'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE vehicle_expenses SET 
                    vehicle_id=?, expense_date=?, expense_type=?, amount=?,
                    vendor_garage=?, invoice_number=?, description=?, odometer_reading=?, paid_by=?, payment_method=?
                WHERE id=?
            ");

            $stmt->execute([
                $_POST['vehicle_id'],
                $_POST['expense_date'],
                $_POST['expense_type'],
                $_POST['amount'],
                $_POST['vendor_garage'] ?? null,
                $_POST['invoice_number'] ?? null,
                $_POST['description'] ?? null,
                !empty($_POST['odometer_reading']) ? $_POST['odometer_reading'] : null,
                $_POST['paid_by'] ?? null,
                $_POST['payment_method'] ?? 'company_cash',
                $_POST['id']
            ]);

            $success = "Expense updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update expense: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    try {
        $expense_id = $_GET['delete'];
        
        // Delete associated GL voucher first
        include_once 'includes/functions.php';
        deleteGlVoucher($pdo, "VEH-EXP-{$expense_id}");
        
        // Delete the expense record
        $stmt = $pdo->prepare("DELETE FROM vehicle_expenses WHERE id=?");
        $stmt->execute([$expense_id]);
        $success = "Expense deleted successfully!";
    } catch (PDOException $e) {
        $error = "Failed to delete expense: " . $e->getMessage();
    }
}

// Get filter parameters
$filter_vehicle = $_GET['vehicle'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

// Fetch all vehicles for dropdown
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY vehicle_number")->fetchAll();

// Expense types
$expense_types = [
    'Fuel',
    'Oil Change',
    'Tyres',
    'Battery',
    'Maintenance',
    'Repair',
    'Registration Renewal',
    'Insurance Renewal',
    'Fines',
    'Washing',
    'Service',
    'Other'
];

// Build query with filters
$query = "
    SELECT 
        ve.*,
        v.vehicle_number,
        v.make,
        v.model
    FROM vehicle_expenses ve
    JOIN vehicles v ON ve.vehicle_id = v.id
    WHERE 1=1
";

$params = [];

if ($filter_vehicle) {
    $query .= " AND ve.vehicle_id = ?";
    $params[] = $filter_vehicle;
}

if ($filter_type) {
    $query .= " AND ve.expense_type = ?";
    $params[] = $filter_type;
}

if ($filter_month) {
    // MySQL uses DATE_FORMAT, SQLite uses strftime
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $query .= " AND DATE_FORMAT(ve.expense_date, '%Y-%m') = ?";
    } else {
        $query .= " AND strftime('%Y-%m', ve.expense_date) = ?";
    }
    $params[] = $filter_month;
}

$query .= " ORDER BY ve.expense_date DESC, ve.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Calculate statistics
$total_amount = array_sum(array_column($expenses, 'amount'));
$total_expenses = count($expenses);

// Group by expense type
$expense_by_type = [];
foreach ($expenses as $exp) {
    $type = $exp['expense_type'];
    if (!isset($expense_by_type[$type])) {
        $expense_by_type[$type] = 0;
    }
    $expense_by_type[$type] += $exp['amount'];
}
arsort($expense_by_type);

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">💰 Vehicle Expenses</h1>
    <p class="text-gray-600 mt-2">Track all vehicle-related expenses and costs</p>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (isset($error) && $error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Navigation Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="flex -mb-px whitespace-nowrap">
            <a href="?page=vehicles"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-car mr-2"></i>Vehicle Master
            </a>
            <a href="?page=vehicle_daily_logs"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-road mr-2"></i>Daily Logs
            </a>
            <a href="?page=vehicle_expenses" class="border-b-2 border-primary text-primary px-6 py-3 font-medium">
                <i class="fas fa-receipt mr-2"></i>Expenses
            </a>
            <a href="?page=vehicle_fuel"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-gas-pump mr-2"></i>Fuel Records
            </a>
            <a href="?page=vehicle_maintenance"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-tools mr-2"></i>Maintenance
            </a>
            <a href="?page=vehicle_reports"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Reports
            </a>
        </nav>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-money-bill-wave text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Expenses</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol(); ?>
                    <?php echo number_format($total_amount, 2); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-receipt text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Records</p>
                <p class="text-2xl font-bold"><?php echo $total_expenses; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-chart-line text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Average Expense</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol(); ?>
                    <?php echo $total_expenses > 0 ? number_format($total_amount / $total_expenses, 2) : '0'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-tags text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Expense Types</p>
                <p class="text-2xl font-bold"><?php echo count($expense_by_type); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Expense Breakdown -->
<?php if (!empty($expense_by_type)): ?>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Expense Breakdown by Type</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($expense_by_type as $type => $amount): ?>
                <div class="border rounded-lg p-4">
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($type); ?></p>
                    <p class="text-lg font-bold text-primary"><?php echo currency_symbol(); ?>
                        <?php echo number_format($amount, 2); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content -->
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Expense Records</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add Expense
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="page" value="vehicle_expenses">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle</label>
                    <select name="vehicle" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Vehicles</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $filter_vehicle == $v['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expense Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Types</option>
                        <?php foreach ($expense_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $filter_type == $type ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
                <div class="flex items-end">
                    <a href="?page=vehicle_expenses"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Expense Form -->
        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle <span
                                class="text-red-500">*</span></label>
                        <select name="vehicle_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?php echo $v['id']; ?>">
                                    <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expense Type <span
                                class="text-red-500">*</span></label>
                        <select name="expense_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                            <option value="">Select Type</option>
                            <?php foreach ($expense_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount
                            (<?php echo currency_symbol(); ?>) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                        <input type="text" name="paid_by" placeholder="Name of person who paid"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span
                                class="text-red-500">*</span></label>
                        <select name="payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                            <?php foreach ($payment_methods as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vendor / Garage</label>
                        <input type="text" name="vendor_garage"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Odometer Reading (KM)</label>
                        <input type="number" name="odometer_reading" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_expense"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Expense
                    </button>
                </div>
            </form>
        </div>

        <!-- Expenses Table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">KM</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($expenses as $expense): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d M Y', strtotime($expense['expense_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($expense['vehicle_number']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($expense['make'] . ' ' . $expense['model']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($expense['expense_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-red-600"><?php echo currency_symbol(); ?>
                                        <?php echo number_format($expense['amount'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($expense['vendor_garage'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($expense['invoice_number'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                            // Check if paid_by is set and not zero/empty
                                            $paid_by = $expense['paid_by'] ?? '';
                                            if (!empty($paid_by) && $paid_by !== '0.00' && $paid_by !== '0') {
                                                echo htmlspecialchars($paid_by);
                                            } elseif (($expense['payment_method'] ?? '') === 'rahees_cash_card') {
                                                echo 'Rahees';
                                            } elseif (($expense['payment_method'] ?? '') === 'salman_cash_card') {
                                                echo 'Salman';
                                            } else {
                                                echo '-';
                                            }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $expense['odometer_reading'] ? number_format($expense['odometer_reading'], 0) : 'N/A'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick='editExpense(<?php echo json_encode($expense, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page=vehicle_expenses&delete=<?php echo $expense['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this expense?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-receipt text-4xl mb-2"></i>
                                    <p>No expenses recorded yet. Add your first expense to start tracking.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Expense</h3>
                    <input type="hidden" id="edit-id" name="id">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle</label>
                            <select name="vehicle_id" id="edit-vehicle_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?php echo $v['id']; ?>">
                                        <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="expense_date" id="edit-expense_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expense Type</label>
                            <select name="expense_type" id="edit-expense_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php foreach ($expense_types as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount
                                (<?php echo currency_symbol(); ?>)</label>
                            <input type="number" name="amount" id="edit-amount" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                            <input type="text" name="paid_by" id="edit-paid_by"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" id="edit-payment_method"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php foreach ($payment_methods as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor / Garage</label>
                            <input type="text" name="vendor_garage" id="edit-vendor_garage"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                            <input type="text" name="invoice_number" id="edit-invoice_number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Odometer Reading (KM)</label>
                            <input type="number" name="odometer_reading" id="edit-odometer_reading" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="edit-description" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_expense"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Update Expense
                    </button>
                    <button type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editExpense(expense) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = expense.id;
        document.getElementById('edit-vehicle_id').value = expense.vehicle_id;
        document.getElementById('edit-expense_date').value = expense.expense_date;
        document.getElementById('edit-expense_type').value = expense.expense_type;
        document.getElementById('edit-amount').value = expense.amount;
        document.getElementById('edit-paid_by').value = expense.paid_by || '';
        document.getElementById('edit-payment_method').value = expense.payment_method || 'company_cash';
        document.getElementById('edit-vendor_garage').value = expense.vendor_garage || '';
        document.getElementById('edit-invoice_number').value = expense.invoice_number || '';
        document.getElementById('edit-odometer_reading').value = expense.odometer_reading || '';
        document.getElementById('edit-description').value = expense.description || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>