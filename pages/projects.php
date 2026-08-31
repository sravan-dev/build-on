<?php


include_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add'])) {

        $stmt = $pdo->prepare("INSERT INTO projects (name, client_id) VALUES (?, ?)");

        $stmt->execute([$_POST['name'], $_POST['client_id']]);

    } elseif (isset($_POST['update'])) {

        $stmt = $pdo->prepare("UPDATE projects SET name=?, client_id=? WHERE id=?");

        $stmt->execute([$_POST['name'], $_POST['client_id'], $_POST['id']]);

    }

}

if (isset($_GET['delete'])) {

    $stmt = $pdo->prepare("DELETE FROM projects WHERE id=?");

    $stmt->execute([$_GET['delete']]);


}



// Detect database driver for cross-database compatibility
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

/**
 * Calculate labour cost for a project using the same logic as attendance_report.php
 * This calculates working hours from in/out times, subtracts break time, and applies the hourly rate formula
 */
function calculateProjectLabourCost($pdo, $projectName)
{
    $stmt = $pdo->prepare("
        SELECT da.id, da.in_time, da.out_time, e.monthly_salary
        FROM daily_attendance da
        JOIN employees e ON da.employee_id = e.id
        WHERE da.work_site = ?
        AND da.in_time IS NOT NULL
        AND da.out_time IS NOT NULL
        AND e.monthly_salary > 0
    ");
    $stmt->execute([$projectName]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($records as $r) {
        $in = strtotime($r['in_time']);
        $out = strtotime($r['out_time']);
        $diff = $out - $in;

        // Handle overnight shifts (if out < in, add 24 hours)
        if ($diff < 0) {
            $diff += 86400; // Add 24 hours in seconds
        }

        // Get break time for this attendance record
        $breakStmt = $pdo->prepare("
            SELECT start_time, end_time 
            FROM attendance_logs 
            WHERE daily_attendance_id = ? AND activity_type = 'break'
        ");
        $breakStmt->execute([$r['id']]);
        $break_time = 0;
        while ($break = $breakStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($break['start_time'] && $break['end_time']) {
                $b1 = strtotime($break['start_time']);
                $b2 = strtotime($break['end_time']);
                $break_diff = $b2 - $b1;
                if ($break_diff < 0)
                    $break_diff += 86400; // Handle overnight breaks
                $break_time += $break_diff / 3600;
            }
        }

        $working_hours = max(0, ($diff / 3600) - $break_time);
        $hourly_rate = ($r['monthly_salary'] / 26 / 8);
        $total += $working_hours * $hourly_rate;
    }

    return round($total, 2);
}

// Fetch projects with basic data (labour cost calculated in PHP)
try {
    $projects = $pdo->query("
        SELECT p.*, 
               c.name as client_name,
               COALESCE((SELECT SUM(pm.amount) 
                         FROM payments pm
                         LEFT JOIN invoices i ON pm.invoice_id = i.id
                         LEFT JOIN quotations q ON i.quotation_id = q.id 
                         WHERE COALESCE(CASE WHEN i.quotation_id IS NOT NULL THEN q.project_id END, i.project_id) = p.id), 0) as total_income,
               COALESCE((SELECT SUM(total_amount) 
                         FROM purchases 
                         WHERE project_id = p.id), 0) as total_expenses
        FROM projects p 
        LEFT JOIN clients c ON p.client_id = c.id
        ORDER BY p.id DESC
    ")->fetchAll();

    // Calculate labour cost for each project using PHP
    foreach ($projects as &$project) {
        $project['total_labour_cost'] = calculateProjectLabourCost($pdo, $project['name']);
        $project['profit'] = $project['total_income'] - $project['total_expenses'] - $project['total_labour_cost'];
    }
    unset($project); // Break reference

} catch (PDOException $e) {
    // If there's an error, log it and set projects to empty array
    error_log("Projects query error: " . $e->getMessage());
    $projects = [];
}


$clients = $pdo->query("SELECT id, name FROM clients")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Projects</h1>
    <p class="text-gray-600 mt-2">Manage your projects and track their financial performance</p>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Project Management</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="pages/projects_export.php"
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors">
                        <i class="fas fa-file-excel mr-1 md:mr-2"></i>Export to Excel
                    </a>
                    <button
                        class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="document.getElementById('addForm').classList.toggle('hidden')">
                        <i class="fas fa-plus mr-1 md:mr-2"></i>Add Project
                    </button>
                </div>
            </div>
        </div>

        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project Name</label>
                        <input type="text" name="name" placeholder="Enter project name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                        <select name="client_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Project
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table id="projectsTable" class="w-full table-auto display">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Client</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Project Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Income</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Labour Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Expenses</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Profit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($projects as $project): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap" data-order="<?php echo (int) $project['id']; ?>">
                                    <div class="text-sm font-mono text-gray-500">
                                        #<?php echo (int) $project['id']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-blue-600">
                                        <?php
                                        // Get total quotation value for this project
                                        $quotationTotal = $pdo->prepare("SELECT
        (SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE project_id = ?)
      + (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE project_id = ? AND quotation_id IS NULL)");
                                        $quotationTotal->execute([$project['id'], $project['id']]);
                                        echo money($quotationTotal->fetchColumn());
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo money($project['total_income'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-orange-600 font-medium">
                                        <?php echo money($project['total_labour_cost'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo money($project['total_expenses'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm <?php echo (($project['profit'] ?? 0) >= 0) ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                        <?php echo money($project['profit'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick="editProject(<?php echo htmlspecialchars(json_encode($project), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?page=projects&delete=<?php echo $project['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this project?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form method="post">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Project</h3>
                            <input type="hidden" id="edit-id" name="id">
                            <div class="mb-4">
                                <label for="edit-name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" id="edit-name"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-client_id"
                                    class="block text-sm font-medium text-gray-700">Client</label>
                                <select name="client_id" id="edit-client_id"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>"><?php echo $client['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
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
        <script>
            function editProject(project) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = project.id;
                document.getElementById('edit-name').value = project.name;
                document.getElementById('edit-client_id').value = project.client_id;
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            // Initialize DataTables
            $(document).ready(function () {
                $('#projectsTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    order: [], // Disable initial sorting
                    columnDefs: [
                        { orderable: false, targets: [-1] } // Disable sorting for actions (last) column
                    ],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search projects...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ projects",
                        infoEmpty: "No projects found",
                        infoFiltered: "(filtered from _MAX_ total projects)",
                        zeroRecords: "No matching projects found",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    },
                    dom: '<"flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4"<"flex items-center gap-2"l><"flex-1"f>>rtip',
                    initComplete: function () {
                        // Style the search input
                        $('.dataTables_filter input').addClass('px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent');
                        $('.dataTables_filter input').css('min-width', '250px');

                        // Style the length select
                        $('.dataTables_length select').addClass('px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary');

                        // Style pagination
                        $('.dataTables_paginate').addClass('flex items-center gap-2 mt-4');
                    }
                });
            });
        </script>

        <style>
            /* DataTables custom styling */
            .dataTables_wrapper .dataTables_filter {
                margin-bottom: 0;
            }

            .dataTables_wrapper .dataTables_filter label {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-weight: 500;
                color: #374151;
            }

            .dataTables_wrapper .dataTables_length label {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-weight: 500;
                color: #374151;
            }

            .dataTables_wrapper .dataTables_info {
                padding-top: 1rem;
                color: #6b7280;
                font-size: 0.875rem;
            }

            .dataTables_wrapper .dataTables_paginate {
                padding-top: 1rem;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 0.5rem 1rem;
                margin: 0 0.125rem;
                border: 1px solid #d1d5db;
                border-radius: 0.375rem;
                background: white;
                color: #374151;
                cursor: pointer;
                transition: all 0.15s;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled):not(.current) {
                background: #f3f4f6;
                border-color: #9ca3af;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background: #f07d00;
                border-color: #f07d00;
                color: white;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
                color: #9ca3af;
                cursor: not-allowed;
            }

            #projectsTable_wrapper .dataTables_filter input {
                min-width: 250px;
            }
        </style>