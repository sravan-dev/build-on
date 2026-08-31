<?php
session_start();

require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$lpo_id = (int)($_GET['id'] ?? 0);

if (!$lpo_id) {
    header('Location: index.php?page=lpos');
    exit;
}

// Get LPO details
try {
    $stmt = $pdo->prepare("
        SELECT l.*, v.name as supplier_name_ref, v.address as supplier_address, 
               v.phone as supplier_phone, v.email as supplier_email,
               p.name as project_name
        FROM lpos l
        LEFT JOIN vendors v ON l.supplier_id = v.id
        LEFT JOIN projects p ON l.project_id = p.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lpo_id]);
    $lpo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lpo) {
        header('Location: index.php?page=lpos');
        exit;
    }
    
    // Get LPO items
    $itemsStmt = $pdo->prepare("SELECT * FROM lpo_items WHERE lpo_id = ? ORDER BY id");
    $itemsStmt->execute([$lpo_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Error loading LPO: ' . $e->getMessage());
}

// Company details
$companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
$companyAddress = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
$companyPhone = getenv('COMPANY_PHONE') ?: '+947 30659993';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';

// Check for company logo
$logoPath = __DIR__ . '/uploads/company_logo.png';
$logoExists = file_exists($logoPath);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LPO - <?php echo htmlspecialchars($lpo['lpo_number']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
            line-height: 1.4;
            background: white;
        }
        .lpo-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        .company-info {
            flex: 1;
        }
        .company-logo {
            width: 120px;
            height: auto;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 5px;
        }
        .company-details {
            font-size: 12px;
            color: #000;
            line-height: 1.3;
        }
        .lpo-title {
            text-align: center;
            flex: 1;
        }
        .lpo-title h1 {
            font-size: 28px;
            margin: 0;
            color: #000;
            font-weight: bold;
        }
        .lpo-number {
            font-size: 18px;
            color: #000;
            margin-top: 5px;
            font-weight: bold;
        }
        .lpo-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-section {
            background: #f5f5f5;
            padding: 15px;
            border: 1px solid #000;
            border-radius: 5px;
        }
        .info-section h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: bold;
            color: #000;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 100px;
            font-size: 12px;
            color: #000;
        }
        .info-value {
            flex: 1;
            font-size: 12px;
            color: #000;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 12px;
            color: #000;
        }
        .items-table th {
            background-color: #e0e0e0;
            font-weight: bold;
            color: #000;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .text-center {
            text-align: center;
        }
        .totals-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 10px;
            border: 1px solid #000;
            font-size: 12px;
            color: #000;
        }
        .totals-table .label {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: right;
            color: #000;
        }
        .totals-table .amount {
            text-align: right;
            font-weight: bold;
            color: #000;
        }
        .totals-table .grand-total {
            background-color: #2563eb;
            color: white;
            font-size: 14px;
        }
        .terms-section {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #000;
        }
        .terms-section h3 {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .terms-content {
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            margin-top: 50px;
            padding-top: 30px;
        }
        .signature-box {
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 10px;
        }
        .signature-label {
            font-size: 12px;
            font-weight: bold;
        }
        .signature-date {
            font-size: 10px;
            color: #000;
            margin-top: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background: #fff3cd; color: #856404; border: 1px solid #856404; }
        .status-approved { background: #d4edda; color: #155724; border: 1px solid #155724; }
        .status-issued { background: #cce7ff; color: #004085; border: 1px solid #004085; }
        .status-closed { background: #e2e3e5; color: #383d41; border: 1px solid #383d41; }
        
        .no-print {
            margin-bottom: 20px;
            text-align: right;
        }
        
        .print-btn {
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .close-btn {
            background: #6b7280;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        @media print {
            body { 
                margin: 0; 
                padding: 10px; 
                color: #000 !important;
                background: white !important;
            }
            .no-print { display: none !important; }
            * {
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .items-table th,
            .items-table td,
            .totals-table td {
                border: 1px solid #000 !important;
            }
            .info-section {
                border: 1px solid #000 !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">
            Print LPO
        </button>
        <button onclick="window.close()" class="close-btn">
            Close
        </button>
    </div>

    <div class="lpo-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <?php if ($logoExists): ?>
                    <img src="uploads/company_logo.png" alt="Company Logo" class="company-logo">
                <?php endif; ?>
                <div class="company-name"><?php echo htmlspecialchars($companyName); ?></div>
                <div class="company-details">
                    <?php echo nl2br(htmlspecialchars($companyAddress)); ?><br>
                    Phone: <?php echo htmlspecialchars($companyPhone); ?><br>
                    Website: <?php echo htmlspecialchars($companyWebsite); ?>
                </div>
            </div>
            
            <div class="lpo-title">
                <h1>LOCAL PURCHASE ORDER</h1>
                <div class="lpo-number"><?php echo htmlspecialchars($lpo['lpo_number']); ?></div>
                <div style="margin-top: 10px;">
                    <span class="status-badge status-<?php echo $lpo['status']; ?>">
                        <?php echo strtoupper($lpo['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- LPO Information -->
        <div class="lpo-info">
            <div class="info-section">
                <h3>LPO Details</h3>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($lpo['date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Delivery Date:</span>
                    <span class="info-value"><?php echo $lpo['delivery_date'] ? date('F d, Y', strtotime($lpo['delivery_date'])) : 'Not specified'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Terms:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['payment_terms']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Project:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['project_name'] ?: 'Not specified'); ?></span>
                </div>
                <?php if ($lpo['department']): ?>
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['department']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lpo['reference']): ?>
                <div class="info-row">
                    <span class="info-label">Reference:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['reference']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-section">
                <h3>Supplier Details</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['supplier_name_ref'] ?: $lpo['supplier_name']); ?></span>
                </div>
                <?php if ($lpo['supplier_address']): ?>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($lpo['supplier_address'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lpo['supplier_phone']): ?>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['supplier_phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($lpo['supplier_email']): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($lpo['supplier_email']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 40%;">Description</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 10%;">Unit</th>
                    <th style="width: 15%;">Unit Price</th>
                    <th style="width: 15%;">Total</th>
                    <th style="width: 5%;">Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td class="text-center"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                    <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                    <td class="text-right"><?php echo money($item['unit_price']); ?></td>
                    <td class="text-right"><?php echo money($item['total_price']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($item['notes'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount"><?php echo money($lpo['subtotal']); ?></td>
                </tr>
                <?php if ($lpo['discount_amount'] > 0): ?>
                <tr>
                    <td class="label">Discount (<?php echo $lpo['discount_percentage']; ?>%):</td>
                    <td class="amount">-<?php echo money($lpo['discount_amount']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($lpo['tax_amount'] > 0): ?>
                <tr>
                    <td class="label">Tax/VAT (<?php echo $lpo['tax_percentage']; ?>%):</td>
                    <td class="amount"><?php echo money($lpo['tax_amount']); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td class="label">Grand Total:</td>
                    <td class="amount"><?php echo money($lpo['grand_total']); ?></td>
                </tr>
            </table>
        </div>

        <!-- Terms and Conditions -->
        <div class="terms-section">
            <h3>Terms and Conditions:</h3>
            <div class="terms-content">
                <?php if ($lpo['notes']): ?>
                    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($lpo['notes'])); ?></p>
                <?php endif; ?>
                <p>1. Please confirm receipt of this purchase order.</p>
                <p>2. Delivery should be made as per the specified delivery date.</p>
                <p>3. All items should be in good condition and as per specifications.</p>
                <p>4. Payment will be made as per agreed terms.</p>
                <p>5. Any changes to this order must be approved in writing.</p>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Prepared By</div>
                <div class="signature-date"><?php echo htmlspecialchars($lpo['created_by']); ?></div>
                <div class="signature-date"><?php echo date('M d, Y', strtotime($lpo['created_at'])); ?></div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Approved By</div>
                <div class="signature-date">
                    <?php if ($lpo['approved_by']): ?>
                        <?php echo htmlspecialchars($lpo['approved_by']); ?><br>
                        <?php echo date('M d, Y', strtotime($lpo['approved_at'])); ?>
                    <?php else: ?>
                        ___________________
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="signature-box">
                <div class="signature-label">Supplier Acknowledgment</div>
                <div class="signature-date">
                    ___________________
                </div>
            </div>
        </div>
    </div>
</body>
</html>
