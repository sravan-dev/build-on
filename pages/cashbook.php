<?php


include_once 'includes/db.php';

$transactions = $pdo->query("SELECT * FROM transactions ORDER BY payment_method, date DESC")->fetchAll();

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Cash & Bank Book</h1>
    <p class="text-gray-600 mt-2">Transaction breakdown by payment method for cash flow analysis</p>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Payment Method Transactions</h2>
                <div class="text-sm text-gray-500">
                    Total Transactions: <?php echo count($transactions); ?>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($transactions as $transaction): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch($transaction['payment_method']) {
                                        case 'cash': echo 'bg-green-100 text-green-800'; break;
                                        case 'bank': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'credit': echo 'bg-purple-100 text-purple-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <i class="fas fa-<?php echo $transaction['payment_method'] === 'cash' ? 'money-bill-wave' : ($transaction['payment_method'] === 'bank' ? 'university' : 'credit-card'); ?> mr-1"></i>
                                    <?php echo ucfirst($transaction['payment_method']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($transaction['date'])); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($transaction['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium <?php echo $transaction['type'] === 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($transaction['amount'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch($transaction['type']) {
                                        case 'income': echo 'bg-green-100 text-green-800'; break;
                                        case 'expense': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($transaction['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($transaction['source']); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
