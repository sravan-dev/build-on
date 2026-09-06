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
/**
 * Labour cost attributable to one project.
 *
 * Attendance records the day's site twice: daily_attendance.work_site holds the
 * project NAME, while attendance_logs holds the real project_id per activity.
 * Matching on the name alone is wrong in two ways:
 *
 *   - projects with duplicate names each claim the same day, so the cost is
 *     counted more than once;
 *   - switch_site overwrites work_site, so a day split between two projects is
 *     credited entirely to whichever site was chosen last.
 *
 * So days that have logs are attributed by project_id and split in proportion
 * to the time logged against each project. Days with no usable log fall back to
 * the historic work_site name match, which keeps older records counted.
 */
function calculateProjectLabourCost($pdo, $projectName, $projectId = null)
{
    $stmt = $pdo->prepare("
        SELECT da.id, da.in_time, da.out_time, e.monthly_salary
        FROM daily_attendance da
        JOIN employees e ON da.employee_id = e.id
        WHERE da.in_time IS NOT NULL
          AND da.out_time IS NOT NULL
          AND e.monthly_salary > 0
          AND (
                EXISTS (SELECT 1 FROM attendance_logs al
                        WHERE al.daily_attendance_id = da.id AND al.project_id = :pid)
             OR (
                da.work_site = :pname
                AND NOT EXISTS (SELECT 1 FROM attendance_logs al2
                                WHERE al2.daily_attendance_id = da.id AND al2.project_id IS NOT NULL)
             )
          )
    ");
    $stmt->execute([':pid' => $projectId, ':pname' => $projectName]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $breakStmt = $pdo->prepare("
        SELECT start_time, end_time
        FROM attendance_logs
        WHERE daily_attendance_id = ? AND activity_type = 'break'
    ");
    $shareStmt = $pdo->prepare("
        SELECT project_id, start_time, end_time
        FROM attendance_logs
        WHERE daily_attendance_id = ? AND project_id IS NOT NULL AND activity_type <> 'break'
    ");

    $total = 0;
    foreach ($records as $r) {
        $in = strtotime($r['in_time']);
        $out = strtotime($r['out_time']);
        $diff = $out - $in;
        if ($diff < 0) {
            $diff += 86400; // overnight shift
        }

        $breakStmt->execute([$r['id']]);
        $break_time = 0;
        while ($break = $breakStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($break['start_time'] && $break['end_time']) {
                $b1 = strtotime($break['start_time']);
                $b2 = strtotime($break['end_time']);
                $break_diff = $b2 - $b1;
                if ($break_diff < 0) {
                    $break_diff += 86400;
                }
                $break_time += $break_diff / 3600;
            }
        }

        $working_hours = max(0, ($diff / 3600) - $break_time);

        // Split the day between the projects actually worked on it.
        $shareStmt->execute([$r['id']]);
        $logs = $shareStmt->fetchAll(PDO::FETCH_ASSOC);
        $mine = 0.0;
        $all = 0.0;
        foreach ($logs as $log) {
            if (!$log['start_time'] || !$log['end_time']) {
                continue;
            }
            $span = strtotime($log['end_time']) - strtotime($log['start_time']);
            if ($span < 0) {
                $span += 86400;
            }
            $all += $span;
            if ((int) $log['project_id'] === (int) $projectId) {
                $mine += $span;
            }
        }
        if ($all > 0) {
            $working_hours = $working_hours * ($mine / $all);
        }

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
    $project['total_labour_cost'] = calculateProjectLabourCost($pdo, $project['name'], $project['id']);
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
