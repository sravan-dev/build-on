<?php
/**
 * Vehicle Management — Vehicles (§2) with the fleet settings the alerts need
 * (§9 daily KM limit, §10 expected mileage range, tank capacity).
 *
 * Registration, purchase and maintenance details stay on the existing vehicle
 * pages; this screen owns the fields fuel tracking depends on.
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/fleet.php';

if (!is_logged_in() || !fleetCan('view')) {
    echo '<div class="p-6 bg-red-50 text-red-700 rounded">You do not have access to Vehicle Management.</div>';
    return;
}

if (empty($_SESSION['fleet_csrf'])) {
    $_SESSION['fleet_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['fleet_csrf'];

$message = '';
$error = '';

$optNum = static function ($v): ?float {
    $v = trim((string) $v);
    if ($v === '') return null;
    if (!is_numeric($v) || (float) $v < 0) {
        throw new Exception('Figures must be positive numbers.');
    }
    return (float) $v;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new Exception('This form expired. Reload the page and try again.');
        }
        if (!fleetCan('manage')) {
            throw new Exception('You cannot edit vehicles.');
        }

        $number = trim((string) ($_POST['vehicle_number'] ?? ''));
        if ($number === '') {
            throw new Exception('Vehicle number is required.');
        }

        $min = $optNum($_POST['expected_kmpl_min'] ?? '');
        $max = $optNum($_POST['expected_kmpl_max'] ?? '');
        if ($min !== null && $max !== null && $min > $max) {
            throw new Exception('Expected mileage: the minimum cannot be above the maximum.');
        }

        $status = in_array($_POST['vehicle_status'] ?? '', ['Active', 'Inactive', 'Under Maintenance'], true)
            ? $_POST['vehicle_status'] : 'Active';

        $row = [
            'vehicle_number' => $number,
            'name' => trim((string) ($_POST['name'] ?? '')) ?: null,
            'type' => trim((string) ($_POST['type'] ?? '')) ?: null,
            'make' => trim((string) ($_POST['make'] ?? '')) ?: null,
            'model' => trim((string) ($_POST['model'] ?? '')) ?: null,
            'year' => ($y = (int) ($_POST['year'] ?? 0)) > 1900 ? $y : null,
            'color' => trim((string) ($_POST['color'] ?? '')) ?: null,
            'chassis_number' => trim((string) ($_POST['chassis_number'] ?? '')) ?: null,
            'engine_number' => trim((string) ($_POST['engine_number'] ?? '')) ?: null,
            'fuel_type' => in_array($_POST['fuel_type'] ?? '', ['Petrol', 'Diesel', 'Other'], true) ? $_POST['fuel_type'] : null,
            'fuel_tank_capacity' => $optNum($_POST['fuel_tank_capacity'] ?? ''),
            'current_mileage' => $optNum($_POST['current_mileage'] ?? ''),
            'driver_id' => !empty($_POST['driver_id']) ? (int) $_POST['driver_id'] : null,
            'project_id' => !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null,
            'vehicle_status' => $status,
            'registration_renewal_date' => ($_POST['registration_renewal_date'] ?? '') ?: null,
            'insurance_renewal_date' => ($_POST['insurance_renewal_date'] ?? '') ?: null,
            'expected_kmpl_min' => $min,
            'expected_kmpl_max' => $max,
            'max_daily_km' => ($m = (int) ($_POST['max_daily_km'] ?? 0)) > 0 ? $m : null,
        ];

        // Keep the legacy free-text driver column in step for older screens.
        if ($row['driver_id']) {
            $d = $pdo->prepare("SELECT name FROM fleet_drivers WHERE id = ?");
            $d->execute([$row['driver_id']]);
            $row['assigned_driver'] = $d->fetchColumn() ?: null;
        }

        $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
        if ($vehicleId) {
            $old = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
            $old->execute([$vehicleId]);
            $oldRow = $old->fetch(PDO::FETCH_ASSOC);
            if (!$oldRow) {
                throw new Exception('Vehicle not found.');
            }
            $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($row)));
            $pdo->prepare("UPDATE vehicles SET $set WHERE id = ?")->execute(array_merge(array_values($row), [$vehicleId]));
            fleetAudit($pdo, 'vehicle', $vehicleId, 'update', $oldRow, $row);
            $message = 'Vehicle updated.';
        } else {
            $cols = implode(', ', array_keys($row));
            $marks = implode(', ', array_fill(0, count($row), '?'));
            $pdo->prepare("INSERT INTO vehicles ($cols) VALUES ($marks)")->execute(array_values($row));
            $vehicleId = (int) $pdo->lastInsertId();
            fleetAudit($pdo, 'vehicle', $vehicleId, 'create', [], $row);
            $message = 'Vehicle added.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$vehicles = $pdo->query("SELECT v.*, d.name AS driver_name, p.name AS project_name
                         FROM vehicles v
                         LEFT JOIN fleet_drivers d ON d.id = v.driver_id
                         LEFT JOIN projects p ON p.id = v.project_id
                         ORDER BY v.vehicle_number")->fetchAll(PDO::FETCH_ASSOC);
$drivers = $pdo->query("SELECT id, name FROM fleet_drivers WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$editing = null;
if (isset($_GET['edit'])) {
    foreach ($vehicles as $v) {
        if ((int) $v['id'] === (int) $_GET['edit']) {
            $editing = $v;
        }
    }
    if (!$editing && $_GET['edit'] === 'new') {
        $editing = [];
    }
}
$e = static fn(string $k) => htmlspecialchars((string) ($editing[$k] ?? ''));
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Vehicles</h1>
        <p class="text-gray-600 mt-2">Fleet master, with the tank size and mileage range the consumption alerts use.</p>
    </div>
    <?php if (fleetCan('manage') && $editing === null): ?>
        <a href="index.php?page=fleet_vehicles&edit=new" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium"><i class="fas fa-plus mr-1"></i>Add Vehicle</a>
    <?php endif; ?>
</div>

<?php if ($message): ?><div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($editing !== null && fleetCan('manage')): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4"><?php echo !empty($editing['id']) ? 'Edit ' . $e('vehicle_number') : 'Add Vehicle'; ?></h2>
    <form method="post" class="space-y-5">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <?php if (!empty($editing['id'])): ?><input type="hidden" name="vehicle_id" value="<?php echo (int) $editing['id']; ?>"><?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Number *</label><input name="vehicle_number" required value="<?php echo $e('vehicle_number'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Name</label><input name="name" value="<?php echo $e('name'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full px-3 py-2 border rounded-md"><option value="">—</option>
                    <?php foreach (['Pickup', 'Car', 'Van', 'Bus', 'Truck', 'Heavy Equipment', 'Other'] as $t): ?><option <?php echo ($editing['type'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="vehicle_status" class="w-full px-3 py-2 border rounded-md">
                    <?php foreach (['Active', 'Inactive', 'Under Maintenance'] as $st): ?><option <?php echo ($editing['vehicle_status'] ?? 'Active') === $st ? 'selected' : ''; ?>><?php echo $st; ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Make / Brand</label><input name="make" value="<?php echo $e('make'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Model</label><input name="model" value="<?php echo $e('model'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Model Year</label><input type="number" name="year" value="<?php echo $e('year'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Color</label><input name="color" value="<?php echo $e('color'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Chassis Number</label><input name="chassis_number" value="<?php echo $e('chassis_number'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Engine Number</label><input name="engine_number" value="<?php echo $e('engine_number'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Registration Expiry</label><input type="date" name="registration_renewal_date" value="<?php echo $e('registration_renewal_date'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Insurance Expiry</label><input type="date" name="insurance_renewal_date" value="<?php echo $e('insurance_renewal_date'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Assigned Driver</label>
                <select name="driver_id" class="w-full px-3 py-2 border rounded-md"><option value="">—</option>
                    <?php foreach ($drivers as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo (int) ($editing['driver_id'] ?? 0) === (int) $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Department / Project</label>
                <select name="project_id" class="w-full px-3 py-2 border rounded-md"><option value="">—</option>
                    <?php foreach ($projects as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo (int) ($editing['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Current KM</label><input type="number" step="0.1" name="current_mileage" value="<?php echo $e('current_mileage'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                <select name="fuel_type" class="w-full px-3 py-2 border rounded-md"><option value="">—</option>
                    <?php foreach (['Petrol', 'Diesel', 'Other'] as $ft): ?><option <?php echo ($editing['fuel_type'] ?? '') === $ft ? 'selected' : ''; ?>><?php echo $ft; ?></option><?php endforeach; ?>
                </select></div>
        </div>

        <fieldset class="border rounded-md p-4">
            <legend class="px-2 text-sm font-semibold text-gray-700">Fuel &amp; alert settings</legend>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tank Capacity (L)</label><input type="number" step="0.1" min="0" name="fuel_tank_capacity" value="<?php echo $e('fuel_tank_capacity'); ?>" class="w-full px-3 py-2 border rounded-md">
                    <p class="text-xs text-gray-400 mt-1">Fuel above this asks for confirmation</p></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Expected KM/L — min</label><input type="number" step="0.1" min="0" name="expected_kmpl_min" value="<?php echo $e('expected_kmpl_min'); ?>" class="w-full px-3 py-2 border rounded-md"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Expected KM/L — max</label><input type="number" step="0.1" min="0" name="expected_kmpl_max" value="<?php echo $e('expected_kmpl_max'); ?>" class="w-full px-3 py-2 border rounded-md">
                    <p class="text-xs text-gray-400 mt-1">Outside = Warning; 25% outside = Critical</p></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max daily KM</label><input type="number" min="0" name="max_daily_km" value="<?php echo $e('max_daily_km'); ?>" class="w-full px-3 py-2 border rounded-md">
                    <p class="text-xs text-gray-400 mt-1">Larger jumps are flagged</p></div>
            </div>
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="index.php?page=fleet_vehicles" class="px-4 py-2 bg-white border rounded-md">Cancel</a>
            <button name="save_vehicle" value="1" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">Save Vehicle</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <?php foreach (['Vehicle', 'Type', 'Fuel', 'Driver', 'Project', 'Odometer', 'Tank', 'Expected KM/L', 'Status', ''] as $h): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo $h; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($vehicles as $v): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><div class="font-medium"><?php echo htmlspecialchars($v['vehicle_number']); ?></div><div class="text-xs text-gray-500"><?php echo htmlspecialchars(trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''))); ?></div></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($v['type'] ?? '—'); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($v['fuel_type'] ?? '—'); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($v['driver_name'] ?? ($v['assigned_driver'] ?: '—')); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($v['project_name'] ?? '—'); ?></td>
                    <td class="px-4 py-3 font-mono"><?php echo fleetNum($v['current_mileage']); ?></td>
                    <td class="px-4 py-3"><?php echo $v['fuel_tank_capacity'] !== null ? fleetNum($v['fuel_tank_capacity']) . ' L' : '<span class="text-gray-400">not set</span>'; ?></td>
                    <td class="px-4 py-3"><?php echo ($v['expected_kmpl_min'] !== null || $v['expected_kmpl_max'] !== null)
                        ? fleetNum($v['expected_kmpl_min'], 1) . ' – ' . fleetNum($v['expected_kmpl_max'], 1)
                        : '<span class="text-gray-400">not set</span>'; ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($v['vehicle_status'] ?: 'Active'); ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <?php if (fleetCan('manage')): ?><a href="index.php?page=fleet_vehicles&edit=<?php echo (int) $v['id']; ?>" class="text-primary mr-2" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                        <a href="index.php?page=vehicle_details&id=<?php echo (int) $v['id']; ?>" class="text-gray-500" title="Full details"><i class="fas fa-external-link-alt"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
