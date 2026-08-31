<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = null;
$error = null;

// Handle template operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_template'])) {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $channel = $_POST['channel'];
        $subject = trim($_POST['subject'] ?? '');
        $message_text = trim($_POST['message']);
        
        if (empty($name) || empty($message_text)) {
            $error = 'Name and message are required';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO followup_templates (name, type, channel, subject, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $type, $channel, $subject, $message_text]);
                $message = 'Template created successfully';
            } catch (Exception $e) {
                $error = 'Failed to create template: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['update_template'])) {
        $id = (int)$_POST['template_id'];
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $channel = $_POST['channel'];
        $subject = trim($_POST['subject'] ?? '');
        $message_text = trim($_POST['message']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name) || empty($message_text)) {
            $error = 'Name and message are required';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE followup_templates SET name = ?, type = ?, channel = ?, subject = ?, message = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $type, $channel, $subject, $message_text, $is_active, $id]);
                $message = 'Template updated successfully';
            } catch (Exception $e) {
                $error = 'Failed to update template: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['delete_template'])) {
        $id = (int)$_POST['template_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM followup_templates WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Template deleted successfully';
        } catch (Exception $e) {
            $error = 'Failed to delete template: ' . $e->getMessage();
        }
    }
}

// Get templates
$templates = [];
try {
    $templates = $pdo->query("SELECT * FROM followup_templates ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load templates: ' . $e->getMessage();
}

?>

<div class="template-management-page">
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Follow-up Templates</h1>
                <p class="text-gray-600 mt-2">Manage message templates for automated and manual follow-ups</p>
            </div>
            <a href="?page=followups" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md font-medium transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Follow-ups
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="mt-4 rounded-lg border border-green-300 bg-green-50 text-green-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Template Form -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Add New Template</h3>
        </div>
        <div class="p-6">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                        <select name="type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="quotation">Quotation</option>
                            <option value="invoice">Invoice</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Channel *</label>
                        <select name="channel" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                            <option value="call">Phone Call</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject (for Email/SMS)</label>
                    <input type="text" name="subject" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message Template *</label>
                    <textarea name="message" rows="8" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    <div class="mt-2 text-xs text-gray-500">
                        <strong>Available placeholders:</strong>
                        {client_name}, {company_name}, {quotation_id}, {invoice_id}, {project_name}, {total_amount}, {balance}, {due_date}, {quotation_date}, {expiry_date}, {days_overdue}
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="add_template" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Template
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Templates List -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Existing Templates</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No templates found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $template['type'] === 'quotation' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ucfirst($template['type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <?php echo ucfirst($template['channel']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($template['subject']); ?>">
                                        <?php echo htmlspecialchars($template['subject']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $template['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editTemplate(<?php echo $template['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteTemplate(<?php echo $template['id']; ?>)" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div id="editTemplateModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <form method="post">
                <input type="hidden" id="edit-template-id" name="template_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Template</h3>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Template Name *</label>
                                <input type="text" id="edit-name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                                <select id="edit-type" name="type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="quotation">Quotation</option>
                                    <option value="invoice">Invoice</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Channel *</label>
                                <select id="edit-channel" name="channel" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="email">Email</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="sms">SMS</option>
                                    <option value="call">Phone Call</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" id="edit-subject" name="subject" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message Template *</label>
                            <textarea id="edit-message" name="message" rows="8" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                        
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" id="edit-is-active" name="is_active" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_template" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary sm:ml-3 sm:w-auto sm:text-sm">
                        Update Template
                    </button>
                    <button type="button" onclick="closeEditModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const templates = <?php echo json_encode($templates); ?>;

function editTemplate(id) {
    const template = templates.find(t => t.id == id);
    if (!template) return;
    
    document.getElementById('edit-template-id').value = template.id;
    document.getElementById('edit-name').value = template.name;
    document.getElementById('edit-type').value = template.type;
    document.getElementById('edit-channel').value = template.channel;
    document.getElementById('edit-subject').value = template.subject || '';
    document.getElementById('edit-message').value = template.message;
    document.getElementById('edit-is-active').checked = template.is_active == 1;
    
    document.getElementById('editTemplateModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editTemplateModal').classList.add('hidden');
}

function deleteTemplate(id) {
    if (confirm('Are you sure you want to delete this template?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="template_id" value="${id}">
            <input type="hidden" name="delete_template" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
