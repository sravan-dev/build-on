<?php
include_once 'includes/db.php';

// Handle Clear Ledger Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_ledger') {
    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM voucher_entries");
        $pdo->exec("DELETE FROM vouchers");
        // Attempt to reset sequence if SQLite, generic SQL ignore if error
        try {
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name='vouchers'");
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name='voucher_entries'");
        } catch (Exception $e) { /* Ignore if not SQLite or table doesn't exist */
        }

        $pdo->commit();
        $success = "Ledger cleared successfully. All vouchers have been deleted.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to clear ledger: " . $e->getMessage();
    }
}

// Get filter parameters
$account_filter = $_GET['account'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for ledger entries
$query = "
    SELECT 
        ve.voucher_id,
        v.voucher_no,
        v.voucher_date,
        ve.account_head,
        ve.debit_amount,
        ve.credit_amount,
        ve.narration,
        v.status as voucher_status
    FROM voucher_entries ve
    LEFT JOIN vouchers v ON ve.voucher_id = v.id
    WHERE 1=1
";

$params = [];

if ($account_filter) {
    $query .= " AND ve.account_head LIKE ?";
    $params[] = "%$account_filter%";
}

if ($date_from) {
    $query .= " AND v.voucher_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND v.voucher_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY v.voucher_date DESC, v.voucher_no DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Get accounts for filter dropdown
$accounts = $pdo->query("SELECT DISTINCT account_head FROM voucher_entries ORDER BY account_head")->fetchAll();

// Calculate running balance for each account
$accountBalances = [];
foreach ($entries as $entry) {
    $account = $entry['account_head'];
    if (!isset($accountBalances[$account])) {
        $accountBalances[$account] = 0;
    }
    $accountBalances[$account] += $entry['debit_amount'] - $entry['credit_amount'];
}

?>

<div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">General Ledger</h1>
        <p class="text-gray-600 mt-2">View all accounting entries from vouchers</p>
    </div>
    <div class="mt-4 md:mt-0">
        <form method="post"
            onsubmit="return confirm('DANGER: Are you sure you want to PERMANENTLY DELETE ALL ledger entries? This action cannot be undone.');">
            <input type="hidden" name="action" value="clear_ledger">
            <button type="submit"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                <i class="fas fa-trash-alt mr-2"></i>Clear Ledger
            </button>
        </form>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Search & Filter</h3>
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="page" value="ledger">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
                <select name="account"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Accounts</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo htmlspecialchars($account['account_head']); ?>" <?php echo $account_filter === $account['account_head'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['account_head']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div class="flex items-end">
                <button type="submit"
                    class="w-full bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md font-medium transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </form>
    </div>

    <!-- Account Balances Summary -->
    <?php if (!empty($accountBalances)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Balances</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($accountBalances as $account => $balance): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($account); ?></div>
                        <div class="text-lg font-bold <?php echo $balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo money(abs($balance)); ?>
                            <?php if ($balance >= 0): ?>
                                <span class="text-xs text-gray-500">(Debit)</span>
                            <?php else: ?>
                                <span class="text-xs text-gray-500">(Credit)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ledger Entries -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold text-gray-900">Ledger Entries</h2>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Voucher No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Account Head</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Narration</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Debit</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Credit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($entries as $entry): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($entry['voucher_date'])); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="index.php?page=voucher_view&id=<?php echo $entry['voucher_id']; ?>"
                                        class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($entry['voucher_no']); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($entry['account_head']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($entry['narration']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo $entry['debit_amount'] > 0 ? money($entry['debit_amount']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo $entry['credit_amount'] > 0 ? money($entry['credit_amount']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch ($entry['voucher_status']) {
                                        case 'draft':
                                            echo 'bg-gray-100 text-gray-800';
                                            break;
                                        case 'approved':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'posted':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                        <?php echo ucfirst($entry['voucher_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>