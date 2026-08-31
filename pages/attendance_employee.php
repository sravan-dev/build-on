<?php
// pages/attendance_employee.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$user = current_user();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    echo "<div class='p-6 bg-red-100 text-red-700 rounded'>Error: Your login is not linked to an employee record. Please contact admin.</div>";
    return;
}

$today = date('Y-m-d');
$now = date('H:i:s');
$message = '';
$error = '';

// Fetch active projects for dropdown
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

// Get Today's Daily Record
$stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?");
$stmt->execute([$employee_id, $today]);
$daily = $stmt->fetch(PDO::FETCH_ASSOC);

$daily_id = $daily['id'] ?? null;
$current_status = 'Not Started';
$last_log = null;

if ($daily) {
    if ($daily['out_time']) {
        $current_status = 'Completed';
    } else {
        // Check active log
        $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE daily_attendance_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$daily_id]);
        $last_log = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last_log && !$last_log['end_time']) {
            if ($last_log['activity_type'] === 'break') {
                $current_status = 'On Break';
            } else {
                $current_status = 'Working';
            }
        } else {
            // Logged in but no active task? Should not happen in this flow usually, unless manual edit.
            // Or maybe between tasks.
            $current_status = 'Idle';
        }
    }
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($action === 'clock_in') {
            if ($daily)
                throw new Exception("Already clocked in for today.");

            $project_id = $_POST['project_id'] ?: null;
            $note = $_POST['note'] ?? '';

            // Get project name for work_site
            $work_site = null;
            if ($project_id) {
                $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                $projStmt->execute([$project_id]);
                $work_site = $projStmt->fetchColumn();
            }

            // Create Daily with work_site
            $stmt = $pdo->prepare("INSERT INTO daily_attendance (employee_id, attendance_date, in_time, status, work_site) VALUES (?, ?, ?, 'Present', ?)");
            $stmt->execute([$employee_id, $today, $now, $work_site]);
            $daily_id = $pdo->lastInsertId();

            // Create Log
            $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, project_id, start_time, activity_type, description) VALUES (?, ?, ?, 'work', ?)");
            $stmt->execute([$daily_id, $project_id, $now, $note]);

            $message = "Clocked In Successfully.";

        } elseif ($action === 'switch_site') {
            if ($current_status !== 'Working')
                throw new Exception("Must be working to switch site.");

            // End current log
            $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
            $stmt->execute([$now, $last_log['id']]);

            // Start new log
            $project_id = $_POST['project_id'] ?: null;
            $note = $_POST['note'] ?? '';
            $is_offsite = isset($_POST['is_offsite']) ? 1 : 0;
            $type = $is_offsite ? 'offsite' : 'work';

            // Get project name for work_site
            $work_site = null;
            if ($is_offsite) {
                $work_site = 'Offsite / Outside';
            } elseif ($project_id) {
                $projStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                $projStmt->execute([$project_id]);
                $work_site = $projStmt->fetchColumn();
            }

            // Update work_site in daily_attendance
            $stmt = $pdo->prepare("UPDATE daily_attendance SET work_site = ? WHERE id = ?");
            $stmt->execute([$work_site, $daily_id]);

            $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, project_id, start_time, activity_type, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$daily_id, $project_id, $now, $type, $note]);

            $message = "Switched site/activity.";

        } elseif ($action === 'start_break') {
            if ($current_status !== 'Working')
                throw new Exception("Must be working to start break.");

            // End current work log
            $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
            $stmt->execute([$now, $last_log['id']]);

            // Start break log
            $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, start_time, activity_type) VALUES (?, ?, 'break')");
            $stmt->execute([$daily_id, $now]);

            // Update daily break start if first break (optional, or just rely on logs)
            // User requirement: "Option to log break start and break end separately".
            // We can just use logs.

            $message = "Break started.";

        } elseif ($action === 'end_break') {
            if ($current_status !== 'On Break')
                throw new Exception("Not on break.");

            // End break log
            $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
            $stmt->execute([$now, $last_log['id']]);

            // Resume work (ask for site again? or resume previous?)
            // For simplicity, let's ask user to confirm site or default to previous.
            // Implementation: Modal asks for site.
            $project_id = $_POST['project_id'] ?: null;

            $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, project_id, start_time, activity_type) VALUES (?, ?, ?, 'work')");
            $stmt->execute([$daily_id, $project_id, $now]);

            $message = "Break ended. Back to work.";

        } elseif ($action === 'clock_out') {
            if (!$daily || $daily['out_time'])
                throw new Exception("Cannot clock out.");

            // End active log
            if ($last_log && !$last_log['end_time']) {
                $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
                $stmt->execute([$now, $last_log['id']]);
            }

            // Calculate total hours from logs
            // (Assuming simple calculation here, or done later in report)
            // Let's just update out_time
            $stmt = $pdo->prepare("UPDATE daily_attendance SET out_time = ?, approval_status = 'pending' WHERE id = ?");
            $stmt->execute([$now, $daily_id]);

            $message = "Clocked Out. Good job today!";
        }

        $pdo->commit();
        // Refresh page
        // Refresh page
        echo "<script>window.location.href = 'index.php?page=attendance';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="max-w-4xl mx-auto">

    <!-- Employee Header with Logout -->
    <div class="flex items-center justify-between mb-6 bg-white p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div
                class="h-12 w-12 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xl mr-4">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Good Morning,
                    <?php echo htmlspecialchars($user['username']); ?>!
                </h1>
                <p class="text-sm text-gray-500">Employee</p>
            </div>
        </div>
        <a href="pages/logout.php"
            class="bg-red-50 text-red-600 px-4 py-2 rounded-lg font-medium hover:bg-red-100 transition-colors">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
    </div>
    <!-- Status Card -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="p-6 text-center border-b">
            <h2 class="text-gray-500 font-medium uppercase tracking-wider text-sm">Today's Status</h2>
            <div class="mt-2 flex justify-center items-center space-x-3">
                <span class="text-4xl font-bold text-gray-900"><?php echo $current_status; ?></span>
                <?php if ($current_status === 'Working'): ?>
                    <span class="animate-pulse h-4 w-4 rounded-full bg-green-500"></span>
                <?php elseif ($current_status === 'On Break'): ?>
                    <span class="animate-pulse h-4 w-4 rounded-full bg-yellow-500"></span>
                <?php endif; ?>
            </div>
            <p class="text-gray-400 mt-1"><?php echo date('l, F j, Y'); ?></p>

            <?php if ($current_status === 'Working' && $last_log): ?>
                <div class="mt-4 bg-gray-50 rounded p-3 inline-block">
                    <p class="text-sm font-semibold text-gray-700">Currently at:</p>
                    <?php
                    // Find project name
                    $curr_proj = 'Unknown Site';
                    foreach ($projects as $p) {
                        if ($p['id'] == $last_log['project_id']) {
                            $curr_proj = $p['name'];
                            break;
                        }
                    }
                    if ($last_log['activity_type'] == 'offsite')
                        $curr_proj = "Offsite / Outside";
                    ?>
                    <p class="text-lg text-primary"><?php echo htmlspecialchars($curr_proj); ?></p>
                    <?php if ($last_log['description']): ?>
                        <p class="text-xs text-gray-500 mt-1">"<?php echo htmlspecialchars($last_log['description']); ?>"</p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1">Started at
                        <?php echo date('H:i', strtotime($last_log['start_time'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="p-6 bg-gray-50">
            <?php if ($error): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <?php if ($current_status === 'Not Started'): ?>
                    <button onclick="openModal('clockInModal')"
                        class="w-full py-4 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow font-bold text-lg flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i> CLOCK IN
                    </button>
                <?php endif; ?>

                <?php if ($current_status === 'Working'): ?>
                    <button onclick="openModal('switchModal')"
                        class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow font-bold text-lg flex items-center justify-center">
                        <i class="fas fa-exchange-alt mr-2"></i> SWITCH SITE
                    </button>

                    <button onclick="document.getElementById('breakForm').submit()"
                        class="w-full py-4 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg shadow font-bold text-lg flex items-center justify-center">
                        <i class="fas fa-coffee mr-2"></i> START BREAK
                    </button>
                    <form id="breakForm" method="post" class="hidden"><input type="hidden" name="action"
                            value="start_break"></form>

                    <button onclick="confirmClockOut()"
                        class="w-full py-4 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow font-bold text-lg flex items-center justify-center md:col-span-2">
                        <i class="fas fa-sign-out-alt mr-2"></i> CLOCK OUT
                    </button>
                <?php endif; ?>

                <?php if ($current_status === 'On Break'): ?>
                    <button onclick="openModal('endBreakModal')"
                        class="w-full py-4 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow font-bold text-lg flex items-center justify-center md:col-span-2">
                        <i class="fas fa-play mr-2"></i> END BREAK & RESUME WORK
                    </button>
                <?php endif; ?>

                <?php if ($current_status === 'Completed'): ?>
                    <div class="md:col-span-2 text-center text-gray-500 py-4">
                        <i class="fas fa-check-circle text-4xl text-green-500 mb-2"></i>
                        <p>Work completed for today.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Clock In Modal -->
<div id="clockInModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" onclick="closeModal('clockInModal')">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" name="action" value="clock_in">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Start Work Day</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Job Site</label>
                        <select name="project_id" class="w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">-- Choose Site --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea name="note" class="w-full border-gray-300 rounded-md shadow-sm" rows="2"
                            placeholder="Starting day..."></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Clock In
                    </button>
                    <button type="button" onclick="closeModal('clockInModal')"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Switch Site Modal -->
<div id="switchModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" onclick="closeModal('switchModal')">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" name="action" value="switch_site">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Switch Activity / Site</h3>

                    <div class="mb-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="is_offsite" id="switchOffsite"
                                onchange="toggleSiteSelect(this)" class="rounded text-primary focus:ring-primary">
                            <span class="text-sm font-medium text-gray-700">Outside / Offsite Work</span>
                        </label>
                    </div>

                    <div class="mb-4" id="switchSiteDiv">
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Job Site</label>
                        <select name="project_id" id="switchProject"
                            class="w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">-- Choose Site --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activity Description</label>
                        <textarea name="note" class="w-full border-gray-300 rounded-md shadow-sm" rows="2"
                            placeholder="What are you doing?"></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Switch
                    </button>
                    <button type="button" onclick="closeModal('switchModal')"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- End Break Modal -->
<div id="endBreakModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" onclick="closeModal('endBreakModal')">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" name="action" value="end_break">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Resume Work</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resume at Site</label>
                        <select name="project_id" class="w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">-- Choose Site --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($last_log && $last_log['project_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Resume Work
                    </button>
                    <button type="button" onclick="closeModal('endBreakModal')"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="clockOutForm" method="post" class="hidden"><input type="hidden" name="action" value="clock_out"></form>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }
    function toggleSiteSelect(cb) {
        const div = document.getElementById('switchSiteDiv');
        const sel = document.getElementById('switchProject');
        if (cb.checked) {
            div.classList.add('opacity-50', 'pointer-events-none');
            sel.required = false;
        } else {
            div.classList.remove('opacity-50', 'pointer-events-none');
            sel.required = true;
        }
    }
    function confirmClockOut() {
        if (confirm('Are you sure you want to end your work day?')) {
            document.getElementById('clockOutForm').submit();
        }
    }
</script>