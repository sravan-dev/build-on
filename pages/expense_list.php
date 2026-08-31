<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/payment_methods.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expense_id = (int) $_GET['delete'];
    try {
        $pdo->beginTransaction();

        // Get details before deleting
        $stmt = $pdo->prepare("SELECT project_id, attachment_path FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expense) {
            throw new Exception('Expense not found');
        }

        // Reverse GL/card side effects tied to this expense.
        clearExpenseSideEffects($pdo, $expense_id);

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$expense_id]);

        // Keep project running expense total in sync.
        updateProjectExpenseTotal($pdo, (int) ($expense['project_id'] ?? 0));
        $pdo->commit();

        // Delete attachment file if exists
        if ($expense && $expense['attachment_path'] && file_exists(dirname(__DIR__) . '/' . $expense['attachment_path'])) {
            @unlink(dirname(__DIR__) . '/' . $expense['attachment_path']);
        }

        $message = 'Expense deleted successfully';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Failed to delete expense: ' . $e->getMessage();
    }
}

// Get filter parameters
$project_filter = $_GET['project'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($project_filter) {
    $where_conditions[] = "e.project_id = ?";
    $params[] = $project_filter;
}

if ($type_filter) {
    $where_conditions[] = "e.expense_type = ?";
    $params[] = $type_filter;
}

if ($date_from) {
    $where_conditions[] = "e.date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "e.date <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get expenses
$expenses = [];
$total_amount = 0;

try {
    $sql = "SELECT e.*, p.name as project_name 
            FROM expenses e 
            LEFT JOIN projects p ON e.project_id = p.id 
            $where_clause 
            ORDER BY e.date DESC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total
    foreach ($expenses as $expense) {
        $total_amount += $expense['amount'];
    }

} catch (Exception $e) {
    $error = 'Failed to load expenses: ' . $e->getMessage();
}

// Get projects for filter dropdown
$projects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM projects ORDER BY name");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

// Expense types for filter
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

<div class="expense-list-page">
    <div class="mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Expenses</h1>
                <p class="text-gray-600 mt-2">View and manage project expenses</p>
            </div>
            <a href="?page=expenses"
                class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-md font-medium transition-colors text-xs md:text-sm">
                <i class="fas fa-plus mr-1 md:mr-2"></i>Add Expense
            </a>
        </div>

        <?php if (isset($message)): ?>
            <div class="mt-4 rounded-lg border border-green-300 bg-green-50 text-green-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="expense_list">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select name="project"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $project_filter == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expense Type</label>
                    <select name="type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Types</option>
                        <?php foreach ($expense_types as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo $type_filter == $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" name="date_from"
                        value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" name="date_to"
                        value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <div class="md:col-span-4 flex justify-end space-x-2">
                    <a href="?page=expense_list"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                        Clear Filters
                    </a>
                    <button type="submit"
                        class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-blue-900">Total Expenses</h3>
                <p class="text-blue-700"><?php echo count($expenses); ?> expense(s) found</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-blue-900"><?php echo number_format($total_amount, 2); ?></div>
                <div class="text-sm text-blue-700">Total Amount</div>
            </div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Project</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Attachment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Payment Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">No expenses found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($expense['date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($expense['project_name'] ?? 'Unknown Project', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($expense['expense_type'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate"
                                        title="<?php echo htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <?php if ($expense['remarks']): ?>
                                        <div class="text-xs text-gray-500 mt-1 max-w-xs truncate"
                                            title="<?php echo htmlspecialchars($expense['remarks'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($expense['remarks'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo number_format($expense['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($expense['attachment_path']): ?>
                                        <a href="<?php echo htmlspecialchars($expense['attachment_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                            target="_blank" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-paperclip"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">No attachment</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo (strpos($expense['payment_method'] ?? '', 'company') !== false) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo htmlspecialchars(get_payment_method_label($expense['payment_method'] ?? 'company_cash'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="?page=expense_list&delete=<?php echo $expense['id']; ?>"
                                        onclick="return confirm('Are you sure you want to delete this expense?')"
                                        class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
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
