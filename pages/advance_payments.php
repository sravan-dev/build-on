<?php
/**
 * Advance Payment Module
 * Manage employee advance payments
 */

include_once 'includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_advance'])) {
        $stmt = $pdo->prepare("
            INSERT INTO advance_payments (
                employee_id, payment_date, amount, reason, approved_by
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['employee_id'],
            $_POST['payment_date'],
            $_POST['amount'],
            $_POST['reason'],
            $_POST['approved_by']
        ]);

        $success = "Advance payment recorded successfully!";

    } elseif (isset($_POST['update_advance'])) {
        $stmt = $pdo->prepare("
            UPDATE advance_payments SET 
                employee_id=?, payment_date=?, amount=?, reason=?, approved_by=?
            WHERE id=?
        ");

        $stmt->execute([
            $_POST['employee_id'],
            $_POST['payment_date'],
            $_POST['amount'],
            $_POST['reason'],
            $_POST['approved_by'],
            $_POST['id']
        ]);

        $success = "Advance payment updated successfully!";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM advance_payments WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    $success = "Advance payment deleted successfully!";
}

// Get filter parameters
$filter_employee = $_GET['employee'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

// Fetch employees
$employees = $pdo->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();

// Build query
$query = "
    SELECT 
        ap.*,
        e.name as employee_name
    FROM advance_payments ap
    JOIN employees e ON ap.employee_id = e.id
    WHERE 1=1
";

$params = [];

if ($filter_employee) {
    $query .= " AND ap.employee_id = ?";
    $params[] = $filter_employee;
}

if ($filter_month) {
    // MySQL uses DATE_FORMAT, SQLite uses strftime
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $query .= " AND DATE_FORMAT(ap.payment_date, '%Y-%m') = ?";
    } else {
        $query .= " AND strftime('%Y-%m', ap.payment_date) = ?";
    }
    $params[] = $filter_month;
}

$query .= " ORDER BY ap.payment_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$advances = $stmt->fetchAll();

// Calculate total
$total_advances = array_sum(array_column($advances, 'amount'));

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Advance Payments</h1>
    <p class="text-gray-600 mt-2">Manage employee advance salaries and deductions</p>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="bg-gray-100 p-1 rounded-lg inline-flex mb-6">
    <a href="?page=attendance"
        class="px-4 py-2 rounded text-gray-700 hover:bg-white hover:shadow transition">Attendance</a>
    <a href="?page=advance_payments" class="px-4 py-2 rounded bg-white shadow text-primary font-medium">Advance
        Payments</a>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-hand-holding-usd text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Advances (Selected Month)</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol(); ?>
                    <?php echo number_format($total_advances, 2); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Advance Records</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-2"></i>Add Advance
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="advance_payments">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                    <select name="employee" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $filter_employee == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['name']); ?>
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
                    <a href="?page=advance_payments"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Form -->
        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee *</label>
                        <select name="employee_id" id="edit-employee_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount
                            (<?php echo currency_symbol(); ?>) *</label>
                        <input type="number" name="amount" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                        <input type="text" name="approved_by" placeholder="Manager Name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason / Remarks</label>
                        <input type="text" name="reason" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_advance"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">Save
                        Record</button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($advances as $adv): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('d M Y', strtotime($adv['payment_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($adv['employee_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-red-600"><?php echo currency_symbol(); ?>
                                        <?php echo number_format($adv['amount'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($adv['reason'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($adv['approved_by'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick='editAdvance(<?php echo json_encode($adv, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page=advance_payments&delete=<?php echo $adv['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Delete this record?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($advances)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No advance payment records
                                    found.</td>
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
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Advance Payment</h3>
                    <input type="hidden" id="edit-id" name="id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Employee</label>
                            <select name="employee_id" id="modal-employee_id"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" name="payment_date" id="modal-payment_date"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount</label>
                            <input type="number" name="amount" id="modal-amount" step="0.01"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reason</label>
                            <input type="text" name="reason" id="modal-reason"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Approved By</label>
                            <input type="text" name="approved_by" id="modal-approved_by"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_advance"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Update</button>
                    <button type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        onclick="document.getElementById('editModal').classList.add('hidden')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editAdvance(data) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = data.id;
        document.getElementById('modal-employee_id').value = data.employee_id;
        document.getElementById('modal-payment_date').value = data.payment_date;
        document.getElementById('modal-amount').value = data.amount;
        document.getElementById('modal-reason').value = data.reason;
        document.getElementById('modal-approved_by').value = data.approved_by;
    }
</script>