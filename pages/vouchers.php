<?php
include_once 'includes/db.php';

// Function to convert number to words
function numberToWords($number) {
    $ones = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    );
    
    $tens = array(
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    
    $hundreds = array(100 => 'Hundred', 1000 => 'Thousand', 1000000 => 'Million');
    
    if ($number < 20) {
        return $ones[$number];
    } elseif ($number < 100) {
        $tens_digit = intval($number / 10) * 10;
        $ones_digit = $number % 10;
        return $tens[$tens_digit] . ($ones_digit > 0 ? ' ' . $ones[$ones_digit] : '');
    } elseif ($number < 1000) {
        $hundreds_digit = intval($number / 100);
        $remainder = $number % 100;
        $result = $ones[$hundreds_digit] . ' ' . $hundreds[100];
        if ($remainder > 0) {
            $result .= ' ' . numberToWords($remainder);
        }
        return $result;
    } elseif ($number < 1000000) {
        $thousands = intval($number / 1000);
        $remainder = $number % 1000;
        $result = numberToWords($thousands) . ' ' . $hundreds[1000];
        if ($remainder > 0) {
            $result .= ' ' . numberToWords($remainder);
        }
        return $result;
    }
    
    return 'Number too large';
}

// Function to generate next voucher number
function generateVoucherNumber($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTR(voucher_no, 2) AS UNSIGNED)) as max_num FROM vouchers WHERE voucher_no LIKE 'V%'");
    $result = $stmt->fetch();
    $next_num = ($result['max_num'] ?? 0) + 1;
    return 'V' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

function extractRenewalRpId($voucher) {
    $reference = trim((string)($voucher['reference'] ?? ''));
    if ($reference !== '' && preg_match('/^RP-\d{4}-\d{4}$/', $reference)) {
        return $reference;
    }

    $description = (string)($voucher['description'] ?? '');
    if (preg_match('/\((RP-\d{4}-\d{4})\)/', $description, $matches)) {
        return $matches[1];
    }

    return null;
}

function reverseCardTransactionsByReference($pdo, $reference) {
    if (empty($reference)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT card_id FROM credit_card_transactions WHERE reference = ? AND card_id IS NOT NULL");
        $stmt->execute([$reference]);
        $card_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE reference = ?");
        $stmt->execute([$reference]);

        foreach (array_values(array_unique(array_filter(array_map('intval', $card_ids)))) as $card_id) {
            updateCardBalance($pdo, $card_id);
        }
    } catch (Exception $e) {
        // Keep voucher deletion working even if credit card tables are unavailable.
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_voucher'])) {
        $voucher_no = generateVoucherNumber($pdo);
        $amount = floatval($_POST['amount']);
        $amount_in_words = numberToWords($amount) . ' Riyals Only';
        
        try {
            $pdo->beginTransaction();
            
            // Insert voucher
            $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, amount_in_words, description, prepared_by, checked_by, approved_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $voucher_no,
                $_POST['voucher_date'],
                $_POST['paid_to_received_from'],
                $amount,
                $amount_in_words,
                $_POST['description'],
                $_POST['prepared_by'],
                $_POST['checked_by'],
                $_POST['approved_by'],
                'draft'
            ]);
            
            $voucher_id = $pdo->lastInsertId();
            
            // Insert voucher entries for double-entry bookkeeping
            if (!empty($_POST['debit_accounts'])) {
                foreach ($_POST['debit_accounts'] as $index => $account) {
                    if (!empty($account) && !empty($_POST['debit_amounts'][$index])) {
                        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, 0, ?)");
                        $stmt->execute([
                            $voucher_id,
                            $account,
                            floatval($_POST['debit_amounts'][$index]),
                            $_POST['debit_narrations'][$index] ?? ''
                        ]);
                    }
                }
            }
            
            if (!empty($_POST['credit_accounts'])) {
                foreach ($_POST['credit_accounts'] as $index => $account) {
                    if (!empty($account) && !empty($_POST['credit_amounts'][$index])) {
                        $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, 0, ?, ?)");
                        $stmt->execute([
                            $voucher_id,
                            $account,
                            floatval($_POST['credit_amounts'][$index]),
                            $_POST['credit_narrations'][$index] ?? ''
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            $success = "Voucher created successfully! Voucher No: " . $voucher_no;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating voucher: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_status'])) {
        $stmt = $pdo->prepare("UPDATE vouchers SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['voucher_id']]);
        $success = "Voucher status updated successfully!";
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        $voucher_id = $_GET['delete'];
        
        // Get voucher details
        $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
        $stmt->execute([$voucher_id]);
        $voucher = $stmt->fetch();
        
        if ($voucher) {
            // Check if this is a renewal payment voucher
            $rp_id = extractRenewalRpId($voucher);
            if ($rp_id) {
                // Reverse only the selected card/source transactions linked to this renewal payment.
                reverseCardTransactionsByReference($pdo, $rp_id);

                // Delete renewal payment record
                $stmt = $pdo->prepare("DELETE FROM renewal_payments WHERE rp_id = ?");
                $stmt->execute([$rp_id]);
            }
            
            // Delete voucher entries first (foreign key constraint)
            $stmt = $pdo->prepare("DELETE FROM voucher_entries WHERE voucher_id = ?");
            $stmt->execute([$voucher_id]);
            
            // Delete the voucher
            $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
            $stmt->execute([$voucher_id]);
        }
        
        $pdo->commit();
        $success = "Voucher deleted successfully and transactions reversed!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting voucher: " . $e->getMessage();
    }
}

if (isset($_POST['bulk_delete']) && !empty($_POST['selected_vouchers'])) {
    $selected_ids = $_POST['selected_vouchers'];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($selected_ids as $voucher_id) {
            // Get voucher details
            $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
            $stmt->execute([$voucher_id]);
            $voucher = $stmt->fetch();
            
            if ($voucher) {
                $rp_id = extractRenewalRpId($voucher);
                if ($rp_id) {
                    reverseCardTransactionsByReference($pdo, $rp_id);
                    $stmt = $pdo->prepare("DELETE FROM renewal_payments WHERE rp_id = ?");
                    $stmt->execute([$rp_id]);
                } else {
                    // Non-renewal fallback logic for older voucher types.
                    $stmt = $pdo->prepare("SELECT * FROM voucher_entries WHERE voucher_id = ?");
                    $stmt->execute([$voucher_id]);
                    $entries = $stmt->fetchAll();

                    $credit_card_entry = null;
                    foreach ($entries as $entry) {
                        if ($entry['account_head'] === 'Credit Card Payable' && $entry['credit_amount'] > 0) {
                            $credit_card_entry = $entry;
                            break;
                        }
                    }

                    if ($credit_card_entry) {
                        $amount = $credit_card_entry['credit_amount'];
                        $stmt = $pdo->prepare("SELECT * FROM credit_card_transactions WHERE reference LIKE ? OR description LIKE ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute(['%' . $voucher['voucher_no'] . '%', '%' . $voucher['voucher_no'] . '%']);
                        $cc_transaction = $stmt->fetch();

                        if ($cc_transaction) {
                            $stmt = $pdo->prepare("UPDATE credit_cards SET current_balance = current_balance - ? WHERE id = ?");
                            $stmt->execute([$amount, $cc_transaction['card_id']]);
                            $stmt = $pdo->prepare("DELETE FROM credit_card_transactions WHERE id = ?");
                            $stmt->execute([$cc_transaction['id']]);
                        }
                    }
                }
                
                // Delete voucher entries
                $stmt = $pdo->prepare("DELETE FROM voucher_entries WHERE voucher_id = ?");
                $stmt->execute([$voucher_id]);
                
                // Delete voucher
                $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
                $stmt->execute([$voucher_id]);
            }
        }
        
        $pdo->commit();
        $success = count($selected_ids) . " voucher(s) deleted successfully and transactions reversed!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting vouchers: " . $e->getMessage();
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$query = "SELECT v.*, 
    (SELECT COUNT(*) FROM voucher_entries WHERE voucher_id = v.id) as entry_count
    FROM vouchers v WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (v.voucher_no LIKE ? OR v.paid_to_received_from LIKE ? OR v.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($date_from) {
    $query .= " AND v.voucher_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND v.voucher_date <= ?";
    $params[] = $date_to;
}

if ($status_filter) {
    $query .= " AND v.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY v.voucher_date DESC, v.id DESC";

$vouchers = $pdo->prepare($query);
$vouchers->execute($params);
$vouchers = $vouchers->fetchAll();

// Get accounts for dropdown
$accounts = $pdo->query("SELECT account_code, account_name FROM accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Journal Entries</h1>
    <p class="text-gray-600 mt-2">Manage Journal Entries and General Ledger Vouchers</p>
</div>

<?php if (isset($success)): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
    <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Search & Filter</h3>
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="page" value="vouchers">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Voucher No, Reference, Description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="posted" <?php echo $status_filter === 'posted' ? 'selected' : ''; ?>>Posted</option>
                </select>
            </div>
            <div class="md:col-span-4 flex space-x-2">
                <button type="submit" class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <a href="index.php?page=vouchers" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md font-medium transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Vouchers List -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Journal Entries</h2>
                <div class="flex flex-wrap gap-2">
                    <button id="bulkDeleteBtn" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors hidden" onclick="confirmBulkDelete()">
                        <i class="fas fa-trash mr-1 md:mr-2"></i>Delete Selected
                    </button>
                    <button class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors" onclick="document.getElementById('addForm').classList.toggle('hidden')">
                        <i class="fas fa-plus mr-1 md:mr-2"></i>New Entry
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form id="bulkDeleteForm" method="post" style="display: none;">
                <input type="hidden" name="bulk_delete" value="1">
            </form>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-primary focus:ring-primary" onchange="toggleAllCheckboxes()">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voucher No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference / Payee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($vouchers as $voucher): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <input type="checkbox" name="selected_vouchers[]" value="<?php echo $voucher['id']; ?>" class="voucher-checkbox rounded border-gray-300 text-primary focus:ring-primary" onchange="updateBulkDeleteButton()">
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($voucher['voucher_no']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($voucher['voucher_date'])); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php echo htmlspecialchars($voucher['paid_to_received_from']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo money($voucher['amount']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch($voucher['status']) {
                                        case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                        case 'approved': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'posted': echo 'bg-green-100 text-green-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($voucher['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="index.php?page=voucher_view&id=<?php echo $voucher['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="index.php?page=voucher_print&id=<?php echo $voucher['id']; ?>" target="_blank" class="text-green-600 hover:text-green-900" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="index.php?page=voucher_pdf&id=<?php echo $voucher['id']; ?>" class="text-red-600 hover:text-red-900" title="PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    <?php if ($voucher['status'] !== 'posted'): ?>
                                    <button onclick="openStatusModal(<?php echo $voucher['id']; ?>, '<?php echo $voucher['status']; ?>')" class="text-purple-600 hover:text-purple-900" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="?page=vouchers&delete=<?php echo $voucher['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this voucher? All transactions will be reversed.')" title="Delete">
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
    </div>
</div>

<!-- Add Voucher Form -->
<div id="addForm" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <form method="post" class="max-h-screen overflow-y-auto">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">New Journal Entry</h3>
                    
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Entry Date *</label>
                            <input type="date" name="voucher_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference / Payee *</label>
                            <input type="text" name="paid_to_received_from" placeholder="Enter reference" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount *</label>
                            <input type="number" step="0.01" name="amount" id="amount" placeholder="0.00" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required onchange="updateAmountInWords()">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" placeholder="Enter description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>

                    <!-- Double Entry Bookkeeping -->
                    <div class="mb-6">
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Double Entry Bookkeeping</h4>
                        
                        <!-- Debit Entries -->
                        <div class="mb-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Debit Entries</h5>
                            <div id="debit_entries">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-2">
                                    <select name="debit_accounts[]" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select Account</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo htmlspecialchars($account['account_name']); ?>"><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" step="0.01" name="debit_amounts[]" placeholder="Amount" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <input type="text" name="debit_narrations[]" placeholder="Narration" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" onclick="removeDebitEntry(this)" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" onclick="addDebitEntry()" class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-sm">
                                <i class="fas fa-plus mr-1"></i>Add Debit Entry
                            </button>
                        </div>

                        <!-- Credit Entries -->
                        <div class="mb-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Credit Entries</h5>
                            <div id="credit_entries">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-2">
                                    <select name="credit_accounts[]" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select Account</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo htmlspecialchars($account['account_name']); ?>"><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" step="0.01" name="credit_amounts[]" placeholder="Amount" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <input type="text" name="credit_narrations[]" placeholder="Narration" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" onclick="removeCreditEntry(this)" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" onclick="addCreditEntry()" class="px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm">
                                <i class="fas fa-plus mr-1"></i>Add Credit Entry
                            </button>
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prepared By</label>
                            <input type="text" name="prepared_by" placeholder="Enter name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Checked By</label>
                            <input type="text" name="checked_by" placeholder="Enter name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Approved By</label>
                            <input type="text" name="approved_by" placeholder="Enter name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Amount in Words:</strong> <span id="amount_in_words_display">Zero Riyals Only</span>
                        </p>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="add_voucher" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                        Create Voucher
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeAddForm()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Update Voucher Status</h3>
                    <input type="hidden" id="status_voucher_id" name="voucher_id">
                    <div class="mb-4">
                        <label for="status_select" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status_select" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_status" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm">
                        Update Status
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeStatusModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateAmountInWords() {
    const amount = document.getElementById('amount').value;
    if (amount) {
        // This would need a proper number-to-words conversion
        document.getElementById('amount_in_words_display').textContent = amount + ' Riyals Only';
    } else {
        document.getElementById('amount_in_words_display').textContent = 'Zero Riyals Only';
    }
}

function addDebitEntry() {
    const container = document.getElementById('debit_entries');
    const newEntry = document.createElement('div');
    newEntry.className = 'grid grid-cols-1 md:grid-cols-4 gap-2 mb-2';
    newEntry.innerHTML = `
        <select name="debit_accounts[]" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            <option value="">Select Account</option>
            <?php foreach ($accounts as $account): ?>
            <option value="<?php echo htmlspecialchars($account['account_name']); ?>"><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" step="0.01" name="debit_amounts[]" placeholder="Amount" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
        <input type="text" name="debit_narrations[]" placeholder="Narration" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
        <button type="button" onclick="removeDebitEntry(this)" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(newEntry);
}

function addCreditEntry() {
    const container = document.getElementById('credit_entries');
    const newEntry = document.createElement('div');
    newEntry.className = 'grid grid-cols-1 md:grid-cols-4 gap-2 mb-2';
    newEntry.innerHTML = `
        <select name="credit_accounts[]" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            <option value="">Select Account</option>
            <?php foreach ($accounts as $account): ?>
            <option value="<?php echo htmlspecialchars($account['account_name']); ?>"><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" step="0.01" name="credit_amounts[]" placeholder="Amount" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
        <input type="text" name="credit_narrations[]" placeholder="Narration" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
        <button type="button" onclick="removeCreditEntry(this)" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(newEntry);
}

function removeDebitEntry(button) {
    button.parentElement.remove();
}

function removeCreditEntry(button) {
    button.parentElement.remove();
}

function openStatusModal(voucherId, currentStatus) {
    document.getElementById('statusModal').classList.remove('hidden');
    document.getElementById('status_voucher_id').value = voucherId;
    document.getElementById('status_select').value = currentStatus;
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}

function closeAddForm() {
    document.getElementById('addForm').classList.add('hidden');
}

function toggleAllCheckboxes() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.voucher-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.voucher-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    if (checkboxes.length > 0) {
        bulkDeleteBtn.classList.remove('hidden');
        bulkDeleteBtn.innerHTML = `<i class="fas fa-trash mr-2"></i>Delete Selected (${checkboxes.length})`;
    } else {
        bulkDeleteBtn.classList.add('hidden');
    }
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.voucher-checkbox');
    const selectAll = document.getElementById('selectAll');
    
    if (checkboxes.length === allCheckboxes.length && allCheckboxes.length > 0) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
    } else if (checkboxes.length > 0) {
        selectAll.checked = false;
        selectAll.indeterminate = true;
    } else {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }
}

function confirmBulkDelete() {
    const checkboxes = document.querySelectorAll('.voucher-checkbox:checked');
    const count = checkboxes.length;
    
    if (count === 0) {
        alert('Please select at least one voucher to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${count} voucher(s)? This action cannot be undone.`)) {
        // Collect selected voucher IDs
        const selectedIds = Array.from(checkboxes).map(checkbox => checkbox.value);
        
        // Add hidden inputs to the form
        const form = document.getElementById('bulkDeleteForm');
        form.innerHTML = '<input type="hidden" name="bulk_delete" value="1">';
        
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_vouchers[]';
            input.value = id;
            form.appendChild(input);
        });
        
        // Submit the form
        form.submit();
    }
}
</script>
