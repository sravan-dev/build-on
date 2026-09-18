<?php
/**
 * Vehicle Management — Reports (§11 monthly report, §12 fuel cost analysis).
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/fleet.php';

if (!is_logged_in() || !fleetCan('reports')) {
    echo '<div class="p-6 bg-red-50 text-red-700 rounded">You do not have access to fleet reports.</div>';
    return;
}

$month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$from = $month . '-01';
$to = date('Y-m-t', strtotime($from));
$year = substr($month, 0, 4);

$filterVehicle = (int) ($_GET['vehicle'] ?? 0);
$filterDriver = (int) ($_GET['driver'] ?? 0);
$filterProject = (int) ($_GET['project'] ?? 0);
$filterFuel = in_array($_GET['fuel_type'] ?? '', ['Petrol', 'Diesel', 'Other'], true) ? $_GET['fuel_type'] : '';

// ------------------------------------------------------ §11 per vehicle for month
$vWhere = ['1 = 1'];
$vParams = [];
if ($filterVehicle) { $vWhere[] = 'v.id = ?'; $vParams[] = $filterVehicle; }
if ($filterFuel) { $vWhere[] = 'v.fuel_type = ?'; $vParams[] = $filterFuel; }
if ($filterProject) { $vWhere[] = 'v.project_id = ?'; $vParams[] = $filterProject; }
if ($filterDriver) { $vWhere[] = 'v.driver_id = ?'; $vParams[] = $filterDriver; }

$monthly = $pdo->prepare("
    SELECT v.id, v.vehicle_number, v.fuel_type, v.expected_kmpl_min, v.expected_kmpl_max,
           (SELECT COALESCE(SUM(total_km), 0) FROM vehicle_daily_logs  WHERE vehicle_id = v.id AND log_date  BETWEEN ? AND ?) AS km,
           (SELECT COALESCE(SUM(liters), 0)   FROM vehicle_fuel_records WHERE vehicle_id = v.id AND fuel_date BETWEEN ? AND ?) AS litres,
           (SELECT COALESCE(SUM(amount), 0)   FROM vehicle_fuel_records WHERE vehicle_id = v.id AND fuel_date BETWEEN ? AND ?) AS cost
    FROM vehicles v
    WHERE " . implode(' AND ', $vWhere) . "
    ORDER BY v.vehicle_number");
$monthly->execute(array_merge([$from, $to, $from, $to, $from, $to], $vParams));
$rows = $monthly->fetchAll(PDO::FETCH_ASSOC);

$tot = ['km' => 0.0, 'litres' => 0.0, 'cost' => 0.0];
foreach ($rows as $r) {
    $tot['km'] += (float) $r['km'];
    $tot['litres'] += (float) $r['litres'];
    $tot['cost'] += (float) $r['cost'];
}

// ------------------------------------------------------------- §12 fuel cost
$byDriver = $pdo->prepare("SELECT COALESCE(driver_name, 'Unassigned') AS label, SUM(amount) AS cost, SUM(liters) AS litres
                           FROM vehicle_fuel_records WHERE fuel_date BETWEEN ? AND ?
                           GROUP BY COALESCE(driver_name, 'Unassigned') ORDER BY cost DESC");
$byDriver->execute([$from, $to]);

$byProject = $pdo->prepare("SELECT COALESCE(p.name, 'No project') AS label, SUM(f.amount) AS cost, SUM(f.liters) AS litres
                            FROM vehicle_fuel_records f
                            LEFT JOIN vehicle_daily_logs l ON l.id = f.daily_log_id
                            LEFT JOIN projects p ON p.id = l.project_id
                            WHERE f.fuel_date BETWEEN ? AND ?
                            GROUP BY COALESCE(p.name, 'No project') ORDER BY cost DESC");
$byProject->execute([$from, $to]);

$byMonth = $pdo->prepare("SELECT SUBSTRING(fuel_date, 1, 7) AS ym, SUM(amount) AS cost, SUM(liters) AS litres
                          FROM vehicle_fuel_records WHERE fuel_date LIKE ?
                          GROUP BY SUBSTRING(fuel_date, 1, 7) ORDER BY ym");
$byMonth->execute([$year . '-%']);
$months = $byMonth->fetchAll(PDO::FETCH_ASSOC);
$yearCost = array_sum(array_map(static fn($m) => (float) $m['cost'], $months));
$maxMonth = max(array_map(static fn($m) => (float) $m['cost'], $months) ?: [1]);

$vehicles = $pdo->query("SELECT id, vehicle_number FROM vehicles ORDER BY vehicle_number")->fetchAll(PDO::FETCH_ASSOC);
$drivers = $pdo->query("SELECT id, name FROM fleet_drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Fleet Reports</h1>
        <p class="text-gray-600 mt-2"><?php echo date('F Y', strtotime($from)); ?></p>
    </div>
    <div class="flex gap-3 text-sm">
        <a href="index.php?page=fleet_export&type=monthly&month=<?php echo urlencode($month); ?>" class="text-primary hover:underline"><i class="fas fa-file-csv mr-1"></i>CSV / Excel</a>
        <button onclick="window.print()" class="text-primary hover:underline"><i class="fas fa-print mr-1"></i>Print / PDF</button>
    </div>
</div>

<form method="get" class="bg-white rounded-lg shadow-sm p-4 mb-6 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
    <input type="hidden" name="page" value="fleet_reports">
    <div><label class="block text-xs text-gray-500 mb-1">Month</label><input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" class="w-full px-2 py-2 border rounded-md text-sm"></div>
    <div><label class="block text-xs text-gray-500 mb-1">Vehicle</label><select name="vehicle" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option><?php foreach ($vehicles as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo $filterVehicle === (int) $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['vehicle_number']); ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-xs text-gray-500 mb-1">Driver</label><select name="driver" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option><?php foreach ($drivers as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo $filterDriver === (int) $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-xs text-gray-500 mb-1">Project</label><select name="project" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option><?php foreach ($projects as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo $filterProject === (int) $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-xs text-gray-500 mb-1">Fuel Type</label><select name="fuel_type" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option><?php foreach (['Petrol', 'Diesel', 'Other'] as $ft): ?><option <?php echo $filterFuel === $ft ? 'selected' : ''; ?>><?php echo $ft; ?></option><?php endforeach; ?></select></div>
    <div><button class="w-full bg-primary text-white px-3 py-2 rounded-md text-sm">Apply</button></div>
</form>

<div class="bg-white rounded-lg shadow-md mb-6 overflow-x-auto">
    <div class="px-6 py-4 border-b"><h2 class="font-semibold text-gray-900">Monthly report</h2></div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <?php foreach (['Vehicle', 'Total KM', 'Fuel L', 'Fuel Cost', 'Avg KM/L', 'Cost/KM'] as $i => $h): ?>
                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase <?php echo $i ? 'text-right' : 'text-left'; ?>"><?php echo $h; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($rows as $r):
                $avg = fleetKmpl((float) $r['km'], (float) $r['litres']);
                [$flag] = fleetMileageAlert($r, $avg);
                $cpk = fleetCostPerKm((float) $r['cost'] ?: null, (float) $r['km']); ?>
                <tr>
                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($r['vehicle_number']); ?></td>
                    <td class="px-4 py-3 text-right"><?php echo fleetNum($r['km']); ?></td>
                    <td class="px-4 py-3 text-right"><?php echo fleetNum($r['litres'], 2); ?></td>
                    <td class="px-4 py-3 text-right">QAR <?php echo fleetNum($r['cost'], 2); ?></td>
                    <td class="px-4 py-3 text-right <?php echo $flag === 'Critical' ? 'text-red-600 font-semibold' : ($flag === 'Warning' ? 'text-yellow-700 font-semibold' : ''); ?>"><?php echo $avg !== null ? number_format($avg, 2) : '—'; ?></td>
                    <td class="px-4 py-3 text-right"><?php echo $cpk !== null ? 'QAR ' . number_format($cpk, 2) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-gray-50 font-semibold">
            <tr>
                <td class="px-4 py-3">Total</td>
                <td class="px-4 py-3 text-right"><?php echo fleetNum($tot['km']); ?></td>
                <td class="px-4 py-3 text-right"><?php echo fleetNum($tot['litres'], 2); ?></td>
                <td class="px-4 py-3 text-right">QAR <?php echo fleetNum($tot['cost'], 2); ?></td>
                <td class="px-4 py-3 text-right"><?php echo ($a = fleetKmpl($tot['km'], $tot['litres'])) !== null ? number_format($a, 2) : '—'; ?></td>
                <td class="px-4 py-3 text-right"><?php echo ($c = fleetCostPerKm($tot['cost'] ?: null, $tot['km'])) !== null ? 'QAR ' . number_format($c, 2) : '—'; ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <?php foreach ([['Fuel cost by driver', $byDriver->fetchAll(PDO::FETCH_ASSOC)], ['Fuel cost by project', $byProject->fetchAll(PDO::FETCH_ASSOC)]] as [$title, $list]): ?>
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b"><h2 class="font-semibold text-gray-900"><?php echo $title; ?></h2></div>
            <ul class="divide-y">
                <?php if (!$list): ?><li class="px-6 py-4 text-sm text-gray-500">No fuel recorded this month.</li><?php endif; ?>
                <?php foreach ($list as $item): ?>
                    <li class="px-6 py-3 flex justify-between text-sm">
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                        <span class="font-semibold">QAR <?php echo fleetNum($item['cost'], 2); ?> <span class="text-gray-400 font-normal">· <?php echo fleetNum($item['litres'], 1); ?> L</span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow-md">
    <div class="px-6 py-4 border-b flex justify-between">
        <h2 class="font-semibold text-gray-900">Fuel cost by month, <?php echo htmlspecialchars($year); ?></h2>
        <span class="text-sm font-semibold">Year: QAR <?php echo fleetNum($yearCost, 2); ?></span>
    </div>
    <div class="p-6 space-y-2">
        <?php if (!$months): ?><p class="text-sm text-gray-500">No fuel recorded this year.</p><?php endif; ?>
        <?php foreach ($months as $m): ?>
            <div class="flex items-center gap-3 text-sm">
                <span class="w-20 text-gray-600"><?php echo date('M', strtotime($m['ym'] . '-01')); ?></span>
                <div class="flex-1 bg-gray-100 rounded h-5 overflow-hidden">
                    <div class="h-5 bg-primary" style="width: <?php echo round((float) $m['cost'] / $maxMonth * 100, 1); ?>%"></div>
                </div>
                <span class="w-28 text-right font-semibold">QAR <?php echo fleetNum($m['cost'], 0); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
