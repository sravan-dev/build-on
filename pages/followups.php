<?php

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = null;
$error = null;

// Handle manual follow-up submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_followup'])) {
    $record_type = $_POST['record_type'];
    $record_id = (int)$_POST['record_id'];
    $channel = $_POST['channel'];
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message']);
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($message_text)) {
        $error = 'Message content is required';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO followup_records 
                (record_type, record_id, followup_type, channel, status, subject, message, sent_by, sent_at, notes) 
                VALUES (?, ?, 'manual', ?, 'sent', ?, ?, 'Admin', ?, ?)");
            
            $stmt->execute([
                $record_type,
                $record_id,
                $channel,
                $subject,
                $message_text,
                date('Y-m-d H:i:s'),
                $notes
            ]);
            
            // Update last follow-up date
            $update_table = $record_type === 'quotation' ? 'quotations' : 'invoices';
            $update_stmt = $pdo->prepare("UPDATE $update_table SET last_followup_date = ? WHERE id = ?");
            $update_stmt->execute([date('Y-m-d H:i:s'), $record_id]);
            
            $message = 'Follow-up recorded successfully';
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $error = 'Failed to record follow-up: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$record_type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for follow-up records
$where_conditions = ['1=1'];
$params = [];

if ($record_type_filter) {
    $where_conditions[] = "fr.record_type = ?";
    $params[] = $record_type_filter;
}

if ($status_filter) {
    $where_conditions[] = "fr.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(fr.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(fr.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get follow-up records
$followups = [];
try {
    $sql = "SELECT fr.*, 
            CASE 
                WHEN fr.record_type = 'quotation' THEN q.id
                WHEN fr.record_type = 'invoice' THEN i.id
            END as record_number,
            CASE 
                WHEN fr.record_type = 'quotation' THEN p.name
                WHEN fr.record_type = 'invoice' THEN p2.name
            END as project_name,
            CASE 
                WHEN fr.record_type = 'quotation' THEN c.name
                WHEN fr.record_type = 'invoice' THEN c2.name
            END as client_name,
            CASE 
                WHEN fr.record_type = 'quotation' THEN q.total_amount
                WHEN fr.record_type = 'invoice' THEN i.total_amount
            END as amount
            FROM followup_records fr
            LEFT JOIN quotations q ON fr.record_type = 'quotation' AND fr.record_id = q.id
            LEFT JOIN invoices i ON fr.record_type = 'invoice' AND fr.record_id = i.id
            LEFT JOIN projects p ON q.project_id = p.id
            LEFT JOIN projects p2 ON p2.id = COALESCE(i.project_id, (SELECT project_id FROM quotations WHERE id = i.quotation_id))
            LEFT JOIN clients c ON q.client_id = c.id
            LEFT JOIN clients c2 ON i.client_id = c2.id
            WHERE $where_clause
            ORDER BY fr.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Failed to load follow-ups: ' . $e->getMessage();
}

// Get pending quotations and overdue invoices for quick actions
$pending_quotations = [];
$overdue_invoices = [];

try {
    // Pending quotations (sent but not accepted/rejected)
    $pending_quotations = $pdo->query("
        SELECT q.id, q.total_amount, q.date, q.sent_date, q.last_followup_date,
               p.name as project_name, c.name as client_name
        FROM quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE q.status = 'pending' OR q.status = 'sent'
        ORDER BY q.date DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Overdue invoices
    $overdue_invoices = $pdo->query("
        SELECT i.id, i.total_amount, i.paid_amount, i.balance, i.date, i.due_date, i.last_followup_date,
               COALESCE(i.project_id, q.project_id) as project_id,
               COALESCE(dp.name, p.name) as project_name, c.name as client_name
        FROM invoices i
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN projects dp ON i.project_id = dp.id
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.status != 'paid' AND i.due_date < DATE('now')
        ORDER BY i.due_date ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Handle error silently
}

?>

<div class="followups-page">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Follow-up Management</h1>
        <p class="text-gray-600 mt-2">Track and manage quotation and invoice follow-ups</p>
        
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

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Pending Quotations -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Pending Quotations</h3>
                <p class="text-sm text-gray-600">Quotations awaiting client response</p>
            </div>
            <div class="p-4">
                <?php if (empty($pending_quotations)): ?>
                    <p class="text-gray-500 text-center py-4">No pending quotations</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($pending_quotations as $quote): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">Q#<?php echo $quote['id']; ?> - <?php echo htmlspecialchars($quote['project_name']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($quote['client_name']); ?></div>
                                    <div class="text-xs text-gray-500">
                                        Sent: <?php echo $quote['sent_date'] ? date('M d, Y', strtotime($quote['sent_date'])) : 'Not sent'; ?>
                                        <?php if ($quote['last_followup_date']): ?>
                                            | Last follow-up: <?php echo date('M d, Y', strtotime($quote['last_followup_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900"><?php echo money($quote['total_amount']); ?></div>
                                    <button onclick="openFollowupModal('quotation', <?php echo $quote['id']; ?>, 'Q#<?php echo $quote['id']; ?> - <?php echo htmlspecialchars($quote['project_name'], ENT_QUOTES); ?>')" 
                                            class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded mt-1">
                                        Follow-up
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Overdue Invoices -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Overdue Invoices</h3>
                <p class="text-sm text-gray-600">Invoices past due date</p>
            </div>
            <div class="p-4">
                <?php if (empty($overdue_invoices)): ?>
                    <p class="text-gray-500 text-center py-4">No overdue invoices</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($overdue_invoices as $invoice): 
                            $days_overdue = (strtotime('now') - strtotime($invoice['due_date'])) / (60 * 60 * 24);
                        ?>
                            <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg border border-red-200">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">I#<?php echo $invoice['id']; ?> - <?php echo htmlspecialchars($invoice['project_name']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                                    <div class="text-xs text-red-600">
                                        Overdue by <?php echo floor($days_overdue); ?> days
                                        <?php if ($invoice['last_followup_date']): ?>
                                            | Last follow-up: <?php echo date('M d, Y', strtotime($invoice['last_followup_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900"><?php echo money($invoice['balance']); ?></div>
                                    <button onclick="openFollowupModal('invoice', <?php echo $invoice['id']; ?>, 'I#<?php echo $invoice['id']; ?> - <?php echo htmlspecialchars($invoice['project_name'], ENT_QUOTES); ?>')" 
                                            class="text-sm bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded mt-1">
                                        Follow-up
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Follow-up History -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Follow-up History</h3>
                <a href="?page=followup_templates" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-cog mr-2"></i>Manage Templates
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-4 border-b bg-gray-50">
            <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="page" value="followups">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Types</option>
                        <option value="quotation" <?php echo $record_type_filter === 'quotation' ? 'selected' : ''; ?>>Quotations</option>
                        <option value="invoice" <?php echo $record_type_filter === 'invoice' ? 'selected' : ''; ?>>Invoices</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium transition-colors">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Follow-up Records Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($followups)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No follow-up records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($followups as $followup): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y H:i', strtotime($followup['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="font-medium">
                                        <?php echo strtoupper($followup['record_type'][0]); ?>#<?php echo $followup['record_number']; ?>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($followup['project_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($followup['client_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        switch($followup['channel']) {
                                            case 'email': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'whatsapp': echo 'bg-green-100 text-green-800'; break;
                                            case 'sms': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'call': echo 'bg-purple-100 text-purple-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($followup['channel']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($followup['subject']); ?>">
                                        <?php echo htmlspecialchars($followup['subject']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        switch($followup['status']) {
                                            case 'sent': echo 'bg-green-100 text-green-800'; break;
                                            case 'delivered': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'failed': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($followup['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewFollowup(<?php echo $followup['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
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

<!-- Follow-up Modal -->
<div id="followupModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Send Follow-up</h3>
                    <div id="followupRecordInfo" class="mb-4 p-3 bg-gray-50 rounded-lg"></div>
                    
                    <input type="hidden" id="followup-record-type" name="record_type">
                    <input type="hidden" id="followup-record-id" name="record_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Channel *</label>
                            <select name="channel" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="sms">SMS</option>
                                <option value="call">Phone Call</option>
                                <option value="in_app">In-App Note</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" name="subject" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                            <textarea name="message" rows="6" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="2" placeholder="Internal notes (optional)" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="send_followup" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary sm:ml-3 sm:w-auto sm:text-sm">
                        Send Follow-up
                    </button>
                    <button type="button" onclick="closeFollowupModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openFollowupModal(recordType, recordId, recordTitle) {
    document.getElementById('followup-record-type').value = recordType;
    document.getElementById('followup-record-id').value = recordId;
    document.getElementById('followupRecordInfo').innerHTML = '<strong>' + recordTitle + '</strong>';
    document.getElementById('followupModal').classList.remove('hidden');
}

function closeFollowupModal() {
    document.getElementById('followupModal').classList.add('hidden');
}

function viewFollowup(id) {
    // TODO: Implement view followup details
    alert('View followup details for ID: ' + id);
}
</script>
