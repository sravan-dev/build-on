<?php
/**
 * Vehicle Details Page
 * Displays comprehensive information about a specific vehicle
 */

include_once 'includes/db.php';

$vehicle_id = $_GET['id'] ?? null;

if (!$vehicle_id) {
    header('Location: ?page=vehicles');
    exit;
}

// Fetch vehicle details
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    header('Location: ?page=vehicles');
    exit;
}

// Fetch related statistics
$stats = [];

// Daily logs count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vehicle_daily_logs WHERE vehicle_id = ?");
$stmt->execute([$vehicle_id]);
$stats['total_logs'] = $stmt->fetchColumn();

// Total expenses
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM vehicle_expenses WHERE vehicle_id = ?");
$stmt->execute([$vehicle_id]);
$stats['total_expenses'] = $stmt->fetchColumn() ?? 0;

// Total fuel cost
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM vehicle_fuel_records WHERE vehicle_id = ?");
$stmt->execute([$vehicle_id]);
$stats['total_fuel'] = $stmt->fetchColumn() ?? 0;

// Total maintenance cost
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM vehicle_maintenance WHERE vehicle_id = ?");
$stmt->execute([$vehicle_id]);
$stats['total_maintenance'] = $stmt->fetchColumn() ?? 0;

// Active alerts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vehicle_alerts WHERE vehicle_id = ? AND is_active = 1");
$stmt->execute([$vehicle_id]);
$stats['active_alerts'] = $stmt->fetchColumn();

// Recent logs
$stmt = $pdo->prepare("SELECT * FROM vehicle_daily_logs WHERE vehicle_id = ? ORDER BY log_date DESC LIMIT 10");
$stmt->execute([$vehicle_id]);
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent expenses
$stmt = $pdo->prepare("SELECT * FROM vehicle_expenses WHERE vehicle_id = ? ORDER BY expense_date DESC LIMIT 10");
$stmt->execute([$vehicle_id]);
$recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="?page=vehicles" class="text-blue-600 hover:text-blue-800 mb-2 inline-block">
                <i class="fas fa-arrow-left mr-2"></i>Back to Vehicles
            </a>
            <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></h1>
            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></p>
        </div>
        <div>
            <?php
            $statusColors = [
                'Active' => 'bg-green-100 text-green-800',
                'Under Repair' => 'bg-yellow-100 text-yellow-800',
                'Sold' => 'bg-gray-100 text-gray-800'
            ];
            $statusColor = $statusColors[$vehicle['vehicle_status']] ?? 'bg-gray-100 text-gray-800';
            ?>
            <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full <?php echo $statusColor; ?>">
                <?php echo htmlspecialchars($vehicle['vehicle_status']); ?>
            </span>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-road text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Logs</p>
                <p class="text-2xl font-bold"><?php echo $stats['total_logs']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-dollar-sign text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Expenses</p>
                <p class="text-2xl font-bold"><?php echo number_format($stats['total_expenses'], 2); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-gas-pump text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Fuel Cost</p>
                <p class="text-2xl font-bold"><?php echo number_format($stats['total_fuel'], 2); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-wrench text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Maintenance</p>
                <p class="text-2xl font-bold"><?php echo number_format($stats['total_maintenance'], 2); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Vehicle Information -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Vehicle Information</h2>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-gray-600">Vehicle Number:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Make:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['make']); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Model:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['model']); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Year:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['year'] ?? 'N/A'); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Fuel Type:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['fuel_type']); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Current Mileage:</dt>
                <dd class="font-semibold"><?php echo number_format($vehicle['current_mileage'], 0); ?> KM</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Additional Details</h2>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-gray-600">Chassis Number:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['chassis_number'] ?? 'N/A'); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Engine Number:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['engine_number'] ?? 'N/A'); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Assigned Driver:</dt>
                <dd class="font-semibold"><?php echo htmlspecialchars($vehicle['assigned_driver'] ?? 'Unassigned'); ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Registration Renewal:</dt>
                <dd class="font-semibold"><?php echo $vehicle['registration_renewal_date'] ? date('d M Y', strtotime($vehicle['registration_renewal_date'])) : 'N/A'; ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Insurance Renewal:</dt>
                <dd class="font-semibold"><?php echo $vehicle['insurance_renewal_date'] ? date('d M Y', strtotime($vehicle['insurance_renewal_date'])) : 'N/A'; ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Purchase Date:</dt>
                <dd class="font-semibold"><?php echo $vehicle['purchase_date'] ? date('d M Y', strtotime($vehicle['purchase_date'])) : 'N/A'; ?></dd>
            </div>
        </dl>
    </div>
</div>

<!-- Recent Activity -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-900">Recent Daily Logs</h2>
        </div>
        <div class="p-6">
            <?php if (empty($recent_logs)): ?>
                <p class="text-gray-500 text-center py-4">No logs recorded yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="border-l-4 border-blue-500 pl-4 py-2">
                            <div class="font-semibold"><?php echo date('d M Y', strtotime($log['log_date'])); ?></div>
                            <div class="text-sm text-gray-600">
                                <?php echo number_format($log['total_km'], 0); ?> KM
                                <?php if ($log['driver_name']): ?>
                                    - <?php echo htmlspecialchars($log['driver_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-900">Recent Expenses</h2>
        </div>
        <div class="p-6">
            <?php if (empty($recent_expenses)): ?>
                <p class="text-gray-500 text-center py-4">No expenses recorded yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_expenses as $expense): ?>
                        <div class="border-l-4 border-red-500 pl-4 py-2">
                            <div class="flex justify-between">
                                <div class="font-semibold"><?php echo htmlspecialchars($expense['expense_type']); ?></div>
                                <div class="font-bold text-red-600"><?php echo number_format($expense['amount'], 2); ?></div>
                            </div>
                            <div class="text-sm text-gray-600"><?php echo date('d M Y', strtotime($expense['expense_date'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
