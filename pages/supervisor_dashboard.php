<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

require_role(['superadmin', 'supervisor']);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE daily_attendance SET approval_status = 'approved', approved_by = ?, approved_at = ? WHERE id = ?");
        $stmt->execute([$user_id, $now, $id]);
        $message = "Attendance approved.";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE daily_attendance SET approval_status = 'rejected', approved_by = ?, approved_at = ? WHERE id = ?");
        $stmt->execute([$user_id, $now, $id]);
        $message = "Attendance rejected.";
    }
}

// Fetch Pending Records with site switch detection
$pending = $pdo->query("
    SELECT d.*, e.name as emp_name,
    (SELECT GROUP_CONCAT(activity_type, ', ') FROM attendance_logs WHERE daily_attendance_id = d.id) as activities,
    (SELECT work_site FROM daily_attendance d2 
     WHERE d2.employee_id = d.employee_id 
     AND d2.attendance_date < d.attendance_date 
     AND d2.work_site IS NOT NULL 
     ORDER BY d2.attendance_date DESC LIMIT 1) as previous_site
    FROM daily_attendance d
    JOIN employees e ON d.employee_id = e.id
    WHERE d.approval_status = 'pending'
    ORDER BY d.attendance_date DESC
")->fetchAll();

// Stats for Supervisor Dashboard
$total_employees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$pending_count = count($pending);
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Supervisor Dashboard</h1>
    <p class="text-gray-600 mt-2">Overview of users and pending requests</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-blue-600 rounded-xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="relative z-10">
            <h3 class="text-sm font-semibold uppercase tracking-wider opacity-80">Total Users</h3>
            <div class="mt-2 text-4xl font-bold"><?php echo $total_employees; ?></div>
            <p class="text-sm opacity-80 mt-1">Active employees.</p>
        </div>
        <div class="absolute right-4 bottom-4 opacity-20 text-6xl">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <div class="bg-orange-500 rounded-xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="relative z-10">
            <h3 class="text-sm font-semibold uppercase tracking-wider opacity-80">Pending Requests</h3>
            <div class="mt-2 text-4xl font-bold"><?php echo $pending_count; ?></div>
            <a href="#approvals-list" class="inline-flex items-center mt-2 text-sm font-medium hover:underline">
                Review now <i class="fas fa-chevron-right ml-1"></i>
            </a>
        </div>
        <div class="absolute right-4 bottom-4 opacity-20 text-6xl">
            <i class="fas fa-user-check"></i>
        </div>
    </div>
</div>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4" id="approvals-list">Attendance Approvals</h2>
    <p class="text-gray-600 mb-4 text-sm">Review outcome of employee daily work.</p>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (empty($pending)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-check-circle text-4xl mb-4 text-green-500"></i>
            <p>No pending approvals. All caught up!</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Date</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Employee</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Work Site</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Times</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Break Time</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Activities</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($pending as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($row['attendance_date'])); ?></td>
                            <td class="px-6 py-4 font-medium whitespace-nowrap"><?php echo htmlspecialchars($row['emp_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $current_site = $row['work_site'] ?? 'N/A';
                                $previous_site = $row['previous_site'] ?? null;

                                // Check if there's a site switch
                                if ($previous_site && $current_site !== 'N/A' && $previous_site !== $current_site): ?>
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 line-through">
                                            <?php echo htmlspecialchars($previous_site); ?>
                                        </span>
                                        <i class="fas fa-arrow-right text-orange-500"></i>
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            <?php echo htmlspecialchars($current_site); ?>
                                        </span>
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Site Switch
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($current_site); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono whitespace-nowrap">
                                <?php echo substr($row['in_time'] ?? '', 0, 5); ?> -
                                <?php echo substr($row['out_time'] ?? '', 0, 5); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                // Get break times from attendance_logs
                                $breakStmt = $pdo->prepare("
                                    SELECT start_time, end_time 
                                    FROM attendance_logs 
                                    WHERE daily_attendance_id = ? 
                                    AND activity_type = 'break'
                                    ORDER BY start_time
                                ");
                                $breakStmt->execute([$row['id']]);
                                $breaks = $breakStmt->fetchAll();

                                if (empty($breaks)) {
                                    echo '<span class="text-gray-400 text-sm">No break</span>';
                                } else {
                                    foreach ($breaks as $break) {
                                        $breakStart = date('H:i', strtotime($break['start_time']));
                                        $breakEnd = $break['end_time'] ? date('H:i', strtotime($break['end_time'])) : 'Ongoing';
                                        echo '<div class="mb-1">';
                                        echo '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">';
                                        echo '<i class="fas fa-coffee mr-1"></i>';
                                        echo "$breakStart - $breakEnd";
                                        echo '</span>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 truncate max-w-xs"
                                title="<?php echo htmlspecialchars($row['remarks'] . ' ' . $row['activities']); ?>">
                                <?php echo htmlspecialchars(ucfirst($row['activities'])); ?>
                            </td>
                            <td class="px-6 py-4 flex space-x-2 whitespace-nowrap">
                                <form method="post" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="text-green-600 hover:text-green-900" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form method="post" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Reject"
                                        onclick="return confirm('Reject this entry?')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
