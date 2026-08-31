<?php
include_once 'includes/db.php';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_tool') {
        try {
            $stmt = $pdo->prepare("INSERT INTO tools (name, category, serial_number, purchase_date, supplier, cost, warranty_expiry, status, notes, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['category'],
                $_POST['serial_number'],
                $_POST['purchase_date'],
                $_POST['supplier'],
                $_POST['cost'],
                $_POST['warranty_expiry'],
                $_POST['status'] ?? 'in_store',
                $_POST['notes'],
                ($_POST['status'] == 'issued' && !empty($_POST['assigned_to'])) ? $_POST['assigned_to'] : null
            ]);
            $success = "Tool added successfully.";
        } catch (Exception $e) {
            $error = "Error adding tool: " . $e->getMessage();
        }
    } elseif ($action == 'edit_tool') {
        try {
            $stmt = $pdo->prepare("UPDATE tools SET name=?, category=?, serial_number=?, purchase_date=?, supplier=?, cost=?, warranty_expiry=?, status=?, notes=?, assigned_to=? WHERE id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['category'],
                $_POST['serial_number'],
                $_POST['purchase_date'],
                $_POST['supplier'],
                $_POST['cost'],
                $_POST['warranty_expiry'],
                $_POST['status'],
                $_POST['notes'],
                ($_POST['status'] == 'issued' && !empty($_POST['assigned_to'])) ? $_POST['assigned_to'] : null,
                $_POST['id']
            ]);
            $success = "Tool updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating tool: " . $e->getMessage();
        }
    } elseif ($action == 'assign_tool') {
        try {
            $pdo->beginTransaction();

            // 1. Update Tool Status
            $stmt = $pdo->prepare("UPDATE tools SET status = 'issued', assigned_to = ? WHERE id = ?");
            $stmt->execute([$_POST['employee_id'], $_POST['tool_id']]);

            // 2. Add History Record
            $stmt = $pdo->prepare("INSERT INTO tool_assignments (tool_id, employee_id, notes, condition_on_issue) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['tool_id'], $_POST['employee_id'], $_POST['notes'], $_POST['condition']]);

            $pdo->commit();
            $success = "Tool assigned successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "assignment failed: " . $e->getMessage();
        }
    } elseif ($action == 'return_tool') {
        try {
            $pdo->beginTransaction();

            // 1. Update Tool Status
            $new_status = $_POST['status_after_return'] ?? 'in_store'; // could be damaged etc
            $stmt = $pdo->prepare("UPDATE tools SET status = ?, assigned_to = NULL WHERE id = ?");
            $stmt->execute([$new_status, $_POST['tool_id']]);

            // 2. Update History Record (Close open assignment)
            // Find latest open assignment
            $stmt = $pdo->prepare("UPDATE tool_assignments SET returned_date = CURRENT_TIMESTAMP, condition_on_return = ? WHERE tool_id = ? AND returned_date IS NULL");
            $stmt->execute([$_POST['condition'], $_POST['tool_id']]);

            $pdo->commit();
            $success = "Tool returned successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "return failed: " . $e->getMessage();
        }
    } elseif ($action == 'delete_tool') {
        try {
            $pdo->prepare("DELETE FROM tools WHERE id = ?")->execute([$_POST['id']]);
            $success = "Tool deleted.";
        } catch (Exception $e) {
            $error = "Deletion failed: " . $e->getMessage();
        }
    }
}

// Stats
$total_tools = $pdo->query("SELECT COUNT(*) FROM tools")->fetchColumn();
$issued_tools = $pdo->query("SELECT COUNT(*) FROM tools WHERE status = 'issued'")->fetchColumn();
$available_tools = $pdo->query("SELECT COUNT(*) FROM tools WHERE status = 'in_store'")->fetchColumn();
$damaged_tools = $pdo->query("SELECT COUNT(*) FROM tools WHERE status = 'damaged'")->fetchColumn();

// Fetch Data
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (t.name LIKE ? OR t.serial_number LIKE ? OR t.category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_status) {
    $where .= " AND t.status = ?";
    $params[] = $filter_status;
}

$sql = "SELECT t.*, e.name as employee_name 
        FROM tools t 
        LEFT JOIN employees e ON t.assigned_to = e.id 
        $where 
        ORDER BY t.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tools = $stmt->fetchAll();

// Fetch Employees for dropdown
$employees = $pdo->query("SELECT id, name FROM employees WHERE status='active' ORDER BY name")->fetchAll();

?>

<div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Tools & Inventory</h1>
        <p class="text-gray-600 mt-2">Manage company tools, equipment, and staff assignments.</p>
    </div>
    <div class="mt-4 md:mt-0">
        <button onclick="openModal('addToolModal')"
            class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm">
            <i class="fas fa-plus mr-2"></i>Add New Tool
        </button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Total Tools</div>
        <div class="text-2xl font-bold text-gray-800"><?php echo $total_tools; ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-orange-500">
        <div class="text-sm text-gray-500">Issued / In Use</div>
        <div class="text-2xl font-bold text-gray-800"><?php echo $issued_tools; ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Available In Store</div>
        <div class="text-2xl font-bold text-gray-800"><?php echo $available_tools; ?></div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-red-500">
        <div class="text-sm text-gray-500">Damaged / Repair</div>
        <div class="text-2xl font-bold text-gray-800"><?php echo $damaged_tools; ?></div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white p-4 rounded-lg shadow-sm mb-6">
    <form method="get" class="flex flex-col md:flex-row gap-4">
        <input type="hidden" name="page" value="tools">
        <div class="flex-1">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search by name, serial #, or category..."
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
        </div>
        <div class="w-full md:w-48">
            <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                <option value="">All Statuses</option>
                <option value="in_store" <?php echo $filter_status == 'in_store' ? 'selected' : ''; ?>>In Store</option>
                <option value="issued" <?php echo $filter_status == 'issued' ? 'selected' : ''; ?>>Issued</option>
                <option value="damaged" <?php echo $filter_status == 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                <option value="lost" <?php echo $filter_status == 'lost' ? 'selected' : ''; ?>>Lost</option>
                <option value="scrapped" <?php echo $filter_status == 'scrapped' ? 'selected' : ''; ?>>Scrapped</option>
            </select>
        </div>
        <button type="submit"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium transition-colors">
            Filter
        </button>
        <?php if ($search || $filter_status): ?>
            <a href="?page=tools" class="flex items-center text-gray-500 hover:text-gray-700 px-2">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tools Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                    <th class="p-4 border-b whitespace-nowrap">Tool Details</th>
                    <th class="p-4 border-b whitespace-nowrap">Serial / Category</th>
                    <th class="p-4 border-b whitespace-nowrap">Status</th>
                    <th class="p-4 border-b whitespace-nowrap">Current Holder</th>
                    <th class="p-4 border-b whitespace-nowrap">Purchase Info</th>
                    <th class="p-4 border-b text-center whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                <?php if (empty($tools)): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-gray-500">No tools found matching your criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tools as $tool): ?>
                        <tr class="hover:bg-gray-50 group">
                            <td class="p-4 whitespace-nowrap">
                                <div class="font-bold text-gray-900"><?php echo htmlspecialchars($tool['name']); ?></div>
                                <?php if ($tool['notes']): ?>
                                    <div class="text-xs text-gray-500 mt-1"><i
                                            class="fas fa-sticky-note mr-1"></i><?php echo htmlspecialchars($tool['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <div class="text-gray-900 font-mono text-xs">
                                    <?php echo htmlspecialchars($tool['serial_number'] ?? '-'); ?></div>
                                <span
                                    class="inline-block mt-1 px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600"><?php echo htmlspecialchars($tool['category']); ?></span>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'in_store' => 'bg-green-100 text-green-800',
                                    'issued' => 'bg-orange-100 text-orange-800',
                                    'damaged' => 'bg-red-100 text-red-800',
                                    'lost' => 'bg-gray-800 text-white',
                                    'scrapped' => 'bg-gray-200 text-gray-600'
                                ];
                                $cls = $status_colors[$tool['status']] ?? 'bg-gray-100';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-bold uppercase <?php echo $cls; ?>">
                                    <?php echo str_replace('_', ' ', $tool['status']); ?>
                                </span>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <?php if ($tool['status'] == 'issued' && $tool['employee_name']): ?>
                                    <div class="flex items-center text-blue-900 font-medium">
                                        <i class="fas fa-user-circle mr-2 text-blue-400"></i>
                                        <?php echo htmlspecialchars($tool['employee_name']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 whitespace-nowrap text-xs text-gray-600">
                                <div><span class="font-medium">Date:</span>
                                    <?php echo $tool['purchase_date'] ? date('M Y', strtotime($tool['purchase_date'])) : '-'; ?>
                                </div>
                                <div><span class="font-medium">Cost:</span>
                                    <?php echo $tool['cost'] ? money($tool['cost']) : '-'; ?></div>
                                <?php if ($tool['warranty_expiry']):
                                    $days_left = (strtotime($tool['warranty_expiry']) - time()) / (60 * 60 * 24);
                                    $w_color = $days_left < 0 ? 'text-red-500' : ($days_left < 30 ? 'text-orange-500' : 'text-green-600');
                                    ?>
                                    <div class="<?php echo $w_color; ?> mt-1" title="Warranty Expiry">
                                        <i
                                            class="fas fa-shield-alt mr-1"></i><?php echo date('d/m/Y', strtotime($tool['warranty_expiry'])); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center space-x-2">
                                    <?php if ($tool['status'] == 'in_store'): ?>
                                        <button onclick="openAssignModal(<?php echo htmlspecialchars(json_encode($tool)); ?>)"
                                            class="text-blue-600 hover:text-blue-800" title="Issue Tool">
                                            <i class="fas fa-hand-holding"></i>
                                        </button>
                                    <?php elseif ($tool['status'] == 'issued'): ?>
                                        <button onclick="openReturnModal(<?php echo htmlspecialchars(json_encode($tool)); ?>)"
                                            class="text-orange-600 hover:text-orange-800" title="Return Tool">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($tool)); ?>)"
                                        class="text-gray-500 hover:text-gray-700" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="post" onsubmit="return confirm('Are you sure? This cannot be undone.');"
                                        class="inline">
                                        <input type="hidden" name="action" value="delete_tool">
                                        <input type="hidden" name="id" value="<?php echo $tool['id']; ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-600" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Tool Modal -->
<div id="addToolModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 opacity-75" onclick="closeModal('addToolModal')"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post" id="toolForm">
                <input type="hidden" name="action" id="toolAction" value="add_tool">
                <input type="hidden" name="id" id="toolId">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4" id="toolModalTitle">Add New Tool</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tool Name *</label>
                            <input type="text" name="name" id="toolName" required
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <input type="text" name="category" id="toolCategory" placeholder="e.g. Power Tools"
                                    list="categories" class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <datalist id="categories">
                                    <option value="Power Tools">
                                    <option value="Hand Tools">
                                    <option value="Safety Gear">
                                    <option value="Electronics">
                                    <option value="Vehicles">
                                </datalist>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Serial Number</label>
                                <input type="text" name="serial_number" id="toolSerial"
                                    class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Purchase Date</label>
                                <input type="date" name="purchase_date" id="toolDate"
                                    class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Warranty Expiry</label>
                                <input type="date" name="warranty_expiry" id="toolWarranty"
                                    class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Cost</label>
                                <input type="number" step="0.01" name="cost" id="toolCost"
                                    class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Supplier</label>
                                <input type="text" name="supplier" id="toolSupplier"
                                    class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="toolStatus" onchange="toggleAssignedTo()"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="in_store">In Store</option>
                                <option value="issued">Issued</option>
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                                <option value="scrapped">Scrapped</option>
                            </select>
                        </div>
                        <div id="assignedToContainer" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700">Assigned To (Employee) *</label>
                            <select name="assigned_to" id="toolAssignedTo" class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" id="toolNotes" rows="2"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                    <button type="button" onclick="closeModal('addToolModal')"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Tool Modal -->
<div id="assignModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 opacity-75" onclick="closeModal('assignModal')"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" name="action" value="assign_tool">
                <input type="hidden" name="tool_id" id="assignToolId">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Issue Tool: <span id="assignToolName"
                            class="text-blue-600"></span></h3>
                    <p class="text-sm text-gray-500 mb-4">Assign this tool to a staff member.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Select Employee *</label>
                            <select name="employee_id" required
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="">-- Select --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Condition</label>
                            <input type="text" name="condition" value="Good"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="2"
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Issue
                        Tool</button>
                    <button type="button" onclick="closeModal('assignModal')"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Tool Modal -->
<div id="returnModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 opacity-75" onclick="closeModal('returnModal')"></div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" name="action" value="return_tool">
                <input type="hidden" name="tool_id" id="returnToolId">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Return Tool: <span id="returnToolName"
                            class="text-orange-600"></span></h3>
                    <p class="text-sm text-gray-500 mb-4">Mark this tool as returned to store.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Condition on Return</label>
                            <input type="text" name="condition" value="Good" required
                                class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status After Return</label>
                            <select name="status_after_return" class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="in_store">In Store (Good Condition)</option>
                                <option value="damaged">Damaged (Needs Repair)</option>
                                <option value="scrapped">Scrapped (Broken/Unsuable)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Process
                        Return</button>
                    <button type="button" onclick="closeModal('returnModal')"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        if (id === 'addToolModal') {
            document.getElementById('toolForm').reset();
            document.getElementById('toolAction').value = 'add_tool';
            document.getElementById('toolModalTitle').innerText = 'Add New Tool';
            document.getElementById('assignedToContainer').style.display = 'none';
            document.getElementById('toolAssignedTo').required = false;
        }
    }

    function toggleAssignedTo() {
        const status = document.getElementById('toolStatus').value;
        const container = document.getElementById('assignedToContainer');
        const select = document.getElementById('toolAssignedTo');
        
        if (status === 'issued') {
            container.style.display = 'block';
            select.required = true;
        } else {
            container.style.display = 'none';
            select.value = '';
            select.required = false;
        }
    }

    function openEditModal(tool) {
        openModal('addToolModal');
        document.getElementById('toolAction').value = 'edit_tool';
        document.getElementById('toolId').value = tool.id;
        document.getElementById('toolModalTitle').innerText = 'Edit Tool: ' + tool.name;

        document.getElementById('toolName').value = tool.name;
        document.getElementById('toolCategory').value = tool.category;
        document.getElementById('toolSerial').value = tool.serial_number;
        document.getElementById('toolDate').value = tool.purchase_date;
        document.getElementById('toolWarranty').value = tool.warranty_expiry;
        document.getElementById('toolCost').value = tool.cost;
        document.getElementById('toolSupplier').value = tool.supplier;
        document.getElementById('toolStatus').value = tool.status;
        document.getElementById('toolNotes').value = tool.notes;
        
        const container = document.getElementById('assignedToContainer');
        const select = document.getElementById('toolAssignedTo');
        
        if (tool.status === 'issued') {
            container.style.display = 'block';
            select.value = tool.assigned_to;
            select.required = true;
        } else {
            container.style.display = 'none';
            select.value = '';
            select.required = false;
        }
    }

    function openAssignModal(tool) {
        openModal('assignModal');
        document.getElementById('assignToolId').value = tool.id;
        document.getElementById('assignToolName').innerText = tool.name;
    }

    function openReturnModal(tool) {
        openModal('returnModal');
        document.getElementById('returnToolId').value = tool.id;
        document.getElementById('returnToolName').innerText = tool.name;
    }
</script>