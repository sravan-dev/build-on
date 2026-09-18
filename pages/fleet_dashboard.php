<?php
/**
 * Vehicle Management — Dashboard (§6 daily KPIs, §7 vehicle-wise figures).
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/fleet.php';

if (!is_logged_in() || !fleetCan('view')) {
    echo '<div class="p-6 bg-red-50 text-red-700 rounded">You do not have access to Vehicle Management.</div>';
    return;
}

$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
$monthFrom = date('Y-m-01', strtotime($day));
$monthTo = date('Y-m-t', strtotime($day));

// ------------------------------------------------------------------ §6 KPIs
$totalVehicles = (int) $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();

$k = $pdo->prepare("SELECT COUNT(DISTINCT vehicle_id) AS used, COALESCE(SUM(total_km), 0) AS km
                    FROM vehicle_daily_logs WHERE log_date = ?");
$k->execute([$day]);
$today = $k->fetch(PDO::FETCH_ASSOC);

$f = $pdo->prepare("SELECT COALESCE(SUM(liters), 0) AS litres, COALESCE(SUM(amount), 0) AS cost
                    FROM vehicle_fuel_records WHERE fuel_date = ?");
$f->execute([$day]);
$fuelToday = $f->fetch(PDO::FETCH_ASSOC);

$kmToday = (float) $today['km'];
$litresToday = (float) $fuelToday['litres'];
$costToday = (float) $fuelToday['cost'];

$alerts = $pdo->prepare("SELECT l.id, l.log_date, l.alert_status, l.alert_note, v.vehicle_number
                         FROM vehicle_daily_logs l JOIN vehicles v ON v.id = l.vehicle_id
                         WHERE l.alert_status IN ('Warning', 'Critical') AND l.log_date BETWEEN ? AND ?
                         ORDER BY l.alert_status = 'Critical' DESC, l.log_date DESC LIMIT 8");
$alerts->execute([$monthFrom, $monthTo]);
$alertRows = $alerts->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------ §7 per vehicle
$vs = $pdo->prepare("
    SELECT v.id, v.vehicle_number, v.name, v.vehicle_status, v.current_mileage,
           v.expected_kmpl_min, v.expected_kmpl_max, d.name AS driver_name,
           (SELECT COALESCE(SUM(total_km), 0) FROM vehicle_daily_logs WHERE vehicle_id = v.id AND log_date = ?) AS km_day,
           (SELECT COALESCE(SUM(liters), 0)   FROM vehicle_fuel_records WHERE vehicle_id = v.id AND fuel_date = ?) AS l_day,
           (SELECT COALESCE(SUM(amount), 0)   FROM vehicle_fuel_records WHERE vehicle_id = v.id AND fuel_date = ?) AS c_day,
           (SELECT COALESCE(SUM(total_km), 0) FROM vehicle_daily_logs WHERE vehicle_id = v.id AND log_date BETWEEN ? AND ?) AS km_month,
           (SELECT COALESCE(SUM(liters), 0)   FROM vehicle_fuel_records WHERE vehicle_id = v.id AND fuel_date BETWEEN ? AND ?) AS l_month,
           (SELECT COALESCE(SUM(amount), 0)   FROM vehicle_fuel_records WHERE vehicle_id = v.id AND fuel_date BETWEEN ? AND ?) AS c_month
    FROM vehicles v
    LEFT JOIN fleet_drivers d ON d.id = v.driver_id
    ORDER BY v.vehicle_number");
$vs->execute([$day, $day, $day, $monthFrom, $monthTo, $monthFrom, $monthTo, $monthFrom, $monthTo]);
$vehicles = $vs->fetchAll(PDO::FETCH_ASSOC);

$statusBadge = static function (?string $s): string {
    $s = strtolower((string) $s);
    if (strpos($s, 'maint') !== false) return 'bg-yellow-100 text-yellow-800';
    if (strpos($s, 'inactive') !== false) return 'bg-gray-100 text-gray-600';
    return 'bg-green-100 text-green-800';
};

$kpis = [
    ['Total Vehicles', number_format($totalVehicles), 'fa-truck'],
    ['Vehicles Used Today', number_format((int) $today['used']), 'fa-road'],
    ['Total KM Today', number_format($kmToday), 'fa-tachometer-alt'],
    ['Fuel Litres', number_format($litresToday, 2), 'fa-gas-pump'],
    ['Fuel Cost (QAR)', number_format($costToday, 2), 'fa-coins'],
    ['Average KM/L', ($v = fleetKmpl($kmToday, $litresToday)) !== null ? number_format($v, 2) : '—', 'fa-leaf'],
    ['Fuel Cost / KM', ($c = fleetCostPerKm($costToday ?: null, $kmToday)) !== null ? 'QAR ' . number_format($c, 3) : '—', 'fa-calculator'],
];
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Fleet Dashboard</h1>
        <p class="text-gray-600 mt-2"><?php echo date('l, F j, Y', strtotime($day)); ?></p>
    </div>
    <div class="flex gap-2 items-center">
        <form method="get" class="flex gap-2">
            <input type="hidden" name="page" value="fleet_dashboard">
            <input type="date" name="date" value="<?php echo htmlspecialchars($day); ?>" onchange="this.form.submit()"
                class="px-3 py-2 border border-gray-300 rounded-md">
        </form>
        <?php if (fleetCan('enter')): ?>
            <a href="index.php?page=fleet_daily" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium whitespace-nowrap">
                <i class="fas fa-plus mr-1"></i>Daily Record
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    <?php foreach ($kpis as [$label, $value, $icon]): ?>
        <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="text-xs text-gray-500 flex items-center gap-2"><i class="fas <?php echo $icon; ?> text-primary"></i><?php echo $label; ?></div>
            <div class="text-xl font-bold text-gray-900 mt-1"><?php echo $value; ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($alertRows): ?>
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h2 class="font-semibold text-gray-900"><i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i>Consumption alerts this month</h2>
            <a href="index.php?page=fleet_daily&status=Critical&from=<?php echo $monthFrom; ?>&to=<?php echo $monthTo; ?>" class="text-sm text-primary hover:underline">View all</a>
        </div>
        <ul class="divide-y">
            <?php foreach ($alertRows as $a): ?>
                <li class="px-6 py-3 flex items-center gap-3 text-sm">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $a['alert_status'] === 'Critical' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>"><?php echo $a['alert_status']; ?></span>
                    <span class="font-medium"><?php echo htmlspecialchars($a['vehicle_number']); ?></span>
                    <span class="text-gray-600 flex-1"><?php echo htmlspecialchars($a['alert_note'] ?? ''); ?></span>
                    <span class="text-gray-400"><?php echo date('d M', strtotime($a['log_date'])); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md">
    <div class="px-6 py-4 border-b">
        <h2 class="font-semibold text-gray-900">By vehicle</h2>
        <p class="text-xs text-gray-500">Today and <?php echo date('F', strtotime($day)); ?> to date</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Driver</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Odometer</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">KM today</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fuel today</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">KM month</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fuel month</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost month</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg KM/L</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost/KM</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($vehicles as $v):
                    $avg = fleetKmpl((float) $v['km_month'], (float) $v['l_month']);
                    $cpk = fleetCostPerKm((float) $v['c_month'] ?: null, (float) $v['km_month']);
                    [$flag] = fleetMileageAlert($v, $avg); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($v['vehicle_number']); ?></div>
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full <?php echo $statusBadge($v['vehicle_status']); ?>"><?php echo htmlspecialchars($v['vehicle_status'] ?: 'Active'); ?></span>
                        </td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($v['driver_name'] ?? '—'); ?></td>
                        <td class="px-4 py-3 text-right font-mono"><?php echo fleetNum($v['current_mileage']); ?></td>
                        <td class="px-4 py-3 text-right"><?php echo (float) $v['km_day'] > 0 ? fleetNum($v['km_day']) : '—'; ?></td>
                        <td class="px-4 py-3 text-right"><?php echo (float) $v['l_day'] > 0 ? fleetNum($v['l_day'], 2) . ' L' : '—'; ?></td>
                        <td class="px-4 py-3 text-right font-semibold"><?php echo fleetNum($v['km_month']); ?></td>
                        <td class="px-4 py-3 text-right"><?php echo fleetNum($v['l_month'], 2); ?> L</td>
                        <td class="px-4 py-3 text-right"><?php echo fleetNum($v['c_month'], 2); ?></td>
                        <td class="px-4 py-3 text-right <?php echo $flag === 'Critical' ? 'text-red-600 font-semibold' : ($flag === 'Warning' ? 'text-yellow-700 font-semibold' : ''); ?>">
                            <?php echo $avg !== null ? number_format($avg, 2) : '—'; ?>
                        </td>
                        <td class="px-4 py-3 text-right"><?php echo $cpk !== null ? number_format($cpk, 3) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
