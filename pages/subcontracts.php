<?php

include_once 'includes/db.php';
include_once 'includes/payment_methods.php';

// Fetch all subcontracts with contractor info
$subcontracts = $pdo->query("
    SELECT s.*, 
           pr.name as project_name,
           c.company_name as contractor_name,
           c.phone_number as contractor_phone
    FROM subcontracts s
    LEFT JOIN projects pr ON s.project_id = pr.id
    LEFT JOIN contractors c ON s.contractor_id = c.id
    ORDER BY s.payment_date DESC, s.id DESC
")->fetchAll();

// Fetch projects for dropdown
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

// Fetch contractors for dropdown
$contractors = $pdo->query("SELECT id, company_name, phone_number FROM contractors ORDER BY company_name")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Subcontracts</h1>
    <p class="text-gray-600 mt-2">Manage subcontractor payments and outsourced work</p>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
    <span class="block sm:inline">Operation completed successfully!</span>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
    <span class="block sm:inline"><?php echo htmlspecialchars($_GET['error']); ?></span>
</div>
<?php endif; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h2 class="text-xl font-semibold text-gray-900">Subcontract Records</h2>
                <div class="flex gap-2">
                    <button class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors" 
                            onclick="openContractorModal()">
                        <i class="fas fa-user-plus mr-2"></i>Add Contractor
                    </button>
                    <button class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors" 
                            onclick="openAddModal()">
                        <i class="fas fa-plus mr-2"></i>Add Subcontract
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table id="subcontractsTable" class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contractor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($subcontracts as $subcontract): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($subcontract['payment_date'])); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium"><?php echo htmlspecialchars($subcontract['project_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="font-medium"><?php echo htmlspecialchars($subcontract['contractor_name'] ?? 'N/A'); ?></div>
                                <?php if ($subcontract['contractor_phone']): ?>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($subcontract['contractor_phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo money($subcontract['amount']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch ($subcontract['payment_method']) {
                                        case 'company_cash':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'company_bank':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'company_card':
                                        case 'credit_card':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'company_cheque':
                                            echo 'bg-orange-100 text-orange-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo get_payment_method_label($subcontract['payment_method']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php if (!empty($subcontract['description'])): ?>
                                    <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($subcontract['description']); ?>">
                                        <?php echo htmlspecialchars($subcontract['description']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2">
                                <a href="subcontract_receipt.php?id=<?php echo $subcontract['id']; ?>" target="_blank" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
                                <button onclick="editSubcontract(<?php echo htmlspecialchars(json_encode($subcontract), ENT_QUOTES, 'UTF-8'); ?>)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteSubcontract(<?php echo $subcontract['id']; ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Contractor Modal -->
<div id="contractorModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" onclick="closeContractorModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" onclick="event.stopPropagation()">
            <form method="post" action="index.php?page=subcontracts">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Add Contractor</h3>
                        <button type="button" onclick="closeContractorModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
                            <input type="text" name="company_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required placeholder="Enter company name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                            <input type="text" name="phone_number" class="w-full px-3 py-2 border border-gray-300 rounded-md" required placeholder="Enter phone number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter email (optional)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter address (optional)"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeContractorModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="add_contractor" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">
                        <i class="fas fa-save mr-2"></i>Add Contractor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Subcontract Modal -->
<div id="subcontractModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" onclick="closeModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full" onclick="event.stopPropagation()">
            <form method="post" action="index.php?page=subcontracts">
                <input type="hidden" name="subcontract_id" id="subcontract_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add Subcontract</h3>
                        <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Project *</label>
                            <select name="project_id" id="project_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contractor *</label>
                            <div class="flex gap-2">
                                <div class="flex-1 relative">
                                    <select name="contractor_id" id="contractor_id" class="w-full px-3 py-2 border border-gray-300 rounded-md pr-10" required onchange="updateDeleteButton()">
                                        <option value="">Select Contractor</option>
                                        <?php foreach ($contractors as $contractor): ?>
                                        <option value="<?php echo $contractor['id']; ?>" data-name="<?php echo htmlspecialchars($contractor['company_name']); ?>">
                                            <?php echo htmlspecialchars($contractor['company_name']); ?>
                                            <?php if ($contractor['phone_number']): ?>
                                                - <?php echo htmlspecialchars($contractor['phone_number']); ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="deleteContractorBtn" onclick="deleteSelectedContractor()" class="hidden absolute right-2 top-1/2 transform -translate-y-1/2 text-red-600 hover:text-red-800 px-2" title="Remove this contractor">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <button type="button" onclick="openQuickContractorModal()" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md" title="Add New Contractor">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select name="payment_method" id="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md" required onchange="toggleChequeFields()">
                                <?php echo payment_method_options(); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                            <input type="number" step="0.01" name="amount" id="amount" class="w-full px-3 py-2 border border-gray-300 rounded-md" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                            <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Account</label>
                            <input type="text" name="payment_account" id="payment_account" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g., Account #1234">
                        </div>
                    </div>
                    
                    <div id="cheque_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cheque Number</label>
                            <input type="text" name="cheque_number" id="cheque_number" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" id="bank_name" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter work description or notes..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="add_subcontract" id="submitBtn" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">
                        <i class="fas fa-save mr-2"></i><span id="submitText">Paid to Contract</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="post" style="display:none;">
    <input type="hidden" name="delete_subcontract" value="1">
    <input type="hidden" name="delete_id" id="delete_id">
</form>

<script>
function openContractorModal() {
    document.getElementById('contractorModal').classList.remove('hidden');
}

function closeContractorModal() {
    document.getElementById('contractorModal').classList.add('hidden');
}

function openAddModal() {
    document.getElementById('subcontractModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Add Subcontract';
    document.getElementById('submitText').textContent = 'Paid to Contract';
    document.getElementById('subcontract_id').value = '';
    document.querySelector('#subcontractModal form').reset();
    document.getElementById('payment_date').value = '<?php echo date('Y-m-d'); ?>';
    document.querySelector('[name="add_subcontract"]').setAttribute('name', 'add_subcontract');
}

function closeModal() {
    document.getElementById('subcontractModal').classList.add('hidden');
}

function toggleChequeFields() {
    const method = document.getElementById('payment_method').value;
    const chequeFields = document.getElementById('cheque_fields');
    
    if (method === 'company_cheque') {
        chequeFields.classList.remove('hidden');
    } else {
        chequeFields.classList.add('hidden');
    }
}

function editSubcontract(subcontract) {
    document.getElementById('subcontractModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Edit Subcontract';
    document.getElementById('submitText').textContent = 'Update Payment';
    
    document.getElementById('subcontract_id').value = subcontract.id;
    document.getElementById('project_id').value = subcontract.project_id;
    document.getElementById('contractor_id').value = subcontract.contractor_id;
    document.getElementById('payment_method').value = subcontract.payment_method;
    document.getElementById('amount').value = subcontract.amount;
    document.getElementById('payment_date').value = subcontract.payment_date;
    document.getElementById('payment_account').value = subcontract.payment_account || '';
    document.getElementById('cheque_number').value = subcontract.cheque_number || '';
    document.getElementById('bank_name').value = subcontract.bank_name || '';
    document.getElementById('description').value = subcontract.description || '';
    document.getElementById('notes').value = subcontract.notes || '';
    
    toggleChequeFields();
    
    // Change submit button name for update
    document.querySelector('[name="add_subcontract"]').setAttribute('name', 'update_subcontract');
}

function deleteSubcontract(id) {
    if (confirm('Are you sure you want to delete this subcontract payment? This action cannot be undone.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Quick add contractor from subcontract modal
function openQuickContractorModal() {
    // Create a simplified modal inline
    const existingQuickModal = document.getElementById('quickContractorModal');
    if (existingQuickModal) {
        existingQuickModal.classList.remove('hidden');
        return;
    }
    
    const modalHTML = `
        <div id="quickContractorModal" class="fixed z-[60] inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" onclick="closeQuickContractorModal()">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" onclick="event.stopPropagation()">
                    <form id="quickContractorForm">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Quick Add Contractor</h3>
                                <button type="button" onclick="closeQuickContractorModal()" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
                                    <input type="text" name="company_name" id="quick_company_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required placeholder="Enter company name">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                    <input type="text" name="phone_number" id="quick_phone_number" class="w-full px-3 py-2 border border-gray-300 rounded-md" required placeholder="Enter phone number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end space-x-3">
                            <button type="button" onclick="closeQuickContractorModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                            <button type="submit" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">
                                <i class="fas fa-save mr-2"></i>Add Contractor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add form submit handler
    document.getElementById('quickContractorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveQuickContractor();
    });
}

function closeQuickContractorModal() {
    const modal = document.getElementById('quickContractorModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function saveQuickContractor() {
    const form = document.getElementById('quickContractorForm');
    const formData = new FormData(form);
    formData.append('action', 'add_contractor');
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    fetch('ajax/add_contractor.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new contractor to dropdown
            const contractorSelect = document.getElementById('contractor_id');
            const option = document.createElement('option');
            option.value = data.contractor.id;
            option.textContent = data.contractor.company_name;
            if (data.contractor.phone_number) {
                option.textContent += ' - ' + data.contractor.phone_number;
            }
            contractorSelect.appendChild(option);
            
            // Select the newly added contractor
            contractorSelect.value = data.contractor.id;
            
            // Close modal and reset form
            closeQuickContractorModal();
            form.reset();
            
            // Show success message
            alert('Contractor added successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the contractor');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Show/hide delete button based on contractor selection
function updateDeleteButton() {
    const contractorSelect = document.getElementById('contractor_id');
    const deleteBtn = document.getElementById('deleteContractorBtn');
    
    if (contractorSelect.value && contractorSelect.value !== '') {
        deleteBtn.classList.remove('hidden');
    } else {
        deleteBtn.classList.add('hidden');
    }
}

// Delete selected contractor
function deleteSelectedContractor() {
    const contractorSelect = document.getElementById('contractor_id');
    const contractorId = contractorSelect.value;
    const contractorName = contractorSelect.options[contractorSelect.selectedIndex].getAttribute('data-name');
    
    if (!contractorId) {
        alert('Please select a contractor to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete contractor "${contractorName}"? This action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_contractor');
    formData.append('contractor_id', contractorId);
    
    fetch('ajax/delete_contractor.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove option from dropdown
            contractorSelect.remove(contractorSelect.selectedIndex);
            
            // Hide delete button
            document.getElementById('deleteContractorBtn').classList.add('hidden');
            
            alert('Contractor deleted successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the contractor');
    });
}

// Initialize DataTable
$(document).ready(function() {
    $('#subcontractsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25
    });
});
</script>
