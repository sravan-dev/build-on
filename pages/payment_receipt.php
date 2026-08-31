<?php
include_once __DIR__ . '/../includes/db.php';

// Get payment ID from URL (accepts both 'id' and 'payment_id')
$payment_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0);
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

if (!$payment_id) {
    die('Payment ID required');
}

// Determine which type of payment (invoice payment vs purchase payment)
// If invoice_id is present, it's from the payments table (client payments)
$payment = null;

if ($invoice_id) {
    // Fetch from payments table (client/invoice payments)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               i.total_amount as invoice_total,
               COALESCE(i.project_id, q.project_id) as project_id,
               COALESCE(dpr.name, pr.name) as project_name,
               c.name as client_name
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN projects pr ON q.project_id = pr.id
        LEFT JOIN projects dpr ON i.project_id = dpr.id
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if ($payment) {
        // Map fields to common format
        $payment['payment_date'] = $payment['date'];
        $payment['vendor_name'] = $payment['client_name'] ?? '';
    }
} else {
    // Fetch from purchase_payments table
    $stmt = $pdo->prepare("
        SELECT pp.*, 
               p.id as purchase_ref,
               p.description as purchase_description,
               pr.name as project_name,
               v.name as vendor_name,
               e.name as employee_name
        FROM purchase_payments pp
        LEFT JOIN purchases p ON pp.purchase_id = p.id
        LEFT JOIN projects pr ON p.project_id = pr.id
        LEFT JOIN vendors v ON p.vendor_id = v.id
        LEFT JOIN employees e ON pp.employee_id = e.id
        WHERE pp.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
}

if (!$payment) {
    die('Payment not found');
}

// Helper function to convert number to words
function numberToWords($num)
{
    $ones = array(
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen'
    );
    $tens = array(
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety'
    );

    if ($num < 20)
        return $ones[$num];
    if ($num < 100)
        return $tens[floor($num / 10)] . ($num % 10 ? ' ' . $ones[$num % 10] : '');
    if ($num < 1000)
        return $ones[floor($num / 100)] . ' Hundred' . ($num % 100 ? ' and ' . numberToWords($num % 100) : '');
    if ($num < 1000000)
        return numberToWords(floor($num / 1000)) . ' Thousand' . ($num % 1000 ? ' ' . numberToWords($num % 1000) : '');
    return numberToWords(floor($num / 1000000)) . ' Million' . ($num % 1000000 ? ' ' . numberToWords($num % 1000000) : '');
}

$amount = floatval($payment['amount']);
$amountWhole = floor($amount);
$amountDecimal = round(($amount - $amountWhole) * 100);
$amountInWords = numberToWords($amountWhole) . ' Riyals';
if ($amountDecimal > 0) {
    $amountInWords .= ' and ' . numberToWords($amountDecimal) . ' Dirhams';
}
$amountInWords .= ' Only';

$isCash = in_array($payment['payment_method'], ['company_cash', 'personal']);
$isCheque = $payment['payment_method'] === 'company_cheque';

// Get company info from env
$companyName = getenv('COMPANY_NAME') ?: 'Buildon Qatar';
$companyAddress = getenv('COMPANY_ADDRESS') ?: 'Almijan Centre Capr, Almjad: Doha, Al Rayyan, Qatar';
$companyMobile = getenv('COMPANY_MOBILE') ?: '+974 3065 9993';
$companyPhone = getenv('COMPANY_PHONE') ?: '77721243';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.builqatar.com';
$companyCR = getenv('COMPANY_CR') ?: 'C.R. No: 158176';

// Check for custom receipt logo
$uploadDir = dirname(__DIR__) . '/uploads';
$receiptLogoUrl = null;
$possibleExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/receipt_logo.' . $ext;
    if (file_exists($testPath)) {
        $receiptLogoUrl = '../uploads/receipt_logo.' . $ext . '?t=' . filemtime($testPath);
        break;
    }
}

// Check for company seal
$companySealUrl = null;
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/company_seal.' . $ext;
    if (file_exists($testPath)) {
        $companySealUrl = '../uploads/company_seal.' . $ext . '?t=' . filemtime($testPath);
        break;
    }
}

// Check for authorized signature
$signatureUrl = null;
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/authorized_signature.' . $ext;
    if (file_exists($testPath)) {
        $signatureUrl = '../uploads/authorized_signature.' . $ext . '?t=' . filemtime($testPath);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt #<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        @page {
            size: A5 portrait;
            margin: 10mm;
        }

        @media print {

            html,
            body {
                width: 148mm;
                height: 210mm;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .receipt-container {
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .no-print button {
            background: #f07d00;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 10px;
        }

        .no-print button:hover {
            background: #d66a00;
        }

        .receipt-container {
            max-width: 550px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
            position: relative;
        }

        /* Top orange bar */
        .top-bar {
            height: 8px;
            background: linear-gradient(90deg, #f07d00, #f7a600);
        }

        /* Header section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #f07d00, #f7a600);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: bold;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 300;
            color: #333;
            letter-spacing: 1px;
        }

        .reg-no {
            text-align: right;
        }

        .reg-no-label {
            color: #666;
            font-size: 12px;
        }

        .reg-no-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        /* Title section */
        .title-section {
            text-align: center;
            padding: 12px;
            position: relative;
        }

        .title-section::before,
        .title-section::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 25%;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd, transparent);
        }

        .title-section::before {
            left: 5%;
        }

        .title-section::after {
            right: 5%;
        }

        .receipt-title {
            display: inline-block;
            background: linear-gradient(135deg, #2d8a4e, #4CAF50);
            color: white;
            padding: 8px 25px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
            border-radius: 20px;
        }

        .date-display {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            text-align: right;
        }

        .date-label {
            color: #666;
            font-size: 12px;
        }

        .date-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        /* Content section */
        .content {
            padding: 15px 25px;
            background: linear-gradient(180deg, #fafafa 0%, #fff 100%);
        }

        .field-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .field-label {
            min-width: 120px;
            font-size: 12px;
            color: #555;
        }

        .field-value {
            flex: 1;
            border-bottom: 1px dashed #aaa;
            padding: 3px 8px;
            font-size: 12px;
            color: #333;
            min-height: 20px;
        }

        /* Amount box */
        .amount-box {
            background: linear-gradient(90deg, #f7f7f7, #fff);
            border: 2px solid #f07d00;
            border-radius: 6px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            margin: 15px 0;
        }

        .amount-label {
            background: #f07d00;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 15px;
        }

        .amount-value {
            font-size: 26px;
            font-weight: 300;
            color: #2d8a4e;
            letter-spacing: 1px;
        }

        .amount-currency {
            font-size: 14px;
            color: #2d8a4e;
            margin-left: 8px;
        }

        /* Payment options */
        .payment-options {
            display: flex;
            gap: 20px;
            margin: 12px 0;
            flex-wrap: wrap;
            font-size: 12px;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox {
            width: 14px;
            height: 14px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .checkbox.checked::after {
            content: '✓';
            font-weight: bold;
            color: #2d8a4e;
        }

        .cheque-field {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .cheque-line {
            flex: 1;
            border-bottom: 1px dashed #aaa;
            min-width: 150px;
            padding: 5px;
        }

        /* Footer */
        .footer {
            display: flex;
            justify-content: space-between;
            padding: 15px 25px;
            background: linear-gradient(180deg, #fff, #f9f9f9);
            border-top: 1px solid #eee;
            position: relative;
        }

        .company-info {
            max-width: 250px;
        }

        .company-logo-small {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .company-logo-small .icon {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #f07d00, #f7a600);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .company-logo-small .name {
            font-weight: 600;
            color: #333;
        }

        .company-details {
            font-size: 11px;
            color: #666;
            line-height: 1.6;
        }

        .signature-section {
            text-align: center;
            min-width: 200px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 8px;
            height: 50px;
        }

        .signature-label {
            font-size: 11px;
            color: #666;
        }

        /* Hide stamp area */
        .stamp-area {
            display: none;
        }

        /* Stamp area */
        .stamp-area {
            width: 120px;
            height: 120px;
            border: 3px dashed #1a5276;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #1a5276;
            font-size: 10px;
            text-align: center;
            padding: 15px;
        }

        .stamp-cr {
            font-weight: bold;
            font-size: 11px;
        }

        .stamp-location {
            font-weight: 600;
            margin-top: 3px;
        }

        /* Bottom bar */
        .bottom-bar {
            height: 15px;
            background: linear-gradient(90deg, #f07d00 0%, #f07d00 20%, #1a3a5c 20%, #1a3a5c 100%);
            position: relative;
        }

        .bottom-bar::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            width: 50%;
            height: 100%;
            background: repeating-linear-gradient(-45deg,
                    transparent,
                    transparent 2px,
                    rgba(255, 255, 255, 0.1) 2px,
                    rgba(255, 255, 255, 0.1) 4px);
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
        <button onclick="goBack()"><i class="fas fa-arrow-left"></i> Back</button>
    </div>
    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        }
    </script>

    <div class="receipt-container">
        <div class="top-bar"></div>

        <div class="header">
            <div class="logo">
                <?php if ($receiptLogoUrl): ?>
                    <img src="<?php echo $receiptLogoUrl; ?>" alt="Logo"
                        style="max-height:40px;max-width:150px;object-fit:contain;">
                <?php else: ?>
                    <div class="logo-icon">b</div>
                    <div class="logo-text">buildon</div>
                <?php endif; ?>
            </div>
            <div class="reg-no">
                <div class="reg-no-label">Reg. No.:</div>
                <div class="reg-no-value"><?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></div>
            </div>
        </div>

        <div class="title-section">
            <div class="receipt-title">MONEY RECEIPT</div>
            <div class="date-display">
                <div class="date-label">Date:</div>
                <div class="date-value"><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></div>
            </div>
        </div>

        <div class="content">
            <div class="field-row">
                <div class="field-label">Received from:</div>
                <div class="field-value">
                    <?php echo htmlspecialchars($payment['vendor_name'] ?? $payment['project_name'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="field-row">
                <div class="field-label">Amount in Words:</div>
                <div class="field-value"><?php echo $amountInWords; ?></div>
            </div>

            <div class="amount-box">
                <div class="amount-label">Amount of Pay:</div>
                <div class="amount-value"><?php echo number_format($payment['amount'], 2); ?></div>
                <div class="amount-currency">ر.ق</div>
            </div>

            <div class="payment-options">
                <div class="payment-option">
                    <span class="field-label" style="min-width: auto;">Payment:</span>
                    <div class="checkbox <?php echo $isCash ? 'checked' : ''; ?>"></div>
                    <span>Cash</span>
                </div>
                <div class="payment-option">
                    <div class="checkbox <?php echo $isCheque ? 'checked' : ''; ?>"></div>
                    <span>Cheque</span>
                </div>
                <div class="cheque-field">
                    <span>Cheque No. line:</span>
                    <div class="cheque-line"><?php echo htmlspecialchars($payment['cheque_number'] ?? ''); ?></div>
                </div>
            </div>

            <div class="field-row">
                <div class="field-label">For the purpose of:</div>
                <div class="field-value">
                    <?php echo htmlspecialchars($payment['purchase_description'] ?? $payment['notes'] ?? ''); ?>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="company-info">
                <div class="company-details">
                    <?php echo nl2br(htmlspecialchars($companyAddress)); ?><br>
                    Mobile: <?php echo htmlspecialchars($companyMobile); ?><br>
                    Toll free: <?php echo htmlspecialchars($companyPhone); ?><br>
                    <?php echo htmlspecialchars($companyWebsite); ?>
                </div>
            </div>

            <div class="signature-section">
                <?php if ($signatureUrl): ?>
                    <div style="height:40px; display:flex; align-items:flex-end; justify-content:center;">
                        <img src="<?php echo $signatureUrl; ?>" alt="Signature"
                            style="max-height:40px;max-width:100px;object-fit:contain;">
                    </div>
                <?php else: ?>
                    <div class="signature-line"></div>
                <?php endif; ?>
                <div class="signature-label">Received by:</div>
                <div style="height: 30px;"></div>
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Signature</div>
            </div>

            <?php if ($companySealUrl): ?>
                <div class="stamp-area" style="display:flex; border:none; padding:0;">
                    <img src="<?php echo $companySealUrl; ?>" alt="Company Seal"
                        style="max-width:100px;max-height:100px;object-fit:contain;">
                </div>
            <?php else: ?>
                <div class="stamp-area">
                    <div class="stamp-cr"><?php echo htmlspecialchars($companyCR); ?></div>
                    <div class="stamp-location">DOHA - QATAR</div>
                    <div style="font-size: 9px; margin-top: 5px;">BUILDING ENG AND CONTRACTING</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="bottom-bar"></div>
    </div>
</body>

</html>