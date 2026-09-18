<?php
/**
 * Vehicle Management — Drivers (§13).
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new Exception('This form expired. Reload the page and try again.');
        }
        if (!fleetCan('manage')) {
            throw new Exception('You cannot manage drivers.');
        }

        if (isset($_POST['save_driver'])) {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new Exception('Driver name is required.');
            }
            $expiry = trim((string) ($_POST['license_expiry'] ?? ''));
            if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
                throw new Exception('Enter a valid licence expiry date.');
            }

            $row = [
                'name' => $name,
                'employee_id' => !empty($_POST['employee_id']) ? (int) $_POST['employee_id'] : null,
                'mobile' => trim((string) ($_POST['mobile'] ?? '')) ?: null,
                'license_number' => trim((string) ($_POST['license_number'] ?? '')) ?: null,
                'license_expiry' => $expiry ?: null,
                'assigned_vehicle_id' => !empty($_POST['assigned_vehicle_id']) ? (int) $_POST['assigned_vehicle_id'] : null,
                'status' => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            ];

            $pdo->beginTransaction();
            $driverId = (int) ($_POST['driver_id'] ?? 0);
            if ($driverId) {
                $old = $pdo->prepare("SELECT * FROM fleet_drivers WHERE id = ?");
                $old->execute([$driverId]);
                $oldRow = $old->fetch(PDO::FETCH_ASSOC);
                if (!$oldRow) {
                    throw new Exception('Driver not found.');
                }
                $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($row)));
                $pdo->prepare("UPDATE fleet_drivers SET $set WHERE id = ?")->execute(array_merge(array_values($row), [$driverId]));
                fleetAudit($pdo, 'driver', $driverId, 'update', $oldRow, $row);
            } else {
                $cols = implode(', ', array_keys($row));
                $marks = implode(', ', array_fill(0, count($row), '?'));
                $pdo->prepare("INSERT INTO fleet_drivers ($cols) VALUES ($marks)")->execute(array_values($row));
                $driverId = (int) $pdo->lastInsertId();
                fleetAudit($pdo, 'driver', $driverId, 'create', [], $row);
            }

            // Assigning a vehicle here also makes this the vehicle's driver, so
            // daily entries auto-populate the right name (§3).
            if ($row['assigned_vehicle_id']) {
                $pdo->prepare("UPDATE vehicles SET driver_id = ?, assigned_driver = ? WHERE id = ?")
                    ->execute([$driverId, $name, $row['assigned_vehicle_id']]);
            }
            $pdo->commit();
            $message = 'Driver saved.';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$drivers = $pdo->query("SELECT d.*, v.vehicle_number, e.name AS employee_name, e.emp_id
                        FROM fleet_drivers d
                        LEFT JOIN vehicles v ON v.id = d.assigned_vehicle_id
                        LEFT JOIN employees e ON e.id = d.employee_id
                        ORDER BY d.status = 'active' DESC, d.name")->fetchAll(PDO::FETCH_ASSOC);
$vehicles = $pdo->query("SELECT id, vehicle_number FROM vehicles ORDER BY vehicle_number")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT id, name, emp_id FROM employees WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$editing = null;
if (!empty($_GET['edit'])) {
    foreach ($drivers as $d) {
        if ((int) $d['id'] === (int) $_GET['edit']) {
            $editing = $d;
        }
    }
}
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Drivers</h1>
    <p class="text-gray-600 mt-2">Driver records, licences and vehicle assignment.</p>
</div>

<?php if ($message): ?><div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (fleetCan('manage')): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4"><?php echo $editing ? 'Edit Driver' : 'Add Driver'; ?></h2>
    <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <?php if ($editing): ?><input type="hidden" name="driver_id" value="<?php echo (int) $editing['id']; ?>"><?php endif; ?>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded-md"><option value="">Not an employee</option>
                <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>" <?php echo (int) ($editing['employee_id'] ?? 0) === (int) $e['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(($e['emp_id'] ? $e['emp_id'] . ' — ' : '') . $e['name']); ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
            <input type="text" name="mobile" value="<?php echo htmlspecialchars($editing['mobile'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Assigned Vehicle</label>
            <select name="assigned_vehicle_id" class="w-full px-3 py-2 border rounded-md"><option value="">None</option>
                <?php foreach ($vehicles as $v): ?><option value="<?php echo $v['id']; ?>" <?php echo (int) ($editing['assigned_vehicle_id'] ?? 0) === (int) $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['vehicle_number']); ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Licence Number</label>
            <input type="text" name="license_number" value="<?php echo htmlspecialchars($editing['license_number'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Licence Expiry</label>
            <input type="date" name="license_expiry" value="<?php echo htmlspecialchars($editing['license_expiry'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md"></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border rounded-md">
                <option value="active">Active</option>
                <option value="inactive" <?php echo ($editing['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select></div>
        <div class="flex items-end gap-2">
            <?php if ($editing): ?><a href="index.php?page=fleet_drivers" class="px-4 py-2 bg-white border rounded-md">Cancel</a><?php endif; ?>
            <button name="save_driver" value="1" class="flex-1 bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium">Save Driver</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <?php foreach (['Driver', 'Employee', 'Mobile', 'Licence', 'Expiry', 'Vehicle', 'Status', ''] as $h): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo $h; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (!$drivers): ?><tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No drivers yet.</td></tr><?php endif; ?>
            <?php foreach ($drivers as $d):
                $exp = $d['license_expiry'];
                $expClass = !$exp ? 'text-gray-400' : ($exp < $today ? 'text-red-600 font-semibold' : ($exp <= $soon ? 'text-yellow-700 font-semibold' : '')); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($d['name']); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($d['emp_id'] ?? '—'); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($d['mobile'] ?? '—'); ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?php echo htmlspecialchars($d['license_number'] ?? '—'); ?></td>
                    <td class="px-4 py-3 <?php echo $expClass; ?>">
                        <?php echo $exp ? date('d M Y', strtotime($exp)) : '—'; ?>
                        <?php if ($exp && $exp < $today): ?><span class="text-xs"> expired</span><?php elseif ($exp && $exp <= $soon): ?><span class="text-xs"> soon</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($d['vehicle_number'] ?? '—'); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs <?php echo $d['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'; ?>"><?php echo ucfirst($d['status']); ?></span></td>
                    <td class="px-4 py-3 text-right"><?php if (fleetCan('manage')): ?><a href="index.php?page=fleet_drivers&edit=<?php echo (int) $d['id']; ?>" class="text-primary"><i class="fas fa-edit"></i></a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
