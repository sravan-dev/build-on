<?php
/**
 * Credit Cards Module
 * Track company credit cards with opening balance, credit limit, and balance
 */

include_once 'includes/db.php';
require_once 'includes/payment_methods.php';
require_once 'includes/functions.php';

// Auto-create table if it doesn't exist
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
try {
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS credit_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_name VARCHAR(100) NOT NULL,
            card_number VARCHAR(50),
            bank_name VARCHAR(100),
            credit_limit DECIMAL(12,2) DEFAULT 0,
            opening_balance DECIMAL(12,2) DEFAULT 0,
            current_balance DECIMAL(12,2) DEFAULT 0,
            billing_date INT DEFAULT 1,
            payment_due_date INT DEFAULT 15,
            status VARCHAR(20) DEFAULT 'active',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Transactions table
        $pdo->exec("CREATE TABLE IF NOT EXISTS credit_card_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id INT NOT NULL,
            transaction_date DATE NOT NULL,
            description TEXT,
            amount DECIMAL(12,2) NOT NULL,
            transaction_type VARCHAR(20) DEFAULT 'expense',
            reference VARCHAR(100),
            payment_method VARCHAR(50) DEFAULT 'company_cash',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(card_id) REFERENCES credit_cards(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS credit_cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            card_name TEXT NOT NULL,
            card_number TEXT,
            bank_name TEXT,
            credit_limit REAL DEFAULT 0,
            opening_balance REAL DEFAULT 0,
            current_balance REAL DEFAULT 0,
            billing_date INTEGER DEFAULT 1,
            payment_due_date INTEGER DEFAULT 15,
            status TEXT DEFAULT 'active',
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS credit_card_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id INTEGER NOT NULL,
            transaction_date TEXT NOT NULL,
            description TEXT,
            amount REAL NOT NULL,
            transaction_type TEXT DEFAULT 'expense',
            reference TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(card_id) REFERENCES credit_cards(id) ON DELETE CASCADE
        )");
    }
} catch (Exception $e) {
}

// updateCardBalance function is now in includes/functions.php

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_card'])) {
        $stmt = $pdo->prepare("INSERT INTO credit_cards (card_name, card_number, bank_name, credit_limit, opening_balance, current_balance, billing_date, payment_due_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $opening = floatval($_POST['opening_balance']);
        $stmt->execute([
            $_POST['card_name'],
            $_POST['card_number'],
            $_POST['bank_name'],
            $_POST['credit_limit'],
            $opening,
            $opening, // current_balance starts as opening_balance
            $_POST['billing_date'],
            $_POST['payment_due_date'],
            $_POST['notes']
        ]);
        $success = "Credit card added successfully!";
    } elseif (isset($_POST['update_card'])) {
        $stmt = $pdo->prepare("UPDATE credit_cards SET card_name=?, card_number=?, bank_name=?, credit_limit=?, billing_date=?, payment_due_date=?, notes=?, status=? WHERE id=?");
        $stmt->execute([
            $_POST['card_name'],
            $_POST['card_number'],
            $_POST['bank_name'],
            $_POST['credit_limit'],
            $_POST['billing_date'],
            $_POST['payment_due_date'],
            $_POST['notes'],
            $_POST['status'],
            $_POST['id']
        ]);
        $success = "Credit card updated successfully!";
    } elseif (isset($_POST['add_transaction'])) {
        $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['card_id'],
            $_POST['transaction_date'],
            $_POST['description'],
            $_POST['amount'],
            $_POST['transaction_type'],
            $_POST['reference']
        ]);
        updateCardBalance($pdo, $_POST['card_id']);
        $success = "Transaction recorded successfully!";
    }elseif (isset($_POST['topup_card'])) {
        // Topup adds a payment transaction to reduce the outstanding balance
        $stmt = $pdo->prepare("INSERT INTO credit_card_transactions (card_id, transaction_date, description, amount, transaction_type, reference, payment_method) VALUES (?, ?, ?, ?, 'payment', ?, ?)");
        $stmt->execute([
            $_POST['card_id'],
            $_POST['topup_date'],
            $_POST['topup_description'] ?: 'Card Topup',
            $_POST['topup_amount'],
            $_POST['topup_reference'],
            $_POST['topup_payment_method'] ?? 'company_cash'
        ]);
        updateCardBalance($pdo, $_POST['card_id']);
        
        // Add GL Voucher for Topup (Deduct from Company Balance)
        addCreditCardTopupVoucher($pdo, $_POST['card_id'], $_POST['topup_amount'], $_POST['topup_date'], $_POST['topup_reference'], $_POST['topup_payment_method'] ?? 'company_cash');

        $success = "Card topped up successfully! Balance reduced by " . currency_symbol() . number_format($_POST['topup_amount'], 2);
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM credit_cards WHERE id = ?")->execute([$_GET['delete']]);
    $success = "Credit card deleted successfully!";
}

if (isset($_GET['delete_transaction'])) {
    $stmt = $pdo->prepare("SELECT card_id FROM credit_card_transactions WHERE id = ?");
    $stmt->execute([$_GET['delete_transaction']]);
    $trans = $stmt->fetch();
    if ($trans) {
        $pdo->prepare("DELETE FROM credit_card_transactions WHERE id = ?")->execute([$_GET['delete_transaction']]);
        updateCardBalance($pdo, $trans['card_id']);
        $success = "Transaction deleted successfully!";
    }
}

// Fetch cards
$cards = $pdo->query("SELECT * FROM credit_cards ORDER BY card_name")->fetchAll();

// Calculate totals
$total_credit_limit = 0;
$total_balance = 0;
$total_available = 0;
foreach ($cards as $card) {
    $total_credit_limit += $card['credit_limit'];
    $total_balance += $card['current_balance'];
    $total_available += ($card['credit_limit'] - $card['current_balance']);
}

// Get selected card transactions
$selected_card = isset($_GET['card_id']) ? intval($_GET['card_id']) : null;
$transactions = [];
if ($selected_card) {
    $stmt = $pdo->prepare("SELECT * FROM credit_card_transactions WHERE card_id = ? ORDER BY transaction_date DESC, id DESC");
    $stmt->execute([$selected_card]);
    $transactions = $stmt->fetchAll();
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">💳 Credit Cards</h1>
    <p class="text-gray-600 mt-2">Manage company credit cards and transactions</p>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-credit-card text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Cards</p>
                <p class="text-2xl font-bold"><?php echo count($cards); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-wallet text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Credit Limit</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol() . number_format($total_credit_limit, 2); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-money-bill-wave text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Total Outstanding</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol() . number_format($total_balance, 2); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-chart-line text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-600">Available Credit</p>
                <p class="text-2xl font-bold"><?php echo currency_symbol() . number_format($total_available, 2); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="space-y-6">
    <!-- Cards List -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Credit Cards</h2>
                <button
                    class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    onclick="document.getElementById('addCardForm').classList.toggle('hidden')">
                    <i class="fas fa-plus mr-2"></i>Add Card
                </button>
            </div>
        </div>

        <!-- Add Card Form -->
        <div id="addCardForm" class="hidden p-6 border-b bg-gray-50">
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Card Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="card_name" required placeholder="e.g., Company Visa"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Card Number (Last 4)</label>
                        <input type="text" name="card_number" maxlength="4" placeholder="1234"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                        <input type="text" name="bank_name" placeholder="e.g., QNB"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit</label>
                        <input type="number" name="credit_limit" step="0.01" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
                        <input type="number" name="opening_balance" step="0.01" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Billing Date</label>
                        <input type="number" name="billing_date" min="1" max="31" value="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Due Date</label>
                        <input type="number" name="payment_due_date" min="1" max="31" value="15"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <input type="text" name="notes" placeholder="Optional notes"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                        onclick="document.getElementById('addCardForm').classList.add('hidden')">Cancel</button>
                    <button type="submit" name="add_card"
                        class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium">
                        <i class="fas fa-save mr-2"></i>Save Card
                    </button>
                </div>
            </form>
        </div>

        <!-- Cards Table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Card</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Credit Limit
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Balance
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($cards as $card):
                            $available = $card['credit_limit'] - $card['current_balance'];
                            $usage_percent = $card['credit_limit'] > 0 ? ($card['current_balance'] / $card['credit_limit']) * 100 : 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center">
                                        <div
                                            class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($card['card_name']); ?>
                                            </div>
                                            <?php if ($card['card_number']): ?>
                                                <div class="text-xs text-gray-500">
                                                    ****<?php echo htmlspecialchars($card['card_number']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($card['bank_name'] ?: '-'); ?>
                                </td>
                                <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                    <?php echo currency_symbol() . number_format($card['credit_limit'], 2); ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div
                                        class="text-sm font-bold <?php echo $card['current_balance'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo currency_symbol() . number_format($card['current_balance'], 2); ?>
                                    </div>
                                    <?php if ($card['credit_limit'] > 0): ?>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                            <div class="h-1.5 rounded-full <?php echo $usage_percent > 80 ? 'bg-red-500' : ($usage_percent > 50 ? 'bg-yellow-500' : 'bg-green-500'); ?>"
                                                style="width: <?php echo min($usage_percent, 100); ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-sm font-medium text-green-600">
                                    <?php echo currency_symbol() . number_format($available, 2); ?>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $card['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo ucfirst($card['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm">
                                    <a href="?page=credit_cards&card_id=<?php echo $card['id']; ?>"
                                        class="text-blue-600 hover:text-blue-900 mr-2" title="View Transactions">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button
                                        onclick='editCard(<?php echo json_encode($card, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'
                                        class="text-primary hover:text-secondary mr-2" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button
                                        onclick='openTopupModal(<?php echo $card["id"]; ?>, <?php echo json_encode($card["card_name"], JSON_HEX_QUOT | JSON_HEX_APOS); ?>, <?php echo $card["current_balance"]; ?>)'
                                        class="text-green-600 hover:text-green-800 mr-2" title="Topup">
                                        <i class="fas fa-plus-circle"></i>
                                    </button>
                                    <a href="?page=credit_cards&delete=<?php echo $card['id']; ?>"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Delete this card and all its transactions?')"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cards)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-credit-card text-4xl mb-2"></i>
                                    <p>No credit cards added yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Transactions Section (if card selected) -->
    <?php if ($selected_card):
        $selected_card_data = null;
        foreach ($cards as $c) {
            if ($c['id'] == $selected_card) {
                $selected_card_data = $c;
                break;
            }
        }
        ?>
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Transactions -
                            <?php echo htmlspecialchars($selected_card_data['card_name']); ?>
                        </h2>
                        <p class="text-sm text-gray-500">Current Balance: <span
                                class="font-bold"><?php echo currency_symbol() . number_format($selected_card_data['current_balance'], 2); ?></span>
                        </p>
                    </div>
                    <div class="space-x-2">
                        <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium"
                            onclick="openTransactionModal('expense')">
                            <i class="fas fa-minus-circle mr-2"></i>Add Expense
                        </button>
                        <button class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium"
                            onclick="openTransactionModal('payment')">
                            <i class="fas fa-plus-circle mr-2"></i>Add Payment
                        </button>
                        <a href="?page=credit_cards"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>Back
                        </a>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $trans): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <?php echo date('d M Y', strtotime($trans['transaction_date'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($trans['description'] ?: '-'); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $trans['transaction_type'] === 'expense' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo ucfirst($trans['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm font-bold <?php echo $trans['transaction_type'] === 'expense' ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo ($trans['transaction_type'] === 'expense' ? '+' : '-') . currency_symbol() . number_format($trans['amount'], 2); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($trans['reference'] ?: '-'); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="?page=credit_cards&card_id=<?php echo $selected_card; ?>&delete_transaction=<?php echo $trans['id']; ?>"
                                            class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Delete this transaction?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        No transactions recorded yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Card Modal -->
<div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="fixed inset-0 transition-opacity" onclick="closeEditModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-2xl sm:w-full"
            onclick="event.stopPropagation()">
            <form method="post">
                <input type="hidden" name="id" id="edit-id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Credit Card</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Card Name</label>
                            <input type="text" name="card_name" id="edit-card_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Card Number (Last 4)</label>
                            <input type="text" name="card_number" id="edit-card_number" maxlength="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" id="edit-bank_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit</label>
                            <input type="number" name="credit_limit" id="edit-credit_limit" step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Billing Date</label>
                            <input type="number" name="billing_date" id="edit-billing_date" min="1" max="31"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Due Date</label>
                            <input type="number" name="payment_due_date" id="edit-payment_due_date" min="1" max="31"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="edit-status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <input type="text" name="notes" id="edit-notes"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_card"
                        class="w-full sm:w-auto sm:ml-3 px-4 py-2 bg-primary text-white rounded-md hover:bg-secondary">
                        Update Card
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div id="transactionModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="fixed inset-0 transition-opacity" onclick="closeTransactionModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
            onclick="event.stopPropagation()">
            <form method="post">
                <input type="hidden" name="card_id" value="<?php echo $selected_card; ?>">
                <input type="hidden" name="transaction_type" id="trans-type">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4" id="trans-title">Add Transaction</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" name="amount" step="0.01" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="description"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                            <input type="text" name="reference" placeholder="Invoice #, etc."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="add_transaction" id="trans-submit"
                        class="w-full sm:w-auto sm:ml-3 px-4 py-2 text-white rounded-md">
                        Add Transaction
                    </button>
                    <button type="button" onclick="closeTransactionModal()"
                        class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editCard(card) {
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('edit-id').value = card.id;
        document.getElementById('edit-card_name').value = card.card_name;
        document.getElementById('edit-card_number').value = card.card_number || '';
        document.getElementById('edit-bank_name').value = card.bank_name || '';
        document.getElementById('edit-credit_limit').value = card.credit_limit;
        document.getElementById('edit-billing_date').value = card.billing_date;
        document.getElementById('edit-payment_due_date').value = card.payment_due_date;
        document.getElementById('edit-status').value = card.status;
        document.getElementById('edit-notes').value = card.notes || '';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function openTransactionModal(type) {
        document.getElementById('transactionModal').classList.remove('hidden');
        document.getElementById('trans-type').value = type;
        if (type === 'expense') {
            document.getElementById('trans-title').innerText = 'Add Expense';
            document.getElementById('trans-submit').className = 'w-full sm:w-auto sm:ml-3 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-md';
        } else {
            document.getElementById('trans-title').innerText = 'Add Payment';
            document.getElementById('trans-submit').className = 'w-full sm:w-auto sm:ml-3 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md';
        }
    }

    function closeTransactionModal() {
        document.getElementById('transactionModal').classList.add('hidden');
    }

    function openTopupModal(cardId, cardName, currentBalance) {
        document.getElementById('topupModal').classList.remove('hidden');
        document.getElementById('topup-card-id').value = cardId;
        document.getElementById('topup-card-name').innerText = cardName;
        document.getElementById('topup-current-balance').innerText = currentBalance.toFixed(2);
    }

    function closeTopupModal() {
        document.getElementById('topupModal').classList.add('hidden');
    }
</script>

<!-- Topup Modal -->
<div id="topupModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="fixed inset-0 transition-opacity" onclick="closeTopupModal()">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
            onclick="event.stopPropagation()">
            <form method="post">
                <input type="hidden" name="card_id" id="topup-card-id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
                            <i class="fas fa-plus-circle text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Top Up Credit Card</h3>
                            <p class="text-sm text-gray-500">Card: <span id="topup-card-name" class="font-medium"></span></p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Current Outstanding Balance: <strong><?php echo currency_symbol(); ?><span id="topup-current-balance">0.00</span></strong>
                        </p>
                        <p class="text-xs text-blue-600 mt-1">Topup will reduce your outstanding balance. Credit limit remains unchanged.</p>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Topup Amount <span class="text-red-500">*</span></label>
                            <input type="number" name="topup_amount" step="0.01" required min="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter topup amount">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="topup_date" required value="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="topup_description" placeholder="e.g., Payment from bank account"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                            <input type="text" name="topup_reference" placeholder="e.g., Receipt #, Transfer ID"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select name="topup_payment_method" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <?php foreach ($PAYMENT_METHODS as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $key === 'company_cash' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="topup_card"
                        class="w-full sm:w-auto sm:ml-3 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md font-medium">
                        <i class="fas fa-plus-circle mr-2"></i>Top Up Card
                    </button>
                    <button type="button" onclick="closeTopupModal()"
                        class="mt-3 sm:mt-0 w-full sm:w-auto px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>