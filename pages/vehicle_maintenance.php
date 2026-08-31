<?php
/**
 * Vehicle Maintenance Module
 * Track maintenance history and schedule preventive maintenance
 */

include_once 'includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_maintenance'])) {
        $stmt = $pdo->prepare("
            INSERT INTO vehicle_maintenance (
                vehicle_id, service_date, service_type, details, km_reading,
                amount, next_due_km, garage_name, invoice_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['vehicle_id'],
            $_POST['service_date'],
            $_POST['service_type'],
            $_POST['details'],
            $_POST['km_reading'],
            $_POST['amount'],
            $_POST['next_due_km'],
            $_POST['garage_name'],
            $_POST['invoice_number']
        ]);

        // Create alert for next due if specified
        if (!empty($_POST['next_due_km'])) {
            $alertStmt = $pdo->prepare("
                INSERT INTO vehicle_alerts (vehicle_id, alert_type, alert_message, due_km)
                VALUES (?, ?, ?, ?)
            ");
            $alertStmt->execute([
                $_POST['vehicle_id'],
                $_POST['service_type'] . ' Due',
                $_POST['service_type'] . ' is due at ' . $_POST['next_due_km'] . ' KM',
                $_POST['next_due_km']
            ]);
        }

        $success = "Maintenance record added successfully!";

    } elseif (isset($_POST['update_maintenance'])) {
        $stmt = $pdo->prepare("
            UPDATE vehicle_maintenance SET 
                vehicle_id=?, service_date=?, service_type=?, details=?, km_reading=?,
                amount=?, next_due_km=?, garage_name=?, invoice_number=?
            WHERE id=?
        ");

        $stmt->execute([
            $_POST['vehicle_id'],
            $_POST['service_date'],
            $_POST['service_type'],
            $_POST['details'],
            $_POST['km_reading'],
            $_POST['amount'],
            $_POST['next_due_km'],
            $_POST['garage_name'],
            $_POST['invoice_number'],
            $_POST['id']
        ]);

        $success = "Maintenance record updated successfully!";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM vehicle_maintenance WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    $success = "Maintenance record deleted successfully!";
}

// Get filter parameters
$filter_vehicle = $_GET['vehicle'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Fetch all vehicles for dropdown
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY vehicle_number")->fetchAll();

// Service types
$service_types = [
    'Oil Change',
    'Tyre Change',
    'Battery Replacement',
    'Brake Service',
    'General Service',
    'AC Service',
    'Transmission Service',
    'Engine Repair',
    'Electrical Repair',
    'Body Work',
    'Paint Work',
    'Other'
];

// Build query with filters
$query = "
    SELECT 
        vm.*,
        v.vehicle_number,
        v.make,
        v.model,
        v.current_mileage
    FROM vehicle_maintenance vm
    JOIN vehicles v ON vm.vehicle_id = v.id
    WHERE 1=1
";

$params = [];

if ($filter_vehicle) {
    $query .= " AND vm.vehicle_id = ?";
    $params[] = $filter_vehicle;
}

if ($filter_type) {
    $query .= " AND vm.service_type = ?";
    $params[] = $filter_type;
}

$query .= " ORDER BY vm.service_date DESC, vm.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenance_records = $stmt->fetchAll();

// Calculate statistics
$total_amount = array_sum(array_column($maintenance_records, 'amount'));
$total_records = count($maintenance_records);

// Upcoming maintenance (due soon)
$upcoming = $pdo->query("
    SELECT 
        vm.*,
        v.vehicle_number,
        v.make,
        v.model,
        v.current_mileage,
        (vm.next_due_km - v.current_mileage) as km_remaining
    FROM vehicle_maintenance vm
    JOIN vehicles v ON vm.vehicle_id = v.id
    WHERE vm.next_due_km IS NOT NULL 
    AND vm.next_due_km > v.current_mileage
    AND (vm.next_due_km - v.current_mileage) <= 1000
    ORDER BY km_remaining ASC
    LIMIT 5
")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">🔧 Vehicle Maintenance</h1>
    <p class="text-gray-600 mt-2">Track maintenance history and schedule preventive services</p>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
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
            <a href="?page=vehicle_fuel"
                class="border-b-2 border-transparent text-gray-600 hover:text-gray-900 px-6 py-3 font-medium">
                <i class="fas fa-gas-pump mr-2"></i>Fuel Records
            </a>
            <a href="?page=vehicle_maintenance" class="border-b-2 border-primary text-primary px-6 py-3 font-medium">
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
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-money-bill-wave text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Maintenance Cost</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol(); ?> <?php echo number_format($total_amount, 2); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-tools text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Services</p>
                <p class="text-2xl font-bold"><?php echo $total_records; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Due Soon</p>
                <p class="text-2xl font-bold"><?php echo count($upcoming); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Maintenance -->
<?php if (!empty($upcoming)): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-bell text-yellow-600 mr-2"></i>Upcoming Maintenance (Due within 1000 KM)
        </h3>
        <div class="space-y-3">
            <?php foreach ($upcoming as $item): ?>
                <div class="bg-white rounded p-4 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['vehicle_number']); ?> -
                            <?php echo htmlspecialchars($item['service_type']); ?></p>
                        <p class="text-sm text-gray-600">Due at <?php echo number_format($item['next_due_km'], 0); ?> KM
                            (Current: <?php echo number_format($item['current_mileage'], 0); ?> KM)</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-yellow-600"><?php echo number_format($item['km_remaining'], 0); ?> KM
                        </p>
                        <p class="text-xs text-gray-500">remaining</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content -->
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Maintenance History</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add Maintenance Record
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="vehicle_maintenance">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Types</option>
                        <?php foreach ($service_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $filter_type == $type ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
                <div class="flex items-end">
                    <a href="?page=vehicle_maintenance"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Add Maintenance Form -->
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
                                    (<?php echo number_format($v['current_mileage'], 0); ?> KM)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="service_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Type <span
                                class="text-red-500">*</span></label>
                        <select name="service_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                            <option value="">Select Type</option>
                            <?php foreach ($service_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">KM Reading <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="km_reading" step="0.01" placeholder="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (<?php echo currency_symbol(); ?>)</label>
                        <input type="number" name="amount" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Next Due KM</label>
                        <input type="number" name="next_due_km" step="0.01" placeholder="e.g., 50000"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Garage Name</label>
                        <input type="text" name="garage_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Details</label>
                        <textarea name="details" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Describe the service performed..."></textarea>
                    </div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded p-3">
                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i><strong>Tip:</strong> Set
                        "Next Due KM" to automatically create a reminder alert for future maintenance.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_maintenance"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Maintenance
                    </button>
                </div>
            </form>
        </div>

        <!-- Maintenance Table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">KM Reading</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Due</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Garage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($maintenance_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d M Y', strtotime($record['service_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($record['vehicle_number']); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($record['make'] . ' ' . $record['model']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        <?php echo htmlspecialchars($record['service_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo number_format($record['km_reading'], 0); ?> KM</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-red-600"><?php echo currency_symbol(); ?>
                                        <?php echo number_format($record['amount'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($record['next_due_km']): ?>
                                        <div class="text-sm text-gray-900">
                                            <?php echo number_format($record['next_due_km'], 0); ?> KM</div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($record['garage_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick='editMaintenance(<?php echo json_encode($record, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page=vehicle_maintenance&delete=<?php echo $record['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this maintenance record?')"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($maintenance_records)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-tools text-4xl mb-2"></i>
                                    <p>No maintenance records found. Add your first service record to start tracking.</p>
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Maintenance Record</h3>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service Date</label>
                            <input type="date" name="service_date" id="edit-service_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                            <select name="service_type" id="edit-service_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <?php foreach ($service_types as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">KM Reading</label>
                            <input type="number" name="km_reading" id="edit-km_reading" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (<?php echo currency_symbol(); ?>)</label>
                            <input type="number" name="amount" id="edit-amount" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Next Due KM</label>
                            <input type="number" name="next_due_km" id="edit-next_due_km" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Garage Name</label>
                            <input type="text" name="garage_name" id="edit-garage_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                            <input type="text" name="invoice_number" id="edit-invoice_number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service Details</label>
                            <textarea name="details" id="edit-details" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_maintenance"
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
    function editMaintenance(record) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = record.id;
        document.getElementById('edit-vehicle_id').value = record.vehicle_id;
        document.getElementById('edit-service_date').value = record.service_date;
        document.getElementById('edit-service_type').value = record.service_type;
        document.getElementById('edit-km_reading').value = record.km_reading;
        document.getElementById('edit-amount').value = record.amount || '';
        document.getElementById('edit-next_due_km').value = record.next_due_km || '';
        document.getElementById('edit-garage_name').value = record.garage_name || '';
        document.getElementById('edit-invoice_number').value = record.invoice_number || '';
        document.getElementById('edit-details').value = record.details || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>