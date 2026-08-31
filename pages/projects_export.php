<?php
/**
 * Projects Export to Excel
 * Exports all project financial data to CSV format
 */

require_once '../includes/db.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=projects_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

/**
 * Calculate labour cost for a project using the same logic as attendance_report.php
 */
function calculateProjectLabourCost($pdo, $projectName)
{
    $stmt = $pdo->prepare("
        SELECT da.in_time, da.out_time, e.monthly_salary
        FROM daily_attendance da
        JOIN employees e ON da.employee_id = e.id
        WHERE da.work_site = ?
        AND da.in_time IS NOT NULL
        AND da.out_time IS NOT NULL
        AND e.monthly_salary > 0
    ");
    $stmt->execute([$projectName]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($records as $r) {
        $in = strtotime($r['in_time']);
        $out = strtotime($r['out_time']);
        $diff = $out - $in;

        // Handle overnight shifts (if out < in, add 24 hours)
        if ($diff < 0) {
            $diff += 86400;
        }

        $working_hours = $diff / 3600;
        $hourly_rate = ($r['monthly_salary'] / 26 / 8);
        $total += $working_hours * $hourly_rate;
    }

    return round($total, 2);
}

// Fetch projects basic data
$projects = $pdo->query("
    SELECT p.*, 
           c.name as client_name,
           COALESCE((SELECT SUM(pm.amount) 
                     FROM payments pm
                     LEFT JOIN invoices i ON pm.invoice_id = i.id
                     LEFT JOIN quotations q ON i.quotation_id = q.id 
                     WHERE COALESCE(CASE WHEN i.quotation_id IS NOT NULL THEN q.project_id END, i.project_id) = p.id), 0) as total_income,
           COALESCE((SELECT SUM(total_amount) 
                     FROM purchases 
                     WHERE project_id = p.id), 0) as total_expenses
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id
    ORDER BY p.id DESC
")->fetchAll();

// Calculate labour cost for each project using PHP
foreach ($projects as &$project) {
    $project['total_labour_cost'] = calculateProjectLabourCost($pdo, $project['name']);
    $project['profit'] = $project['total_income'] - $project['total_expenses'] - $project['total_labour_cost'];
}
unset($project);


// Add CSV headers
fputcsv($output, [
    'Project ID',
    'Project Name',
    'Client',
    'Total Project Value',
    'Total Income',
    'Total Labour Cost',
    'Total Expenses',
    'Profit',
    'Created Date'
]);

// Add data rows
foreach ($projects as $project) {
    // Get total quotation value
    $quotationTotal = $pdo->prepare("SELECT
        (SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE project_id = ?)
      + (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE project_id = ? AND quotation_id IS NULL)");
    $quotationTotal->execute([$project['id'], $project['id']]);
    $projectValue = $quotationTotal->fetchColumn();

    fputcsv($output, [
        $project['id'],
        $project['name'],
        $project['client_name'] ?? 'N/A',
        number_format($projectValue, 2),
        number_format($project['total_income'], 2),
        number_format($project['total_labour_cost'], 2),
        number_format($project['total_expenses'], 2),
        number_format($project['profit'], 2),
        $project['created_at'] ?? date('Y-m-d')
    ]);
}

fclose($output);
exit;
