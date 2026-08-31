<?php



include_once 'includes/db.php';

// Create table if it doesn't exist
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'mysql') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS outside_labours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        trade VARCHAR(100),
        daily_rate DECIMAL(10,2),
        project_id INT,
        work_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if project_id column exists, if not add it
    $checkCol = $pdo->query("SHOW COLUMNS FROM outside_labours LIKE 'project_id'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE outside_labours ADD COLUMN project_id INT AFTER daily_rate");
        $pdo->exec("ALTER TABLE outside_labours ADD FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL");
    }

    // Check if work_date column exists, if not add it
    $checkDate = $pdo->query("SHOW COLUMNS FROM outside_labours LIKE 'work_date'")->fetch();
    if (!$checkDate) {
        $pdo->exec("ALTER TABLE outside_labours ADD COLUMN work_date DATE AFTER project_id");
    }
} else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS outside_labours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        trade TEXT,
        daily_rate REAL,
        project_id INTEGER,
        work_date TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL
    )");

    // Add work_date column if it doesn't exist (SQLite)
    try {
        $pdo->exec("ALTER TABLE outside_labours ADD COLUMN work_date TEXT");
    } catch (Exception $e) {
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add'])) {
            $stmt = $pdo->prepare("INSERT INTO outside_labours (name, trade, daily_rate, project_id, work_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['trade'], $_POST['daily_rate'], $_POST['project_id'] ?: null, $_POST['work_date'] ?: null]);
            $success_message = "Labour added successfully!";

        } elseif (isset($_POST['update'])) {
            $stmt = $pdo->prepare("UPDATE outside_labours SET name=?, trade=?, daily_rate=?, project_id=?, work_date=? WHERE id=?");
            $stmt->execute([$_POST['name'], $_POST['trade'], $_POST['daily_rate'], $_POST['project_id'] ?: null, $_POST['work_date'] ?: null, $_POST['id']]);
            $success_message = "Labour updated successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM outside_labours WHERE id=?");
        $stmt->execute([$_GET['delete']]);
        $success_message = "Labour deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting labour: " . $e->getMessage();
    }
}

// Query includes total paid from labour_payments
$labours = $pdo->query("
    SELECT ol.*, 
           p.name as project_name,
           COALESCE((SELECT SUM(paid_amount) FROM labour_payments WHERE labour_id = ol.id), 0) as total_paid
    FROM outside_labours ol 
    LEFT JOIN projects p ON ol.project_id = p.id
    ORDER BY ol.name
")->fetchAll();

$projects = $pdo->query("SELECT * FROM projects ORDER BY name")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Outside Labours</h1>
    <p class="text-gray-600 mt-2">Manage external contractors and their daily rates</p>
</div>

<?php if (isset($success_message)): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Labour Management</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="openAddModal()">
                    <i class="fas fa-user-plus mr-1 md:mr-2"></i>Add Labour
                </button>
            </div>
        </div>


        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Labour Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Trade/Skill</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Daily Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Paid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($labours as $labour): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($labour['name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($labour['trade']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php if (!empty($labour['project_name'])): ?>
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo htmlspecialchars($labour['project_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-500 italic">No Project Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php if (!empty($labour['work_date'])): ?>
                                            <?php echo date('M d, Y', strtotime($labour['work_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-green-600">
                                        <?php echo currency_symbol() . number_format($labour['daily_rate'], 2); ?>/day
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-blue-600">
                                        <?php echo currency_symbol() . number_format($labour['total_paid'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="?page=labour_payments&labour_id=<?php echo $labour['id']; ?>" class="text-green-600 hover:text-green-900 mr-3" title="View Payments">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick="editLabour(<?php echo htmlspecialchars(json_encode($labour), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($labour['total_paid'] > 0): ?>
                                        <span class="text-gray-400 cursor-not-allowed" title="Cannot delete: has payments">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                    <?php else: ?>
                                    <a href="?page=labours&delete=<?php echo $labour['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this labour?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeEditModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                    onclick="event.stopPropagation()">
                    <form method="post">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Labour</h3>
                                <button type="button" onclick="closeEditModal()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                            <input type="hidden" id="edit-id" name="id">
                            <div class="space-y-4">
                                <div>
                                    <label for="edit-name" class="block text-sm font-medium text-gray-700 mb-1">Full
                                        Name *</label>
                                    <input type="text" name="name" id="edit-name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required>
                                </div>
                                <div>
                                    <label for="edit-trade"
                                        class="block text-sm font-medium text-gray-700 mb-1">Trade/Skill *</label>
                                    <input type="text" name="trade" id="edit-trade"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required>
                                </div>
                                <div>
                                    <label for="edit-project_id"
                                        class="block text-sm font-medium text-gray-700 mb-1">Project Assignment</label>
                                    <select name="project_id" id="edit-project_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select Project (Optional)</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="edit-work_date"
                                        class="block text-sm font-medium text-gray-700 mb-1">Work Date</label>
                                    <input type="date" name="work_date" id="edit-work_date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="edit-daily_rate"
                                        class="block text-sm font-medium text-gray-700 mb-1">Daily Rate *</label>
                                    <input type="number" step="0.01" name="daily_rate" id="edit-daily_rate"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" name="update"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeEditModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Labour Modal -->
        <div id="addModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeAddModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                    onclick="event.stopPropagation()">
                    <form method="post">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Labour</h3>
                                <button type="button" onclick="closeAddModal()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                    <input type="text" name="name" placeholder="Enter full name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Trade/Skill *</label>
                                    <input type="text" name="trade" placeholder="e.g., Electrician, Plumber, Carpenter"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Project
                                        Assignment</label>
                                    <select name="project_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select Project (Optional)</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Work Date</label>
                                    <input type="date" name="work_date" value="<?php echo date('Y-m-d'); ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Daily Rate *</label>
                                    <input type="number" step="0.01" name="daily_rate" placeholder="0.00"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" name="add"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-save mr-2"></i>Add Labour
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeAddModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function editLabour(labour) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = labour.id;
                document.getElementById('edit-name').value = labour.name;
                document.getElementById('edit-trade').value = labour.trade;
                document.getElementById('edit-project_id').value = labour.project_id || '';
                document.getElementById('edit-work_date').value = labour.work_date || '';
                document.getElementById('edit-daily_rate').value = labour.daily_rate;
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            function openAddModal() {
                document.getElementById('addModal').classList.remove('hidden');
            }

            function closeAddModal() {
                document.getElementById('addModal').classList.add('hidden');
                // Reset form
                document.getElementById('addModal').querySelector('form').reset();
            }
        </script>