<?php
/**
 * Vehicle Alerts Page
 * Displays active alerts for vehicles (maintenance due, renewals, etc.)
 */

include_once 'includes/db.php';

// Fetch all active alerts
$alerts = $pdo->query("
    SELECT 
        va.*,
        v.vehicle_number,
        v.make,
        v.model
    FROM vehicle_alerts va
    JOIN vehicles v ON va.vehicle_id = v.id
    WHERE va.is_active = 1
    ORDER BY va.alert_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Handle dismiss alert
if (isset($_GET['dismiss'])) {
    $stmt = $pdo->prepare("UPDATE vehicle_alerts SET is_active = 0 WHERE id = ?");
    $stmt->execute([$_GET['dismiss']]);
    header('Location: ?page=vehicle_alerts');
    exit;
}

?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="?page=vehicles" class="text-blue-600 hover:text-blue-800 mb-2 inline-block">
                <i class="fas fa-arrow-left mr-2"></i>Back to Vehicles
            </a>
            <h1 class="text-3xl font-bold text-gray-900">🔔 Vehicle Alerts</h1>
            <p class="text-gray-600 mt-2">Active maintenance and renewal alerts</p>
        </div>
    </div>
</div>

<?php if (empty($alerts)): ?>
    <div class="bg-white rounded-lg shadow-md p-12 text-center">
        <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">All Clear!</h2>
        <p class="text-gray-600">No active alerts for your vehicles.</p>
        <a href="?page=vehicles" class="mt-4 inline-block bg-primary hover:bg-secondary text-white px-6 py-2 rounded-lg font-medium transition-colors">
            <i class="fas fa-car mr-2"></i>Back to Vehicles
        </a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 border-b bg-red-50">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-red-900">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo count($alerts); ?> Active Alert<?php echo count($alerts) > 1 ? 's' : ''; ?>
                </h2>
            </div>
        </div>
        
        <div class="divide-y divide-gray-200">
            <?php foreach ($alerts as $alert): 
                $alertTypes = [
                    'maintenance' => ['icon' => 'wrench', 'color' => 'yellow'],
                    'registration' => ['icon' => 'file-alt', 'color' => 'red'],
                    'insurance' => ['icon' => 'shield-alt', 'color' => 'orange'],
                    'inspection' => ['icon' => 'clipboard-check', 'color' => 'blue'],
                    'other' => ['icon' => 'bell', 'color' => 'gray']
                ];
                
                $type = $alertTypes[$alert['alert_type']] ?? $alertTypes['other'];
                $iconClass = "fas fa-{$type['icon']}";
                $colorClass = "text-{$type['color']}-600";
                $bgClass = "bg-{$type['color']}-50";
            ?>
                <div class="p-6 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4 flex-1">
                            <div class="<?php echo $bgClass; ?> p-3 rounded-lg">
                                <i class="<?php echo $iconClass; ?> <?php echo $colorClass; ?> text-2xl"></i>
                            </div>
                            
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-lg font-bold text-gray-900">
                                        <?php echo htmlspecialchars($alert['vehicle_number']); ?>
                                    </h3>
                                    <span class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($alert['make'] . ' ' . $alert['model']); ?>
                                    </span>
                                </div>
                                
                                <p class="text-gray-700 font-medium mb-2">
                                    <?php echo htmlspecialchars($alert['alert_message']); ?>
                                </p>
                                
                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    <span>
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo date('d M Y', strtotime($alert['alert_date'])); ?>
                                    </span>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $bgClass; ?> <?php echo $colorClass; ?>">
                                        <?php echo ucfirst($alert['alert_type']); ?>
                                    </span>
                                    <?php if ($alert['priority']): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <?php echo ucfirst($alert['priority']); ?> Priority
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ml-4">
                            <a href="?page=vehicle_alerts&dismiss=<?php echo $alert['id']; ?>" 
                               class="text-gray-400 hover:text-gray-600 transition-colors"
                               onclick="return confirm('Mark this alert as resolved?')"
                               title="Dismiss Alert">
                                <i class="fas fa-times-circle text-xl"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
