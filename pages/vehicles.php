<?php
/**
 * Vehicle Management Module - Main Page
 * Comprehensive vehicle fleet management with master data
 */

include_once 'includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_vehicle'])) {
        $stmt = $pdo->prepare("
            INSERT INTO vehicles (
                vehicle_number, model, make, year, chassis_number, engine_number,
                fuel_type, assigned_driver, registration_renewal_date, insurance_renewal_date,
                purchase_date, purchase_price, current_mileage, vehicle_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['vehicle_number'],
            $_POST['model'],
            $_POST['make'],
            $_POST['year'],
            $_POST['chassis_number'],
            $_POST['engine_number'],
            $_POST['fuel_type'],
            $_POST['assigned_driver'],
            $_POST['registration_renewal_date'],
            $_POST['insurance_renewal_date'],
            $_POST['purchase_date'],
            $_POST['purchase_price'],
            $_POST['current_mileage'],
            $_POST['vehicle_status']
        ]);

        $success = "Vehicle added successfully!";

    } elseif (isset($_POST['update_vehicle'])) {
        $stmt = $pdo->prepare("
            UPDATE vehicles SET 
                vehicle_number=?, model=?, make=?, year=?, chassis_number=?, engine_number=?,
                fuel_type=?, assigned_driver=?, registration_renewal_date=?, insurance_renewal_date=?,
                purchase_date=?, purchase_price=?, current_mileage=?, vehicle_status=?,
                updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ");

        $stmt->execute([
            $_POST['vehicle_number'],
            $_POST['model'],
            $_POST['make'],
            $_POST['year'],
            $_POST['chassis_number'],
            $_POST['engine_number'],
            $_POST['fuel_type'],
            $_POST['assigned_driver'],
            $_POST['registration_renewal_date'],
            $_POST['insurance_renewal_date'],
            $_POST['purchase_date'],
            $_POST['purchase_price'],
            $_POST['current_mileage'],
            $_POST['vehicle_status'],
            $_POST['id']
        ]);

        $success = "Vehicle updated successfully!";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    $success = "Vehicle deleted successfully!";
}

// Fetch all vehicles with statistics
$vehicles = $pdo->query("
    SELECT 
        v.*,
        (SELECT COUNT(*) FROM vehicle_daily_logs WHERE vehicle_id = v.id) as total_logs,
        (SELECT SUM(amount) FROM vehicle_expenses WHERE vehicle_id = v.id) as total_expenses,
        (SELECT SUM(amount) FROM vehicle_fuel_records WHERE vehicle_id = v.id) as total_fuel_cost,
        (SELECT COUNT(*) FROM vehicle_alerts WHERE vehicle_id = v.id AND is_active = 1) as active_alerts
    FROM vehicles v
    ORDER BY v.id DESC
")->fetchAll();

// Get active alerts count
$total_alerts = $pdo->query("SELECT COUNT(*) FROM vehicle_alerts WHERE is_active = 1")->fetchColumn();

?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">🚗 Vehicle Fleet Management</h1>
            <p class="text-gray-600 mt-2">Comprehensive vehicle tracking, maintenance, and expense management</p>
        </div>
        <?php if ($total_alerts > 0): ?>
            <a href="?page=vehicle_alerts"
                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors">
                <i class="fas fa-bell mr-1 md:mr-2"></i><?php echo $total_alerts; ?> Active Alerts
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-car text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Vehicles</p>
                <p class="text-2xl font-bold"><?php echo count($vehicles); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Active Vehicles</p>
                <p class="text-2xl font-bold">
                    <?php echo count(array_filter($vehicles, fn($v) => $v['vehicle_status'] == 'Active')); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-wrench text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Under Repair</p>
                <p class="text-2xl font-bold">
                    <?php echo count(array_filter($vehicles, fn($v) => $v['vehicle_status'] == 'Under Repair')); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-bell text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Active Alerts</p>
                <p class="text-2xl font-bold"><?php echo $total_alerts; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="flex -mb-px whitespace-nowrap">
            <a href="?page=vehicles" class="border-b-2 border-primary text-primary px-6 py-3 font-medium">
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

<!-- Main Content -->
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Vehicle Master Data</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add New Vehicle
                </button>
            </div>
        </div>

        <!-- Add Vehicle Form -->
        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Number / Plate <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="vehicle_number" placeholder="e.g., ABC-1234"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Make <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="make" placeholder="e.g., Toyota"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Model <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="model" placeholder="e.g., Hilux"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <input type="number" name="year" placeholder="2024" min="1900" max="2100"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Chassis Number</label>
                        <input type="text" name="chassis_number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Engine Number</label>
                        <input type="text" name="engine_number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                        <select name="fuel_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="Petrol">Petrol</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Electric">Electric</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Driver</label>
                        <input type="text" name="assigned_driver"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Status</label>
                        <select name="vehicle_status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="Active">Active</option>
                            <option value="Under Repair">Under Repair</option>
                            <option value="Sold">Sold</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Registration Renewal Date</label>
                        <input type="date" name="registration_renewal_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Insurance Renewal Date</label>
                        <input type="date" name="insurance_renewal_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                        <input type="date" name="purchase_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Price</label>
                        <input type="number" name="purchase_price" step="0.01" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Mileage (KM)</label>
                        <input type="number" name="current_mileage" step="0.01" placeholder="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_vehicle"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Vehicle
                    </button>
                </div>
            </form>
        </div>

        <!-- Vehicle List -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Driver</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Mileage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Alerts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">
                                        <?php echo htmlspecialchars($vehicle['vehicle_number']); ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></div>
                                    <div class="text-xs text-gray-500">Year:
                                        <?php echo htmlspecialchars($vehicle['year'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-xs text-gray-600">
                                        <div><strong>Fuel:</strong> <?php echo htmlspecialchars($vehicle['fuel_type']); ?>
                                        </div>
                                        <div><strong>Chassis:</strong>
                                            <?php echo htmlspecialchars($vehicle['chassis_number'] ?? 'N/A'); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($vehicle['assigned_driver'] ?? 'Unassigned'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">
                                        <?php echo number_format($vehicle['current_mileage'], 0); ?> KM</div>
                                    <div class="text-xs text-gray-500"><?php echo $vehicle['total_logs']; ?> logs</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'Active' => 'bg-green-100 text-green-800',
                                        'Under Repair' => 'bg-yellow-100 text-yellow-800',
                                        'Sold' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $statusColor = $statusColors[$vehicle['vehicle_status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusColor; ?>">
                                        <?php echo htmlspecialchars($vehicle['vehicle_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($vehicle['active_alerts'] > 0): ?>
                                        <span
                                            class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-bell mr-1"></i><?php echo $vehicle['active_alerts']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No alerts</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="?page=vehicle_details&id=<?php echo $vehicle['id']; ?>"
                                        class="text-blue-600 hover:text-blue-900 mr-3" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick='editVehicle(<?php echo json_encode($vehicle, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?page=vehicles&delete=<?php echo $vehicle['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure? This will delete all related logs, expenses, and records.')"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($vehicles)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-car text-4xl mb-2"></i>
                                    <p>No vehicles registered yet. Click "Add New Vehicle" to get started.</p>
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
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Vehicle</h3>
                    <input type="hidden" id="edit-id" name="id">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Number</label>
                            <input type="text" name="vehicle_number" id="edit-vehicle_number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Make</label>
                            <input type="text" name="make" id="edit-make"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                            <input type="text" name="model" id="edit-model"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                            <input type="number" name="year" id="edit-year"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Chassis Number</label>
                            <input type="text" name="chassis_number" id="edit-chassis_number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Engine Number</label>
                            <input type="text" name="engine_number" id="edit-engine_number"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                            <select name="fuel_type" id="edit-fuel_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="Petrol">Petrol</option>
                                <option value="Diesel">Diesel</option>
                                <option value="Electric">Electric</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Driver</label>
                            <input type="text" name="assigned_driver" id="edit-assigned_driver"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Status</label>
                            <select name="vehicle_status" id="edit-vehicle_status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="Active">Active</option>
                                <option value="Under Repair">Under Repair</option>
                                <option value="Sold">Sold</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Registration Renewal</label>
                            <input type="date" name="registration_renewal_date" id="edit-registration_renewal_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Insurance Renewal</label>
                            <input type="date" name="insurance_renewal_date" id="edit-insurance_renewal_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                            <input type="date" name="purchase_date" id="edit-purchase_date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Price</label>
                            <input type="number" name="purchase_price" id="edit-purchase_price" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Current Mileage (KM)</label>
                            <input type="number" name="current_mileage" id="edit-current_mileage" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_vehicle"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Update Vehicle
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
    function editVehicle(vehicle) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = vehicle.id;
        document.getElementById('edit-vehicle_number').value = vehicle.vehicle_number;
        document.getElementById('edit-make').value = vehicle.make;
        document.getElementById('edit-model').value = vehicle.model;
        document.getElementById('edit-year').value = vehicle.year || '';
        document.getElementById('edit-chassis_number').value = vehicle.chassis_number || '';
        document.getElementById('edit-engine_number').value = vehicle.engine_number || '';
        document.getElementById('edit-fuel_type').value = vehicle.fuel_type;
        document.getElementById('edit-assigned_driver').value = vehicle.assigned_driver || '';
        document.getElementById('edit-vehicle_status').value = vehicle.vehicle_status;
        document.getElementById('edit-registration_renewal_date').value = vehicle.registration_renewal_date || '';
        document.getElementById('edit-insurance_renewal_date').value = vehicle.insurance_renewal_date || '';
        document.getElementById('edit-purchase_date').value = vehicle.purchase_date || '';
        document.getElementById('edit-purchase_price').value = vehicle.purchase_price || '';
        document.getElementById('edit-current_mileage').value = vehicle.current_mileage || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>