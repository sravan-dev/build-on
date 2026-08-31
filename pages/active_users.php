<?php
if (!defined('PDO::ATTR_DRIVER_NAME')) {
    include_once 'includes/db.php';
}

require_role(['superadmin']);

// Get active threshold (e.g., last 30 minutes)
$threshold = date('Y-m-d H:i:s', strtotime('-30 minutes'));

// Fetch active web users
$webUsers = $pdo->prepare("SELECT id, username as name, role, last_active, 'Web Portal' as platform FROM users WHERE last_active >= ? ORDER BY last_active DESC");
$webUsers->execute([$threshold]);
$webResults = $webUsers->fetchAll(PDO::FETCH_ASSOC);

// Fetch active app users (employees)
$appUsers = $pdo->prepare("SELECT id, name, position as role, last_active, 'Mobile App' as platform FROM employees WHERE last_active >= ? ORDER BY last_active DESC");
$appUsers->execute([$threshold]);
$appResults = $appUsers->fetchAll(PDO::FETCH_ASSOC);

// Combine and sort
$allActive = array_merge($webResults, $appResults);
usort($allActive, function ($a, $b) {
    return strtotime($b['last_active']) - strtotime($a['last_active']);
});

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Active Users</h1>
    <p class="text-gray-600 mt-2">Users currently online (active in the last 30 minutes)</p>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Online Users</h2>
    </div>
    
    <div class="overflow-x-auto">
        <?php if (empty($allActive)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-users-slash text-4xl mb-3"></i>
                <p>No active users found.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6">Name</th>
                        <th class="py-3 px-6">Role</th>
                        <th class="py-3 px-6">Platform</th>
                        <th class="py-3 px-6">Last Active</th>
                        <th class="py-3 px-6">Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($allActive as $user): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6 text-left whitespace-nowrap">
                                <span class="font-medium"><?php echo htmlspecialchars($user['name']); ?></span>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <span class="bg-gray-200 text-gray-700 py-1 px-3 rounded-full text-xs">
                                    <?php echo ucfirst($user['role'] ?? 'User'); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <?php if ($user['platform'] === 'Mobile App'): ?>
                                    <span class="text-blue-600"><i class="fas fa-mobile-alt mr-1"></i> Mobile App</span>
                                <?php else: ?>
                                    <span class="text-indigo-600"><i class="fas fa-desktop mr-1"></i> Web Portal</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <?php 
                                    $time = strtotime($user['last_active']);
                                    echo date('h:i A', $time);
                                    echo ' <span class="text-xs text-gray-400">(' . time_elapsed_string($user['last_active']) . ')</span>';
                                ?>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <span class="w-2 h-2 mr-1 bg-green-400 rounded-full animate-pulse"></span>
                                    Online
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="mt-8 mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Login History</h1>
    <p class="text-gray-600 mt-2">Recent login activity (Last 24 Hours)</p>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Recent Logins</h2>
    </div>
    
    <div class="overflow-x-auto">
        <?php
        // Fetch recent logins
        $logStmt = $pdo->prepare("SELECT * FROM login_activity WHERE login_time >= ? ORDER BY login_time DESC");
        $logStmt->execute([date('Y-m-d H:i:s', strtotime('-24 hours'))]);
        $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <?php if (empty($logs)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-history text-4xl mb-3"></i>
                <p>No login activity in the last 24 hours.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6">Time</th>
                        <th class="py-3 px-6">User</th>
                        <th class="py-3 px-6">Role</th>
                        <th class="py-3 px-6">IP Address</th>
                        <th class="py-3 px-6">Platform/Agent</th>
                        <th class="py-3 px-6">Status</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($logs as $log): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6 whitespace-nowrap">
                                <?php echo date('M d, H:i', strtotime($log['login_time'])); ?>
                            </td>
                            <td class="py-3 px-6">
                                <span class="font-medium"><?php echo htmlspecialchars($log['username']); ?></span>
                            </td>
                            <td class="py-3 px-6">
                                <span class="bg-gray-200 text-gray-700 py-1 px-3 rounded-full text-xs">
                                    <?php echo ucfirst($log['user_type']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 font-mono text-xs">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                            <td class="py-3 px-6 text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                <?php echo htmlspecialchars($log['user_agent']); ?>
                            </td>
                            <td class="py-3 px-6">
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="text-green-600"><i class="fas fa-check-circle"></i> Success</span>
                                <?php else: ?>
                                    <span class="text-red-600"><i class="fas fa-times-circle"></i> Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
function time_elapsed_string($datetime, $full = false) {
    if ($datetime == '0000-00-00 00:00:00' || !$datetime) return 'Never';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
