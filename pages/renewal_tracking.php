<?php
include_once 'includes/db.php';
include 'includes/payment_methods.php';

// Create renewal_payments table if not exists
$dto = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($dto == 'mysql') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'company_cash',
        notes TEXT,
        rp_id VARCHAR(50),
        year INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        document_type TEXT NOT NULL,
        amount REAL NOT NULL,
        payment_date TEXT NOT NULL,
        payment_method TEXT DEFAULT 'company_cash',
        notes TEXT,
        rp_id TEXT,
        year INTEGER,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )");
}

$current_date = date('Y-m-d');
$employees = $pdo->query("SELECT * FROM employees ORDER BY name")->fetchAll();

// Constants for expiry thresholds
$threshold_red = 30; // days
$threshold_yellow = 60; // days
$threshold_green = 90; // days

// Helper to calculate days remaining
function getDaysRemaining($date)
{
    if (!$date)
        return 9999;
    $diff = strtotime($date) - time();
    return round($diff / (60 * 60 * 24));
}

function getStatusColor($days)
{
    global $threshold_red, $threshold_yellow;
    if ($days < 0)
        return 'text-red-600 font-bold'; // Expired
    if ($days <= $threshold_red)
        return 'text-red-500 font-semibold';
    if ($days <= $threshold_yellow)
        return 'text-yellow-600';
    return 'text-green-600';
}

function getBadgeColor($days)
{
    global $threshold_red, $threshold_yellow;
    if ($days < 0)
        return 'bg-red-100 text-red-800';
    if ($days <= $threshold_red)
        return 'bg-red-50 text-red-600';
    if ($days <= $threshold_yellow)
        return 'bg-yellow-50 text-yellow-600';
    return 'bg-green-50 text-green-600';
}

$tab = $_GET['tab'] ?? 'expiry';
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Renewal Tracking & Reports</h1>
        <p class="text-gray-600 mt-2">Track Visa, ID, Passport expiries and Annual Tickets</p>
    </div>
    <div class="space-x-2">
        <a href="?page=payroll"
            class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1.5 md:px-4 md:py-2 rounded-lg font-medium transition-colors text-xs md:text-sm">
            <i class="fas fa-users mr-1 md:mr-2"></i>Manage Employees
        </a>
    </div>
</div>

<!-- Tabs -->
<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex space-x-8">
        <a href="?page=renewal_tracking&tab=expiry"
            class="<?php echo $tab == 'expiry' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>Expiry Alerts
        </a>
        <a href="?page=renewal_tracking&tab=tickets"
            class="<?php echo $tab == 'tickets' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            <i class="fas fa-plane mr-2"></i>Annual Tickets
        </a>
    </nav>
</div>

<?php if ($tab == 'expiry'): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-gray-50 border-b">
            <h3 class="font-bold text-gray-700">Upcoming Expiries (Visa, Passport, QID)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-sm uppercase">
                        <th class="p-4 border-b">Employee</th>
                        <th class="p-4 border-b">Document</th>
                        <th class="p-4 border-b">Expiry Date</th>
                        <th class="p-4 border-b">Status</th>
                        <th class="p-4 border-b">Payment Renewal ID</th>
                        <th class="p-4 border-b">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php
                    $alerts_found = false;
                    
                    // Pre-fetch renewal payments to minimize queries
                    // Fetch latest payment for each employee AND document type
                    // Simplified: just fetch all and filter in PHP for now (optimization can follow if slow)
                    $payments = $pdo->query("SELECT * FROM renewal_payments ORDER BY created_at DESC")->fetchAll(PDO::FETCH_GROUP); 
                    // Need to group by employee_id -> then filter by doc type?
                    // Actually, let's just do a simple query inside loop for now for simplicity and correctness, 
                    // or fetch all into a structured array: $renewal_map[emp_id][doc_type] = rp_id
                    
                    $renewal_map = [];
                    $all_payments = $pdo->query("SELECT * FROM renewal_payments ORDER BY id DESC")->fetchAll();
                    foreach ($all_payments as $rp) {
                        if (!isset($renewal_map[$rp['employee_id']][$rp['document_type']])) {
                             $renewal_map[$rp['employee_id']][$rp['document_type']] = $rp;
                        }
                    }

                    foreach ($employees as $emp) {
                        $docs = [
                            'Qatar ID' => $emp['qatar_id_expiry'],
                            'Passport' => $emp['passport_expiry'],
                            'Visa' => $emp['visa_expiry']
                        ];

                        foreach ($docs as $doc_name => $expiry_date) {
                            if (!$expiry_date)
                                continue;
                            $days = getDaysRemaining($expiry_date);

                            // Only show if expiring within 90 days or already expired
                            if ($days > 90)
                                continue;
                            $alerts_found = true;
                            
                            $rp_data = $renewal_map[$emp['id']][$doc_name] ?? null;
                            $rp_display = $rp_data ? $rp_data['rp_id'] : 'Pending';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-medium text-gray-900"><?php echo htmlspecialchars($emp['name']); ?></td>
                                <td class="p-4 text-gray-600 flex items-center">
                                    <?php if ($doc_name == 'Passport')
                                        echo '<i class="fas fa-passport mr-2 text-blue-400"></i>'; ?>
                                    <?php if ($doc_name == 'Visa')
                                        echo '<i class="fas fa-stamp mr-2 text-purple-400"></i>'; ?>
                                    <?php if ($doc_name == 'Qatar ID')
                                        echo '<i class="fas fa-id-card mr-2 text-green-400"></i>'; ?>
                                    <?php echo $doc_name; ?>
                                </td>
                                <td class="p-4 font-mono <?php echo getStatusColor($days); ?>">
                                    <?php echo date('d M Y', strtotime($expiry_date)); ?>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo getBadgeColor($days); ?>">
                                        <?php
                                        if ($days < 0)
                                            echo abs($days) . " days ago";
                                        elseif ($days == 0)
                                            echo "Today";
                                        else
                                            echo "In $days days";
                                        ?>
                                    </span>
                                </td>
                                <td class="p-4 text-gray-700 font-medium">
                                    <?php echo $rp_display; ?>
                                </td>
                                <td class="p-4 flex items-center space-x-2">
                                    <?php 
                                    $is_pending = ($rp_display === 'Pending'); 
                                    $renew_class = $is_pending ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700';
                                    ?>
                                    <button
                                        onclick="openRenewModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name'] ?? '', ENT_QUOTES); ?>', '<?php echo $doc_name; ?>')"
                                        <?php echo $is_pending ? 'disabled title="Complete payment first"' : ''; ?>
                                        class="<?php echo $renew_class; ?> text-white px-3 py-1.5 rounded text-xs font-medium transition-colors">
                                        Renew
                                    </button>
                                    <button
                                        onclick="openPaymentModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name'] ?? '', ENT_QUOTES); ?>', '<?php echo $doc_name; ?>')"
                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors flex items-center">
                                        <i class="fas fa-lock mr-1"></i> Payment
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    if (!$alerts_found) {
                        echo '<tr><td colspan="6" class="p-8 text-center text-gray-500">No upcoming expiries found. Everything is up to date!</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab == 'tickets'): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-gray-50 border-b flex justify-between items-center">
            <h3 class="font-bold text-gray-700">Annual Ticket Eligibility</h3>
            <span class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded">Policy: Default 2 Years</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-sm uppercase">
                        <th class="p-4 border-b">Employee</th>
                        <th class="p-4 border-b">Hire Date</th>
                        <th class="p-4 border-b">Last Ticket</th>
                        <th class="p-4 border-b">Next Due</th>
                        <th class="p-4 border-b">Status</th>
                        <th class="p-4 border-b text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php foreach ($employees as $emp):
                        $hire_date = $emp['hire_date'];
                        if (!$hire_date)
                            continue;

                        $frequency = $emp['ticket_frequency_years'] ?: 2;
                        $last_ticket = $emp['last_ticket_date'];
                        $next_due = $emp['next_ticket_date'];

                        // Calculate Next Due automatically if not set
                        if (!$next_due) {
                            $base_date = $last_ticket ? $last_ticket : $hire_date;
                            $next_due = date('Y-m-d', strtotime("+$frequency years", strtotime($base_date)));
                        }

                        $days_remaining = getDaysRemaining($next_due);
                        $status_badge = '';
                        if ($days_remaining < 0) {
                            $status_badge = '<span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">Overdue</span>';
                        } elseif ($days_remaining <= 60) {
                            $status_badge = '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-bold">Upcoming</span>';
                        } else {
                            $status_badge = '<span class="bg-blue-50 text-blue-600 px-2 py-1 rounded-full text-xs">Eligible Later</span>';
                        }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-4 font-medium text-gray-900"><?php echo htmlspecialchars($emp['name']); ?></td>
                            <td class="p-4 text-gray-500"><?php echo date('d M Y', strtotime($hire_date)); ?></td>
                            <td class="p-4 text-gray-500">
                                <?php echo $last_ticket ? date('d M Y', strtotime($last_ticket)) : 'Never'; ?></td>
                            <td class="p-4 font-bold <?php echo $days_remaining < 0 ? 'text-red-600' : 'text-gray-700'; ?>">
                                <?php echo date('d M Y', strtotime($next_due)); ?>
                            </td>
                            <td class="p-4"><?php echo $status_badge; ?></td>
                            <td class="p-4 flex items-center justify-center space-x-2">
                                <button
                                    onclick="openTicketModal(<?php echo htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8'); ?>)"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors">
                                    Update
                                </button>
                                <button
                                    onclick="openPaymentModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name']); ?>', 'Annual Ticket')"
                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors flex items-center">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Payment
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>


<!-- Payment Renewal Modal -->
<div id="paymentModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closePaymentModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="paymentForm" method="post">
                <input type="hidden" name="employee_id" id="modal-employee-id">
                <input type="hidden" name="document_type" id="modal-document-type">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Record Renewal Payment</h3>
                        <button type="button" onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4 bg-blue-50 p-3 rounded text-sm text-blue-800">
                         Recording payment for: <strong id="modal-employee-name"></strong> - <strong id="modal-doc-name"></strong>
                    </div>

                    <div id="payment-error" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm"></div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (QAR) *</label>
                            <input type="number" name="amount" step="0.01" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select name="payment_method" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <?php foreach ($PAYMENT_METHODS as $code => $label): ?>
                                    <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-check mr-2"></i>Record Payment
                    </button>
                    <button type="button" onclick="closePaymentModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Renewal Modal -->
<div id="documentRenewModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeRenewModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="documentRenewForm" method="post">
                <input type="hidden" name="employee_id" id="renew-modal-employee-id">
                <input type="hidden" name="document_type" id="renew-modal-document-type">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Renew Document</h3>
                        <button type="button" onclick="closeRenewModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4 bg-blue-50 p-3 rounded text-sm text-blue-800">
                         Renewing <strong id="renew-modal-doc-name"></strong> for: <strong id="renew-modal-employee-name"></strong>
                    </div>

                    <div id="renew-error" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm"></div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Expiry Date *</label>
                            <input type="date" name="new_expiry_date" id="renew-new-date" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Update Expiry
                    </button>
                    <button type="button" onclick="closeRenewModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ticket Update Modal -->
<div id="ticketUpdateModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeTicketModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="ticketUpdateForm" method="post">
                <input type="hidden" name="employee_id" id="ticket-modal-employee-id">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Update Ticket Eligibility</h3>
                        <button type="button" onclick="closeTicketModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4 bg-yellow-50 p-3 rounded text-sm text-yellow-800">
                         Updating ticket info for: <strong id="ticket-modal-employee-name"></strong>
                    </div>

                    <div id="ticket-error" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm"></div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Ticket Date</label>
                            <input type="date" name="last_ticket_date" id="ticket-last-date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Leave empty if never issued</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Next Due Date</label>
                            <input type="date" name="next_ticket_date" id="ticket-next-date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                             <p class="text-xs text-gray-500 mt-1">Calculated automatically if left empty</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Frequency (Years)</label>
                            <input type="number" name="ticket_frequency_years" id="ticket-frequency" min="1" value="2" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                    <button type="button" onclick="closeTicketModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Renewal Modal -->
<div id="documentRenewModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeRenewModal()"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="documentRenewForm" method="post">
                <input type="hidden" name="employee_id" id="renew-modal-employee-id">
                <input type="hidden" name="document_type" id="renew-modal-document-type">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Renew Document</h3>
                        <button type="button" onclick="closeRenewModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4 bg-blue-50 p-3 rounded text-sm text-blue-800">
                         Renewing <strong id="renew-modal-doc-name"></strong> for: <strong id="renew-modal-employee-name"></strong>
                    </div>

                    <div id="renew-error" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm"></div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Expiry Date *</label>
                            <input type="date" name="new_expiry_date" id="renew-new-date" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>Update Expiry
                    </button>
                    <button type="button" onclick="closeRenewModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editEmployeeFromTracking(employee) {
        window.location.href = '?page=payroll&edit_employee_id=' + employee.id;
    }

    function openRenewModal(empId, empName, docType) {
        document.getElementById('documentRenewModal').classList.remove('hidden');
        document.getElementById('renew-modal-employee-id').value = empId;
        document.getElementById('renew-modal-document-type').value = docType;
        document.getElementById('renew-modal-employee-name').textContent = empName;
        document.getElementById('renew-modal-doc-name').textContent = docType;
        document.getElementById('renew-error').classList.add('hidden');
        document.getElementById('renew-new-date').value = '';
    }

    function closeRenewModal() {
        document.getElementById('documentRenewModal').classList.add('hidden');
    }

    document.getElementById('documentRenewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
        submitBtn.disabled = true;

        fetch('ajax/update_document_expiry.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                window.location.reload();
            } else {
                const errorDiv = document.getElementById('renew-error');
                errorDiv.textContent = result.message || 'Error occurred';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('renew-error');
            errorDiv.textContent = 'Network or server error occurred';
            errorDiv.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    function openTicketModal(employee) {
        document.getElementById('ticketUpdateModal').classList.remove('hidden');
        document.getElementById('ticket-modal-employee-id').value = employee.id;
        document.getElementById('ticket-modal-employee-name').textContent = employee.name;
        document.getElementById('ticket-last-date').value = employee.last_ticket_date || '';
        document.getElementById('ticket-next-date').value = employee.next_ticket_date || '';
        document.getElementById('ticket-frequency').value = employee.ticket_frequency_years || 2;
        document.getElementById('ticket-error').classList.add('hidden');
    }

    function closeTicketModal() {
        document.getElementById('ticketUpdateModal').classList.add('hidden');
    }

    document.getElementById('ticketUpdateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        submitBtn.disabled = true;

        fetch('ajax/update_ticket_info.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                window.location.reload();
            } else {
                const errorDiv = document.getElementById('ticket-error');
                errorDiv.textContent = result.message || 'Error occurred';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('ticket-error');
            errorDiv.textContent = 'Network or server error occurred';
            errorDiv.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    function openRenewModal(empId, empName, docType) {
        document.getElementById('documentRenewModal').classList.remove('hidden');
        document.getElementById('renew-modal-employee-id').value = empId;
        document.getElementById('renew-modal-document-type').value = docType;
        document.getElementById('renew-modal-employee-name').textContent = empName;
        document.getElementById('renew-modal-doc-name').textContent = docType;
        document.getElementById('renew-error').classList.add('hidden');
        document.getElementById('renew-new-date').value = '';
    }

    function closeRenewModal() {
        document.getElementById('documentRenewModal').classList.add('hidden');
    }

    document.getElementById('documentRenewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
        submitBtn.disabled = true;

        fetch('ajax/update_document_expiry.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                window.location.reload();
            } else {
                const errorDiv = document.getElementById('renew-error');
                errorDiv.textContent = result.message || 'Error occurred';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('renew-error');
            errorDiv.textContent = 'Network or server error occurred';
            errorDiv.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });

    function openPaymentModal(empId, empName, docType) {
        document.getElementById('paymentModal').classList.remove('hidden');
        document.getElementById('modal-employee-id').value = empId;
        document.getElementById('modal-document-type').value = docType;
        document.getElementById('modal-employee-name').textContent = empName;
        document.getElementById('modal-doc-name').textContent = docType;
        document.getElementById('payment-error').classList.add('hidden');
        document.getElementById('paymentForm').reset();
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        submitBtn.disabled = true;

        fetch('ajax/add_renewal_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Determine the row or refresh?
                // Simple refresh for now to show the new ID
                window.location.reload();
            } else {
                const errorDiv = document.getElementById('payment-error');
                errorDiv.textContent = result.message || 'Error occurred';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('payment-error');
            errorDiv.textContent = 'Network or server error occurred';
            errorDiv.classList.remove('hidden');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
</script>