<?php

include_once 'includes/db.php';
require_once 'includes/excel_import.php';
require_once 'includes/payment_methods.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    file_put_contents('debug_post_log.txt', date('Y-m-d H:i:s') . " - POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
}

if (!isset($success_message) && isset($_GET['success'])) {
    $success_message = 'Payment recorded successfully!';
}
if (!isset($error_message) && !empty($_GET['error'])) {
    $error_message = urldecode((string)$_GET['error']);
}

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $importResult = importEmployeesFromExcel($_FILES['excel_file'], $pdo);

        if ($importResult['success'] > 0) {
            $success_message = "Successfully imported {$importResult['success']} employee(s).";
            if (!empty($importResult['errors'])) {
                $success_message .= " " . count($importResult['errors']) . " row(s) had errors.";
            }
        }

        if (!empty($importResult['errors'])) {
            $error_message = "Import errors:\n" . implode("\n", $importResult['errors']);
        }
    } else {
        $error_message = "Please select an Excel file to upload.";
    }
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (!empty($_POST['employee_ids']) && is_array($_POST['employee_ids'])) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $deleted_count = 0;

            foreach ($_POST['employee_ids'] as $emp_id) {
                $stmt->execute([$emp_id]);
                $deleted_count++;
            }

            $pdo->commit();
            $success_message = "Successfully deleted $deleted_count employee(s).";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error deleting employees: " . $e->getMessage();
        }
    } else {
        $error_message = "No employees selected for deletion.";
    }
}

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    file_put_contents('debug_exec.log', date('Y-m-d H:i:s') . " - ENTERED PAYMENT BLOCK\n", FILE_APPEND);
    try {
        $pdo->beginTransaction();
        
        file_put_contents('debug_exec.log', date('Y-m-d H:i:s') . " - Processing Payment...\n", FILE_APPEND);

        $employee_id = $_POST['payment_employee_id'];
        $payment_date = $_POST['payment_date'];
        $advance_amount = floatval($_POST['advance_amount'] ?? 0);
        $salary_amount = floatval($_POST['salary_amount'] ?? 0);
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';
        
        // 1. Handle Advance Payment
        if ($advance_amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO advance_payments (employee_id, payment_date, amount, reason, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $payment_date, $advance_amount, $notes ?: 'Advance Payment', $payment_method]);
            $adv_pay_id = $pdo->lastInsertId();

            // Update employees.advances (Assuming advance given increases the outstanding advances)
            $upd = $pdo->prepare("UPDATE employees SET advances = COALESCE(advances, 0) + ? WHERE id = ?");
            $upd->execute([$advance_amount, $employee_id]);

            // Create GL Voucher for Advance
            addAdvancePaymentVoucher($pdo, $adv_pay_id);
        }
        
        // 2. Handle Salary Payment
        if ($salary_amount > 0) {
            file_put_contents('debug_exec.log', date('Y-m-d H:i:s') . " - Inserting Salary Amount: $salary_amount\n", FILE_APPEND);
            $stmt = $pdo->prepare("INSERT INTO salary_payments (employee_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $payment_date, $salary_amount, $payment_method, $notes]);
            $sal_pay_id = $pdo->lastInsertId();
            file_put_contents('debug_exec.log', date('Y-m-d H:i:s') . " - Salary ID: $sal_pay_id\n", FILE_APPEND);

            // Create GL Voucher for Salary
            addSalaryPaymentVoucher($pdo, $sal_pay_id);
        }
        
        $pdo->commit();
        $success_message = "Payment recorded successfully!";
        // Redirect to clear POST
        echo "<script>window.location.href = 'index.php?page=payroll';</script>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error recording payment: " . $e->getMessage();
        file_put_contents('payroll_error.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add'])) {
            // Determine the employee name
            $employee_name = '';
            if (!empty($_POST['user_id'])) {
                // Get name from selected user
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                $user = $stmt->fetch();
                $employee_name = $user ? $user['username'] : '';
            } else {
                // Use custom name
                $employee_name = $_POST['name'];
            }

            $stmt = $pdo->prepare("INSERT INTO employees (name, employee_id, qatar_id, qatar_id_expiry, passport_number, passport_expiry, visa_expiry, ticket_frequency_years, last_ticket_date, next_ticket_date, email, phone, address, position, department, hire_date, status, emergency_contact, emergency_phone, bank_account, bank_name, payment_method, monthly_salary, room_allowance, food_allowance, telephone_allowance, per_day_rate, per_hour_rate, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $current_time = date('Y-m-d H:i:s');
            $stmt->execute([
                $employee_name,
                $_POST['employee_id'],
                $_POST['qatar_id'],
                $_POST['qatar_id_expiry'],
                $_POST['passport_number'],
                $_POST['passport_expiry'],
                $_POST['visa_expiry'],
                $_POST['ticket_frequency_years'] ?? 2,
                $_POST['last_ticket_date'],
                $_POST['next_ticket_date'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['position'],
                $_POST['department'],
                $_POST['hire_date'],
                $_POST['status'] ?? 'active',
                $_POST['emergency_contact'],
                $_POST['emergency_phone'],
                $_POST['bank_account'],
                $_POST['bank_name'],
                $_POST['payment_method'] ?? 'company_bank',
                $_POST['monthly_salary'],
                $_POST['room_allowance'] ?? 0,
                $_POST['food_allowance'] ?? 0,
                $_POST['telephone_allowance'] ?? 0,
                $_POST['per_day_rate'],
                $_POST['per_hour_rate'],
                $_POST['notes'],
                $current_time,
                $current_time
            ]);

            $new_employee_id = $pdo->lastInsertId();

            // Link user to employee if selected
            if (!empty($_POST['user_id'])) {
                $stmt = $pdo->prepare("UPDATE users SET employee_id = ? WHERE id = ?");
                $stmt->execute([$new_employee_id, $_POST['user_id']]);
            }

            $success_message = "Employee added successfully!";

        } elseif (isset($_POST['update'])) {
            $stmt = $pdo->prepare("UPDATE employees SET name=?, employee_id=?, qatar_id=?, qatar_id_expiry=?, passport_number=?, passport_expiry=?, visa_expiry=?, ticket_frequency_years=?, last_ticket_date=?, next_ticket_date=?, email=?, phone=?, address=?, position=?, department=?, hire_date=?, status=?, emergency_contact=?, emergency_phone=?, bank_account=?, bank_name=?, payment_method=?, monthly_salary=?, room_allowance=?, food_allowance=?, telephone_allowance=?, per_day_rate=?, per_hour_rate=?, notes=?, updated_at=? WHERE id=?");

            $current_time = date('Y-m-d H:i:s');
            $stmt->execute([
                $_POST['name'],
                $_POST['employee_id'],
                $_POST['qatar_id'],
                $_POST['qatar_id_expiry'],
                $_POST['passport_number'],
                $_POST['passport_expiry'],
                $_POST['visa_expiry'],
                $_POST['ticket_frequency_years'] ?? 2,
                $_POST['last_ticket_date'],
                $_POST['next_ticket_date'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['position'],
                $_POST['department'],
                $_POST['hire_date'],
                $_POST['status'] ?? 'active',
                $_POST['emergency_contact'],
                $_POST['emergency_phone'],
                $_POST['bank_account'],
                $_POST['bank_name'],
                $_POST['payment_method'] ?? 'company_bank',
                $_POST['monthly_salary'],
                $_POST['room_allowance'] ?? 0,
                $_POST['food_allowance'] ?? 0,
                $_POST['telephone_allowance'] ?? 0,
                $_POST['per_day_rate'],
                $_POST['per_hour_rate'],
                $_POST['notes'],
                $current_time,
                $_POST['id']
            ]);

            $success_message = "Employee updated successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id=?");
        $stmt->execute([$_GET['delete']]);
        $success_message = "Employee deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting employee: " . $e->getMessage();
    }
}

// Fetch all users for dropdown
$all_users = $pdo->query("SELECT id, username, role, employee_id FROM users ORDER BY username")->fetchAll();

$employees = $pdo->query("SELECT e.*, u.username, u.role as user_role 
                         FROM employees e 
                         LEFT JOIN users u ON e.id = u.employee_id")->fetchAll();

// Fetch attendance summary for the current month
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

$attendance_sql = "SELECT employee_id, SUM(working_hours) as total_hours 
                   FROM daily_attendance 
                   WHERE attendance_date BETWEEN ? AND ? 
                   GROUP BY employee_id";
$stmt = $pdo->prepare($attendance_sql);
$stmt->execute([$current_month_start, $current_month_end]);
$attendance_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Payroll Management</h1>
    <p class="text-gray-600 mt-2">Manage employee information and salary structures</p>
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
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Employee Management</h2>
                <div class="flex space-x-3">
                    <button id="deleteSelectedBtn"
                        class="hidden bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        onclick="deleteSelected()">
                        <i class="fas fa-trash mr-2"></i>Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        onclick="openImportModal()">
                        <i class="fas fa-file-excel mr-2"></i>Import Excel
                    </button>
                    <button
                        class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        onclick="openAddModal()">
                        <i class="fas fa-user-plus mr-2"></i>Add Employee
                    </button>
                </div>
            </div>
        </div>


        <div class="p-6">
            <form id="bulkDeleteForm" method="post">
                <div class="overflow-x-auto">
                    <table id="employeeTable" class="w-full table-auto display">

                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()"
                                        class="rounded border-gray-300 text-primary focus:ring-primary">
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Employee</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    System User</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Position</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment Method</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Monthly Salary</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Hours (Month)</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Labour Cost (Month)</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Advances</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Deductions</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($employees as $employee): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="employee_ids[]" value="<?php echo $employee['id']; ?>"
                                            class="employee-checkbox rounded border-gray-300 text-primary focus:ring-primary"
                                            onchange="updateDeleteButton()">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($employee['name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">ID:
                                            <?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?>
                                        </div>
                                        <?php if (!empty($employee['qatar_id'])): ?>
                                            <div class="text-sm text-gray-500">QID:
                                                <?php echo htmlspecialchars($employee['qatar_id']); ?>
                                            </div>
                                            <?php if (!empty($employee['qatar_id_expiry'])): ?>
                                                <div class="text-sm text-gray-500">
                                                    Expires: <?php echo date('d/m/Y', strtotime($employee['qatar_id_expiry'])); ?>
                                                    <?php
                                                    $expiry_date = strtotime($employee['qatar_id_expiry']);
                                                    $today = time();
                                                    $days_until_expiry = ($expiry_date - $today) / (60 * 60 * 24);
                                                    if ($days_until_expiry < 0): ?>
                                                        <span class="text-red-600 font-semibold">(Expired)</span>
                                                    <?php elseif ($days_until_expiry <= 30): ?>
                                                        <span class="text-yellow-600 font-semibold">(Expires Soon)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($employee['username'])): ?>
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($employee['username']); ?>
                                            </div>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo ucfirst($employee['user_role']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">No Login</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php
                                        echo $employee['status'] == 'active' ? 'bg-green-100 text-green-800' :
                                            ($employee['status'] == 'inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>">
                                            <?php echo ucfirst($employee['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            $method_key = $employee['payment_method'] ?? 'company_bank';
                                            $method_label = get_payment_method_label($method_key);
                                            // Optional: Add icon if available
                                            $icon = get_payment_method_icon($method_key);
                                            echo '<i class="fas ' . $icon . ' text-gray-400 mr-1"></i> ' . htmlspecialchars($method_label);
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo currency_symbol() . number_format((float) ($employee['monthly_salary'] ?? 0), 2); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-bold">
                                            <?php 
                                            $total_hours = $attendance_data[$employee['id']] ?? 0;
                                            echo number_format((float)$total_hours, 2); 
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-green-600 font-bold">
                                            <?php 
                                            $hourly_rate = ($employee['monthly_salary'] > 0) ? ($employee['monthly_salary'] / 26 / 8) : 0;
                                            $labour_cost = $total_hours * $hourly_rate;
                                            echo currency_symbol() . number_format((float)$labour_cost, 2); 
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-red-600">
                                            <?php echo currency_symbol() . number_format((float) ($employee['advances'] ?? 0), 2); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-red-600">
                                            <?php echo currency_symbol() . number_format((float) ($employee['deductions'] ?? 0), 2); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="salary_slip.php?id=<?php echo $employee['id']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </a>
                                        <button type="button" class="text-green-600 hover:text-green-900 mr-3"
                                            onclick="openPaymentModal(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>', '<?php echo $employee['payment_method'] ?? 'company_bank'; ?>')">
                                            <i class="fas fa-money-bill-wave"></i> Payment
                                        </button>
<button type="button" class="text-primary hover:text-secondary mr-3"
    onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee), ENT_QUOTES, 'UTF-8'); ?>)">
    <i class="fas fa-edit"></i> Edit
</button>
                                        <a href="?page=payroll&delete=<?php echo $employee['id']; ?>"
                                            class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Are you sure you want to delete this employee?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <div id="editModal" class="hidden fixed z-[100] inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeEditModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full"
                    onclick="event.stopPropagation()">
                    <form method="post">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Employee</h3>
                                <button type="button" onclick="closeEditModal()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                            <input type="hidden" id="edit-id" name="id">

                            <div class="space-y-6">
                                <!-- Basic Information -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Basic Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <label for="edit-name"
                                                class="block text-sm font-medium text-gray-700 mb-1">Employee Name
                                                *</label>
                                            <input type="text" name="name" id="edit-name"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                                required>
                                        </div>
                                        <div>
                                            <label for="edit-employee_id"
                                                class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                                            <input type="text" name="employee_id" id="edit-employee_id"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-qatar_id"
                                                class="block text-sm font-medium text-gray-700 mb-1">Qatar ID</label>
                                            <input type="text" name="qatar_id" id="edit-qatar_id"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-qatar_id_expiry"
                                                class="block text-sm font-medium text-gray-700 mb-1">Qatar ID Expiry
                                                Date</label>
                                            <input type="date" name="qatar_id_expiry" id="edit-qatar_id_expiry"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-email"
                                                class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" name="email" id="edit-email"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-phone"
                                                class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="tel" name="phone" id="edit-phone"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-position"
                                                class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                                            <input type="text" name="position" id="edit-position"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-department"
                                                class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                            <input type="text" name="department" id="edit-department"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-hire_date"
                                                class="block text-sm font-medium text-gray-700 mb-1">Hire Date</label>
                                            <input type="date" name="hire_date" id="edit-hire_date"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-status"
                                                class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                            <select name="status" id="edit-status"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="terminated">Terminated</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label for="edit-address"
                                            class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <textarea name="address" id="edit-address" rows="2"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                                    </div>
                                </div>

                                <!-- Documents & Tracker -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Documents & Tracker
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="edit-passport_number"
                                                class="block text-sm font-medium text-gray-700 mb-1">Passport
                                                Number</label>
                                            <input type="text" name="passport_number" id="edit-passport_number"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-passport_expiry"
                                                class="block text-sm font-medium text-gray-700 mb-1">Passport
                                                Expiry</label>
                                            <input type="date" name="passport_expiry" id="edit-passport_expiry"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-visa_expiry"
                                                class="block text-sm font-medium text-gray-700 mb-1">Visa Expiry</label>
                                            <input type="date" name="visa_expiry" id="edit-visa_expiry"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-ticket_frequency_years"
                                                class="block text-sm font-medium text-gray-700 mb-1">Ticket Policy
                                                (Years)</label>
                                            <input type="number" name="ticket_frequency_years"
                                                id="edit-ticket_frequency_years"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-last_ticket_date"
                                                class="block text-sm font-medium text-gray-700 mb-1">Last Ticket
                                                Date</label>
                                            <input type="date" name="last_ticket_date" id="edit-last_ticket_date"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-next_ticket_date"
                                                class="block text-sm font-medium text-gray-700 mb-1">Next Ticket
                                                Due</label>
                                            <input type="date" name="next_ticket_date" id="edit-next_ticket_date"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                </div>

                                <!-- Emergency Contact -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Emergency Contact
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="edit-emergency_contact"
                                                class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact
                                                Name</label>
                                            <input type="text" name="emergency_contact" id="edit-emergency_contact"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-emergency_phone"
                                                class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact
                                                Phone</label>
                                            <input type="tel" name="emergency_phone" id="edit-emergency_phone"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                </div>

                                <!-- Banking Information -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Banking Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="edit-bank_account"
                                                class="block text-sm font-medium text-gray-700 mb-1">Bank Account
                                                Number</label>
                                            <input type="text" name="bank_account" id="edit-bank_account"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-bank_name"
                                                class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                                            <input type="text" name="bank_name" id="edit-bank_name"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div class="md:col-span-2">
                                           <label for="edit-payment_method"
                                                class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                            <select name="payment_method" id="edit-payment_method"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                                <?php echo payment_method_options(); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Salary Information -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Salary Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="edit-monthly_salary"
                                                class="block text-sm font-medium text-gray-700 mb-1">Monthly
                                                Salary</label>
                                            <input type="number" step="0.01" name="monthly_salary"
                                                id="edit-monthly_salary"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-room_allowance"
                                                class="block text-sm font-medium text-gray-700 mb-1">Room
                                                Allowance</label>
                                            <input type="number" step="0.01" name="room_allowance"
                                                id="edit-room_allowance"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-food_allowance"
                                                class="block text-sm font-medium text-gray-700 mb-1">Food
                                                Allowance</label>
                                            <input type="number" step="0.01" name="food_allowance"
                                                id="edit-food_allowance"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-telephone_allowance"
                                                class="block text-sm font-medium text-gray-700 mb-1">Phone
                                                Allowance</label>
                                            <input type="number" step="0.01" name="telephone_allowance"
                                                id="edit-telephone_allowance"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-per_day_rate"
                                                class="block text-sm font-medium text-gray-700 mb-1">Per Day
                                                Rate</label>
                                            <input type="number" step="0.01" name="per_day_rate" id="edit-per_day_rate"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label for="edit-per_hour_rate"
                                                class="block text-sm font-medium text-gray-700 mb-1">Per Hour
                                                Rate</label>
                                            <input type="number" step="0.01" name="per_hour_rate"
                                                id="edit-per_hour_rate"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label for="edit-notes"
                                        class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                    <textarea name="notes" id="edit-notes" rows="3"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                                </div>
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

        <!-- Add Employee Modal -->
        <div id="addModal" class="hidden fixed z-[100] inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeAddModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full"
                    onclick="event.stopPropagation()">
                    <form method="post">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Employee</h3>
                                <button type="button" onclick="closeAddModal()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <div class="space-y-6">
                                <!-- Basic Information -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Basic Information
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-user mr-1"></i> Employee Name *
                                            </label>
                                            <select name="user_id" id="user-select-name"
                                                onchange="handleUserSelection()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                                <option value="">-- Select from Users or Enter Custom --</option>
                                                <?php foreach ($all_users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-linked="<?php echo $user['employee_id'] ? 'yes' : 'no'; ?>">
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                        (<?php echo ucfirst($user['role']); ?>)
                                                        <?php if ($user['employee_id']): ?>
                                                            - Already Linked
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="custom">✏️ Enter Custom Name</option>
                                            </select>
                                            <input type="text" name="name" id="custom-name-input"
                                                placeholder="Enter full name" style="display:none;"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent mt-2">
                                            <p class="text-xs text-gray-500 mt-1">Select a user to link or choose custom
                                                to enter manually</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Employee
                                                ID</label>
                                            <input type="text" name="employee_id" placeholder="EMP001"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Qatar ID</label>
                                            <input type="text" name="qatar_id" placeholder="1234567890123456"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Qatar ID Expiry
                                                Date</label>
                                            <input type="date" name="qatar_id_expiry"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" name="email" placeholder="employee@company.com"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="tel" name="phone" placeholder="+974 1234 5678"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                                            <input type="text" name="position" placeholder="Job Title"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                            <input type="text" name="department" placeholder="Department"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Hire
                                                Date</label>
                                            <input type="date" name="hire_date"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                            <select name="status"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="terminated">Terminated</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <textarea name="address" rows="2" placeholder="Full address"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                                    </div>
                                </div>

                                <!-- Documents & Tracker -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Documents & Tracker
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Passport
                                                Number</label>
                                            <input type="text" name="passport_number" placeholder="Passport No"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Passport
                                                Expiry</label>
                                            <input type="date" name="passport_expiry"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Visa
                                                Expiry</label>
                                            <input type="date" name="visa_expiry"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Ticket Policy
                                                (Years)</label>
                                            <input type="number" name="ticket_frequency_years" value="2"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Ticket
                                                Date</label>
                                            <input type="date" name="last_ticket_date"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Next Ticket
                                                Due</label>
                                            <input type="date" name="next_ticket_date"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                </div>

                                <!-- Emergency Contact -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Emergency Contact
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency
                                                Contact Name</label>
                                            <input type="text" name="emergency_contact"
                                                placeholder="Emergency contact name"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Emergency
                                                Contact Phone</label>
                                            <input type="tel" name="emergency_phone" placeholder="+974 1234 5678"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                </div>

                                <!-- Banking Information -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Banking Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account
                                                Number</label>
                                            <input type="text" name="bank_account" placeholder="Account number"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank
                                                Name</label>
                                            <input type="text" name="bank_name" placeholder="Bank name"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                            <select name="payment_method"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                                <?php echo payment_method_options('company_bank'); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Salary Information -->
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3 border-b pb-2">Salary Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Monthly
                                                Salary</label>
                                            <input type="number" step="0.01" name="monthly_salary" placeholder="0.00"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Room
                                                Allowance</label>
                                            <input type="number" step="0.01" name="room_allowance" placeholder="0.00"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Food
                                                Allowance</label>
                                            <input type="number" step="0.01" name="food_allowance" placeholder="0.00"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone
                                                Allowance</label>
                                            <input type="number" step="0.01" name="telephone_allowance"
                                                placeholder="0.00"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Per Day
                                                Rate</label>
                                            <input type="number" step="0.01" name="per_day_rate" placeholder="0.00"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Per Hour
                                                Rate</label>
                                            <input type="number" step="0.01" name="per_hour_rate" placeholder="0.00"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                    <textarea name="notes" rows="3" placeholder="Additional notes about the employee"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" name="add"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-save mr-2"></i>Add Employee
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeAddModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function editEmployee(employee) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = employee.id;
                document.getElementById('edit-name').value = employee.name || '';
                document.getElementById('edit-employee_id').value = employee.employee_id || '';
                document.getElementById('edit-qatar_id').value = employee.qatar_id || '';
                document.getElementById('edit-qatar_id_expiry').value = employee.qatar_id_expiry || '';
                document.getElementById('edit-email').value = employee.email || '';
                document.getElementById('edit-phone').value = employee.phone || '';
                document.getElementById('edit-position').value = employee.position || '';
                document.getElementById('edit-department').value = employee.department || '';
                document.getElementById('edit-hire_date').value = employee.hire_date || '';
                document.getElementById('edit-status').value = employee.status || 'active';
                document.getElementById('edit-address').value = employee.address || '';
                document.getElementById('edit-emergency_contact').value = employee.emergency_contact || '';
                document.getElementById('edit-emergency_phone').value = employee.emergency_phone || '';
                document.getElementById('edit-bank_account').value = employee.bank_account || '';
                document.getElementById('edit-bank_name').value = employee.bank_name || '';
                document.getElementById('edit-payment_method').value = employee.payment_method || 'company_bank';
                document.getElementById('edit-monthly_salary').value = employee.monthly_salary || '';
                document.getElementById('edit-room_allowance').value = employee.room_allowance || '';
                document.getElementById('edit-food_allowance').value = employee.food_allowance || '';
                document.getElementById('edit-telephone_allowance').value = employee.telephone_allowance || '';
                document.getElementById('edit-per_day_rate').value = employee.per_day_rate || '';
                document.getElementById('edit-per_hour_rate').value = employee.per_hour_rate || '';
                document.getElementById('edit-notes').value = employee.notes || '';

                // Tracking Fields
                document.getElementById('edit-passport_number').value = employee.passport_number || '';
                document.getElementById('edit-passport_expiry').value = employee.passport_expiry || '';
                document.getElementById('edit-visa_expiry').value = employee.visa_expiry || '';
                document.getElementById('edit-ticket_frequency_years').value = employee.ticket_frequency_years || '2';
                document.getElementById('edit-last_ticket_date').value = employee.last_ticket_date || '';
                document.getElementById('edit-next_ticket_date').value = employee.next_ticket_date || '';
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            function openAddModal() {
                document.getElementById('addModal').classList.remove('hidden');
            }

            function closeAddModal() {
                document.getElementById('addModal').classList.add('hidden');
                // Reset form
                document.getElementById('addModal').querySelector('form').reset();
                // Reset user select and custom input
                document.getElementById('user-select-name').value = '';
                document.getElementById('custom-name-input').style.display = 'none';
                document.getElementById('custom-name-input').removeAttribute('required');
            }

            function handleUserSelection() {
                const select = document.getElementById('user-select-name');
                const customInput = document.getElementById('custom-name-input');
                const selectedOption = select.options[select.selectedIndex];

                if (select.value === 'custom') {
                    // Show custom input field
                    customInput.style.display = 'block';
                    customInput.setAttribute('required', 'required');
                    customInput.focus();
                    // Clear the select value so user_id is not sent
                    select.value = '';
                } else if (select.value) {
                    // User selected from list
                    const username = selectedOption.getAttribute('data-username');
                    const isLinked = selectedOption.getAttribute('data-linked');

                    // Hide custom input
                    customInput.style.display = 'none';
                    customInput.removeAttribute('required');
                    customInput.value = '';

                    // Show warning if already linked
                    if (isLinked === 'yes') {
                        if (!confirm('This user is already linked to another employee. Do you want to update the link to this new employee?')) {
                            select.value = '';
                            return;
                        }
                    }
                } else {
                    // Nothing selected
                    customInput.style.display = 'none';
                    customInput.removeAttribute('required');
                    customInput.value = '';
                }
            }
        </script>

        <?php
        // Pass employees to JS for auto-opening modal
        echo "<script>const allEmployees = " . json_encode($employees) . ";</script>";
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const urlParams = new URLSearchParams(window.location.search);
                const editId = urlParams.get('edit_employee_id');
                if (editId && typeof allEmployees !== 'undefined') {
                    const emp = allEmployees.find(e => e.id == editId);
                    if (emp) {
                        editEmployee(emp);
                        // Optional: Clean URL
                        // window.history.replaceState({}, document.title, window.location.pathname + '?page=payroll');
                    }
                }
            });

            // Import Excel modal functions
            function openImportModal() {
                document.getElementById('importModal').classList.remove('hidden');
            }

            function closeImportModal() {
                document.getElementById('importModal').classList.add('hidden');
                document.getElementById('importForm').reset();
            }

            // Bulk delete functions
            function toggleSelectAll() {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.employee-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
                updateDeleteButton();
            }

            function updateDeleteButton() {
                const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
                const deleteBtn = document.getElementById('deleteSelectedBtn');
                const countSpan = document.getElementById('selectedCount');

                if (checkboxes.length > 0) {
                    deleteBtn.classList.remove('hidden');
                    countSpan.textContent = checkboxes.length;
                } else {
                    deleteBtn.classList.add('hidden');
                    countSpan.textContent = '0';
                }

                // Update select all checkbox state
                const allCheckboxes = document.querySelectorAll('.employee-checkbox');
                const selectAll = document.getElementById('selectAll');
                selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
            }

            function deleteSelected() {
                const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
                const count = checkboxes.length;

                if (count === 0) {
                    alert('Please select at least one employee to delete.');
                    return;
                }

                if (confirm(`Are you sure you want to delete ${count} employee(s)? This action cannot be undone.`)) {
                    const form = document.getElementById('bulkDeleteForm');
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_delete';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            }
        </script>

        <!-- Import Excel Modal -->
        <div id="importModal" class="hidden fixed z-[100] inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeImportModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                    onclick="event.stopPropagation()">
                    <form id="importForm" method="post" enctype="multipart/form-data">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Import Employees from Excel</h3>
                                <button type="button" onclick="closeImportModal()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                    <p class="text-sm text-blue-800 mb-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <strong>Instructions:</strong>
                                    </p>
                                    <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                                        <li>Download the sample template below</li>
                                        <li>Fill in your employee data</li>
                                        <li>Upload the completed Excel file</li>
                                    </ol>
                                    <div class="mt-3">
                                        <a href="?page=payroll&download_template=1"
                                            class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors">
                                            <i class="fas fa-download mr-2"></i>Download Sample Template
                                        </a>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Excel File (.xlsx, .xls)
                                    </label>
                                    <input type="file" name="excel_file" accept=".xlsx,.xls" required
                                        class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-600 file:text-white hover:file:bg-green-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                    <p class="mt-1 text-xs text-gray-500">Maximum file size: 5MB</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" name="import_excel"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-upload mr-2"></i>Upload & Import
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeImportModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables Initialization -->
<script>
    $(document).ready(function () {
        // Initialize DataTable on employee table
        if ($('#employeeTable').length) {
            $('#employeeTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[1, 'asc']], // Sort by employee name
                columnDefs: [
                    { orderable: false, targets: [0, -1] } // Disable sorting on checkbox and actions columns
                ],
                language: {
                    search: "Search employees:",
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ employees",
                    infoEmpty: "No employees found",
                    infoFiltered: "(filtered from _MAX_ total employees)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Prev"
                    }
                },
                dom: '<"flex flex-col md:flex-row md:items-center md:justify-between mb-4"<"mb-2 md:mb-0"l><""f>>rt<"flex flex-col md:flex-row md:items-center md:justify-between mt-4"<"mb-2 md:mb-0"i><""p>>',
                initComplete: function () {
                    // Style DataTables search input
                    $('.dataTables_filter input').addClass('px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary');
                    // Style select dropdown
                    $('.dataTables_length select').addClass('px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary');
                }
            });
        }
    });
</script>

<style>
    /* DataTables custom styling */
    .dataTables_wrapper .dataTables_filter input {
        margin-left: 0.5rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
    }

    .dataTables_wrapper .dataTables_length select {
        padding: 0.5rem 2rem 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.5rem 0.75rem;
        margin: 0 0.125rem;
        border-radius: 0.375rem;
        border: 1px solid #d1d5db;
        background: #fff;
        cursor: pointer;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #f07d00;
        color: white !important;
        border-color: #f07d00;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

</script>

<!-- Payment Modal Script -->
<script>
    function openPaymentModal(employeeId, employeeName, paymentMethod = '') {
        document.getElementById('payment_employee_id').value = employeeId;
        document.getElementById('paymentMethodName').textContent = employeeName;
        document.getElementById('paymentModal').classList.remove('hidden');
        
        // Select payment method if provided
        const methodSelect = document.querySelector('#paymentModal select[name="payment_method"]');
        if (methodSelect && paymentMethod) {
            methodSelect.value = paymentMethod;
        }
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('payrollPaymentForm');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            const advance = parseFloat(form.querySelector('input[name=\"advance_amount\"]').value || '0') || 0;
            const salary = parseFloat(form.querySelector('input[name=\"salary_amount\"]').value || '0') || 0;
            if (advance <= 0 && salary <= 0) {
                e.preventDefault();
                alert('Please enter Salary or Advance amount greater than 0.');
            }
        });
    });
</script>

<!-- Payment Modal -->
<div id="paymentModal" class="hidden fixed z-[100] inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closePaymentModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" onclick="event.stopPropagation()">
            <form method="post" id="payrollPaymentForm">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Record Payment - <span id="paymentMethodName"></span></h3>
                        <button type="button" onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <input type="hidden" name="payment_employee_id" id="payment_employee_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Date</label>
                            <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Advance Amount</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm"><?php echo currency_symbol(); ?></span>
                                </div>
                                <input type="number" name="advance_amount" step="0.01" min="0" placeholder="0.00"
                                    class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salary Paid</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm"><?php echo currency_symbol(); ?></span>
                                </div>
                                <input type="number" name="salary_amount" step="0.01" min="0" placeholder="0.00"
                                    class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <?php echo payment_method_options(); ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="add_payment"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Submit Payment
                    </button>
                    <button type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        onclick="closePaymentModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
