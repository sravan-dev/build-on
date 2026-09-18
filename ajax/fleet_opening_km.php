<?php
/**
 * Opening KM for a vehicle on a date (§3): the previous closing reading.
 * Called by the daily-entry form when the vehicle or date changes.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fleet.php';

if (empty($_SESSION['logged_in']) || !fleetCan('enter')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not allowed.']);
    exit;
}

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if ($vehicleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select a vehicle.']);
    exit;
}

$stmt = $pdo->prepare("SELECT v.id, v.vehicle_number, v.fuel_type, v.fuel_tank_capacity, v.driver_id,
                              v.project_id, d.name AS driver_name
                       FROM vehicles v
                       LEFT JOIN fleet_drivers d ON d.id = v.driver_id
                       WHERE v.id = ?");
$stmt->execute([$vehicleId]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
    exit;
}

echo json_encode([
    'success' => true,
    'opening_km' => fleetPreviousClosingKm($pdo, $vehicleId, $date),
    'already_recorded' => fleetExistingLogId($pdo, $vehicleId, $date) !== null,
    'fuel_type' => $vehicle['fuel_type'],
    'tank_capacity' => $vehicle['fuel_tank_capacity'] !== null ? (float) $vehicle['fuel_tank_capacity'] : null,
    'driver_id' => $vehicle['driver_id'] !== null ? (int) $vehicle['driver_id'] : null,
    'project_id' => $vehicle['project_id'] !== null ? (int) $vehicle['project_id'] : null,
]);
