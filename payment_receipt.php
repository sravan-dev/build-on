<?php
// Standalone payment receipt page
session_start();
include_once 'includes/db.php';

// Get payment ID
$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch payment details with invoice and client information
$stmt = $pdo->prepare("
    SELECT p.*, 
           i.id as invoice_id,
           i.client_id,
           c.name as client_name,
           c.phone as client_phone,
           c.email as client_email,
           c.address as client_address,
           COALESCE(dpr.name, pr.name) as project_name
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    LEFT JOIN clients c ON i.client_id = c.id
    LEFT JOIN quotations q ON i.quotation_id = q.id
    LEFT JOIN projects pr ON q.project_id = pr.id
    LEFT JOIN projects dpr ON i.project_id = dpr.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Payment not found');
}

// Generate receipt number
$receipt_no = 'PMT-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);

// Check for authorized signature
$signatureUrl = null;
$possibleExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
$uploadDir = __DIR__ . '/uploads';

foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/authorized_signature.' . $ext;
    if (file_exists($testPath)) {
        $signatureUrl = 'uploads/authorized_signature.' . $ext;
        break;
    }
}

// Get company info
$company_name = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $receipt_no; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 10px;
        }
        
        .receipt-container {
            max-width: 650px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header-bar {
            background: linear-gradient(135deg, #f07d00 0%, #ff9933 100%);
            height: 8px;
        }
        
        .receipt-header {
            padding: 20px;
            border-bottom: 3px solid #f5f5f5;
        }
        
        .company-info {
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .company-logo {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }
        
        .receipt-badge {
            background: #28a745;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .receipt-details {
            text-align: right;
            margin-top: 10px;
        }
        
        .receipt-no {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .receipt-date {
            color: #666;
            margin-top: 5px;
        }
        
        .receipt-body {
            padding: 20px;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        
        .amount-box {
            background: #fff5e6;
            border: 2px solid #f07d00;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .amount-label {
            font-size: 14px;
            color: #f07d00;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .amount-value {
            font-size: 32px;
            color: #f07d00;
            font-weight: bold;
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .detail-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .receipt-footer {
            padding: 20px;
            border-top: 2px solid #f5f5f5;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .signature-section {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 2px solid #333;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .print-button {
            background: #f07d00;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px;
        }
        
        .print-button:hover {
            background: #d96d00;
        }
        
        .back-button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
            }
            
            .print-button,
            .back-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px;">
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="index.php?page=payments" class="back-button">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    
    <div class="receipt-container">
        <div class="header-bar"></div>
        
        <div class="receipt-header">
            <div class="company-info">
                <div>
                    <div class="company-logo"><?php echo htmlspecialchars($company_name); ?></div>
                    <div style="color: #666; font-size: 14px; margin-top: 5px;">Construction Management</div>
                </div>
                <div class="receipt-badge">MONEY RECEIPT</div>
            </div>
            
            <div class="receipt-details">
                <div class="receipt-no">Rec. No: <?php echo $receipt_no; ?></div>
                <div class="receipt-date">Date: <?php echo date('F d, Y', strtotime($payment['date'])); ?></div>
            </div>
        </div>
        
        <div class="receipt-body">
            <div class="info-section">
                <div class="info-label">Received from:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['client_name'] ?? 'N/A'); ?></div>
                <?php if ($payment['client_phone']): ?>
                <div style="color: #666; font-size: 14px; margin-top: 5px;">
                    <?php echo htmlspecialchars($payment['client_phone']); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="amount-box">
                <div class="amount-label">Amount of Payment</div>
                <div class="amount-value"><?php echo number_format($payment['amount'], 2); ?> ر.ع</div>
            </div>
            
            <div class="payment-details">
                <div class="detail-item">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value">
                        <?php 
                        $payment_labels = [
                            'company_cash' => 'Cash',
                            'company_bank' => 'Bank Transfer',
                            'company_card' => 'Card',
                            'credit_card' => 'Credit Card',
                            'company_cheque' => 'Cheque'
                        ];
                        echo $payment_labels[$payment['payment_method']] ?? ucfirst(str_replace('_', ' ', $payment['payment_method']));
                        ?>
                    </div>
                </div>
                
                <?php if ($payment['invoice_id']): ?>
                <div class="detail-item">
                    <div class="info-label">Invoice Number</div>
                    <div class="info-value">INV-<?php echo str_pad($payment['invoice_id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($payment['cheque_number'] || $payment['bank_name']): ?>
            <div class="payment-details">
                <?php if ($payment['cheque_number']): ?>
                <div class="detail-item">
                    <div class="info-label">Cheque Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['cheque_number']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($payment['bank_name']): ?>
                <div class="detail-item">
                    <div class="info-label">Bank Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($payment['bank_name']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($payment['project_name']): ?>
            <div class="info-section">
                <div class="info-label">Project:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['project_name']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($payment['notes']): ?>
            <div class="info-section">
                <div class="info-label">Notes:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['notes']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="signature-section">
                <?php if ($signatureUrl): ?>
                    <img src="<?php echo $signatureUrl; ?>?t=<?php echo time(); ?>" alt="Authorized Signature" style="max-width: 200px; max-height: 80px; margin: 0 auto 10px;">
                <?php endif; ?>
                <div>Authorized Signature</div>
            </div>
        </div>
        
        <div class="receipt-footer">
            <p>This is a computer-generated receipt and is valid without signature.</p>
            <p style="margin-top: 10px;">Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
    </div>
</body>
</html>
