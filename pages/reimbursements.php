<?php

include_once 'includes/db.php';
include_once 'includes/payment_methods.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['approve_reimbursement'])) {
        $reimb_id = intval($_POST['reimbursement_id']);

        $pdo->beginTransaction();
        try {
            // Update reimbursement
            $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'approved', approved_by = ?, approval_date = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute(['Admin', $reimb_id]);

            // Update purchase payment status
            $updatePayment = $pdo->prepare("UPDATE purchase_payments SET reimbursement_status = 'approved' WHERE id = (SELECT purchase_payment_id FROM reimbursements WHERE id = ?)");
            $updatePayment->execute([$reimb_id]);

            $pdo->commit();
            header('Location: index.php?page=reimbursements&success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error approving reimbursement: " . $e->getMessage();
        }
    }

    if (isset($_POST['reject_reimbursement'])) {
        $reimb_id = intval($_POST['reimbursement_id']);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'rejected', rejection_reason = ? WHERE id = ?");
            $stmt->execute([$_POST['rejection_reason'], $reimb_id]);

            $updatePayment = $pdo->prepare("UPDATE purchase_payments SET reimbursement_status = 'rejected' WHERE id = (SELECT purchase_payment_id FROM reimbursements WHERE id = ?)");
            $updatePayment->execute([$reimb_id]);

            $pdo->commit();
            header('Location: index.php?page=reimbursements&success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error rejecting reimbursement: " . $e->getMessage();
        }
    }

    if (isset($_POST['pay_reimbursement'])) {
        $reimb_id = intval($_POST['reimbursement_id']);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE reimbursements SET status = 'paid', payment_date = ?, payment_method = ? WHERE id = ?");
            $stmt->execute([$_POST['payment_date'], $_POST['payment_method'], $reimb_id]);

            $updatePayment = $pdo->prepare("UPDATE purchase_payments SET reimbursement_status = 'paid' WHERE id = (SELECT purchase_payment_id FROM reimbursements WHERE id = ?)");
            $updatePayment->execute([$reimb_id]);

            $pdo->commit();
            header('Location: index.php?page=reimbursements&success=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error processing payment: " . $e->getMessage();
        }
    }
}

// Fetch reimbursements
$reimbursements = $pdo->query("
    SELECT r.*, 
           e.name as employee_name,
           pp.payment_date as payment_made_date,
           pp.amount as payment_amount,
           pp.notes as payment_notes,
           p.id as purchase_id,
           p.invoice_number,
           pr.name as project_name
    FROM reimbursements r
    LEFT JOIN employees e ON r.employee_id = e.id
    LEFT JOIN purchase_payments pp ON r.purchase_payment_id = pp.id
    LEFT JOIN purchases p ON pp.purchase_id = p.id
    LEFT JOIN projects pr ON p.project_id = pr.id
    ORDER BY r.request_date DESC
")->fetchAll();

// Summary statistics
$pending_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM reimbursements WHERE status = 'pending'")->fetchColumn();
$approved_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM reimbursements WHERE status = 'approved'")->fetchColumn();
$paid_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM reimbursements WHERE status = 'paid'")->fetchColumn();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Reimbursements</h1>
    <p class="text-gray-600 mt-2">Manage employee reimbursement requests and payments</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">Operation completed successfully!</span>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-yellow-50 rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-yellow-600 font-medium">Pending Requests</p>
                <p class="text-2xl font-bold text-yellow-900 mt-1"><?php echo money($pending_total); ?></p>
            </div>
            <div class="bg-yellow-500 rounded-full p-3">
                <i class="fas fa-clock text-white text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-blue-600 font-medium">Approved (To Pay)</p>
                <p class="text-2xl font-bold text-blue-900 mt-1"><?php echo money($approved_total); ?></p>
            </div>
            <div class="bg-blue-500 rounded-full p-3">
                <i class="fas fa-check text-white text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-green-50 rounded-lg shadow-md p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-green-600 font-medium">Total Paid</p>
                <p class="text-2xl font-bold text-green-900 mt-1"><?php echo money($paid_total); ?></p>
            </div>
            <div class="bg-green-500 rounded-full p-3">
                <i class="fas fa-money-bill-wave text-white text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Reimbursement Requests</h2>
                <a href="index.php?page=purchase_payments"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-1 md:mr-2"></i>Back to Payments
                </a>
            </div>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Request Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Purchase/Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reimbursements as $reimb): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">R#<?php echo $reimb['id']; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($reimb['request_date'])); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($reimb['employee_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <a href="index.php?page=purchases" class="text-primary hover:underline">
                                        P#<?php echo $reimb['purchase_id']; ?>
                                    </a>
                                    <?php if ($reimb['invoice_number']): ?>
                                        <span
                                            class="text-xs text-gray-500">(<?php echo htmlspecialchars($reimb['invoice_number']); ?>)</span>
                                    <?php endif; ?>
                                    <br><span
                                        class="text-xs text-gray-500"><?php echo htmlspecialchars($reimb['project_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo money($reimb['amount']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch ($reimb['status']) {
                                        case 'paid':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'approved':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'rejected':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                    }
                                    ?>">
                                        <?php echo ucfirst($reimb['status']); ?>
                                    </span>
                                    <?php if ($reimb['status'] == 'approved' || $reimb['status'] == 'paid'): ?>
                                        <br><span class="text-xs text-gray-500">
                                            by <?php echo htmlspecialchars($reimb['approved_by'] ?? 'N/A'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($reimb['status'] == 'paid' && $reimb['payment_date']): ?>
                                        <br><span class="text-xs text-green-600">
                                            Paid: <?php echo date('M d, Y', strtotime($reimb['payment_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <?php if ($reimb['status'] == 'pending'): ?>
                                        <button onclick="approveReimbursement(<?php echo $reimb['id']; ?>)"
                                            class="text-green-600 hover:text-green-900 mr-2">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button onclick="rejectReimbursement(<?php echo $reimb['id']; ?>)"
                                            class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($reimb['status'] == 'approved'): ?>
                                        <button
                                            onclick="payReimbursement(<?php echo htmlspecialchars(json_encode($reimb), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-dollar-sign"></i> Pay Now
                                        </button>
                                    <?php elseif ($reimb['status'] == 'rejected'): ?>
                                        <span class="text-xs text-red-600">
                                            <?php echo htmlspecialchars($reimb['rejection_reason'] ?? 'Rejected'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" id="approve-id" name="reimbursement_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Approve Reimbursement</h3>
                    <p>Are you sure you want to approve this reimbursement request?</p>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="approve_reimbursement"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">Approve</button>
                    <button type="button" onclick="closeApproveModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" id="reject-id" name="reimbursement_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Reject Reimbursement</h3>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason *</label>
                    <textarea name="rejection_reason" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md" required></textarea>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="reject_reimbursement"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Reject</button>
                    <button type="button" onclick="closeRejectModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pay Modal -->
<div id="payModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50"></div>
        </div>
        <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <input type="hidden" id="pay-id" name="reimbursement_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Process Reimbursement Payment</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                        <input type="text" id="pay-employee"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input type="text" id="pay-amount"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                        <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            required>
                            <?php echo payment_method_options(); ?>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="pay_reimbursement"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Process
                        Payment</button>
                    <button type="button" onclick="closePayModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function approveReimbursement(id) {
        document.getElementById('approve-id').value = id;
        document.getElementById('approveModal').classList.remove('hidden');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }

    function rejectReimbursement(id) {
        document.getElementById('reject-id').value = id;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function payReimbursement(reimb) {
        document.getElementById('pay-id').value = reimb.id;
        document.getElementById('pay-employee').value = reimb.employee_name || 'N/A';
        document.getElementById('pay-amount').value = '<?php echo currency_symbol(); ?>' + parseFloat(reimb.amount).toFixed(2);
        document.getElementById('payModal').classList.remove('hidden');
    }

    function closePayModal() {
        document.getElementById('payModal').classList.add('hidden');
    }
</script>