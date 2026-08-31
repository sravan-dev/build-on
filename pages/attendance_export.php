<?php
include_once 'includes/db.php';

// Get filter parameters
$employee_filter = $_GET['employee'] ?? '';
$month_filter = $_GET['month'] ?? '';
$year_filter = $_GET['year'] ?? '';
$project_filter = $_GET['project'] ?? '';
$format = $_GET['format'] ?? 'excel';

// Build query with filters
$query = "SELECT a.*, e.name as employee_name, p.name as project_name 
          FROM attendance a 
          LEFT JOIN employees e ON a.employee_id = e.id 
          LEFT JOIN projects p ON a.project_id = p.id 
          WHERE 1=1";

$params = [];

if ($employee_filter) {
    $query .= " AND a.employee_id = ?";
    $params[] = $employee_filter;
}

if ($month_filter) {
    $query .= " AND a.month = ?";
    $params[] = $month_filter;
}

if ($year_filter) {
    $query .= " AND a.year = ?";
    $params[] = $year_filter;
}

if ($project_filter) {
    $query .= " AND a.project_id = ?";
    $params[] = $project_filter;
}

$query .= " ORDER BY a.year DESC, a.month DESC, e.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance = $stmt->fetchAll();

// Get company details
$companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
$companyAddress = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
$companyPhone = getenv('COMPANY_PHONE') ?: '+947 30659993';
$companyTollFree = getenv('COMPANY_TOLL_FREE') ?: '77721423';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';
$currencySymbol = getenv('CURRENCY_SYMBOL') ?: 'ريال';

if ($format === 'excel') {
    // Excel Export
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.xls"');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    
    // Company Header
    echo "<tr><td colspan='8' style='text-align:center; font-size:18px; font-weight:bold; background-color:#f0f0f0;'>";
    echo htmlspecialchars($companyName) . "<br>";
    echo "ATTENDANCE REPORT<br>";
    echo "Generated on: " . date('d/m/Y H:i:s');
    echo "</td></tr>";
    
    // Address
    $addressLines = explode("\n", $companyAddress);
    echo "<tr><td colspan='8' style='text-align:center; font-size:12px; background-color:#f8f8f8;'>";
    foreach ($addressLines as $line) {
        if (trim($line)) {
            echo htmlspecialchars(trim($line)) . "<br>";
        }
    }
    echo "Tel: " . htmlspecialchars($companyPhone) . " | " . htmlspecialchars($companyTollFree) . "<br>";
    echo htmlspecialchars($companyWebsite);
    echo "</td></tr>";
    
    // Filter Info
    echo "<tr><td colspan='8' style='background-color:#e8f4fd; font-weight:bold;'>";
    echo "Filter Applied: ";
    $filters = [];
    if ($employee_filter) {
        $emp = $pdo->query("SELECT name FROM employees WHERE id = $employee_filter")->fetch();
        $filters[] = "Employee: " . ($emp['name'] ?? 'Unknown');
    }
    if ($month_filter) {
        $filters[] = "Month: " . date('F', mktime(0, 0, 0, $month_filter, 1));
    }
    if ($year_filter) {
        $filters[] = "Year: " . $year_filter;
    }
    if ($project_filter) {
        $proj = $pdo->query("SELECT name FROM projects WHERE id = $project_filter")->fetch();
        $filters[] = "Project: " . ($proj['name'] ?? 'Unknown');
    }
    echo implode(' | ', $filters ?: ['All Records']);
    echo "</td></tr>";
    
    // Table Headers
    echo "<tr style='background-color:#d0d0d0; font-weight:bold;'>";
    echo "<td>Employee</td>";
    echo "<td>Month</td>";
    echo "<td>Year</td>";
    echo "<td>Project</td>";
    echo "<td>Working Days</td>";
    echo "<td>Overtime Hours</td>";
    echo "<td>Total Earnings</td>";
    echo "<td>Per Day Rate</td>";
    echo "</tr>";
    
    // Data Rows
    $totalEarnings = 0;
    foreach ($attendance as $att) {
        $employee = $pdo->query("SELECT per_day_rate FROM employees WHERE id = {$att['employee_id']}")->fetch();
        $perDayRate = $employee['per_day_rate'] ?? 0;
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($att['employee_name'] ?? 'N/A') . "</td>";
        echo "<td>" . date('F', mktime(0, 0, 0, $att['month'], 1)) . "</td>";
        echo "<td>" . $att['year'] . "</td>";
        echo "<td>" . htmlspecialchars($att['project_name'] ?? 'No Project') . "</td>";
        echo "<td>" . number_format($att['working_days'], 1) . " days</td>";
        echo "<td>" . number_format($att['overtime_hours'], 1) . " hrs</td>";
        echo "<td>" . money($att['total_earnings']) . "</td>";
        echo "<td>" . money($perDayRate) . "</td>";
        echo "</tr>";
        
        $totalEarnings += $att['total_earnings'];
    }
    
    // Total Row
    echo "<tr style='background-color:#f0f0f0; font-weight:bold;'>";
    echo "<td colspan='6'>TOTAL EARNINGS</td>";
    echo "<td>" . money($totalEarnings) . "</td>";
    echo "<td>-</td>";
    echo "</tr>";
    
    echo "</table></body></html>";
    
} else {
    // PDF Export
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Attendance Report</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @media print {
                body { margin: 0; padding: 0; }
                .no-print { display: none !important; }
                @page { margin: 15mm; size: A4; }
            }
        </style>
    </head>
    <body class="bg-gray-50">
        <div class="max-w-6xl mx-auto bg-white shadow-lg">
            <!-- Header -->
            <div class="text-center p-6 border-b">
                <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($companyName); ?></h1>
                <h2 class="text-xl font-semibold text-gray-800 mb-4">ATTENDANCE REPORT</h2>
                <div class="text-sm text-gray-600">
                    <?php
                    $addressLines = explode("\n", $companyAddress);
                    foreach ($addressLines as $line):
                        if (trim($line)): ?>
                            <div><?php echo htmlspecialchars(trim($line)); ?></div>
                        <?php endif;
                    endforeach; ?>
                    <div>Tel: <?php echo htmlspecialchars($companyPhone); ?> | <?php echo htmlspecialchars($companyTollFree); ?></div>
                    <div><?php echo htmlspecialchars($companyWebsite); ?></div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    Generated on: <?php echo date('d/m/Y H:i:s'); ?>
                </div>
            </div>

            <!-- Filter Info -->
            <div class="p-4 bg-blue-50 border-b">
                <h3 class="font-semibold text-gray-800 mb-2">Filter Applied:</h3>
                <div class="text-sm text-gray-700">
                    <?php
                    $filters = [];
                    if ($employee_filter) {
                        $emp = $pdo->query("SELECT name FROM employees WHERE id = $employee_filter")->fetch();
                        $filters[] = "Employee: " . ($emp['name'] ?? 'Unknown');
                    }
                    if ($month_filter) {
                        $filters[] = "Month: " . date('F', mktime(0, 0, 0, $month_filter, 1));
                    }
                    if ($year_filter) {
                        $filters[] = "Year: " . $year_filter;
                    }
    if ($project_filter) {
        $proj = $pdo->query("SELECT name FROM projects WHERE id = $project_filter")->fetch();
        $filters[] = "Project: " . ($proj['name'] ?? 'Unknown');
    }
                    echo implode(' | ', $filters ?: ['All Records']);
                    ?>
                </div>
            </div>

            <!-- Table -->
            <div class="p-6">
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Employee</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Month</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Year</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Project</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Working Days</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Overtime Hours</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">Total Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalEarnings = 0;
                        foreach ($attendance as $att): 
                            $totalEarnings += $att['total_earnings'];
                        ?>
                        <tr>
                            <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($att['employee_name'] ?? 'N/A'); ?></td>
                            <td class="border border-gray-300 px-3 py-2"><?php echo date('F', mktime(0, 0, 0, $att['month'], 1)); ?></td>
                            <td class="border border-gray-300 px-3 py-2"><?php echo $att['year']; ?></td>
                            <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($att['project_name'] ?? 'No Project'); ?></td>
                            <td class="border border-gray-300 px-3 py-2"><?php echo number_format($att['working_days'], 1); ?> days</td>
                            <td class="border border-gray-300 px-3 py-2"><?php echo number_format($att['overtime_hours'], 1); ?> hrs</td>
                            <td class="border border-gray-300 px-3 py-2 font-semibold"><?php echo money($att['total_earnings']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Total Row -->
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="6" class="border border-gray-300 px-3 py-2">TOTAL EARNINGS</td>
                            <td class="border border-gray-300 px-3 py-2"><?php echo money($totalEarnings); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="p-4 border-t text-center text-sm text-gray-500">
                <div>Report generated by <?php echo htmlspecialchars($companyName); ?></div>
                <div>For any queries, contact: <?php echo htmlspecialchars($companyPhone); ?></div>
            </div>
        </div>

        <div class="no-print text-center mt-4">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-print mr-2"></i>Print PDF
            </button>
        </div>
    </body>
    </html>
    <?php
}
?>
