<?php
/**
 * Supervisor entry for multi-site attendance.
 *
 * Pick a worker and a date, then add one entry per site worked that day. The
 * day keeps a single attendance record; the entries hang off it.
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/attendance_sites.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'supervisor', 'accounts_manager'], true)) {
    echo '<div class="p-6 bg-red-50 text-red-700 rounded">You do not have access to attendance entry.</div>';
    return;
}

$message = '';
$error = '';

$selectedDate = $_GET['date'] ?? $_POST['attendance_date'] ?? date('Y-m-d');
$selectedEmployee = (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_entry'])) {
            if (!$selectedEmployee) {
                throw new Exception('Select a worker first.');
            }
            if (empty($_POST['time_in'])) {
                throw new Exception('Time In is required.');
            }

            saveSiteEntry($pdo, [
                'id' => $_POST['entry_id'] ?? null,
                'employee_id' => $selectedEmployee,
                'attendance_date' => $selectedDate,
                'project_id' => $_POST['project_id'] ?? null,
                'site_name' => $_POST['site_name'] ?? '',
                'time_in' => $_POST['time_in'] ?? null,
                'break_out' => $_POST['break_out'] ?? null,
                'break_in' => $_POST['break_in'] ?? null,
                'time_out' => $_POST['time_out'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'created_by' => $_SESSION['username'] ?? 'supervisor',
            ]);
            $message = empty($_POST['entry_id']) ? 'Site entry added.' : 'Site entry updated.';

        } elseif (isset($_POST['delete_entry'])) {
            deleteSiteEntry($pdo, (int) $_POST['delete_entry']);
            $message = 'Site entry removed.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$employees = $pdo->query("SELECT id, name, emp_id FROM employees WHERE status = 'active' OR status IS NULL ORDER BY name")->fetchAll();
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

$entries = $selectedEmployee ? siteEntriesForDay($pdo, $selectedEmployee, $selectedDate) : [];

$totalHours = 0.0;
$totalBreak = 0.0;
foreach ($entries as $e) {
    $totalHours += (float) $e['working_hours'];
    $totalBreak += siteEntryBreakHours($e['break_out'], $e['break_in']);
}
$normal = normalWorkingHours();
$overtime = max(0, $totalHours - $normal);

$editing = null;
if (!empty($_GET['edit'])) {
    foreach ($entries as $e) {
        if ((int) $e['id'] === (int) $_GET['edit']) {
            $editing = $e;
            break;
        }
    }
}

$employeeName = '';
foreach ($employees as $emp) {
    if ((int) $emp['id'] === $selectedEmployee) {
        $employeeName = $emp['name'];
        break;
    }
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Attendance Entry</h1>
    <p class="text-gray-600 mt-2">One record per worker per day, with an entry for every site worked.</p>
</div>

<?php if ($message): ?>
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Worker + date -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="page" value="attendance_sites">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Worker</label>
            <select name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                <option value="">Select worker</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>" <?php echo $selectedEmployee === (int) $emp['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($emp['emp_id'] ? $emp['emp_id'] . ' — ' : '') . $emp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>"
                class="w-full px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <div>
            <button type="submit" class="w-full bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium">
                <i class="fas fa-search mr-2"></i>Load Day
            </button>
        </div>
    </form>
</div>

<?php if ($selectedEmployee): ?>

    <!-- Entries for the day -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($employeeName); ?></h2>
                <p class="text-sm text-gray-500"><?php echo date('l, F j, Y', strtotime($selectedDate)); ?></p>
            </div>
            <div class="flex gap-6 text-sm">
                <div>
                    <div class="text-gray-500">Sites</div>
                    <div class="text-lg font-bold text-gray-900"><?php echo count($entries); ?></div>
                </div>
                <div>
                    <div class="text-gray-500">Break</div>
                    <div class="text-lg font-bold text-gray-900"><?php echo number_format($totalBreak, 2); ?> h</div>
                </div>
                <div>
                    <div class="text-gray-500">Total Hours</div>
                    <div class="text-lg font-bold text-primary"><?php echo number_format($totalHours, 2); ?> h</div>
                </div>
                <div>
                    <div class="text-gray-500">Overtime</div>
                    <div class="text-lg font-bold <?php echo $overtime > 0 ? 'text-orange-600' : 'text-gray-400'; ?>">
                        <?php echo number_format($overtime, 2); ?> h
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 md:p-6 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Break Out</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Break In</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time Out</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Hours</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!$entries): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-gray-500">
                                No site entries for this day yet. Add the first one below.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($entries as $e): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-3">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($e['site_name'] ?: ($e['project_name'] ?? '—')); ?></div>
                                <?php if ($e['notes']): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($e['notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3"><?php echo substr((string) $e['time_in'], 0, 5); ?></td>
                            <td class="px-3 py-3"><?php echo $e['break_out'] ? substr($e['break_out'], 0, 5) : '—'; ?></td>
                            <td class="px-3 py-3"><?php echo $e['break_in'] ? substr($e['break_in'], 0, 5) : '—'; ?></td>
                            <td class="px-3 py-3"><?php echo $e['time_out'] ? substr($e['time_out'], 0, 5) : '<span class="text-green-600">still on site</span>'; ?></td>
                            <td class="px-3 py-3 text-right font-semibold"><?php echo number_format((float) $e['working_hours'], 2); ?></td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <a href="?page=attendance_sites&employee_id=<?php echo $selectedEmployee; ?>&date=<?php echo urlencode($selectedDate); ?>&edit=<?php echo $e['id']; ?>"
                                    class="text-primary hover:text-secondary mr-3" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="post" class="inline" onsubmit="return confirm('Remove this site entry?')">
                                    <input type="hidden" name="employee_id" value="<?php echo $selectedEmployee; ?>">
                                    <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                                    <button type="submit" name="delete_entry" value="<?php echo $e['id']; ?>"
                                        class="text-red-600 hover:text-red-800" title="Remove"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($entries): ?>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-3 py-3 text-right font-semibold text-gray-900">Total Daily Working Hours</td>
                            <td class="px-3 py-3 text-right font-bold text-primary"><?php echo number_format($totalHours, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Add / edit a site entry -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <?php echo $editing ? 'Edit Site Entry' : 'Add Site'; ?>
        </h3>
        <form method="post" class="space-y-4">
            <input type="hidden" name="employee_id" value="<?php echo $selectedEmployee; ?>">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="entry_id" value="<?php echo $editing['id']; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Site <span class="text-red-500">*</span></label>
                    <select name="project_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Other / not listed</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $editing && (int) $editing['project_id'] === (int) $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="site_name" placeholder="Site name if not listed"
                        value="<?php echo htmlspecialchars($editing['site_name'] ?? ''); ?>"
                        class="w-full mt-2 px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Time In <span class="text-red-500">*</span></label>
                    <input type="time" name="time_in" required
                        value="<?php echo htmlspecialchars(substr($editing['time_in'] ?? '', 0, 5)); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md time-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break Out</label>
                    <input type="time" name="break_out"
                        value="<?php echo htmlspecialchars(substr($editing['break_out'] ?? '', 0, 5)); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md time-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break In</label>
                    <input type="time" name="break_in"
                        value="<?php echo htmlspecialchars(substr($editing['break_in'] ?? '', 0, 5)); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md time-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Time Out</label>
                    <input type="time" name="time_out"
                        value="<?php echo htmlspecialchars(substr($editing['time_out'] ?? '', 0, 5)); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md time-field">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <input type="text" name="notes" value="<?php echo htmlspecialchars($editing['notes'] ?? ''); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="bg-gray-50 border rounded-md px-4 py-3">
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Hours for this site</div>
                    <div id="calc-hours" class="text-2xl font-bold text-primary">0.00</div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <?php if ($editing): ?>
                    <a href="?page=attendance_sites&employee_id=<?php echo $selectedEmployee; ?>&date=<?php echo urlencode($selectedDate); ?>"
                        class="px-4 py-2 bg-white border rounded-md text-gray-700">Cancel</a>
                <?php endif; ?>
                <button type="submit" name="save_entry" value="1"
                    class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">
                    <i class="fas fa-plus mr-2"></i><?php echo $editing ? 'Save Entry' : 'Add Site'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Mirror of siteEntryHours() in includes/attendance_sites.php: the server
        // recomputes on save, this is only so the supervisor sees it immediately.
        (function () {
            const fields = document.querySelectorAll('.time-field');
            const out = document.getElementById('calc-hours');
            if (!fields.length || !out) return;

            const minutes = function (value) {
                if (!value) return null;
                const parts = value.split(':');
                return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            };

            const recalc = function () {
                const form = out.closest('form');
                const tIn = minutes(form.querySelector('[name="time_in"]').value);
                const tOut = minutes(form.querySelector('[name="time_out"]').value);
                const bOut = minutes(form.querySelector('[name="break_out"]').value);
                const bIn = minutes(form.querySelector('[name="break_in"]').value);

                if (tIn === null || tOut === null) {
                    out.textContent = '0.00';
                    return;
                }
                let span = tOut - tIn;
                if (span < 0) span += 1440; // past midnight
                if (bOut !== null && bIn !== null) {
                    let br = bIn - bOut;
                    if (br < 0) br += 1440;
                    if (br > 0 && br <= span) span -= br;
                }
                out.textContent = (Math.max(0, span) / 60).toFixed(2);
            };

            fields.forEach(f => f.addEventListener('input', recalc));
            recalc();
        })();
    </script>

<?php endif; ?>
