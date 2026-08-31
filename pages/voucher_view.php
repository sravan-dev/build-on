<?php
require_once dirname(__DIR__) . '/includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Missing voucher ID'); }

// Get voucher details
$stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
$stmt->execute([$id]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) { die('Voucher not found'); }

// Get voucher entries
$stmt = $pdo->prepare("SELECT * FROM voucher_entries WHERE voucher_id = ? ORDER BY id");
$stmt->execute([$id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company details
$companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
$companyAddress = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
$companyPhone = getenv('COMPANY_PHONE') ?: '+947 30659993';
$companyTollFree = getenv('COMPANY_TOLL_FREE') ?: '77721423';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';
$currencySymbol = getenv('CURRENCY_SYMBOL') ?: 'ريال';

// Find company logo
$uploadDir = dirname(__DIR__) . '/uploads';
$companyLogoUrl = null;
$companyLogoExists = false;
$possibleExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/company_logo.' . $ext;
    if (file_exists($testPath)) {
        $companyLogoUrl = 'uploads/company_logo.' . $ext . '?t=' . filemtime($testPath);
        $companyLogoExists = true;
        break;
    }
}

// Using money() function from includes/functions.php

$print = isset($_GET['print']) ? true : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Voucher #<?php echo htmlspecialchars($voucher['voucher_no']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                color: black !important;
            }
            .container { 
                box-shadow: none !important; 
                margin: 0 !important;
                width: 100% !important;
                max-width: none !important;
            }
            @page {
                margin: 15mm;
                size: A4;
            }
            .voucher-container {
                border: 2px solid #000 !important;
                padding: 20px !important;
                background: white !important;
                color: black !important;
                display: block !important;
                visibility: visible !important;
            }
            /* Ensure all text is visible and black */
            * {
                color: black !important;
                background: white !important;
            }
            /* Hide only specific UI elements, not content */
            .sidebar, .nav, .navigation, .menu, .header, .footer {
                display: none !important;
            }
        }
        .voucher-container {
            border: 2px solid #e5e7eb;
            padding: 20px;
            background: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container max-w-4xl mx-auto my-6 bg-white shadow-lg rounded-lg print:shadow-none print:my-0">
        <!-- Action Buttons (Hidden on Print) -->
        <div class="p-6 border-b flex flex-col md:flex-row items-center justify-between gap-4 no-print">
            <h1 class="text-2xl font-bold text-gray-900">Cash Voucher</h1>
            <div class="flex flex-wrap gap-2">
                <a href="?id=<?php echo $voucher['id']; ?>&print=1" class="px-3 py-1.5 md:px-4 md:py-2 rounded bg-primary text-white text-xs md:text-sm">
                    <i class="fas fa-print mr-1"></i>Print
                </a>
                <a href="index.php?page=voucher_pdf&id=<?php echo $voucher['id']; ?>" class="px-3 py-1.5 md:px-4 md:py-2 rounded bg-red-600 text-white text-xs md:text-sm">
                    <i class="fas fa-file-pdf mr-1"></i>PDF
                </a>
                <a href="index.php?page=vouchers" class="px-3 py-1.5 md:px-4 md:py-2 rounded border text-xs md:text-sm">Back</a>
            </div>
        </div>

        <!-- Voucher Content -->
        <div class="voucher-container">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <?php if ($companyLogoExists): ?>
                        <img src="<?php echo $companyLogoUrl; ?>" alt="Company Logo" class="h-16 object-contain mr-4">
                    <?php endif; ?>
                    <div class="text-center">
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo strtoupper(htmlspecialchars($companyName)); ?></h2>
                        <p class="text-sm text-gray-600">CASH VOUCHER</p>
                    </div>
                </div>
                
                <div class="text-xs text-gray-600 space-y-1">
                    <?php
                    $addressLines = explode("\n", $companyAddress);
                    foreach ($addressLines as $line):
                        if (trim($line)): ?>
                            <div><?php echo htmlspecialchars(trim($line)); ?></div>
                        <?php endif;
                    endforeach; ?>
                    <div>Tel: <?php echo htmlspecialchars($companyPhone); ?> | <?php echo htmlspecialchars($companyTollFree); ?></div>
                    <div><?php echo htmlspecialchars($companyWebsite); ?></div>
                </div>
            </div>

            <!-- Voucher Details -->
            <div class="mb-6">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold text-gray-800">Voucher No:</span>
                            <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($voucher['voucher_no']); ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold text-gray-800">Date:</span>
                            <span class="text-gray-900 font-medium"><?php echo date('d/m/Y', strtotime($voucher['voucher_date'])); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold text-gray-800">Paid To:</span>
                            <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($voucher['paid_to_received_from']); ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold text-gray-800">Amount:</span>
                            <span class="font-bold text-gray-900 text-lg"><?php echo money($voucher['amount']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <?php if ($voucher['description']): ?>
            <div class="mb-6">
                <div class="font-semibold mb-2 text-gray-800">Description:</div>
                <div class="border border-gray-300 p-3 rounded bg-gray-50">
                    <span class="text-gray-900"><?php echo nl2br(htmlspecialchars($voucher['description'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Amount in Words -->
            <div class="mb-6">
                <div class="font-semibold mb-2 text-gray-800">Amount in Words:</div>
                <div class="border border-gray-300 p-3 rounded bg-gray-50">
                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($voucher['amount_in_words']); ?></span>
                </div>
            </div>

            <!-- Voucher Entries Table -->
            <?php if (!empty($entries)): ?>
            <div class="mb-6">
                <div class="font-semibold mb-2 text-gray-800">Account Entries:</div>
                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border border-gray-300 px-3 py-2 text-left text-gray-800 font-semibold whitespace-nowrap">Account Head</th>
                                <th class="border border-gray-300 px-3 py-2 text-right text-gray-800 font-semibold whitespace-nowrap">Debit</th>
                                <th class="border border-gray-300 px-3 py-2 text-right text-gray-800 font-semibold whitespace-nowrap">Credit</th>
                                <th class="border border-gray-300 px-3 py-2 text-left text-gray-800 font-semibold whitespace-nowrap">Narration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-3 py-2 text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($entry['account_head']); ?></td>
                                <td class="border border-gray-300 px-3 py-2 text-right text-gray-900 font-medium whitespace-nowrap"><?php echo $entry['debit_amount'] > 0 ? money($entry['debit_amount']) : '-'; ?></td>
                                <td class="border border-gray-300 px-3 py-2 text-right text-gray-900 font-medium whitespace-nowrap"><?php echo $entry['credit_amount'] > 0 ? money($entry['credit_amount']) : '-'; ?></td>
                                <td class="border border-gray-300 px-3 py-2 text-gray-900"><?php echo htmlspecialchars($entry['narration']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Signatures -->
            <div class="mt-8">
                <div class="grid grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="border-t border-gray-400 pt-2 mb-2">
                            <span class="text-sm font-semibold text-gray-800">Prepared By</span>
                        </div>
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($voucher['prepared_by'] ?: '_________________'); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="border-t border-gray-400 pt-2 mb-2">
                            <span class="text-sm font-semibold text-gray-800">Checked By</span>
                        </div>
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($voucher['checked_by'] ?: '_________________'); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="border-t border-gray-400 pt-2 mb-2">
                            <span class="text-sm font-semibold text-gray-800">Approved By</span>
                        </div>
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($voucher['approved_by'] ?: '_________________'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="mt-6 text-center">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                    <?php
                    switch($voucher['status']) {
                        case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                        case 'approved': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'posted': echo 'bg-green-100 text-green-800'; break;
                        default: echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                    Status: <?php echo ucfirst($voucher['status']); ?>
                </span>
            </div>
        </div>

        <!-- Print Button (Hidden on Print) -->
        <div class="no-print mt-6 text-center">
            <button onclick="window.print()" class="px-4 py-2 rounded bg-gray-900 text-white">
                <i class="fas fa-print mr-1"></i>Print Voucher
            </button>
        </div>
    </div>

    <?php if ($print): ?>
    <script>window.print()</script>
    <?php endif; ?>
</body>
</html>
