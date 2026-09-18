<?php
/**
 * Vehicle Management — Audit Trail (§17): who changed what, from what, to what.
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/fleet.php';

if (!is_logged_in() || !fleetCan('manage')) {
    echo '<div class="p-6 bg-red-50 text-red-700 rounded">Only administrators and fleet managers can view the audit trail.</div>';
    return;
}

$labels = [
    'closing_km' => 'Closing KM', 'opening_km' => 'Opening KM', 'total_km' => 'Total KM',
    'liters' => 'Fuel litres', 'amount' => 'Fuel cost', 'price_per_liter' => 'Rate per litre',
    'driver_name' => 'Driver', 'driver_id' => 'Driver', 'project_id' => 'Project',
    'alert_status' => 'Alert status', 'log_date' => 'Date', 'vehicle_id' => 'Vehicle',
    'expected_kmpl_min' => 'Expected KM/L min', 'expected_kmpl_max' => 'Expected KM/L max',
    'fuel_tank_capacity' => 'Tank capacity', 'current_mileage' => 'Odometer', 'vehicle_status' => 'Status',
];

$entity = in_array($_GET['entity'] ?? '', ['daily_log', 'fuel', 'vehicle', 'driver'], true) ? $_GET['entity'] : '';
$where = $entity ? 'WHERE entity = ?' : '';
$stmt = $pdo->prepare("SELECT * FROM fleet_audit_log $where ORDER BY performed_at DESC, id DESC LIMIT 300");
$stmt->execute($entity ? [$entity] : []);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$names = ['daily_log' => 'Daily record', 'fuel' => 'Fuel', 'vehicle' => 'Vehicle', 'driver' => 'Driver'];
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Fleet Audit Trail</h1>
    <p class="text-gray-600 mt-2">Every creation, edit and deletion, with the old and new value.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="index.php?page=fleet_audit" class="px-3 py-1 rounded <?php echo $entity === '' ? 'bg-primary text-white' : 'bg-white border'; ?>">All</a>
    <?php foreach ($names as $k => $n): ?>
        <a href="index.php?page=fleet_audit&entity=<?php echo $k; ?>" class="px-3 py-1 rounded <?php echo $entity === $k ? 'bg-primary text-white' : 'bg-white border'; ?>"><?php echo $n; ?></a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <?php foreach (['When', 'By', 'Record', 'Action', 'Change'] as $h): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php echo $h; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (!$rows): ?><tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No changes recorded yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo date('d-M-Y H:i', strtotime($r['performed_at'])); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars($r['performed_by'] ?? '—'); ?></td>
                    <td class="px-4 py-3"><?php echo htmlspecialchars(($names[$r['entity']] ?? $r['entity']) . ' #' . $r['entity_id']); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $r['action'] === 'delete' ? 'bg-red-100 text-red-800' : ($r['action'] === 'create' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'); ?>"><?php echo ucfirst($r['action']); ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($r['action'] === 'update'): ?>
                            <strong><?php echo htmlspecialchars($labels[$r['field']] ?? $r['field']); ?></strong>
                            changed from <span class="font-mono bg-red-50 px-1"><?php echo htmlspecialchars($r['old_value'] ?? '—'); ?></span>
                            to <span class="font-mono bg-green-50 px-1"><?php echo htmlspecialchars($r['new_value'] ?? '—'); ?></span>
                        <?php else: ?>
                            <details><summary class="cursor-pointer text-gray-600">View record</summary>
                                <pre class="text-xs bg-gray-50 p-2 mt-1 rounded whitespace-pre-wrap"><?php echo htmlspecialchars(json_encode(json_decode($r['action'] === 'delete' ? $r['old_value'] : $r['new_value'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
