<?php
/**
 * Daily Vehicle Logs - KM per Day Tracking
 */

include_once 'includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_log'])) {
        $opening_km = floatval($_POST['opening_km']);
        $closing_km = floatval($_POST['closing_km']);

        // Validation: Closing KM cannot be less than Opening KM
        if ($closing_km < $opening_km) {
            $error = "Closing KM cannot be less than Opening KM!";
        } else {
            $total_km = $closing_km - $opening_km;

            $stmt = $pdo->prepare("
                INSERT INTO vehicle_daily_logs (
                    vehicle_id, log_date, opening_km, closing_km, total_km,
                    driver_name, route_trip, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            try {
                $stmt->execute([
                    $_POST['vehicle_id'],
                    $_POST['log_date'],
                    $opening_km,
                    $closing_km,
                    $total_km,
                    $_POST['driver_name'],
                    $_POST['route_trip'],
                    $_POST['remarks']
                ]);

                // Update vehicle's current mileage
                $updateStmt = $pdo->prepare("UPDATE vehicles SET current_mileage = ? WHERE id = ?");
                $updateStmt->execute([$closing_km, $_POST['vehicle_id']]);

                $success = "Daily log added successfully! Vehicle mileage updated.";

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "A log already exists for this vehicle on this date!";
                } else {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }

    } elseif (isset($_POST['update_log'])) {
        $opening_km = floatval($_POST['opening_km']);
        $closing_km = floatval($_POST['closing_km']);

        if ($closing_km < $opening_km) {
            $error = "Closing KM cannot be less than Opening KM!";
        } else {
            $total_km = $closing_km - $opening_km;

            $stmt = $pdo->prepare("
                UPDATE vehicle_daily_logs SET 
                    vehicle_id=?, log_date=?, opening_km=?, closing_km=?, total_km=?,
                    driver_name=?, route_trip=?, remarks=?
                WHERE id=?
            ");

            $stmt->execute([
                $_POST['vehicle_id'],
                $_POST['log_date'],
                $opening_km,
                $closing_km,
                $total_km,
                $_POST['driver_name'],
                $_POST['route_trip'],
                $_POST['remarks'],
                $_POST['id']
            ]);

            // Update vehicle's current mileage
            $updateStmt = $pdo->prepare("UPDATE vehicles SET current_mileage = ? WHERE id = ?");
            $updateStmt->execute([$closing_km, $_POST['vehicle_id']]);

            $success = "Log updated successfully!";
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM vehicle_daily_logs WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    $success = "Log deleted successfully!";
}

// Get filter parameters
$filter_vehicle = $_GET['vehicle'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

// Fetch all vehicles for dropdown
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY vehicle_number")->fetchAll();

// Build query with filters
$query = "
    SELECT 
        vdl.*,
        v.vehicle_number,
        v.make,
        v.model
    FROM vehicle_daily_logs vdl
    JOIN vehicles v ON vdl.vehicle_id = v.id
    WHERE 1=1
";

$params = [];

if ($filter_vehicle) {
    $query .= " AND vdl.vehicle_id = ?";
    $params[] = $filter_vehicle;
}

if ($filter_month) {
    // MySQL uses DATE_FORMAT, SQLite uses strftime
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $query .= " AND DATE_FORMAT(vdl.log_date, '%Y-%m') = ?";
    } else {
        $query .= " AND strftime('%Y-%m', vdl.log_date) = ?";
    }
    $params[] = $filter_month;
}

$query .= " ORDER BY vdl.log_date DESC, vdl.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Calculate statistics
$total_km = array_sum(array_column($logs, 'total_km'));
$total_logs = count($logs);

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">📅 Daily Vehicle Logs</h1>
    <p class="text-gray-600 mt-2">Track daily kilometer readings for each vehicle</p>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($error); ?>
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
            <a href="?page=vehicle_daily_logs" class="border-b-2 border-primary text-primary px-6 py-3 font-medium">
                <i class="fas fa-road mr-2"></i>Daily Logs
            </a>
            <a href="?page=vehicle_expenses"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-receipt mr-2"></i>Expenses
            </a>
            <a href="?page=vehicle_fuel"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
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
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-road text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total KM (Filtered)</p>
                <p class="text-2xl font-bold"><?php echo number_format($total_km, 2); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-calendar-check text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Logs</p>
                <p class="text-2xl font-bold"><?php echo $total_logs; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-chart-line text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Average KM/Day</p>
                <p class="text-2xl font-bold">
                    <?php echo $total_logs > 0 ? number_format($total_km / $total_logs, 2) : '0'; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Daily Logs</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add Daily Log
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="vehicle_daily_logs">
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
                    <a href="?page=vehicle_daily_logs"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Log Form -->
        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle <span
                                class="text-red-500">*</span></label>
                        <select name="vehicle_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?php echo $v['id']; ?>">
                                    <?php echo htmlspecialchars($v['vehicle_number'] . ' - ' . $v['make'] . ' ' . $v['model']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Driver Name</label>
                        <input type="text" name="driver_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Opening KM <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="opening_km" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Closing KM <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="closing_km" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Route / Trip</label>
                        <input type="text" name="route_trip" placeholder="e.g., Site A to Site B"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea name="remarks" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_log"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Log
                    </button>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Opening KM</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Closing KM</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total KM</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Driver</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Route</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d M Y', strtotime($log['log_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($log['vehicle_number']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($log['make'] . ' ' . $log['model']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo number_format($log['opening_km'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo number_format($log['closing_km'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-primary">
                                        <?php echo number_format($log['total_km'], 2); ?> KM
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($log['driver_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($log['route_trip'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick='editLog(<?php echo json_encode($log, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page=vehicle_daily_logs&delete=<?php echo $log['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this log?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-road text-4xl mb-2"></i>
                                    <p>No logs found. Add your first daily log to start tracking.</p>
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Daily Log</h3>
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
                            <input type="date" name="log_date" id="edit-log_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Driver Name</label>
                            <input type="text" name="driver_name" id="edit-driver_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Opening KM</label>
                            <input type="number" name="opening_km" id="edit-opening_km" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Closing KM</label>
                            <input type="number" name="closing_km" id="edit-closing_km" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Route / Trip</label>
                            <input type="text" name="route_trip" id="edit-route_trip"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                            <textarea name="remarks" id="edit-remarks" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_log"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Update Log
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
    function editLog(log) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = log.id;
        document.getElementById('edit-vehicle_id').value = log.vehicle_id;
        document.getElementById('edit-log_date').value = log.log_date;
        document.getElementById('edit-driver_name').value = log.driver_name || '';
        document.getElementById('edit-opening_km').value = log.opening_km;
        document.getElementById('edit-closing_km').value = log.closing_km;
        document.getElementById('edit-route_trip').value = log.route_trip || '';
        document.getElementById('edit-remarks').value = log.remarks || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>