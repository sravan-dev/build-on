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

// Using money() function from includes/functions.php

// Simple PDF generation using HTML to PDF conversion
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Voucher #' . htmlspecialchars($voucher['voucher_no']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .voucher-container { border: 2px solid #000; padding: 20px; background: white; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .voucher-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; }
        .company-details { font-size: 12px; line-height: 1.4; }
        .voucher-details { margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .detail-label { font-weight: bold; }
        .description-box { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
        .amount-words-box { border: 1px solid #ccc; padding: 10px; margin: 10px 0; font-weight: bold; }
        .entries-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .entries-table th, .entries-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .entries-table th { background-color: #f5f5f5; font-weight: bold; }
        .entries-table .amount { text-align: right; }
        .signatures { margin-top: 40px; }
        .signature-row { display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 30%; }
        .signature-line { border-top: 1px solid #000; margin-top: 5px; padding-top: 5px; }
        .status { text-align: center; margin-top: 20px; }
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 15px; font-weight: bold; }
        .status-draft { background-color: #f3f4f6; color: #374151; }
        .status-approved { background-color: #fef3c7; color: #92400e; }
        .status-posted { background-color: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="voucher-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">' . strtoupper(htmlspecialchars($companyName)) . '</div>
            <div class="voucher-title">CASH VOUCHER</div>
            <div class="company-details">';

$addressLines = explode("\n", $companyAddress);
foreach ($addressLines as $line) {
    if (trim($line)) {
        $html .= htmlspecialchars(trim($line)) . '<br>';
    }
}

$html .= '
                Tel: ' . htmlspecialchars($companyPhone) . ' | ' . htmlspecialchars($companyTollFree) . '<br>
                ' . htmlspecialchars($companyWebsite) . '
            </div>
        </div>

        <!-- Voucher Details -->
        <div class="voucher-details">
            <div class="detail-row">
                <span class="detail-label">Voucher No:</span>
                <span>' . htmlspecialchars($voucher['voucher_no']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span>' . date('d/m/Y', strtotime($voucher['voucher_date'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Paid To:</span>
                <span>' . htmlspecialchars($voucher['paid_to_received_from']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span><strong>' . money($voucher['amount']) . '</strong></span>
            </div>
        </div>';

// Description
if ($voucher['description']) {
    $html .= '
        <div>
            <div class="detail-label">Description:</div>
            <div class="description-box">' . nl2br(htmlspecialchars($voucher['description'])) . '</div>
        </div>';
}

// Amount in Words
$html .= '
        <div>
            <div class="detail-label">Amount in Words:</div>
            <div class="amount-words-box">' . htmlspecialchars($voucher['amount_in_words']) . '</div>
        </div>';

// Voucher Entries Table
if (!empty($entries)) {
    $html .= '
        <div>
            <div class="detail-label">Account Entries:</div>
            <table class="entries-table">
                <thead>
                    <tr>
                        <th>Account Head</th>
                        <th class="amount">Debit</th>
                        <th class="amount">Credit</th>
                        <th>Narration</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($entries as $entry) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($entry['account_head']) . '</td>
                        <td class="amount">' . ($entry['debit_amount'] > 0 ? money($entry['debit_amount']) : '-') . '</td>
                        <td class="amount">' . ($entry['credit_amount'] > 0 ? money($entry['credit_amount']) : '-') . '</td>
                        <td>' . htmlspecialchars($entry['narration']) . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>';
}

// Signatures
$html .= '
        <div class="signatures">
            <div class="signature-row">
                <div class="signature-box">
                    <div class="signature-line">
                        <strong>Prepared By</strong>
                    </div>
                    <div>' . htmlspecialchars($voucher['prepared_by'] ?: '_________________') . '</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        <strong>Checked By</strong>
                    </div>
                    <div>' . htmlspecialchars($voucher['checked_by'] ?: '_________________') . '</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        <strong>Approved By</strong>
                    </div>
                    <div>' . htmlspecialchars($voucher['approved_by'] ?: '_________________') . '</div>
                </div>
            </div>
        </div>';

// Status
$statusClass = 'status-' . $voucher['status'];
$html .= '
        <div class="status">
            <span class="status-badge ' . $statusClass . '">
                Status: ' . ucfirst($voucher['status']) . '
            </span>
        </div>
    </div>
</body>
</html>';

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Cash_Voucher_' . $voucher['voucher_no'] . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// For now, we'll output HTML that can be converted to PDF by the browser
// In a production environment, you would use a library like TCPDF or mPDF
echo $html;
?>
