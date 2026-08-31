<?php
/**
 * Detailed Attendance Report Export
 * Generates PDF/Excel for Individual Monthly Report
 */

include_once 'includes/db.php';

$employee_id = $_GET['employee'] ?? '';
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? 'pdf'; // pdf (print view) or excel

if (!$employee_id) {
    die("Employee ID is required for detailed report.");
}

// 1. Get Employee Details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

// 2. Get Daily Attendance
$start_date = "$year-$month-01";
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$end_date = "$year-$month-$days_in_month";

$stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
$stmt->execute([$employee_id, $start_date, $end_date]);
$raw_attendance = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

// 3. Build Data
$report_data = [];
$stats = ['present' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'total_hours' => 0];

for ($d = 1; $d <= $days_in_month; $d++) {
    $current_date = sprintf("%s-%02d-%02d", $year, $month, $d);
    $day_name = date('l', strtotime($current_date));

    $att = $raw_attendance[$current_date] ?? null;

    $status = $att['status'] ?? 'Absent';
    if ($day_name == 'Sunday')
        $status = 'Holiday';

    $report_data[] = [
        'date' => $current_date,
        'day' => $day_name,
        'in_time' => $att['in_time'] ?? '-',
        'out_time' => $att['out_time'] ?? '-',
        'working_hours' => $att['working_hours'] ?? 0,
        'status' => $status,
        'remarks' => $att['remarks'] ?? ''
    ];

    if ($status == 'Present')
        $stats['present']++;
    elseif ($status == 'Absent')
        $stats['absent']++;
    elseif ($status == 'Leave')
        $stats['leave']++;
    elseif ($status == 'Holiday')
        $stats['holiday']++;

    $stats['total_hours'] += ($att['working_hours'] ?? 0);
}

// 4. Get Advance Payments
// Get Advance Payments
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'mysql') {
    $stmt = $pdo->prepare("SELECT * FROM advance_payments WHERE employee_id = ? AND DATE_FORMAT(payment_date, '%Y-%m') = ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM advance_payments WHERE employee_id = ? AND strftime('%Y-%m', payment_date) = ?");
}
$stmt->execute([$employee_id, sprintf("%s-%02d", $year, $month)]);
$advances = $stmt->fetchAll();
$total_advance = array_sum(array_column($advances, 'amount'));

// Company Details
$companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';

// EXCEL EXPORT
if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Attendance_' . $employee['name'] . '_' . "$year-$month" . '.xls"');

    echo "<html><body>";
    echo "<h2>$companyName</h2>";
    echo "<h3>Monthly Attendance Report</h3>";
    echo "<p><strong>Employee:</strong> {$employee['name']} (ID: {$employee['id']})</p>";
    echo "<p><strong>Month:</strong> " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . "</p>";

    // Daily Table
    echo "<table border='1'>";
    echo "<tr><th>Date</th><th>Day</th><th>In Time</th><th>Out Time</th><th>Hours</th><th>Status</th><th>Remarks</th></tr>";

    foreach ($report_data as $row) {
        echo "<tr>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$row['day']}</td>";
        echo "<td>{$row['in_time']}</td>";
        echo "<td>{$row['out_time']}</td>";
        echo "<td>{$row['working_hours']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['remarks']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Summary
    echo "<h4>Summary</h4>";
    echo "<table border='1'>";
    echo "<tr><th>Present</th><th>Absent</th><th>Leave</th><th>Total Hours</th></tr>";
    echo "<tr><td>{$stats['present']}</td><td>{$stats['absent']}</td><td>{$stats['leave']}</td><td>{$stats['total_hours']}</td></tr>";
    echo "</table>";

    // Advances
    echo "<h4>Advance Payments</h4>";
    echo "<table border='1'>";
    echo "<tr><th>Date</th><th>Reason</th><th>Amount</th></tr>";
    foreach ($advances as $adv) {
        echo "<tr><td>{$adv['payment_date']}</td><td>{$adv['reason']}</td><td>{$adv['amount']}</td></tr>";
    }
    echo "<tr><td colspan='2'><strong>Total Deductions</strong></td><td><strong>$total_advance</strong></td></tr>";
    echo "</table>";

    echo "</body></html>";
    exit;
}

// PDF Print View
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance Report - <?php echo htmlspecialchars($employee['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
                background: white;
            }

            .shadow-lg {
                box-shadow: none;
            }
        }
    </style>
</head>

<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white p-8 shadow-lg">
        <div class="text-center border-b pb-4 mb-4">
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($companyName); ?></h1>
            <h2 class="text-xl text-gray-600">Monthly Attendance Report</h2>
            <p class="text-sm mt-2">Generated on <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <div class="flex justify-between mb-6 bg-gray-50 p-4 rounded">
            <div>
                <p class="text-gray-600 text-sm">Employee Name</p>
                <p class="font-bold text-lg"><?php echo htmlspecialchars($employee['name']); ?></p>
                <p class="text-sm text-gray-500">ID: <?php echo $employee['id']; ?></p>
            </div>
            <div class="text-right">
                <p class="text-gray-600 text-sm">Report Month</p>
                <p class="font-bold text-lg"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
            </div>
        </div>

        <!-- Daily Table -->
        <table class="w-full text-sm border-collapse border border-gray-300 mb-6">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border p-2 text-left">Date</th>
                    <th class="border p-2 text-left">Day</th>
                    <th class="border p-2 text-center">In Time</th>
                    <th class="border p-2 text-center">Out Time</th>
                    <th class="border p-2 text-center">Hours</th>
                    <th class="border p-2 text-center">Status</th>
                    <th class="border p-2 text-left">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $row): ?>
                    <tr
                        class="<?php echo $row['status'] == 'Absent' ? 'bg-red-50' : ($row['status'] == 'Holiday' ? 'text-gray-500 bg-gray-50' : ''); ?>">
                        <td class="border p-2"><?php echo date('d M', strtotime($row['date'])); ?></td>
                        <td class="border p-2"><?php echo $row['day']; ?></td>
                        <td class="border p-2 text-center"><?php echo $row['in_time']; ?></td>
                        <td class="border p-2 text-center"><?php echo $row['out_time']; ?></td>
                        <td class="border p-2 text-center font-bold">
                            <?php echo $row['working_hours'] > 0 ? $row['working_hours'] : '-'; ?>
                        </td>
                        <td class="border p-2 text-center"><?php echo $row['status']; ?></td>
                        <td class="border p-2 text-gray-500 truncate max-w-xs">
                            <?php echo htmlspecialchars($row['remarks']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="grid grid-cols-2 gap-8">
            <!-- Summary -->
            <div>
                <h3 class="font-bold text-gray-800 mb-2">Attendance Summary</h3>
                <table class="w-full text-sm border">
                    <tr>
                        <td class="border p-2">Present Days</td>
                        <td class="border p-2 font-bold text-green-600"><?php echo $stats['present']; ?></td>
                    </tr>
                    <tr>
                        <td class="border p-2">Absent Days</td>
                        <td class="border p-2 font-bold text-red-600"><?php echo $stats['absent']; ?></td>
                    </tr>
                    <tr>
                        <td class="border p-2">Leave Days</td>
                        <td class="border p-2 font-bold text-yellow-600"><?php echo $stats['leave']; ?></td>
                    </tr>
                    <tr>
                        <td class="border p-2">Total Working Hours</td>
                        <td class="border p-2 font-bold text-blue-600"><?php echo $stats['total_hours']; ?></td>
                    </tr>
                </table>
            </div>

            <!-- Advances -->
            <div>
                <h3 class="font-bold text-gray-800 mb-2">Advance Payments</h3>
                <?php if (empty($advances)): ?>
                    <p class="text-sm text-gray-500 border p-2">No advances taken.</p>
                <?php else: ?>
                    <table class="w-full text-sm border">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border p-1">Date</th>
                                <th class="border p-1">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($advances as $adv): ?>
                                <tr>
                                    <td class="border p-1"><?php echo date('d/m', strtotime($adv['payment_date'])); ?></td>
                                    <td class="border p-1 text-right"><?php echo number_format($adv['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="font-bold bg-gray-50">
                                <td class="border p-1">Total</td>
                                <td class="border p-1 text-right"><?php echo number_format($total_advance, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-8 border-t pt-4 flex justify-between text-sm text-gray-500">
            <div>Authorized Signature</div>
            <div>Employee Signature</div>
        </div>
    </div>

    <div class="no-print fixed bottom-4 right-4 text-center">
        <button onclick="window.print()"
            class="bg-blue-600 text-white px-6 py-3 rounded-full shadow-lg hover:bg-blue-700 font-bold flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print Report
        </button>
    </div>
</body>

</html>