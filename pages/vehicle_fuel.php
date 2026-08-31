<?php
/**
 * Vehicle Fuel Records Module
 * Detailed fuel tracking with mileage calculation
 */

include_once 'includes/db.php';
include_once 'includes/functions.php';
include_once 'includes/payment_methods.php';
include_once 'includes/gl_functions.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$payment_methods = get_payment_methods();

// Auto-add payment_method column if it doesn't exist
try {
    if ($driver === 'mysql') {
        $check = $pdo->query("SHOW COLUMNS FROM vehicle_fuel_records LIKE 'payment_method'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE vehicle_fuel_records ADD COLUMN payment_method VARCHAR(100) DEFAULT 'company_cash'");
        }
    } else {
        $pdo->exec("ALTER TABLE vehicle_fuel_records ADD COLUMN payment_method TEXT DEFAULT 'company_cash'");
    }
} catch (Exception $e) {
}

$error = null;
$success = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_fuel'])) {
        try {
            $liters = floatval($_POST['liters'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $odometer_reading = floatval($_POST['odometer_reading'] ?? 0);
            $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
            $fuel_date = trim($_POST['fuel_date'] ?? '');
            $driver_name = trim($_POST['driver_name'] ?? '');
            $fuel_station = trim($_POST['fuel_station'] ?? '');
            $payment_method = trim($_POST['payment_method'] ?? 'company_cash');

            if ($vehicle_id <= 0) {
                throw new Exception('Please select a vehicle.');
            }
            if ($fuel_date === '') {
                throw new Exception('Please select a date.');
            }
            if ($liters <= 0) {
                throw new Exception('Liters must be greater than 0.');
            }
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0.');
            }

            $price_per_liter = $amount / $liters;

            $pdo->beginTransaction();

            // Get previous fuel record for mileage calculation
            $prevStmt = $pdo->prepare("
                SELECT odometer_reading 
                FROM vehicle_fuel_records 
                WHERE vehicle_id = ? 
                ORDER BY fuel_date DESC, id DESC 
                LIMIT 1
            ");
            $prevStmt->execute([$vehicle_id]);
            $prevRecord = $prevStmt->fetch();

            $previous_odometer = $prevRecord ? $prevRecord['odometer_reading'] : 0;
            $mileage = 0;

            if ($previous_odometer > 0 && $odometer_reading > $previous_odometer) {
                $km_driven = $odometer_reading - $previous_odometer;
                $mileage = $km_driven / $liters;
            }

            $stmt = $pdo->prepare("
                INSERT INTO vehicle_fuel_records (
                    vehicle_id, fuel_date, liters, amount, price_per_liter,
                    odometer_reading, driver_name, fuel_station, mileage_km_per_liter, previous_odometer, payment_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $vehicle_id,
                $fuel_date,
                $liters,
                $amount,
                $price_per_liter,
                $odometer_reading,
                $driver_name !== '' ? $driver_name : null,
                $fuel_station !== '' ? $fuel_station : null,
                $mileage,
                $previous_odometer,
                $payment_method
            ]);

            $fuel_id = (int) $pdo->lastInsertId();

            // Update vehicle's current mileage
            $updateStmt = $pdo->prepare("UPDATE vehicles SET current_mileage = ? WHERE id = ?");
            $updateStmt->execute([$odometer_reading, $vehicle_id]);

            // GL posting against selected payment method.
            $vehStmt = $pdo->prepare("SELECT vehicle_number, make, model FROM vehicles WHERE id = ?");
            $vehStmt->execute([$vehicle_id]);
            $vehicle = $vehStmt->fetch();
            $vehicle_label = $vehicle ? trim(($vehicle['vehicle_number'] ?? '') . ' ' . ($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : 'Vehicle';
            $narration = "Vehicle Fuel - " . trim($vehicle_label);
            if ($fuel_station !== '') {
                $narration .= " - " . $fuel_station;
            }

            create_journal_entry(
                $pdo,
                $fuel_date,
                $amount,
                'Fuel',
                get_gl_account_for_payment_method($payment_method),
                $narration,
                null,
                $driver_name !== '' ? $driver_name : null,
                "VEH-FUEL-{$fuel_id}"
            );

            // Card balance impact when card is selected.
            addVehicleFuelCardTransaction($pdo, $fuel_id);

            $pdo->commit();
            $success = "Fuel record added successfully! Mileage: " . number_format($mileage, 2) . " KM/L";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to add fuel record: " . $e->getMessage();
        }

    } elseif (isset($_POST['update_fuel'])) {
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
            $fuel_date = trim($_POST['fuel_date'] ?? '');
            $liters = floatval($_POST['liters'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $odometer_reading = floatval($_POST['odometer_reading'] ?? 0);
            $driver_name = trim($_POST['driver_name'] ?? '');
            $fuel_station = trim($_POST['fuel_station'] ?? '');
            $payment_method = trim($_POST['payment_method'] ?? 'company_cash');

            if ($id <= 0) {
                throw new Exception('Invalid fuel record.');
            }
            if ($vehicle_id <= 0) {
                throw new Exception('Please select a vehicle.');
            }
            if ($fuel_date === '') {
                throw new Exception('Please select a date.');
            }
            if ($liters <= 0) {
                throw new Exception('Liters must be greater than 0.');
            }
            if ($amount <= 0) {
                throw new Exception('Amount must be greater than 0.');
            }

            $price_per_liter = $amount / $liters;

            $pdo->beginTransaction();

            // Remove old voucher/card impact before rebuilding from updated values.
            clearVehicleFuelSideEffects($pdo, $id);

            $stmt = $pdo->prepare("
                UPDATE vehicle_fuel_records SET 
                    vehicle_id=?, fuel_date=?, liters=?, amount=?, price_per_liter=?,
                    odometer_reading=?, driver_name=?, fuel_station=?, payment_method=?
                WHERE id=?
            ");

            $stmt->execute([
                $vehicle_id,
                $fuel_date,
                $liters,
                $amount,
                $price_per_liter,
                $odometer_reading,
                $driver_name !== '' ? $driver_name : null,
                $fuel_station !== '' ? $fuel_station : null,
                $payment_method,
                $id
            ]);

            $updateStmt = $pdo->prepare("UPDATE vehicles SET current_mileage = ? WHERE id = ?");
            $updateStmt->execute([$odometer_reading, $vehicle_id]);

            $vehStmt = $pdo->prepare("SELECT vehicle_number, make, model FROM vehicles WHERE id = ?");
            $vehStmt->execute([$vehicle_id]);
            $vehicle = $vehStmt->fetch();
            $vehicle_label = $vehicle ? trim(($vehicle['vehicle_number'] ?? '') . ' ' . ($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : 'Vehicle';
            $narration = "Vehicle Fuel - " . trim($vehicle_label);
            if ($fuel_station !== '') {
                $narration .= " - " . $fuel_station;
            }

            create_journal_entry(
                $pdo,
                $fuel_date,
                $amount,
                'Fuel',
                get_gl_account_for_payment_method($payment_method),
                $narration,
                null,
                $driver_name !== '' ? $driver_name : null,
                "VEH-FUEL-{$id}"
            );

            addVehicleFuelCardTransaction($pdo, $id);

            $pdo->commit();
            $success = "Fuel record updated successfully!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to update fuel record: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    try {
        $id = (int) $_GET['delete'];
        if ($id <= 0) {
            throw new Exception('Invalid fuel record.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle_fuel_records WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            throw new Exception('Fuel record not found.');
        }

        clearVehicleFuelSideEffects($pdo, $id);

        $stmt = $pdo->prepare("DELETE FROM vehicle_fuel_records WHERE id=?");
        $stmt->execute([$id]);

        // Keep vehicle mileage aligned to latest remaining fuel record.
        $latestStmt = $pdo->prepare("SELECT odometer_reading FROM vehicle_fuel_records WHERE vehicle_id = ? ORDER BY fuel_date DESC, id DESC LIMIT 1");
        $latestStmt->execute([$record['vehicle_id']]);
        $latest = $latestStmt->fetch();
        $latestMileage = $latest ? $latest['odometer_reading'] : 0;

        $updateStmt = $pdo->prepare("UPDATE vehicles SET current_mileage = ? WHERE id = ?");
        $updateStmt->execute([$latestMileage, $record['vehicle_id']]);

        $pdo->commit();
        $success = "Fuel record deleted successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to delete fuel record: " . $e->getMessage();
    }
}

// Get filter parameters
$filter_vehicle = $_GET['vehicle'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

// Fetch all vehicles for dropdown
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY vehicle_number")->fetchAll();

// Build query with filters
$query = "
    SELECT 
        vfr.*,
        v.vehicle_number,
        v.make,
        v.model
    FROM vehicle_fuel_records vfr
    JOIN vehicles v ON vfr.vehicle_id = v.id
    WHERE 1=1
";

$params = [];

if ($filter_vehicle) {
    $query .= " AND vfr.vehicle_id = ?";
    $params[] = $filter_vehicle;
}

if ($filter_month) {
    // MySQL uses DATE_FORMAT, SQLite uses strftime
    if ($driver === 'mysql') {
        $query .= " AND DATE_FORMAT(vfr.fuel_date, '%Y-%m') = ?";
    } else {
        $query .= " AND strftime('%Y-%m', vfr.fuel_date) = ?";
    }
    $params[] = $filter_month;
}

$query .= " ORDER BY vfr.fuel_date DESC, vfr.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$fuel_records = $stmt->fetchAll();

// Calculate statistics
$total_liters = array_sum(array_column($fuel_records, 'liters'));
$total_amount = array_sum(array_column($fuel_records, 'amount'));
$total_records = count($fuel_records);

// Calculate average mileage (excluding zero values)
$mileages = array_filter(array_column($fuel_records, 'mileage_km_per_liter'), fn($m) => $m > 0);
$avg_mileage = !empty($mileages) ? array_sum($mileages) / count($mileages) : 0;

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">⛽ Fuel Records</h1>
    <p class="text-gray-600 mt-2">Track fuel consumption and calculate mileage efficiency</p>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Navigation Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="flex -mb-px whitespace-nowrap">
            <a href="?page=vehicles"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-car mr-2"></i>Vehicle Master
            </a>
            <a href="?page=vehicle_daily_logs"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-road mr-2"></i>Daily Logs
            </a>
            <a href="?page=vehicle_expenses"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-receipt mr-2"></i>Expenses
            </a>
            <a href="?page=vehicle_fuel" class="border-b-2 border-primary text-primary px-6 py-3 font-medium">
                <i class="fas fa-gas-pump mr-2"></i>Fuel Records
            </a>
            <a href="?page=vehicle_maintenance"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-tools mr-2"></i>Maintenance
            </a>
            <a href="?page=vehicle_reports"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Reports
            </a>
        </nav>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-gas-pump text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Liters</p>
                <p class="text-2xl font-bold"><?php echo number_format($total_liters, 2); ?> L</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-money-bill-wave text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Cost</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol(); ?>
                    <?php echo number_format($total_amount, 2); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-chart-line text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Avg Mileage</p>
                <p class="text-2xl font-bold"><?php echo number_format($avg_mileage, 2); ?> KM/L</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-receipt text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Records</p>
                <p class="text-2xl font-bold"><?php echo $total_records; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Fuel Records</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add Fuel Record
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="vehicle_fuel">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle</label>
                    <select name="vehicle" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Vehicles</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $filter_vehicle == $v['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
                <div class="flex items-end">
                    <a href="?page=vehicle_fuel"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Fuel Form -->
        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle <span
                                class="text-red-500">*</span></label>
                        <select name="vehicle_id" id="add-vehicle_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required onchange="updateCurrentMileage(this)">
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?php echo $v['id']; ?>" data-mileage="<?php echo $v['current_mileage']; ?>">
                                    <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                                    (Current: <?php echo number_format($v['current_mileage'], 0); ?> KM)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="fuel_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Liters <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="liters" id="add-liters" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required oninput="calculatePrice()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount
                            (<?php echo currency_symbol(); ?>) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" id="add-amount" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required oninput="calculatePrice()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price per Liter (Auto)</label>
                        <input type="text" id="price_per_liter_display" readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            placeholder="Auto calculated">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Odometer Reading (KM) <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="odometer_reading" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Driver Name</label>
                        <input type="text" name="driver_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fuel Station</label>
                        <input type="text" name="fuel_station" placeholder="e.g., ADNOC, ENOC"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span
                                class="text-red-500">*</span></label>
                        <select name="payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                            <?php foreach ($payment_methods as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $key === 'company_cash' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded p-3">
                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i><strong>Note:</strong>
                        Mileage (KM/L) will be automatically calculated based on the difference from the previous fuel
                        record.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_fuel"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Fuel Record
                    </button>
                </div>
            </form>
        </div>

        <!-- Fuel Records Table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Liters</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price/L</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">KM Reading</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mileage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Station</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($fuel_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d M Y', strtotime($record['fuel_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($record['vehicle_number']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($record['make'] . ' ' . $record['model']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo number_format($record['liters'], 2); ?> L
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-red-600"><?php echo currency_symbol(); ?>
                                        <?php echo number_format($record['amount'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo currency_symbol(); ?>
                                        <?php echo number_format($record['price_per_liter'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo number_format($record['odometer_reading'], 0); ?> KM
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($record['mileage_km_per_liter'] > 0): ?>
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo number_format($record['mileage_km_per_liter'], 2); ?> KM/L
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">First record</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($record['fuel_station'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo (strpos(($record['payment_method'] ?? ''), 'company') !== false) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo htmlspecialchars(get_payment_method_label($record['payment_method'] ?? 'company_cash')); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick='editFuel(<?php echo json_encode($record, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page=vehicle_fuel&delete=<?php echo $record['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this fuel record?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($fuel_records)): ?>
                            <tr>
                                <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-gas-pump text-4xl mb-2"></i>
                                    <p>No fuel records found. Add your first fuel record to start tracking.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Fuel Record</h3>
                    <input type="hidden" id="edit-id" name="id">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle</label>
                            <select name="vehicle_id" id="edit-vehicle_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?php echo $v['id']; ?>">
                                        <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="fuel_date" id="edit-fuel_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Liters</label>
                            <input type="number" name="liters" id="edit-liters" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount
                                (<?php echo currency_symbol(); ?>)</label>
                            <input type="number" name="amount" id="edit-amount" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Odometer Reading (KM)</label>
                            <input type="number" name="odometer_reading" id="edit-odometer_reading" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Driver Name</label>
                            <input type="text" name="driver_name" id="edit-driver_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fuel Station</label>
                            <input type="text" name="fuel_station" id="edit-fuel_station"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" id="edit-payment_method"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php foreach ($payment_methods as $key => $label): ?>
                                    <option value="<?php echo $key; ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_fuel"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Update Record
                    </button>
                    <button type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function calculatePrice() {
        const liters = parseFloat(document.getElementById('add-liters').value) || 0;
        const amount = parseFloat(document.getElementById('add-amount').value) || 0;

        if (liters > 0 && amount > 0) {
            const pricePerLiter = amount / liters;
            document.getElementById('price_per_liter_display').value = '<?php echo currency_symbol(); ?> ' + pricePerLiter.toFixed(2);
        } else {
            document.getElementById('price_per_liter_display').value = '';
        }
    }

    function editFuel(record) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = record.id;
        document.getElementById('edit-vehicle_id').value = record.vehicle_id;
        document.getElementById('edit-fuel_date').value = record.fuel_date;
        document.getElementById('edit-liters').value = record.liters;
        document.getElementById('edit-amount').value = record.amount;
        document.getElementById('edit-odometer_reading').value = record.odometer_reading;
        document.getElementById('edit-driver_name').value = record.driver_name || '';
        document.getElementById('edit-fuel_station').value = record.fuel_station || '';
        document.getElementById('edit-payment_method').value = record.payment_method || 'company_cash';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
