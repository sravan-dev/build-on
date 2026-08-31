<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
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

    try {
        $sql = "SELECT e.*, p.name as project_name 
                FROM expenses e 
                LEFT JOIN projects p ON e.project_id = p.id 
                $where_clause 
                ORDER BY e.date DESC, e.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($export_type === 'excel') {
            // Export to Excel
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="expenses_' . date('Y-m-d') . '.xls"');

            echo "<table border='1'>";
            echo "<tr>";
            echo "<th>Date</th>";
            echo "<th>Project</th>";
            echo "<th>Type</th>";
            echo "<th>Description</th>";
            echo "<th>Amount</th>";
            echo "<th>Remarks</th>";
            echo "</tr>";

            foreach ($expenses as $expense) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($expense['date'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars($expense['project_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars($expense['expense_type'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . number_format($expense['amount'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($expense['remarks'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                echo "</tr>";
            }

            echo "</table>";
            exit;

        } elseif ($export_type === 'pdf') {
            // Export to PDF
            require_once dirname(__DIR__) . '/includes/tcpdf.php';

            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Buildon CRM');
            $pdf->SetAuthor('Buildon CRM');
            $pdf->SetTitle('Expense Report');
            $pdf->SetSubject('Project Expenses');

            $pdf->SetHeaderData('', 0, 'EXPENSE REPORT', 'Generated on ' . date('Y-m-d H:i:s'));
            $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

            $pdf->AddPage();

            // Add content
            $html = '<h2>Expense Report</h2>';
            $html .= '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';

            if ($project_filter || $type_filter || $date_from || $date_to) {
                $html .= '<p><strong>Filters Applied:</strong></p><ul>';
                if ($project_filter) {
                    $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                    $stmt->execute([$project_filter]);
                    $project = $stmt->fetch(PDO::FETCH_ASSOC);
                    $html .= '<li>Project: ' . htmlspecialchars($project['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($type_filter) {
                    $html .= '<li>Type: ' . htmlspecialchars($type_filter, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($date_from) {
                    $html .= '<li>From: ' . htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($date_to) {
                    $html .= '<li>To: ' . htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr style="background-color:#f0f0f0;">';
            $html .= '<th>Date</th><th>Project</th><th>Type</th><th>Description</th><th>Amount</th><th>Remarks</th>';
            $html .= '</tr>';

            $total_amount = 0;
            foreach ($expenses as $expense) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($expense['date'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($expense['project_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($expense['expense_type'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . number_format($expense['amount'], 2) . '</td>';
                $html .= '<td>' . htmlspecialchars($expense['remarks'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
                $total_amount += $expense['amount'];
            }

            $html .= '<tr style="background-color:#f0f0f0; font-weight:bold;">';
            $html .= '<td colspan="4">TOTAL</td>';
            $html .= '<td>' . number_format($total_amount, 2) . '</td>';
            $html .= '<td></td>';
            $html .= '</tr>';
            $html .= '</table>';

            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Output('expenses_' . date('Y-m-d') . '.pdf', 'D');
            exit;
        }

    } catch (Exception $e) {
        $error = 'Export failed: ' . $e->getMessage();
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

// Get summary by project
$project_summary = [];
try {
    $sql = "SELECT p.name as project_name, SUM(e.amount) as total_amount, COUNT(e.id) as expense_count
            FROM expenses e 
            LEFT JOIN projects p ON e.project_id = p.id 
            $where_clause 
            GROUP BY e.project_id, p.name 
            ORDER BY total_amount DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $project_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}

?>

<div class="expense-reports-page">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Expense Reports</h1>
        <p class="text-gray-600 mt-2">Generate and export expense reports</p>

        <?php if (isset($error)): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Filters</h3>
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="expense_reports">

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
                    <a href="?page=expense_reports"
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

    <!-- Export Options -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Options</h3>
            <div class="flex space-x-4">
                <a href="?page=expense_reports&export=excel&project=<?php echo urlencode($project_filter); ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md font-medium transition-colors">
                    <i class="fas fa-file-excel mr-2"></i>Export to Excel
                </a>
                <a href="?page=expense_reports&export=pdf&project=<?php echo urlencode($project_filter); ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium transition-colors">
                    <i class="fas fa-file-pdf mr-2"></i>Export to PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-receipt text-blue-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-blue-900">Total Expenses</h3>
                    <p class="text-2xl font-bold text-blue-800"><?php echo count($expenses); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-dollar-sign text-green-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-green-900">Total Amount</h3>
                    <p class="text-2xl font-bold text-green-800"><?php echo number_format($total_amount, 2); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-project-diagram text-purple-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-purple-900">Projects</h3>
                    <p class="text-2xl font-bold text-purple-800"><?php echo count($project_summary); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Project Summary -->
    <?php if (!empty($project_summary)): ?>
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Expenses by Project</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Project</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expense Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($project_summary as $summary): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($summary['project_name'] ?? 'Unknown Project', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $summary['expense_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo number_format($summary['total_amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Detailed Expenses Table -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Detailed Expenses</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No expenses found</td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>