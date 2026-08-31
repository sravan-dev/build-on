<?php


include_once 'includes/db.php';
include_once 'includes/payment_methods.php';

$error_message = null;
$success_message = null;

function paymentMethodUsesCard($payment_method)
{
    return in_array($payment_method, ['credit_card', 'company_card'], true);
}

function clearInvoicePaymentSideEffects($pdo, $payment_id, $fallback_card_id = null)
{
    $reference = "PAY-{$payment_id}";

    // Remove GL voucher linked to this payment.
    deleteGlVoucher($pdo, $reference);

    $card_ids = [];

    // Remove linked card transactions, then refresh the touched card balances.
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);
    } catch (Exception $e) {
        // Keep payment flow working even if card transaction table is unavailable.
    }

    if (!empty($fallback_card_id)) {
        $card_ids[] = $fallback_card_id;
    }

    $card_ids = array_values(array_unique(array_filter(array_map('intval', $card_ids))));
    foreach ($card_ids as $card_id) {
        updateCardBalance($pdo, $card_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['add'])) {
            $payment_method = trim((string) ($_POST['payment_method'] ?? ''));
            $card_id = paymentMethodUsesCard($payment_method) ? (int) ($_POST['card_id'] ?? 0) : 0;
            $card_id = $card_id > 0 ? $card_id : null;
            $is_cheque = in_array($payment_method, ['cheque', 'company_cheque'], true);

            if (paymentMethodUsesCard($payment_method) && $card_id === null) {
                throw new Exception('Please select a card for card payment methods.');
            }

            $stmt = $pdo->prepare("INSERT INTO payments (invoice_id, amount, date, payment_method, card_id, cheque_number, bank_name, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['invoice_id'],
                $_POST['amount'],
                $_POST['date'],
                $payment_method,
                $card_id,
                $is_cheque ? ($_POST['cheque_number'] ?? null) : null,
                $is_cheque ? ($_POST['bank_name'] ?? null) : null,
                $_POST['notes'] ?? null
            ]);

            $payment_id = $pdo->lastInsertId();

            // Update invoice paid_amount, balance, and status
            updateInvoicePaymentStatus($pdo, $_POST['invoice_id']);

            // Create GL Voucher + method-specific side effects
            addInvoicePaymentVoucher($pdo, $payment_id);
            $success_message = 'Payment added successfully!';
        } elseif (isset($_POST['update'])) {
            $payment_id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT invoice_id, card_id FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $old_payment = $stmt->fetch();

            if (!$old_payment) {
                throw new Exception('Payment record not found.');
            }

            $payment_method = trim((string) ($_POST['payment_method'] ?? ''));
            $card_id = paymentMethodUsesCard($payment_method) ? (int) ($_POST['card_id'] ?? 0) : 0;
            $card_id = $card_id > 0 ? $card_id : null;
            $is_cheque = in_array($payment_method, ['cheque', 'company_cheque'], true);

            if (paymentMethodUsesCard($payment_method) && $card_id === null) {
                throw new Exception('Please select a card for card payment methods.');
            }

            // Reverse old side effects first, then apply the updated ones.
            clearInvoicePaymentSideEffects($pdo, $payment_id, $old_payment['card_id'] ?? null);

            $stmt = $pdo->prepare("UPDATE payments SET invoice_id=?, amount=?, date=?, payment_method=?, card_id=?, cheque_number=?, bank_name=?, notes=? WHERE id=?");
            $stmt->execute([
                $_POST['invoice_id'],
                $_POST['amount'],
                $_POST['date'],
                $payment_method,
                $card_id,
                $is_cheque ? ($_POST['cheque_number'] ?? null) : null,
                $is_cheque ? ($_POST['bank_name'] ?? null) : null,
                $_POST['notes'] ?? null,
                $payment_id
            ]);

            // Update invoice paid_amount, balance, and status
            if ((string) $old_payment['invoice_id'] !== (string) $_POST['invoice_id']) {
                updateInvoicePaymentStatus($pdo, $old_payment['invoice_id']);
            }
            updateInvoicePaymentStatus($pdo, $_POST['invoice_id']);

            // Recreate GL/card side effects based on updated payment method.
            addInvoicePaymentVoucher($pdo, $payment_id);
            $success_message = 'Payment updated successfully!';
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Database error: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    try {
        $payment_id = (int) $_GET['delete'];
        $pdo->beginTransaction();

        // Get payment context before deleting
        $stmt = $pdo->prepare("SELECT invoice_id, card_id FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if ($payment) {
            clearInvoicePaymentSideEffects($pdo, $payment_id, $payment['card_id'] ?? null);

            // Delete payment
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);

            // Update invoice paid_amount, balance, and status
            updateInvoicePaymentStatus($pdo, $payment['invoice_id']);
        }

        $pdo->commit();

        // Redirect back to payments page
        header('Location: ?page=payments&deleted=1');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Function to update invoice payment status
function updateInvoicePaymentStatus($pdo, $invoice_id)
{
    // Calculate total paid for this invoice
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $result = $stmt->fetch();
    $total_paid = $result['total_paid'];

    // Get invoice total amount
    $stmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    $total_amount = $invoice['total_amount'] ?? 0;

    // Calculate balance
    $balance = $total_amount - $total_paid;

    // Determine status
    if ($total_paid == 0) {
        $status = 'unpaid';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } else {
        $status = 'partially_paid';
    }

    // Update invoice
    $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
    $stmt->execute([$total_paid, $balance, $status, $invoice_id]);
}


// Functions moved to includes/functions.php to support backfill and reuse


$payments = $pdo->query("SELECT p.*, i.total_amount as invoice_amount FROM payments p LEFT JOIN invoices i ON p.invoice_id = i.id ORDER BY p.date DESC")->fetchAll();

$invoices = $pdo->query("SELECT id, total_amount FROM invoices")->fetchAll();

// Fetch credit cards for dropdown
$credit_cards = $pdo->query("SELECT id, card_name, bank_name, current_balance FROM credit_cards WHERE status = 'active' ORDER BY card_name")->fetchAll();

// Check for delete success message
$deleted_message = isset($_GET['deleted']) ? 'Payment deleted successfully!' : null;
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Payments</h1>
    <p class="text-gray-600 mt-2">Manage payment receipts and track payment methods</p>
</div>

<?php if ($deleted_message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($deleted_message); ?>
    </div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Payment Management</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                    onclick="document.getElementById('addForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-1 md:mr-2"></i>Add Payment
                </button>
            </div>
        </div>

        <div id="addForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice</label>
                        <select name="invoice_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                            <option value="">Select Invoice</option>
                            <?php foreach ($invoices as $invoice): ?>
                                <option value="<?php echo $invoice['id']; ?>">Invoice #<?php echo $invoice['id']; ?> -
                                    <?php echo money($invoice['total_amount']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input type="number" step="0.01" name="amount" placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" id="payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                            required onchange="togglePaymentFields()">
                            <?php echo payment_method_options(); ?>
                        </select>
                    </div>
                    <div id="card_field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Credit Card</label>
                        <select name="card_id" id="card_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Credit Card</option>
                            <?php foreach ($credit_cards as $card): ?>
                                <option value="<?php echo $card['id']; ?>">
                                    <?php echo htmlspecialchars($card['card_name']); ?>
                                    <?php if ($card['bank_name']): ?> - <?php echo htmlspecialchars($card['bank_name']); ?><?php endif; ?>
                                    (Balance: <?php echo money($card['current_balance']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="cheque_fields" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cheque Number</label>
                        <input type="text" name="cheque_number" placeholder="Enter cheque number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div id="bank_name_field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                        <input type="text" name="bank_name" placeholder="Enter bank name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                    <input type="text" name="paid_by" placeholder="Enter name of person who paid"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Add any additional notes about this payment..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Payment
                    </button>
                </div>
            </form>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Invoice Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payment Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Paid By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Notes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo money($payment['invoice_amount'] ?? 0); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-green-600"><?php echo money($payment['amount']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($payment['date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                            <?php
                            $label = get_payment_method_label($payment['payment_method']);

                            switch ($payment['payment_method']) {
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
                                case 'personal':
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                                            <?php echo $label; ?>
                                        </span>
                                        <?php if (($payment['payment_method'] === 'cheque' || $payment['payment_method'] === 'company_cheque') && ($payment['cheque_number'] || $payment['bank_name'])): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?php if ($payment['cheque_number']): ?>
                                                    <div>Cheque #: <?php echo htmlspecialchars($payment['cheque_number']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($payment['bank_name']): ?>
                                                    <div>Bank: <?php echo htmlspecialchars($payment['bank_name']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['paid_by'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($payment['notes'])): ?>
                                        <div class="text-sm text-gray-600 max-w-xs truncate"
                                            title="<?php echo htmlspecialchars($payment['notes']); ?>">
                                            <?php echo htmlspecialchars($payment['notes']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="payment_receipt.php?id=<?php echo $payment['id']; ?>" target="_blank" class="text-green-600 hover:text-green-900 mr-3">
                                        <i class="fas fa-receipt"></i> Receipt
                                    </a>
                                    <button class="text-primary hover:text-secondary mr-3"
                                        onclick="editPayment(<?php echo htmlspecialchars(json_encode($payment), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?page=payments&delete=<?php echo $payment['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this payment?')">
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
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Payment</h3>
                            <input type="hidden" id="edit-id" name="id">
                            <div class="mb-4">
                                <label for="edit-invoice_id"
                                    class="block text-sm font-medium text-gray-700">Invoice</label>
                                <select name="invoice_id" id="edit-invoice_id"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <?php foreach ($invoices as $invoice): ?>
                                        <option value="<?php echo $invoice['id']; ?>">Invoice #<?php echo $invoice['id']; ?>
                                            - <?php echo money($invoice['total_amount']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="edit-amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                <input type="number" step="0.01" name="amount" id="edit-amount"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-date" class="block text-sm font-medium text-gray-700">Date</label>
                                <input type="date" name="date" id="edit-date"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-payment_method" class="block text-sm font-medium text-gray-700">Payment
                                    Method</label>
                                <select name="payment_method" id="edit-payment_method"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                    onchange="toggleEditPaymentFields()">
                                    <?php echo payment_method_options(null, false); ?>
                                </select>
                            </div>
                            <div id="edit_card_field" class="mb-4 hidden">
                                <label for="edit-card_id" class="block text-sm font-medium text-gray-700">Credit Card</label>
                                <select name="card_id" id="edit-card_id"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    <option value="">Select Credit Card</option>
                                    <?php foreach ($credit_cards as $card): ?>
                                        <option value="<?php echo $card['id']; ?>">
                                            <?php echo htmlspecialchars($card['card_name']); ?>
                                            <?php if ($card['bank_name']): ?> - <?php echo htmlspecialchars($card['bank_name']); ?><?php endif; ?>
                                            (Balance: <?php echo money($card['current_balance']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="edit_cheque_fields" class="mb-4 hidden">
                                <label for="edit-cheque_number" class="block text-sm font-medium text-gray-700">Cheque
                                    Number</label>
                                <input type="text" name="cheque_number" id="edit-cheque_number"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div id="edit_bank_name_field" class="mb-4 hidden">
                                <label for="edit-bank_name" class="block text-sm font-medium text-gray-700">Bank
                                    Name</label>
                                <input type="text" name="bank_name" id="edit-bank_name"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-paid_by" class="block text-sm font-medium text-gray-700">Paid
                                    By</label>
                                <input type="text" name="paid_by" id="edit-paid_by"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                    placeholder="Enter name of person who paid">
                            </div>
                            <div class="mb-4">
                                <label for="edit-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea name="notes" id="edit-notes" rows="3"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                    placeholder="Add any additional notes..."></textarea>
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
            function togglePaymentFields() {
                const paymentMethod = document.getElementById('payment_method').value;
                const chequeFields = document.getElementById('cheque_fields');
                const bankNameField = document.getElementById('bank_name_field');
                const cardField = document.getElementById('card_field');
                const cardInput = document.getElementById('card_id');
                const isCardPayment = paymentMethod === 'credit_card' || paymentMethod === 'company_card';

                // Hide all conditional fields first
                chequeFields.classList.add('hidden');
                bankNameField.classList.add('hidden');
                cardField.classList.add('hidden');
                cardInput.required = false;

                // Show relevant fields based on payment method
                if (paymentMethod === 'cheque' || paymentMethod === 'company_cheque') {
                    chequeFields.classList.remove('hidden');
                    bankNameField.classList.remove('hidden');
                } else if (isCardPayment) {
                    cardField.classList.remove('hidden');
                    cardInput.required = true;
                } else {
                    cardInput.value = '';
                }
            }

            function toggleEditPaymentFields() {
                const paymentMethod = document.getElementById('edit-payment_method').value;
                const chequeFields = document.getElementById('edit_cheque_fields');
                const bankNameField = document.getElementById('edit_bank_name_field');
                const cardField = document.getElementById('edit_card_field');
                const cardInput = document.getElementById('edit-card_id');
                const isCardPayment = paymentMethod === 'credit_card' || paymentMethod === 'company_card';

                // Hide all conditional fields first
                chequeFields.classList.add('hidden');
                bankNameField.classList.add('hidden');
                cardField.classList.add('hidden');
                cardInput.required = false;

                // Show relevant fields based on payment method
                if (paymentMethod === 'cheque' || paymentMethod === 'company_cheque') {
                    chequeFields.classList.remove('hidden');
                    bankNameField.classList.remove('hidden');
                } else if (isCardPayment) {
                    cardField.classList.remove('hidden');
                    cardInput.required = true;
                } else {
                    cardInput.value = '';
                }
            }

            function editPayment(payment) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = payment.id;
                document.getElementById('edit-invoice_id').value = payment.invoice_id;
                document.getElementById('edit-amount').value = payment.amount;
                document.getElementById('edit-date').value = payment.date;
                document.getElementById('edit-payment_method').value = payment.payment_method;
                document.getElementById('edit-card_id').value = payment.card_id || '';
                document.getElementById('edit-cheque_number').value = payment.cheque_number || '';
                document.getElementById('edit-bank_name').value = payment.bank_name || '';
                document.getElementById('edit-paid_by').value = payment.paid_by || '';
                document.getElementById('edit-notes').value = payment.notes || '';

                // Toggle fields based on payment method
                toggleEditPaymentFields();
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            togglePaymentFields();
        </script>
