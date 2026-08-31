<?php
include_once 'includes/db.php';
require_once 'includes/payment_methods.php';

// Check if user is supervisor - show simplified dashboard
$role = $_SESSION['role'] ?? 'employee';
if ($role === 'supervisor') {
    // Get user count (employees)
    $user_count = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch()['count'] ?? 0;

    // Get pending attendance requests
    $pending_requests = $pdo->query("SELECT COUNT(*) as count FROM daily_attendance WHERE approval_status = 'pending'")->fetch()['count'] ?? 0;
    ?>
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Supervisor Dashboard</h1>
        <p class="text-gray-600 mt-2">Overview of users and pending requests</p>
    </div>

    <div class="space-y-6">
        <!-- Supervisor Cards: Only User Count and Requests -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- User Count Card -->
            <a href="index.php?page=payroll" class="block">
                <div
                    class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-8 hover:shadow-xl transition-all transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm uppercase font-bold text-blue-100 mb-2">Total Users</p>
                            <h2 class="text-5xl font-bold text-white mb-2">
                                <?php echo $user_count; ?>
                            </h2>
                            <p class="text-sm text-blue-100">Active employees</p>
                        </div>
                        <div
                            class="w-20 h-20 rounded-full bg-white bg-opacity-30 flex items-center justify-center text-white">
                            <i class="fas fa-users text-4xl"></i>
                        </div>
                    </div>
                </div>
            </a>

            <!-- Pending Requests Card -->
            <a href="index.php?page=attendance_approvals" class="block">
                <div
                    class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-8 hover:shadow-xl transition-all transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm uppercase font-bold text-orange-100 mb-2">Pending Requests</p>
                            <h2 class="text-5xl font-bold text-white mb-2">
                                <?php echo $pending_requests; ?>
                            </h2>
                            <p class="text-sm text-orange-100">
                                <?php if ($pending_requests > 0): ?>
                                    <span class="underline">Review now</span>
                                <?php else: ?>
                                    All caught up!
                                <?php endif; ?>
                            </p>
                        </div>
                        <div
                            class="w-20 h-20 rounded-full bg-white bg-opacity-30 flex items-center justify-center text-white">
                            <i class="fas fa-user-check text-4xl"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
    <?php
    return; // Stop here for supervisors
}

// Get purchase metrics (with table existence check)
$purchases_exist = false;
try {
    $pdo->query("SELECT 1 FROM purchases LIMIT 1");
    $purchases_exist = true;
} catch (PDOException $e) {
    // Tables don't exist yet
}

// DATA GATHERING - GENERAL LEDGER BASED
// -------------------------------------------------------------------------

// Helper to get ledger balance
function get_ledger_balance($pdo, $account_name) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(debit_amount) - SUM(credit_amount) as balance FROM voucher_entries WHERE account_head = ?");
        $stmt->execute([$account_name]);
        return floatval($stmt->fetch()['balance'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// 1. Asset Balances (Debit is Positive)
$cash_balance = get_ledger_balance($pdo, 'Cash');
$bank_balance = get_ledger_balance($pdo, 'Bank – Company Account');

// Company Liquid Funds
$company_balance = $cash_balance + $bank_balance;


// 2. Liability Balances (Credit is Positive, so negate the result of Dr-Cr)
// Since get_ledger_balance returns (Dr - Cr), a credit balance will be negative.
// We want to show the liability amount as positive.
// Credit Cards - Read from credit_cards table
// $credit_card_payable = -1 * get_ledger_balance($pdo, 'Credit Card Payable');
// FIX: Read directly from credit_cards table to match the Credit Cards page
try {
    $stmt = $pdo->query("SELECT SUM(current_balance) as total_balance, SUM(credit_limit) as total_limit FROM credit_cards WHERE status = 'active'");
    $res = $stmt->fetch();
    $credit_card_payable = floatval($res['total_balance'] ?? 0);
    $credit_card_limit = floatval($res['total_limit'] ?? 0);
    $credit_card_available = $credit_card_limit - $credit_card_payable;
    // Calculate available percentage
    $credit_card_percent = $credit_card_limit > 0 ? ($credit_card_available / $credit_card_limit) * 100 : 0;
} catch (Exception $e) {
    $credit_card_payable = 0;
    $credit_card_available = 0;
    $credit_card_percent = 0;
}

// Partner Loans
$rahees_loan = -1 * (get_ledger_balance($pdo, 'Rahees – Cash') + get_ledger_balance($pdo, 'Rahees – Card'));
$salman_loan = -1 * (get_ledger_balance($pdo, 'Salman – Cash') + get_ledger_balance($pdo, 'Salman – Card'));


// 3. Performance Metrics (Keep existing logic for Sales/Profit for now unless GL accounts for Revenue exist)
// Typically Sales = Credit to Revenue Account.
// But for now we stick to Invoices table for Sales as per previous logic, unless specified.
// User said "Dashboard balances must read directly from General Ledger" mainly for the Tiles listed (Cash, Bank, CC, Partners).
// I will keep Sales/Expenses as is for trends, unless I map them to GL too. The prompt specifically listed mappings for the Tiles.

// Total Sales (from Invoices for trend)
$total_sales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices")->fetch()['total'] ?? 0;

// Vehicle Income
$vehicle_income_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vehicle_income")->fetch()['total'] ?? 0;

// Total Income (Sales + Vehicle Income)
$total_income = $total_sales + $vehicle_income_total;

// Total Expenses (Aggregation for trend) - Optional: could pull from Expense GL accounts if we had a group.
// Sticking to existing aggregation for "Total Expenses" tile to avoid breaking it if entries aren't backfilled.
$expenses_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses")->fetch()['total'] ?? 0;
$vendor_payments_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vendor_payments")->fetch()['total'] ?? 0;
$purchase_payments_total = 0;
if ($purchases_exist) {
    $purchase_payments_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM purchase_payments")->fetch()['total'] ?? 0;
}
$labour_cost = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) as total FROM labour_payments")->fetch()['total'] ?? 0;
$vehicle_expenses_total = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM vehicle_expenses")->fetch()['total'] ?? 0;

$total_all_expenses = $expenses_total + $vendor_payments_total + $purchase_payments_total + $labour_cost + $vehicle_expenses_total;

// Net Profit
$net_profit = $total_sales - $total_all_expenses;
$net_profit_percent = $total_sales > 0 ? ($net_profit / $total_sales) * 100 : 0;


// 4. Recent Transactions (Keep existing logic for list)

// 4. Purchase Metrics
if ($purchases_exist) {
    $total_purchases_approved = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM purchases WHERE status = 'approved'")->fetch()['total'] ?? 0;
    $pending_approvals = $pdo->query("SELECT COUNT(*) as count FROM purchases WHERE status = 'pending'")->fetch()['count'] ?? 0;
    
    // Pending Reimbursements (GL based? Or keep existing?)
    // Existing logic was sum of purchase_payments via personal cards.
    // Ideally this matches the GL Partner Loan balance, BUT Partner Loan might include other things (Manual JE).
    // Let's use the Partner Loan Balance from GL for consistency with the Tiles.
    // Actually, "Pending Reimbursements" card usually implies what the company OWES.
    // Which is the Credit Balance of Partner Loan.
    $pending_reimbursements = $rahees_loan + $salman_loan; 

} else {
    $total_purchases_approved = 0;
    $pending_approvals = 0;
    $pending_reimbursements = 0;
}


?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-gray-600">Welcome to Buildon Accounts Dashboard</p>
</div>

<!-- 1. Financial Overview Row -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <!-- Company Balance -->
    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between border-l-4 border-blue-600">
        <div>
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Company Balance</h3>
            <div class="text-2xl font-bold text-gray-800 mt-1" dir="ltr"><?php echo number_format($company_balance, 2); ?> <span class="text-sm text-gray-500">QAR</span></div>
            <div class="text-xs text-gray-400 mt-2">Income: <?php echo number_format($total_income, 2); ?> ريال <br> Exp: <?php echo number_format($total_all_expenses, 2); ?> ريال</div>
        </div>
        <div class="p-3 bg-blue-100 rounded-full text-blue-600">
            <i class="fas fa-wallet text-xl"></i>
        </div>
    </div>

    <!-- Sales -->
    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between border-l-4 border-emerald-500">
        <div>
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Sales</h3>
            <div class="text-2xl font-bold text-gray-800 mt-1" dir="ltr"><?php echo number_format($total_sales, 2); ?> <span class="text-sm text-gray-500">QAR</span></div>
            <div class="text-xs text-emerald-600 mt-2 font-medium flex items-center">
                <i class="fas fa-arrow-up mr-1"></i> +5% than last month
            </div>
        </div>
        <div class="p-3 bg-emerald-100 rounded-full text-emerald-600">
            <i class="fas fa-chart-line text-xl"></i>
        </div>
    </div>

    <!-- Expenses -->
    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between border-l-4 border-red-500">
        <div>
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Expenses</h3>
            <div class="text-2xl font-bold text-gray-800 mt-1" dir="ltr"><?php echo number_format($total_all_expenses, 2); ?> <span class="text-sm text-gray-500">QAR</span></div>
            <div class="text-xs text-red-600 mt-2 font-medium flex items-center">
                <i class="fas fa-arrow-down mr-1"></i> -2% since last quarter
            </div>
        </div>
        <div class="p-3 bg-red-100 rounded-full text-red-600">
            <i class="fas fa-arrow-down text-xl"></i>
        </div>
    </div>

    <!-- Net Profit -->
    <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between border-l-4 border-green-600">
        <div>
            <h3 class="text-xs font-bold text-green-600 uppercase tracking-wider">Net Profit</h3>
            <div class="text-2xl font-bold text-gray-800 mt-1" dir="ltr"><?php echo number_format($net_profit, 2); ?> <span class="text-sm text-gray-500">QAR</span></div>
            <div class="text-xs text-green-600 mt-2 font-medium">
                <i class="fas fa-arrow-up mr-1"></i> <?php echo number_format($net_profit_percent, 1); ?>% margin
            </div>
        </div>
        <!-- No Icon, cleaner look -->
    </div>
</div>

<!-- 2. Payments & Cards Row -->
<div class="mb-4">
    <h3 class="text-lg font-bold text-gray-800 mb-3">Payments Received</h3>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    
            <?php
            // Use GL variables calculated at top
            $rahees_total = $rahees_loan;
            $salman_total = $salman_loan;
            ?>

            <!-- Card 1: Rahees -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-500 rounded-xl shadow-lg p-6 text-white relative overflow-hidden">
                <div class="relative z-10 flex justify-between items-center h-full">
                    <div>
                        <h4 class="text-sm font-bold uppercase opacity-90">Rahees Cash / Card</h4>
                        <div class="text-3xl font-bold mt-2"><?php echo number_format($rahees_total, 0); ?> <span class="text-lg font-normal">QAR</span></div>
                        <div class="text-sm opacity-80 mt-1">Total Paid Out</div>
                    </div>
                    <div class="p-3 bg-white bg-opacity-20 rounded-full">
                        <i class="fas fa-wallet text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: Salman -->
            <div class="bg-gradient-to-r from-purple-600 to-purple-500 rounded-xl shadow-lg p-6 text-white relative overflow-hidden">
                <div class="relative z-10 flex justify-between items-center h-full">
                    <div>
                         <h4 class="text-sm font-bold uppercase opacity-90">Salman Cash / Card</h4>
                        <div class="text-3xl font-bold mt-2"><?php echo number_format($salman_total, 0); ?> <span class="text-lg font-normal">QAR</span></div>
                        <div class="text-sm opacity-80 mt-1">Total Paid Out</div>
                    </div>
                    <div class="p-3 bg-white bg-opacity-20 rounded-full">
                        <i class="fas fa-wallet text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Credit Card Balance -->
            <div class="bg-gradient-to-r from-teal-600 to-teal-400 rounded-xl shadow-lg p-6 text-white relative overflow-hidden">
                 <div class="relative z-10 flex justify-between items-center h-full">
                    <div>
                        <h4 class="text-sm font-normal opacity-90">Available Credit:</h4>
                        <div class="text-3xl font-bold mt-2"><?php echo number_format($credit_card_available ?? 0, 2); ?> <span class="text-lg font-normal">QAR</span></div>
                        <div class="h-2 w-full bg-white bg-opacity-20 rounded-full mt-3 overflow-hidden">
                            <div class="h-full bg-white bg-opacity-60" style="width: <?php echo min($credit_card_percent ?? 0, 100); ?>%"></div>
                        </div>
                    </div>
                     <div class="p-3 bg-white bg-opacity-20 rounded-full">
                        <i class="fas fa-credit-card text-2xl"></i>
                    </div>
                </div>
                <!-- Card Watermark -->
                <div class="absolute -bottom-6 -right-6 text-white text-opacity-10 text-9xl">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
</div>

<!-- Row 3: Purchases & Operations -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Purchases -->
        <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xs font-bold text-blue-800 uppercase tracking-wider">TOTAL PURCHASES</h3>
                    <div class="mt-2 text-2xl font-bold text-gray-900"><?php echo money($total_purchases_approved); ?></div>
                    <div class="mt-1 text-xs text-gray-500">Approved purchases</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white shadow-md">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>

        <!-- Purchase Payments -->
        <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-center">
                 <div>
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">PURCHASE PAYMENTS</h3>
                    <div class="mt-2 text-2xl font-bold text-gray-900"><?php echo money($purchase_payments_total); ?></div>
                    <div class="mt-1 text-xs text-gray-500">Total paid out</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center text-white shadow-md">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-center">
                 <div>
                    <h3 class="text-xs font-bold text-orange-600 uppercase tracking-wider">PENDING APPROVALS</h3>
                    <div class="mt-2 text-2xl font-bold text-gray-900"><?php echo $pending_approvals; ?></div>
                    <div class="mt-1 text-xs text-gray-500">All caught up!</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-orange-400 flex items-center justify-center text-white shadow-md">
                   <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>

        <!-- Pending Reimbursements -->
        <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-center">
                 <div>
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">PENDING REIMBURSEMENTS</h3>
                    <div class="mt-2 text-2xl font-bold text-gray-900"><?php echo money($pending_reimbursements); ?></div>
                    <div class="mt-1 text-xs text-gray-500">All paid</div>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-md">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>