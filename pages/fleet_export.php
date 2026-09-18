<?php
/**
 * CSV export for Vehicle Management (§8 daily records, §11 monthly report).
 * Served without the page layout; opens in Excel directly (UTF-8 with BOM).
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/fleet.php';

if (!is_logged_in() || !fleetCan('reports')) {
    http_response_code(403);
    exit('Not allowed.');
}

$type = ($_GET['type'] ?? 'daily') === 'monthly' ? 'monthly' : 'daily';

$date = static function (string $key, string $fallback): string {
    $v = (string) ($_GET[$key] ?? '');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $fallback;
};

/**
 * Spreadsheet formula injection: a cell beginning with = + - @ is executed by
 * Excel. Free-text fields (remarks, driver names) are user-supplied, so prefix
 * them with an apostrophe to keep them as text.
 */
$safe = static function ($value) {
    if (!is_string($value) || $value === '') {
        return $value;
    }
    return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fleet_' . $type . '_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

if ($type === 'daily') {
    $from = $date('from', date('Y-m-01'));
    $to = $date('to', date('Y-m-d'));

    $where = ['l.log_date BETWEEN ? AND ?'];
    $params = [$from, $to];
    foreach (['vehicle' => 'l.vehicle_id', 'driver' => 'l.driver_id', 'project' => 'l.project_id'] as $k => $col) {
        if (!empty($_GET[$k])) {
            $where[] = "$col = ?";
            $params[] = (int) $_GET[$k];
        }
    }
    if (in_array($_GET['status'] ?? '', ['OK', 'Warning', 'Critical'], true)) {
        $where[] = 'l.alert_status = ?';
        $params[] = $_GET['status'];
    }

    $stmt = $pdo->prepare("
        SELECT l.log_date, v.vehicle_number, l.driver_name, p.name AS project_name,
               l.opening_km, l.closing_km, l.total_km,
               COALESCE(fx.litres, 0) AS litres, COALESCE(fx.cost, 0) AS cost,
               l.alert_status, l.alert_note, l.remarks
        FROM vehicle_daily_logs l
        JOIN vehicles v ON v.id = l.vehicle_id
        LEFT JOIN projects p ON p.id = l.project_id
        LEFT JOIN (SELECT daily_log_id, SUM(liters) litres, SUM(amount) cost
                   FROM vehicle_fuel_records WHERE daily_log_id IS NOT NULL GROUP BY daily_log_id) fx
               ON fx.daily_log_id = l.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.log_date, v.vehicle_number");
    $stmt->execute($params);

    fputcsv($out, ['Date', 'Vehicle', 'Driver', 'Project', 'Opening KM', 'Closing KM', 'Total KM',
        'Fuel L', 'Fuel Cost (QAR)', 'KM/L', 'L/100KM', 'Cost/KM', 'Status', 'Alert', 'Remarks']);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $km = (float) $r['total_km'];
        $l = (float) $r['litres'];
        fputcsv($out, [
            $r['log_date'], $safe($r['vehicle_number']), $safe($r['driver_name']), $safe($r['project_name']),
            $r['opening_km'], $r['closing_km'], $r['total_km'],
            $l > 0 ? $l : '', $r['cost'] > 0 ? $r['cost'] : '',
            fleetKmpl($km, $l), fleetLitresPer100($km, $l), fleetCostPerKm((float) $r['cost'] ?: null, $km),
            $r['alert_status'], $safe($r['alert_note']), $safe($r['remarks']),
        ]);
    }
} else {
    $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
    $from = $month . '-01';
    $to = date('Y-m-t', strtotime($from));

    $stmt = $pdo->prepare("
        SELECT v.vehicle_number, v.fuel_type,
               (SELECT COALESCE(SUM(total_km), 0) FROM vehicle_daily_logs
                 WHERE vehicle_id = v.id AND log_date BETWEEN ? AND ?) AS km,
               (SELECT COALESCE(SUM(liters), 0) FROM vehicle_fuel_records
                 WHERE vehicle_id = v.id AND fuel_date BETWEEN ? AND ?) AS litres,
               (SELECT COALESCE(SUM(amount), 0) FROM vehicle_fuel_records
                 WHERE vehicle_id = v.id AND fuel_date BETWEEN ? AND ?) AS cost
        FROM vehicles v ORDER BY v.vehicle_number");
    $stmt->execute([$from, $to, $from, $to, $from, $to]);

    fputcsv($out, ['Month', 'Vehicle', 'Fuel Type', 'Total KM', 'Fuel L', 'Fuel Cost (QAR)', 'Avg KM/L', 'Cost/KM']);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $month, $safe($r['vehicle_number']), $r['fuel_type'],
            $r['km'], $r['litres'], $r['cost'],
            fleetKmpl((float) $r['km'], (float) $r['litres']),
            fleetCostPerKm((float) $r['cost'] ?: null, (float) $r['km']),
        ]);
    }
}

fclose($out);
exit;
