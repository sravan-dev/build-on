<?php

include_once 'includes/db.php';
require_once 'includes/excel_import.php';

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $importResult = importClientsFromExcel($_FILES['excel_file'], $pdo);

        if ($importResult['success'] > 0) {
            $success_message = "Successfully imported {$importResult['success']} client(s).";
            if (!empty($importResult['errors'])) {
                $success_message .= " " . count($importResult['errors']) . " row(s) had errors.";
            }
        }

        if (!empty($importResult['errors'])) {
            $error_message = "Import errors:\n" . implode("\n", $importResult['errors']);
        }
    } else {
        $error_message = "Please select an Excel file to upload.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add'])) {

        $stmt = $pdo->prepare("INSERT INTO clients (name, contact, email, phone, address) VALUES (?, ?, ?, ?, ?)");

        $stmt->execute([$_POST['name'], $_POST['contact'], $_POST['email'], $_POST['phone'], $_POST['address']]);

    } elseif (isset($_POST['update'])) {

        $stmt = $pdo->prepare("UPDATE clients SET name=?, contact=?, email=?, phone=?, address=? WHERE id=?");

        $stmt->execute([$_POST['name'], $_POST['contact'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['id']]);

    }

}

if (isset($_GET['delete'])) {

    $stmt = $pdo->prepare("DELETE FROM clients WHERE id=?");

    $stmt->execute([$_GET['delete']]);

}

// Handle delete all
if (isset($_POST['delete_all'])) {
    $stmt = $pdo->prepare("DELETE FROM clients");
    $stmt->execute();
    $success_message = "All clients have been deleted successfully.";
}

// Handle bulk delete
if (isset($_POST['delete_selected']) && !empty($_POST['selected_clients'])) {
    $selectedIds = $_POST['selected_clients'];
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id IN ($placeholders)");
    $stmt->execute($selectedIds);
    $success_message = "Successfully deleted " . count($selectedIds) . " client(s).";
}

// Fetch all clients (DataTables will handle pagination and search)
$clients = $pdo->query("SELECT * FROM clients ORDER BY id DESC")->fetchAll();
$all_clients_count = count($clients);

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Clients</h1>
    <p class="text-gray-600 mt-2">Manage your client information and track their payment history</p>
</div>

<?php if (isset($success_message)): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded whitespace-pre-line">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Client Management</h2>
                <!-- DEBUG: File loaded at <?php echo date('H:i:s'); ?> -->
                <div class="flex space-x-3">
                    <button type="button" id="deleteSelectedBtn"
                        class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="deleteSelected()" style="display: none;">
                        <i class="fas fa-trash-alt mr-1 md:mr-2"></i>Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button type="button"
                        class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="confirmDeleteAll()">
                        <i class="fas fa-trash-alt mr-1 md:mr-2"></i>Delete All
                    </button>
                    <button type="button"
                        class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="openImportModal();">
                        <i class="fas fa-file-excel mr-1 md:mr-2"></i>Import Excel
                    </button>
                    <button type="button"
                        class="bg-primary hover:bg-secondary text-white px-2 py-1 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="document.getElementById('addForm').classList.toggle('hidden')">
                        <i class="fas fa-plus mr-1 md:mr-2"></i>Add Client
                    </button>
                </div>
            </div>
        </div>

        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" placeholder="Client Name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact</label>
                        <input type="text" name="contact" placeholder="Contact Person"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" placeholder="client@example.com"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" placeholder="+1 (555) 123-4567"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="3" placeholder="Full address..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Client .
                    </button>
                </div>
            </form>
        </div>

        <div class="p-4 md:p-6">
            <div class="overflow-x-auto -mx-4 md:mx-0">
                <table id="clientsTable" class="w-full table-auto min-w-full display">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 md:px-4 py-3 text-center">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"
                                    class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary">
                            </th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contact</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Phone</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Address</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Invoice</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Paid</th>
                            <th
                                class="px-2 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Balance</th>
                            <th
                                class="px-2 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($clients as $client): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 md:px-4 py-3 text-center">
                                    <input type="checkbox" name="client_checkbox" value="<?php echo $client['id']; ?>"
                                        onchange="updateSelectedCount()"
                                        class="client-checkbox w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary">
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['contact']); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['email']); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['phone']); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo htmlspecialchars($client['address']); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo money($client['total_invoice'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo money($client['total_paid'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap">
                                    <div class="text-xs md:text-sm text-gray-900">
                                        <?php echo money($client['balance'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-2 md:px-6 py-3 whitespace-nowrap text-center">
                                    <div class="flex flex-col space-y-1">
                                        <button class="text-primary hover:text-secondary text-xs"
                                            onclick="editClient(<?php echo htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8'); ?>)"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?page=clients&delete=<?php echo $client['id']; ?>"
                                            class="text-red-600 hover:text-red-900 text-xs"
                                            onclick="return confirm('Are you sure you want to delete this client?')"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
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
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Client</h3>
                            <input type="hidden" id="edit-id" name="id">
                            <div class="mb-4">
                                <label for="edit-name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" id="edit-name"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-contact"
                                    class="block text-sm font-medium text-gray-700">Contact</label>
                                <input type="text" name="contact" id="edit-contact"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="edit-email"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" name="phone" id="edit-phone"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-address"
                                    class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea name="address" id="edit-address" rows="3"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
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
            function editClient(client) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = client.id;
                document.getElementById('edit-name').value = client.name;
                document.getElementById('edit-contact').value = client.contact;
                document.getElementById('edit-email').value = client.email;
                document.getElementById('edit-phone').value = client.phone;
                document.getElementById('edit-address').value = client.address;
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            // Import Excel modal functions
            function openImportModal() {
                document.getElementById('importModal').classList.remove('hidden');
            }

            function closeImportModal() {
                document.getElementById('importModal').classList.add('hidden');
                document.getElementById('importForm').reset();
            }
        </script>

        <!-- Import Excel Modal -->
        <div id="importModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="closeImportModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                    onclick="event.stopPropagation()">
                    <form id="importForm" method="post" enctype="multipart/form-data">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Import Clients from Excel</h3>
                                <button type="button" onclick="closeImportModal()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                    <p class="text-sm text-blue-800 mb-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <strong>Instructions:</strong>
                                    </p>
                                    <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                                        <li>Download the sample template below</li>
                                        <li>Fill in your client data</li>
                                        <li>Upload the completed Excel file</li>
                                    </ol>
                                    <div class="mt-3">
                                        <a href="?page=clients&download_client_template=1"
                                            class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors">
                                            <i class="fas fa-download mr-2"></i>Download Sample Template
                                        </a>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Excel File (.xlsx, .xls)
                                    </label>
                                    <input type="file" name="excel_file" accept=".xlsx,.xls" required
                                        class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-600 file:text-white hover:file:bg-green-700 cursor:pointer focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                    <p class="mt-1 text-xs text-gray-500">Maximum file size: 5MB</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" name="import_excel"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-upload mr-2"></i>Upload & Import
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeImportModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Hidden form for delete all -->
        <form id="deleteAllForm" method="post" style="display: none;">
            <input type="hidden" name="delete_all" value="1">
        </form>

        <!-- Hidden form for delete selected -->
        <form id="deleteSelectedForm" method="post" style="display: none;">
            <input type="hidden" name="delete_selected" value="1">
            <div id="selectedClientsContainer"></div>
        </form>

        <script>
            function openImportModal() {
                document.getElementById('importModal').classList.remove('hidden');
            }

            function closeImportModal() {
                document.getElementById('importModal').classList.add('hidden');
            }

            function confirmDeleteAll() {
                const clientCount = <?php echo $all_clients_count; ?>;

                if (clientCount === 0) {
                    alert('There are no clients to delete.');
                    return;
                }

                const message = `Are you sure you want to delete ALL ${clientCount} client(s)?\n\nThis action cannot be undone!`;

                if (confirm(message)) {
                    document.getElementById('deleteAllForm').submit();
                }
            }

            function toggleSelectAll(checkbox) {
                const checkboxes = document.querySelectorAll('.client-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = checkbox.checked;
                });
                updateSelectedCount();
            }

            function updateSelectedCount() {
                const checkboxes = document.querySelectorAll('.client-checkbox:checked');
                const count = checkboxes.length;
                const deleteBtn = document.getElementById('deleteSelectedBtn');
                const countSpan = document.getElementById('selectedCount');

                countSpan.textContent = count;

                if (count > 0) {
                    deleteBtn.style.display = 'inline-flex';
                } else {
                    deleteBtn.style.display = 'none';
                }

                // Update select all checkbox state
                const allCheckboxes = document.querySelectorAll('.client-checkbox');
                const selectAllCheckbox = document.getElementById('selectAll');
                selectAllCheckbox.checked = count === allCheckboxes.length && count > 0;
            }

            function deleteSelected() {
                const checkboxes = document.querySelectorAll('.client-checkbox:checked');
                const count = checkboxes.length;

                if (count === 0) {
                    alert('Please select at least one client to delete.');
                    return;
                }

                const message = `Are you sure you want to delete ${count} selected client(s)?\n\nThis action cannot be undone!`;

                if (confirm(message)) {
                    const form = document.getElementById('deleteSelectedForm');
                    const container = document.getElementById('selectedClientsContainer');
                    container.innerHTML = '';

                    checkboxes.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_clients[]';
                        input.value = checkbox.value;
                        container.appendChild(input);
                    });

                    form.submit();
                }
            }

            function editClient(client) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = client.id;
                document.getElementById('edit-name').value = client.name;
                document.getElementById('edit-contact').value = client.contact || '';
                document.getElementById('edit-email').value = client.email || '';
                document.getElementById('edit-phone').value = client.phone || '';
                document.getElementById('edit-address').value = client.address || '';
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            // Initialize DataTables
            $(document).ready(function () {
                $('#clientsTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    order: [], // Disable initial sorting
                    columnDefs: [
                        { orderable: false, targets: [0, -1] } // Disable sorting for checkbox (first) and actions (last) columns
                    ],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search clients...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ clients",
                        infoEmpty: "No clients found",
                        infoFiltered: "(filtered from _MAX_ total clients)",
                        zeroRecords: "No matching clients found",
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

            #clientsTable_wrapper .dataTables_filter input {
                min-width: 250px;
            }
        </style>