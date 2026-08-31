<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/payment_methods.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = null;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $expense_type = trim($_POST['expense_type'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $paid_by = trim($_POST['paid_by'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'company_cash');

    if ($project_id <= 0) {
        $error = 'Please select a project';
    } elseif (empty($expense_type)) {
        $error = 'Please select an expense type';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif (empty($description)) {
        $error = 'Please enter a description';
    } elseif (empty($date)) {
        $error = 'Please select a date';
    } else {
        try {
            // Handle file upload
            $attachment_path = null;
            if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
                // Check for upload errors
                if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Upload failed. Error code: ' . $_FILES['attachment']['error'];
                } else {
                    // Validate file size (5MB max)
                    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
                    if ($_FILES['attachment']['size'] > $maxSize) {
                        $error = 'File too large. Maximum size is 5MB.';
                    } else {
                        // Validate file type
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                        $fileType = $_FILES['attachment']['type'];

                        if (in_array($fileType, $allowedTypes)) {
                            // Create uploads directory if it doesn't exist
                            $uploadDir = dirname(__DIR__) . '/uploads/expenses';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0775, true);
                            }

                            // Generate unique filename
                            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                            $filename = 'expense_' . time() . '_' . uniqid() . '.' . $ext;
                            $targetPath = $uploadDir . '/' . $filename;

                            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                                $attachment_path = 'uploads/expenses/' . $filename;
                            } else {
                                $error = 'Failed to upload attachment. Check uploads folder permissions.';
                            }
                        } else {
                            $error = 'Invalid file type. Please upload an image (JPEG, PNG, GIF, WebP) or PDF.';
                        }
                    }
                }
            }

            if (!$error) {
                $pdo->beginTransaction();
                try {
                    // Insert expense
                    $stmt = $pdo->prepare("INSERT INTO expenses (project_id, expense_type, amount, description, remarks, date, paid_by, payment_method, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$project_id, $expense_type, $amount, $description, $remarks, $date, $paid_by, $payment_method, $attachment_path]);
                    $expense_id = (int) $pdo->lastInsertId();

                    // Post to GL against selected payment method and keep reference for delete reversal.
                    include_once dirname(__DIR__) . '/includes/gl_functions.php';
                    $debit_account = $expense_type;
                    $credit_account = get_gl_account_for_payment_method($payment_method);
                    create_journal_entry(
                        $pdo,
                        $date,
                        $amount,
                        $debit_account,
                        $credit_account,
                        $description,
                        null,
                        $paid_by,
                        "EXP-{$expense_id}"
                    );

                    // Keep side effects in sync with selected payment method.
                    addExpenseCardTransaction($pdo, $expense_id);
                    updateProjectExpenseTotal($pdo, $project_id);

                    $pdo->commit();
                    $message = 'Expense added successfully';
                    $_POST = [];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // Remove uploaded file when DB/GL transaction fails.
                    if (!empty($attachment_path)) {
                        $absoluteAttachment = dirname(__DIR__) . '/' . $attachment_path;
                        if (file_exists($absoluteAttachment)) {
                            @unlink($absoluteAttachment);
                        }
                    }

                    throw $e;
                }
            }
        } catch (Exception $e) {
            $error = 'Failed to add expense: ' . $e->getMessage();
        }
    }
}

// Get projects for dropdown
$projects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM projects ORDER BY name");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load projects: ' . $e->getMessage();
}

// Expense types
$expense_types = [
    'Transport' => 'Transport',
    'Food' => 'Food',
    'Labor' => 'Labor',
    'Materials' => 'Materials',
    'Equipment' => 'Equipment',
    'Communication' => 'Communication',
    'Office Supplies' => 'Office Supplies',
    'Utilities' => 'Utilities',
    'Maintenance' => 'Maintenance',
    'Miscellaneous' => 'Miscellaneous'
];

?>

<div class="expenses-page">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Add Expense</h1>
        <p class="text-gray-600 mt-2">Record project-related expenses</p>

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

    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6">
            <form method="post" enctype="multipart/form-data" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project *</label>
                        <select name="project_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expense Type *</label>
                        <select name="expense_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Type</option>
                            <?php foreach ($expense_types as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['expense_type']) && $_POST['expense_type'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0" required
                            value="<?php echo htmlspecialchars($_POST['amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                        <input type="date" name="date" required
                            value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Paid By</label>
                        <input type="text" name="paid_by"
                            value="<?php echo htmlspecialchars($_POST['paid_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Name of person who paid"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                        <select name="payment_method" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <?php foreach ($PAYMENT_METHODS as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == $key) ? 'selected' : ($key === 'company_cash' ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <input type="text" name="description" required
                        value="<?php echo htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Brief description of the expense"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remarks / Notes</label>
                    <textarea name="remarks" rows="3" placeholder="Additional notes or details"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($_POST['remarks'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Receipt / Voucher (Optional)</label>
                    <input type="file" name="attachment" accept="image/*,application/pdf"
                        class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Accepted formats: JPG, PNG, GIF, WebP, PDF. Max size: 5MB</p>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="?page=expense_list"
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                        View Expenses
                    </a>
                    <button type="submit" name="add_expense"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        Add Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
