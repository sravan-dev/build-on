<?php
// Labour Payments Page - Payment Vouchers for Outside Labours
include_once 'includes/db.php';
require_once 'includes/payment_methods.php';

// Auto-create table if it doesn't exist
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
try {
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS labour_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_no VARCHAR(50) NOT NULL,
            labour_id INT NOT NULL,
            project_id INT NOT NULL,
            paid_amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_mode VARCHAR(50) DEFAULT 'cash',
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add total_labour_cost to projects if missing
        $check = $pdo->query("SHOW COLUMNS FROM projects LIKE 'total_labour_cost'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN total_labour_cost DECIMAL(12,2) DEFAULT 0");
        }
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS labour_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            voucher_no TEXT NOT NULL,
            labour_id INTEGER NOT NULL,
            project_id INTEGER NOT NULL,
            paid_amount REAL NOT NULL,
            payment_date TEXT NOT NULL,
            payment_mode TEXT DEFAULT 'cash',
            remarks TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }
} catch (Exception $e) {
    // Table may already exist
}

// Get filter parameters
$filter_labour = isset($_GET['labour_id']) ? intval($_GET['labour_id']) : '';
$filter_project = isset($_GET['project_id']) ? intval($_GET['project_id']) : '';

// Generate next voucher number
function getNextLabourVoucherNo($pdo)
{
    $year = date('Y');
    $prefix = "LP-{$year}-";
    $stmt = $pdo->query("SELECT voucher_no FROM labour_payments WHERE voucher_no LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    if ($last) {
        $num = intval(substr($last['voucher_no'], -4)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// Update project labour cost
function updateProjectLabourCost($pdo, $project_id)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total FROM labour_payments WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $total = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("UPDATE projects SET total_labour_cost = ? WHERE id = ?");
    $stmt->execute([$total, $project_id]);
}

function normalizeLabourPaymentDate($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt instanceof DateTime && $dt->format($format) === $raw) {
            return $dt->format('Y-m-d');
        }
    }
    return null;
}

function resolveProjectsNumericColumnExpr($pdo, $driver, $candidates)
{
    try {
        if ($driver === 'mysql') {
            $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $info = $pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_column($info, 'name');
        }

        foreach ($candidates as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }
    } catch (Exception $e) {
        // Fall back to zero when schema inspection is unavailable.
    }

    return '0';
}

$projectAmountExpr = resolveProjectsNumericColumnExpr($pdo, $driver, ['total_project_amount', 'total_value', 'total_income']);
$projectLabourExpr = resolveProjectsNumericColumnExpr($pdo, $driver, ['total_labour_cost']);
$projectExpensesExpr = resolveProjectsNumericColumnExpr($pdo, $driver, ['total_expenses']);
$projectBalanceSelectSql = "
    SELECT
        COALESCE({$projectAmountExpr}, 0) AS total_project_amount,
        COALESCE({$projectLabourExpr}, 0) AS total_labour_cost,
        COALESCE({$projectExpensesExpr}, 0) AS total_expenses
    FROM projects
    WHERE id = ?
";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_payment'])) {
            $labour_id = intval($_POST['labour_id']);
            $project_id = intval($_POST['project_id']);
            $paid_amount = floatval($_POST['paid_amount']);
            $payment_date = normalizeLabourPaymentDate($_POST['payment_date'] ?? '');
            $payment_mode = $_POST['payment_mode'];
            $remarks = $_POST['remarks'] ?? '';

            if (!$payment_date) {
                throw new Exception('Invalid payment date. Use YYYY-MM-DD.');
            }
            if ($paid_amount <= 0) {
                throw new Exception('Payment amount must be greater than 0.');
            }

            // Get project balance
            $stmt = $pdo->prepare($projectBalanceSelectSql);
            $stmt->execute([$project_id]);
            $project = $stmt->fetch();

            if ($project) {
                $current_balance = $project['total_project_amount'] - $project['total_labour_cost'] - ($project['total_expenses'] ?? 0);

                // Validate - balance cannot go below zero
                if ($paid_amount > $current_balance && $project['total_project_amount'] > 0) {
                        $error_message = "Payment amount ({$paid_amount}) exceeds project remaining balance ({$current_balance}). Payment cannot be processed.";
                    } else {
                        $pdo->beginTransaction();
                        $voucher_no = getNextLabourVoucherNo($pdo);
                        $stmt = $pdo->prepare("INSERT INTO labour_payments (voucher_no, labour_id, project_id, paid_amount, payment_date, payment_mode, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$voucher_no, $labour_id, $project_id, $paid_amount, $payment_date, $payment_mode, $remarks]);
                        $payment_id = $pdo->lastInsertId();

                        // Create GL Voucher
                        addLabourPaymentVoucher($pdo, $payment_id);
                        addLabourPaymentCardTransaction($pdo, $payment_id);

                        // Update project labour cost
                        updateProjectLabourCost($pdo, $project_id);

                        $pdo->commit();

                        $success_message = "Payment voucher {$voucher_no} created successfully!";
                }
            }
        } elseif (isset($_POST['update_payment'])) {
            $id = intval($_POST['id']);
            $paid_amount = floatval($_POST['paid_amount']);
            $payment_date = normalizeLabourPaymentDate($_POST['payment_date'] ?? '');
            $payment_mode = $_POST['payment_mode'];
            $remarks = $_POST['remarks'] ?? '';

            if (!$payment_date) {
                throw new Exception('Invalid payment date. Use YYYY-MM-DD.');
            }
            if ($paid_amount <= 0) {
                throw new Exception('Payment amount must be greater than 0.');
            }

            // Get old payment for balance adjustment
            $stmt = $pdo->prepare("SELECT project_id, paid_amount FROM labour_payments WHERE id = ?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            $project_id = $old['project_id'];
            $difference = $paid_amount - $old['paid_amount'];

            // Get project balance
            $stmt = $pdo->prepare($projectBalanceSelectSql);
            $stmt->execute([$project_id]);
            $project = $stmt->fetch();

            $current_balance = $project['total_project_amount'] - $project['total_labour_cost'] - ($project['total_expenses'] ?? 0);

            // Validate increased amount
            if ($difference > $current_balance && $project['total_project_amount'] > 0) {
                $error_message = "New payment amount exceeds project remaining balance. Update cannot be processed.";
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE labour_payments SET paid_amount = ?, payment_date = ?, payment_mode = ?, remarks = ? WHERE id = ?");
                $stmt->execute([$paid_amount, $payment_date, $payment_mode, $remarks, $id]);

                // Update project labour cost
                updateProjectLabourCost($pdo, $project_id);

                // Rebuild side effects using selected mode only.
                clearLabourPaymentSideEffects($pdo, $id);
                addLabourPaymentVoucher($pdo, $id);
                addLabourPaymentCardTransaction($pdo, $id);

                $pdo->commit();

                $success_message = "Payment updated successfully!";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    try {
        $id = intval($_GET['delete']);
        $pdo->beginTransaction();

        // Get payment info before delete
        $stmt = $pdo->prepare("SELECT project_id FROM labour_payments WHERE id = ?");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();

        if ($payment) {
            clearLabourPaymentSideEffects($pdo, $id);

            $stmt = $pdo->prepare("DELETE FROM labour_payments WHERE id = ?");
            $stmt->execute([$id]);

            // Rollback project balance
            updateProjectLabourCost($pdo, $payment['project_id']);
            $pdo->commit();

            $success_message = "Payment deleted and project balance restored!";
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error deleting payment: " . $e->getMessage();
    }
}

// Fetch data
$labours = $pdo->query("SELECT * FROM outside_labours ORDER BY name")->fetchAll();
$projects = $pdo->query("SELECT * FROM projects ORDER BY name")->fetchAll();

// Build payments query with filters
$where = [];
$params = [];
if ($filter_labour) {
    $where[] = "lp.labour_id = ?";
    $params[] = $filter_labour;
}
if ($filter_project) {
    $where[] = "lp.project_id = ?";
    $params[] = $filter_project;
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT lp.*, ol.name as labour_name, ol.trade, ol.daily_rate, p.name as project_name 
        FROM labour_payments lp 
        LEFT JOIN outside_labours ol ON lp.labour_id = ol.id 
        LEFT JOIN projects p ON lp.project_id = p.id 
        {$whereClause}
        ORDER BY lp.payment_date DESC, lp.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Calculate totals
$total_paid = array_sum(array_column($payments, 'paid_amount'));
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Labour Payments</h1>
    <p class="text-gray-600 mt-2">Manage payment vouchers for outside labours</p>
</div>

<?php if (isset($success_message)): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-4">
        <form method="get" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="page" value="labour_payments">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Labour</label>
                <select name="labour_id" class="px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">All Labours</option>
                    <?php foreach ($labours as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo $filter_labour == $l['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Project</label>
                <select name="project_id" class="px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filter_project == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <a href="?page=labour_payments" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                Clear
            </a>
        </form>
    </div>

    <!-- Payment List -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Payment Vouchers</h2>
                    <p class="text-sm text-gray-500">Total Paid: <span
                            class="font-semibold text-green-600"><?php echo currency_symbol() . number_format($total_paid, 2); ?></span>
                    </p>
                </div>
                <button
                    class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    onclick="openAddModal()">
                    <i class="fas fa-plus mr-2"></i>Add Payment
                </button>
            </div>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voucher No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Labour</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-receipt text-4xl mb-2"></i>
                                    <p>No payment vouchers found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <span
                                            class="font-mono text-sm font-semibold text-primary"><?php echo htmlspecialchars($payment['voucher_no']); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($payment['labour_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($payment['trade']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($payment['project_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm font-semibold text-green-600">
                                            <?php echo currency_symbol() . number_format($payment['paid_amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 capitalize">
                                            <?php echo htmlspecialchars(get_payment_method_label($payment['payment_mode'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <button class="text-blue-600 hover:text-blue-900 mr-2"
                                            onclick="editPayment(<?php echo htmlspecialchars(json_encode($payment), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?page=labour_payments&delete=<?php echo $payment['id']; ?>"
                                            class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Delete this payment? This will restore the project balance.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div id="addModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="fixed inset-0 transition-opacity" onclick="closeAddModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
            onclick="event.stopPropagation()">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Add Payment Voucher</h3>
                        <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Labour *</label>
                                <select name="labour_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                    onchange="updateLabourProject(this)">
                                    <option value="">Select Labour</option>
                                    <?php foreach ($labours as $l): ?>
                                        <option value="<?php echo $l['id']; ?>"
                                            data-project="<?php echo $l['project_id'] ?? ''; ?>">
                                            <?php echo htmlspecialchars($l['name']) . ' - ' . htmlspecialchars($l['trade']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Project *</label>
                                <select name="project_id" id="add_project_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?php echo $p['id']; ?>">
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                                <input type="number" step="0.01" name="paid_amount" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                <input type="date" name="payment_date" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                            <select name="payment_mode" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <?php echo payment_method_options('company_cash', false); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                            <textarea name="remarks" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="add_payment"
                        class="w-full sm:w-auto sm:ml-3 px-4 py-2 bg-primary text-white rounded-md hover:bg-secondary">
                        <i class="fas fa-save mr-2"></i>Save Payment
                    </button>
                    <button type="button" onclick="closeAddModal()"
                        class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="fixed inset-0 transition-opacity" onclick="closeEditModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
            onclick="event.stopPropagation()">
            <form method="post">
                <input type="hidden" name="id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Edit Payment Voucher</h3>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm"><strong>Voucher:</strong> <span id="edit_voucher"
                                    class="font-mono"></span></p>
                            <p class="text-sm"><strong>Labour:</strong> <span id="edit_labour_name"></span></p>
                            <p class="text-sm"><strong>Project:</strong> <span id="edit_project_name"></span></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                                <input type="number" step="0.01" name="paid_amount" id="edit_amount" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                <input type="date" name="payment_date" id="edit_date" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                            <select name="payment_mode" id="edit_mode" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <?php echo payment_method_options(); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                            <textarea name="remarks" id="edit_remarks" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_payment"
                        class="w-full sm:w-auto sm:ml-3 px-4 py-2 bg-primary text-white rounded-md hover:bg-secondary">
                        <i class="fas fa-save mr-2"></i>Update Payment
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
        document.getElementById('addModal').querySelector('form').reset();
    }

    function updateLabourProject(select) {
        const option = select.options[select.selectedIndex];
        const projectId = option.dataset.project;
        if (projectId) {
            document.getElementById('add_project_id').value = projectId;
        }
    }

    function editPayment(payment) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit_id').value = payment.id;
        document.getElementById('edit_voucher').textContent = payment.voucher_no;
        document.getElementById('edit_labour_name').textContent = payment.labour_name;
        document.getElementById('edit_project_name').textContent = payment.project_name;
        document.getElementById('edit_amount').value = payment.paid_amount;
        document.getElementById('edit_date').value = payment.payment_date;
        document.getElementById('edit_mode').value = payment.payment_mode;
        document.getElementById('edit_remarks').value = payment.remarks || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
