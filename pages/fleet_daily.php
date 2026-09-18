<?php
/**
 * Vehicle Management — Daily Records (§3, §4, §8, §9, §14, §15, §21).
 *
 * One screen for the daily job: pick the vehicle, the opening odometer fills
 * itself from the previous closing, enter the closing reading and any fuel,
 * attach the receipt, save. Totals, mileage, cost per KM and the alert status
 * are all computed. Below it, the records table with filters and export.
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
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
$confirmations = [];
$posted = [];

/** Parse an optional decimal field; empty stays null, garbage becomes an error. */
$num = static function ($value): ?float {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        throw new Exception('Numbers only in the KM and fuel fields.');
    }
    return (float) $value;
};

// ---------------------------------------------------------------- save / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST;
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new Exception('This form expired. Reload the page and try again.');
        }

        if (isset($_POST['delete_log'])) {
            if (!fleetCan('delete')) {
                throw new Exception('You cannot delete records.');
            }
            $logId = (int) $_POST['delete_log'];
            $old = $pdo->prepare("SELECT * FROM vehicle_daily_logs WHERE id = ?");
            $old->execute([$logId]);
            $oldRow = $old->fetch(PDO::FETCH_ASSOC);
            if (!$oldRow) {
                throw new Exception('Record not found.');
            }

            $pdo->beginTransaction();
            $fuel = $pdo->prepare("SELECT * FROM vehicle_fuel_records WHERE daily_log_id = ?");
            $fuel->execute([$logId]);
            foreach ($fuel->fetchAll(PDO::FETCH_ASSOC) as $f) {
                fleetAudit($pdo, 'fuel', (int) $f['id'], 'delete', $f);
            }
            $pdo->prepare("DELETE FROM vehicle_fuel_records WHERE daily_log_id = ?")->execute([$logId]);
            $pdo->prepare("DELETE FROM vehicle_daily_logs WHERE id = ?")->execute([$logId]);
            fleetAudit($pdo, 'daily_log', $logId, 'delete', $oldRow);
            $pdo->commit();

            $message = 'Record deleted.';
            $posted = [];

        } elseif (isset($_POST['save_log'])) {
            if (!fleetCan('enter')) {
                throw new Exception('You cannot enter daily records.');
            }

            $logId = !empty($_POST['log_id']) ? (int) $_POST['log_id'] : null;
            $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
            $date = (string) ($_POST['log_date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Enter a valid date.');
            }

            $vStmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
            $vStmt->execute([$vehicleId]);
            $vehicle = $vStmt->fetch(PDO::FETCH_ASSOC);
            if (!$vehicle) {
                throw new Exception('Select a vehicle.');
            }

            $existing = null;
            if ($logId) {
                $e = $pdo->prepare("SELECT * FROM vehicle_daily_logs WHERE id = ?");
                $e->execute([$logId]);
                $existing = $e->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw new Exception('Record not found.');
                }
            }

            // Opening KM always comes from the previous closing (§3, §9). A
            // role without KM edit rights cannot override it by posting a value.
            $autoOpening = fleetPreviousClosingKm($pdo, $vehicleId, $date, $logId);
            $opening = $autoOpening;
            if (fleetCan('edit_km') && trim((string) ($_POST['opening_km'] ?? '')) !== '') {
                $opening = $num($_POST['opening_km']);
            }

            $closing = $num($_POST['closing_km'] ?? '');
            // Accounts may not change KM (§16); keep what was there.
            if ($existing && !fleetCan('edit_km')) {
                $opening = $existing['opening_km'] !== null ? (float) $existing['opening_km'] : $opening;
                $closing = $existing['closing_km'] !== null ? (float) $existing['closing_km'] : $closing;
            }

            $litres = $num($_POST['litres'] ?? '') ?? 0.0;
            $rate = $num($_POST['rate'] ?? '') ?? 0.0;

            $check = fleetValidateEntry($pdo, $vehicle, [
                'date' => $date,
                'opening_km' => $opening,
                'closing_km' => $closing,
                'litres' => $litres,
                'rate' => $rate,
            ], $logId);

            if ($check['errors']) {
                throw new Exception(implode(' ', $check['errors']));
            }
            if ($check['confirm'] && empty($_POST['confirmed'])) {
                $confirmations = $check['confirm'];
                throw new Exception('Please confirm before saving.');
            }

            $totalKm = $closing - ($opening ?? $closing);
            $cost = round($litres * $rate, 2);
            $kmpl = $litres > 0 ? fleetKmpl($totalKm, $litres) : null;
            [$alert, $alertNote] = fleetMileageAlert($vehicle, $kmpl);

            $maxDaily = (int) ($vehicle['max_daily_km'] ?? 0);
            if ($alert === 'OK' && $maxDaily > 0 && $totalKm > $maxDaily) {
                $alert = 'Warning';
                $alertNote = sprintf('%s km in one day (limit %s).', number_format($totalKm), number_format($maxDaily));
            }

            $driverId = !empty($_POST['driver_id']) ? (int) $_POST['driver_id'] : ($vehicle['driver_id'] ?? null);
            $driverName = null;
            if ($driverId) {
                $d = $pdo->prepare("SELECT name FROM fleet_drivers WHERE id = ?");
                $d->execute([$driverId]);
                $driverName = $d->fetchColumn() ?: null;
            }
            $projectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;

            $receiptPath = fleetStoreReceipt($_FILES['receipt'] ?? []);

            $pdo->beginTransaction();

            $row = [
                'vehicle_id' => $vehicleId,
                'log_date' => $date,
                'opening_km' => $opening,
                'closing_km' => $closing,
                'total_km' => $totalKm,
                'driver_id' => $driverId,
                'driver_name' => $driverName,
                'project_id' => $projectId,
                'route_trip' => trim((string) ($_POST['route_trip'] ?? '')) ?: null,
                'remarks' => trim((string) ($_POST['remarks'] ?? '')) ?: null,
                'alert_status' => $alert,
                'alert_note' => $alertNote,
            ];

            if ($existing) {
                $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($row)));
                $pdo->prepare("UPDATE vehicle_daily_logs SET $set WHERE id = ?")
                    ->execute(array_merge(array_values($row), [$logId]));
                fleetAudit($pdo, 'daily_log', $logId, 'update', $existing, $row);
            } else {
                $row['created_by'] = $_SESSION['username'] ?? null;
                $cols = implode(', ', array_keys($row));
                $marks = implode(', ', array_fill(0, count($row), '?'));
                $pdo->prepare("INSERT INTO vehicle_daily_logs ($cols) VALUES ($marks)")
                    ->execute(array_values($row));
                $logId = (int) $pdo->lastInsertId();
                fleetAudit($pdo, 'daily_log', $logId, 'create', [], $row);
            }

            // Fuel for the day (§4): one transaction per save, linked to the log.
            if ($litres > 0) {
                $fuel = [
                    'vehicle_id' => $vehicleId,
                    'daily_log_id' => $logId,
                    'fuel_date' => $date,
                    'fuel_type' => $_POST['fuel_type'] ?? ($vehicle['fuel_type'] ?? null),
                    'liters' => $litres,
                    'price_per_liter' => $rate,
                    'amount' => $cost,
                    'odometer_reading' => $closing,
                    'previous_odometer' => $opening,
                    'mileage_km_per_liter' => $kmpl,
                    'driver_name' => $driverName,
                    'fuel_station' => trim((string) ($_POST['fuel_station'] ?? '')) ?: null,
                    'receipt_number' => trim((string) ($_POST['receipt_number'] ?? '')) ?: null,
                    'payment_method' => $_POST['payment_method'] ?? null,
                    'remarks' => trim((string) ($_POST['fuel_remarks'] ?? '')) ?: null,
                    'created_by' => $_SESSION['username'] ?? null,
                ];
                if ($receiptPath) {
                    $fuel['receipt_path'] = $receiptPath;
                }
                $cols = implode(', ', array_keys($fuel));
                $marks = implode(', ', array_fill(0, count($fuel), '?'));
                $pdo->prepare("INSERT INTO vehicle_fuel_records ($cols) VALUES ($marks)")
                    ->execute(array_values($fuel));
                fleetAudit($pdo, 'fuel', (int) $pdo->lastInsertId(), 'create', [], $fuel);
            } elseif ($receiptPath) {
                // A receipt with no litres has nothing to attach to.
                @unlink(__DIR__ . '/../' . $receiptPath);
            }

            // Keep the vehicle's odometer current (§7: current odometer).
            $pdo->prepare("UPDATE vehicles SET current_mileage = GREATEST(COALESCE(current_mileage, 0), ?) WHERE id = ?")
                ->execute([$closing, $vehicleId]);

            $pdo->commit();

            $message = $existing ? 'Record updated.' : 'Record saved.';
            if ($alert !== 'OK') {
                $message .= " Flagged {$alert}: {$alertNote}";
            }
            $posted = [];
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!$confirmations) {
            $error = $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------- data for view
$vehicles = $pdo->query("SELECT id, vehicle_number, name, fuel_type, vehicle_status FROM vehicles ORDER BY vehicle_number")->fetchAll(PDO::FETCH_ASSOC);
$drivers = $pdo->query("SELECT id, name FROM fleet_drivers WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Filters (§8)
$f = [
    'from' => $_GET['from'] ?? date('Y-m-01'),
    'to' => $_GET['to'] ?? date('Y-m-d'),
    'vehicle' => (int) ($_GET['vehicle'] ?? 0),
    'driver' => (int) ($_GET['driver'] ?? 0),
    'project' => (int) ($_GET['project'] ?? 0),
    'status' => $_GET['status'] ?? '',
    'q' => trim((string) ($_GET['q'] ?? '')),
];
foreach (['from', 'to'] as $k) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
        $f[$k] = $k === 'from' ? date('Y-m-01') : date('Y-m-d');
    }
}

$where = ["l.log_date BETWEEN ? AND ?"];
$params = [$f['from'], $f['to']];
if ($f['vehicle']) { $where[] = 'l.vehicle_id = ?'; $params[] = $f['vehicle']; }
if ($f['driver']) { $where[] = 'l.driver_id = ?'; $params[] = $f['driver']; }
if ($f['project']) { $where[] = 'l.project_id = ?'; $params[] = $f['project']; }
if (in_array($f['status'], ['OK', 'Warning', 'Critical'], true)) { $where[] = 'l.alert_status = ?'; $params[] = $f['status']; }
if ($f['q'] !== '') {
    $where[] = '(v.vehicle_number LIKE ? OR l.driver_name LIKE ? OR l.remarks LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like);
}

$sort = $_GET['sort'] ?? 'date_desc';
$orders = [
    'date_desc' => 'l.log_date DESC, l.id DESC',
    'date_asc' => 'l.log_date ASC, l.id ASC',
    'km_desc' => 'l.total_km DESC',
    'vehicle' => 'v.vehicle_number ASC, l.log_date DESC',
];
$orderBy = $orders[$sort] ?? $orders['date_desc'];

$stmt = $pdo->prepare("
    SELECT l.*, v.vehicle_number, p.name AS project_name,
           COALESCE(fx.litres, 0) AS fuel_litres,
           COALESCE(fx.cost, 0) AS fuel_cost,
           fx.receipt_path
    FROM vehicle_daily_logs l
    JOIN vehicles v ON v.id = l.vehicle_id
    LEFT JOIN projects p ON p.id = l.project_id
    LEFT JOIN (
        SELECT daily_log_id, SUM(liters) AS litres, SUM(amount) AS cost, MAX(receipt_path) AS receipt_path
        FROM vehicle_fuel_records WHERE daily_log_id IS NOT NULL GROUP BY daily_log_id
    ) fx ON fx.daily_log_id = l.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $orderBy
    LIMIT 500");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editing = null;
if (!empty($_GET['edit'])) {
    $e = $pdo->prepare("SELECT * FROM vehicle_daily_logs WHERE id = ?");
    $e->execute([(int) $_GET['edit']]);
    $editing = $e->fetch(PDO::FETCH_ASSOC) ?: null;
}

$val = static function (string $key, $fallback = '') use ($posted, $editing) {
    if (array_key_exists($key, $posted)) {
        return $posted[$key];
    }
    if ($editing && array_key_exists($key, $editing)) {
        return $editing[$key];
    }
    return $fallback;
};

$exportQuery = http_build_query(array_merge(['page' => 'fleet_export'], array_filter($f, static fn($v) => $v !== '' && $v !== 0)));
$badge = [
    'OK' => 'bg-green-100 text-green-800',
    'Warning' => 'bg-yellow-100 text-yellow-800',
    'Critical' => 'bg-red-100 text-red-800',
];
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end md:justify-between gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Daily Vehicle Records</h1>
        <p class="text-gray-600 mt-2">Select vehicle, enter closing KM and any fuel, attach the receipt, save.</p>
    </div>
    <a href="index.php?page=fleet_dashboard" class="text-primary hover:underline text-sm"><i class="fas fa-chart-line mr-1"></i>Fleet dashboard</a>
</div>

<?php if ($message): ?>
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (fleetCan('enter')): ?>
<!-- Entry form: the whole V1 workflow on one mobile-friendly card (§15, §21) -->
<div class="bg-white rounded-lg shadow-md p-4 md:p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4"><?php echo $editing ? 'Edit Daily Record' : 'Add Daily Record'; ?></h2>

    <?php if ($confirmations): ?>
        <div class="mb-4 px-4 py-3 bg-yellow-50 border border-yellow-300 text-yellow-900 rounded">
            <p class="font-semibold mb-1">Check before saving:</p>
            <ul class="list-disc ml-5 text-sm">
                <?php foreach ($confirmations as $c): ?>
                    <li><?php echo htmlspecialchars($c); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="text-sm mt-2">If the figures are right, press <strong>Confirm &amp; Save</strong>.</p>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="fleet-entry" class="space-y-4">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="log_id" value="<?php echo (int) $editing['id']; ?>">
        <?php endif; ?>
        <?php if ($confirmations): ?>
            <input type="hidden" name="confirmed" value="1">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="log_date" id="fl-date" required
                    value="<?php echo htmlspecialchars((string) $val('log_date', date('Y-m-d'))); ?>"
                    class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle <span class="text-red-500">*</span></label>
                <select name="vehicle_id" id="fl-vehicle" required class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                    <option value="">Select vehicle</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?php echo $v['id']; ?>" <?php echo (int) $val('vehicle_id') === (int) $v['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['vehicle_number'] . ($v['name'] ? ' — ' . $v['name'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Driver</label>
                <select name="driver_id" id="fl-driver" class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                    <option value="">Assigned driver</option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo (int) $val('driver_id') === (int) $d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Project / Location</label>
                <select name="project_id" id="fl-project" class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                    <option value="">—</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo (int) $val('project_id') === (int) $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 md:gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Opening KM</label>
                <input type="number" step="0.1" min="0" name="opening_km" id="fl-opening"
                    value="<?php echo htmlspecialchars((string) $val('opening_km')); ?>"
                    <?php echo fleetCan('edit_km') ? '' : 'readonly'; ?>
                    class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md bg-gray-50">
                <p class="text-xs text-gray-400 mt-1" id="fl-opening-hint">From previous closing</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Closing KM <span class="text-red-500">*</span></label>
                <input type="number" step="0.1" min="0" name="closing_km" id="fl-closing" required inputmode="decimal"
                    value="<?php echo htmlspecialchars((string) $val('closing_km')); ?>"
                    class="w-full px-3 py-3 md:py-2 border-2 border-primary rounded-md text-lg font-semibold">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total KM</label>
                <div id="fl-total" class="px-3 py-3 md:py-2 bg-gray-50 border rounded-md text-lg font-bold text-primary">—</div>
            </div>
        </div>

        <details class="border rounded-md" <?php echo ($val('litres') !== '' || $confirmations) ? 'open' : ''; ?>>
            <summary class="px-4 py-3 cursor-pointer font-medium text-gray-800"><i class="fas fa-gas-pump mr-2 text-primary"></i>Fuel (optional)</summary>
            <div class="p-4 pt-0 space-y-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                        <select name="fuel_type" id="fl-fueltype" class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                            <?php foreach (['Petrol', 'Diesel', 'Other'] as $ft): ?>
                                <option <?php echo $val('fuel_type') === $ft ? 'selected' : ''; ?>><?php echo $ft; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Litres</label>
                        <input type="number" step="0.01" min="0" name="litres" id="fl-litres" inputmode="decimal"
                            value="<?php echo htmlspecialchars((string) $val('litres')); ?>"
                            class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rate / L (QAR)</label>
                        <input type="number" step="0.01" min="0" name="rate" id="fl-rate" inputmode="decimal"
                            value="<?php echo htmlspecialchars((string) $val('rate')); ?>"
                            class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost</label>
                        <div id="fl-cost" class="px-3 py-3 md:py-2 bg-gray-50 border rounded-md font-bold">—</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fuel Station</label>
                        <input type="text" name="fuel_station" list="fl-stations" value="<?php echo htmlspecialchars((string) $val('fuel_station')); ?>"
                            class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                        <datalist id="fl-stations">
                            <option value="WOQOD"><option value="Al Maha"><option value="Qatar Fuel">
                        </datalist>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Receipt Number</label>
                        <input type="text" name="receipt_number" value="<?php echo htmlspecialchars((string) $val('receipt_number')); ?>"
                            class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment</label>
                        <select name="payment_method" class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
                            <?php foreach (['Cash', 'Card', 'Company Account'] as $pm): ?>
                                <option <?php echo $val('payment_method') === $pm ? 'selected' : ''; ?>><?php echo $pm; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Receipt (photo or PDF)</label>
                    <!-- capture lets a phone open the camera directly (§14, §15) -->
                    <input type="file" name="receipt" accept="image/jpeg,image/png,application/pdf" capture="environment"
                        class="w-full text-sm">
                </div>
                <div id="fl-mileage" class="hidden text-sm px-3 py-2 rounded-md"></div>
            </div>
        </details>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="route_trip" placeholder="Route / trip (optional)" value="<?php echo htmlspecialchars((string) $val('route_trip')); ?>"
                class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
            <input type="text" name="remarks" placeholder="Remarks (optional)" value="<?php echo htmlspecialchars((string) $val('remarks')); ?>"
                class="w-full px-3 py-3 md:py-2 border border-gray-300 rounded-md">
        </div>

        <div class="flex flex-col-reverse md:flex-row md:justify-end gap-3">
            <?php if ($editing): ?>
                <a href="index.php?page=fleet_daily" class="text-center px-4 py-3 md:py-2 bg-white border rounded-md">Cancel</a>
            <?php endif; ?>
            <button type="submit" name="save_log" value="1"
                class="w-full md:w-auto bg-primary hover:bg-secondary text-white px-8 py-3 md:py-2 rounded-md font-semibold text-lg md:text-base">
                <i class="fas fa-save mr-2"></i><?php echo $confirmations ? 'Confirm &amp; Save' : 'Save'; ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Records (§8) -->
<div class="bg-white rounded-lg shadow-md">
    <div class="p-4 md:p-6 border-b">
        <form method="get" class="grid grid-cols-2 md:grid-cols-8 gap-3 items-end">
            <input type="hidden" name="page" value="fleet_daily">
            <div><label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="from" value="<?php echo htmlspecialchars($f['from']); ?>" class="w-full px-2 py-2 border rounded-md text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="to" value="<?php echo htmlspecialchars($f['to']); ?>" class="w-full px-2 py-2 border rounded-md text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">Vehicle</label>
                <select name="vehicle" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option>
                    <?php foreach ($vehicles as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo $f['vehicle'] === (int) $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['vehicle_number']); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-xs text-gray-500 mb-1">Driver</label>
                <select name="driver" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option>
                    <?php foreach ($drivers as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo $f['driver'] === (int) $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-xs text-gray-500 mb-1">Project</label>
                <select name="project" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option>
                    <?php foreach ($projects as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo $f['project'] === (int) $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-xs text-gray-500 mb-1">Status</label>
                <select name="status" class="w-full px-2 py-2 border rounded-md text-sm"><option value="">All</option>
                    <?php foreach (['OK', 'Warning', 'Critical'] as $st): ?><option <?php echo $f['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-xs text-gray-500 mb-1">Search</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($f['q']); ?>" class="w-full px-2 py-2 border rounded-md text-sm"></div>
            <div class="flex gap-2">
                <button class="flex-1 bg-primary text-white px-3 py-2 rounded-md text-sm">Filter</button>
            </div>
        </form>
        <div class="flex flex-wrap gap-2 mt-3 text-sm">
            <span class="text-gray-500">Sort:</span>
            <?php foreach (['date_desc' => 'Newest', 'date_asc' => 'Oldest', 'km_desc' => 'Most KM', 'vehicle' => 'Vehicle'] as $k => $label): ?>
                <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => 'fleet_daily', 'sort' => $k]))); ?>"
                    class="px-2 py-0.5 rounded <?php echo $sort === $k ? 'bg-primary text-white' : 'text-primary hover:underline'; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
            <?php if (fleetCan('reports')): ?>
                <span class="flex-1"></span>
                <a href="index.php?<?php echo htmlspecialchars($exportQuery); ?>" class="text-primary hover:underline"><i class="fas fa-file-csv mr-1"></i>CSV / Excel</a>
                <button type="button" onclick="window.print()" class="text-primary hover:underline"><i class="fas fa-print mr-1"></i>Print</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Date', 'Vehicle', 'Driver', 'Opening KM', 'Closing KM', 'Total KM', 'Fuel L', 'Fuel Cost', 'KM/L', 'Status', ''] as $h): ?>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!$records): ?>
                    <tr><td colspan="11" class="px-3 py-8 text-center text-gray-500">No records for these filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($records as $r):
                    $kmpl = (float) $r['fuel_litres'] > 0 ? fleetKmpl((float) $r['total_km'], (float) $r['fuel_litres']) : null; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-3 whitespace-nowrap"><?php echo date('d-M-y', strtotime($r['log_date'])); ?></td>
                        <td class="px-3 py-3 font-medium whitespace-nowrap"><?php echo htmlspecialchars($r['vehicle_number']); ?></td>
                        <td class="px-3 py-3"><?php echo htmlspecialchars($r['driver_name'] ?? '—'); ?></td>
                        <td class="px-3 py-3 text-right"><?php echo fleetNum($r['opening_km']); ?></td>
                        <td class="px-3 py-3 text-right"><?php echo fleetNum($r['closing_km']); ?></td>
                        <td class="px-3 py-3 text-right font-semibold"><?php echo fleetNum($r['total_km']); ?></td>
                        <td class="px-3 py-3 text-right"><?php echo (float) $r['fuel_litres'] > 0 ? fleetNum($r['fuel_litres'], 2) : '—'; ?></td>
                        <td class="px-3 py-3 text-right"><?php echo (float) $r['fuel_cost'] > 0 ? fleetNum($r['fuel_cost'], 2) : '—'; ?></td>
                        <td class="px-3 py-3 text-right"><?php echo $kmpl !== null ? number_format($kmpl, 2) : '—'; ?></td>
                        <td class="px-3 py-3">
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?php echo $badge[$r['alert_status']] ?? $badge['OK']; ?>"
                                title="<?php echo htmlspecialchars($r['alert_note'] ?? ''); ?>">
                                <?php echo htmlspecialchars($r['alert_status'] ?: 'OK'); ?>
                            </span>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-right">
                            <?php if ($r['receipt_path']): ?>
                                <a href="<?php echo htmlspecialchars($r['receipt_path']); ?>" target="_blank" rel="noopener" class="text-gray-600 hover:text-gray-900 mr-2" title="View receipt"><i class="fas fa-receipt"></i></a>
                            <?php endif; ?>
                            <?php if (fleetCan('enter')): ?>
                                <a href="index.php?page=fleet_daily&edit=<?php echo (int) $r['id']; ?>" class="text-primary mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if (fleetCan('delete')): ?>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this record and its fuel entries?')">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <button name="delete_log" value="<?php echo (int) $r['id']; ?>" class="text-red-600" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Live figures while typing. The server recomputes everything on save;
    // this is only so the person entering sees the result immediately.
    (function () {
        const $ = (id) => document.getElementById(id);
        const vehicle = $('fl-vehicle'), date = $('fl-date');
        const opening = $('fl-opening'), closing = $('fl-closing');
        const litres = $('fl-litres'), rate = $('fl-rate');
        if (!vehicle) return;

        const editing = <?php echo $editing ? 'true' : 'false'; ?>;

        function recalc() {
            const o = parseFloat(opening.value), c = parseFloat(closing.value);
            const l = parseFloat(litres && litres.value), r = parseFloat(rate && rate.value);
            const km = (!isNaN(o) && !isNaN(c)) ? c - o : NaN;

            $('fl-total').textContent = isNaN(km) ? '—' : km.toLocaleString();
            $('fl-total').className = 'px-3 py-3 md:py-2 border rounded-md text-lg font-bold ' +
                (km < 0 ? 'bg-red-50 text-red-600' : 'bg-gray-50 text-primary');

            if ($('fl-cost')) {
                $('fl-cost').textContent = (!isNaN(l) && !isNaN(r)) ? (l * r).toFixed(2) : '—';
            }

            const box = $('fl-mileage');
            if (box) {
                if (!isNaN(km) && km > 0 && !isNaN(l) && l > 0) {
                    const kmpl = km / l;
                    let text = kmpl.toFixed(2) + ' KM/L · ' + (l / km * 100).toFixed(2) + ' L/100KM';
                    if (!isNaN(r)) text += ' · QAR ' + (l * r / km).toFixed(3) + ' per KM';
                    box.textContent = text;
                    box.className = 'text-sm px-3 py-2 rounded-md bg-orange-50 text-orange-900';
                } else {
                    box.className = 'hidden';
                }
            }
        }

        async function loadOpening() {
            if (!vehicle.value || editing) { recalc(); return; }
            try {
                const res = await fetch('ajax/fleet_opening_km.php?vehicle_id=' + encodeURIComponent(vehicle.value) +
                    '&date=' + encodeURIComponent(date.value));
                const data = await res.json();
                if (!data.success) return;

                if (data.opening_km !== null && data.opening_km !== undefined) {
                    opening.value = data.opening_km;
                    $('fl-opening-hint').textContent = 'From previous closing';
                } else {
                    $('fl-opening-hint').textContent = 'No previous reading — enter it';
                }
                if (data.already_recorded) {
                    $('fl-opening-hint').textContent = 'Already recorded today — you will be asked to confirm';
                }
                if (data.driver_id && $('fl-driver') && !$('fl-driver').value) $('fl-driver').value = data.driver_id;
                if (data.project_id && $('fl-project') && !$('fl-project').value) $('fl-project').value = data.project_id;
                if (data.fuel_type && $('fl-fueltype')) $('fl-fueltype').value = data.fuel_type;
            } catch (e) { /* the server still fills opening KM on save */ }
            recalc();
            closing.focus();
        }

        vehicle.addEventListener('change', loadOpening);
        date.addEventListener('change', loadOpening);
        [opening, closing, litres, rate].forEach(el => el && el.addEventListener('input', recalc));
        recalc();
    })();
</script>
