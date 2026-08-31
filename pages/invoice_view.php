<?php
require_once dirname(__DIR__) . '/includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Missing id'); }

$stmt = $pdo->prepare("SELECT i.*, c.name as client_name, c.address as client_address,
                           COALESCE(dp.name, p.name) as project_name, q.total_amount as quotation_amount
                    FROM invoices i
                    LEFT JOIN clients c ON i.client_id = c.id
                    LEFT JOIN quotations q ON i.quotation_id = q.id
                    LEFT JOIN projects p ON q.project_id = p.id
                    LEFT JOIN projects dp ON i.project_id = dp.id
                    WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) { die('Invoice not found'); }

// Ensure discount value exists (may not exist on older DBs)
$discount = isset($invoice['discount']) ? (float)$invoice['discount'] : 0.0;

$it = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$it->execute([$id]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

// An invoice raised without a quotation is created header-only, so it has no
// invoice_items rows. Print the header amount as a single line instead of an
// empty table with a zero total.
$isSynthesizedLine = false;
$headerGross = (float) ($invoice['gross_amount'] ?? ((float) ($invoice['total_amount'] ?? 0) + $discount));
if (!$items && $headerGross > 0) {
    $gross = $headerGross;
    $items = [[
        'description' => trim((string) ($invoice['lpo_number'] ?? '')) !== ''
            ? 'Works as per LPO ' . $invoice['lpo_number']
            : 'Works as per agreement',
        'quantity' => 1,
        'price' => $gross,
        'total' => $gross,
    ]];
    $isSynthesizedLine = true;
}

$uploadDir = dirname(__DIR__) . '/uploads';

// The printed invoice uses the full logo lockup (mark + wordmark) when it exists,
// falling back to the icon-only logo the rest of the app uses.
$logoFs = $uploadDir . '/company_logo_full.png';
$logoUrl = '../uploads/company_logo_full.png';
if (!file_exists($logoFs)) {
    $logoFs = $uploadDir . '/company_logo.png';
    $logoUrl = '../uploads/company_logo.png';
}

$companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
$companyAddress = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
$companyPhone = getenv('COMPANY_PHONE') ?: '+947 30659993';
$companyTollFree = getenv('COMPANY_TOLL_FREE') ?: '77721423';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';
$currencySymbol = getenv('CURRENCY_SYMBOL') ?: 'ريال';
$currencyCode = getenv('CURRENCY_CODE') ?: 'QAR';

// Bank / payment details printed under Notes / Terms.
$bankPaymentType = getenv('BANK_PAYMENT_TYPE') ?: 'Cash or Cheque';
$bankAccountNo = getenv('BANK_ACCOUNT_NO') ?: '';
$bankName = getenv('BANK_NAME') ?: '';
$bankSwift = getenv('BANK_SWIFT_CODE') ?: '';
$bankAddress = getenv('BANK_ADDRESS') ?: '';
$bankIban = getenv('BANK_IBAN') ?: '';
$closingNote = getenv('INVOICE_CLOSING_NOTE') ?: 'We hope that the above invoice is submitted in line with your requirements. If you need any further information, please do not hesitate to contact us. Yours faithfully.';

// Payment due date: the invoice date plus the agreed credit period.
$dueDays = (int) (getenv('INVOICE_DUE_DAYS') ?: 15);
$invoiceDateTs = strtotime((string) ($invoice['date'] ?? ''));
$invoiceDateLabel = $invoiceDateTs ? date('F j, Y', $invoiceDateTs) : (string) ($invoice['date'] ?? '');
$dueDateLabel = $invoiceDateTs ? date('F j, Y', strtotime('+' . $dueDays . ' days', $invoiceDateTs)) : '';

// The reference shown to the customer: the LPO when there is one, else the id.
$invoiceNumber = trim((string) ($invoice['lpo_number'] ?? '')) !== ''
    ? $invoice['lpo_number']
    : 'INV-' . str_pad((string) $invoice['id'], 5, '0', STR_PAD_LEFT);

$paidAmount = (float) ($invoice['paid_amount'] ?? 0);

function moneyf($n, $symbol = null){
    global $currencySymbol;
    $symbol = $symbol ?: $currencySymbol;
    return $symbol . ' ' . number_format($n,2);
}

// The sample layout prints bare figures and names the currency in the column and
// total labels instead, which keeps long Arabic-script symbols out of the table.
function amountf($n){
    return number_format((float) $n, 2);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Invoice <?php echo htmlspecialchars($invoiceNumber); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    body { background:#f3f4f6; color:#3f4551; }
    .sheet { background:#fff; max-width:8.5in; margin:0 auto; }
    .doc-title { font-size:44px; line-height:1; font-weight:300; letter-spacing:.02em; color:#2f3542; }
    .rule { border-top:1px solid #e5e7eb; }
    .items-head { background:#fbbf5c; color:#3f3a33; }
    .items-head th { font-weight:700; }
    .item-row td { border-bottom:1px solid #eceef1; vertical-align:top; }
    .amount-due-band { background:#f1f2f4; }
    .totals-rule { border-top:1px solid #cbd0d6; }
    .muted { color:#8a9099; }
    @media print {
      .no-print { display:none }
      body { background:#fff; }
      .sheet { box-shadow:none; max-width:none; margin:0; }
      .items-head { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      .amount-due-band { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      @page { size:A4; margin:14mm; }
    }
  </style>
</head>
<body class="py-4 md:py-8">
  <div class="sheet shadow p-6 md:p-10">

    <!-- Masthead: logo left, document title and company block right -->
    <div class="flex flex-col md:flex-row justify-between items-start gap-6">
      <div class="w-full md:w-1/2">
        <?php if (file_exists($logoFs)): ?>
          <img src="<?php echo $logoUrl; ?>?t=<?php echo time(); ?>" alt="Logo" class="w-40 md:w-52 h-auto object-contain object-left">
        <?php else: ?>
          <div class="text-xl font-bold"><?php echo htmlspecialchars($companyName ?: 'Company'); ?></div>
        <?php endif; ?>
      </div>
      <div class="w-full md:w-1/2 text-left md:text-right">
        <div class="doc-title">INVOICE</div>
        <div class="text-sm muted mt-1">
          Project name : <span class="text-gray-700"><?php echo htmlspecialchars($invoice['project_name'] ?? ''); ?></span>
        </div>
        <div class="mt-5 space-y-0.5">
          <?php if ($companyName): ?>
            <div class="text-sm font-bold text-gray-900"><?php echo strtoupper(htmlspecialchars($companyName)); ?></div>
          <?php endif; ?>
          <?php
          if ($companyAddress):
              foreach (explode("\n", $companyAddress) as $line):
                  if (trim($line)): ?>
                      <div class="text-sm text-gray-600"><?php echo htmlspecialchars(trim($line)); ?></div>
                  <?php endif;
              endforeach;
          endif; ?>
        </div>
        <div class="mt-4 space-y-0.5">
          <?php if ($companyPhone): ?>
            <div class="text-sm text-gray-600">Mobile: <?php echo htmlspecialchars($companyPhone); ?></div>
          <?php endif; ?>
          <?php if ($companyTollFree): ?>
            <div class="text-sm text-gray-600">Toll free: <?php echo htmlspecialchars($companyTollFree); ?></div>
          <?php endif; ?>
          <?php if ($companyWebsite): ?>
            <div class="text-sm text-gray-600"><?php echo htmlspecialchars($companyWebsite); ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="rule mt-6 mb-5"></div>

    <!-- Bill to + invoice meta -->
    <div class="flex flex-col md:flex-row justify-between gap-6">
      <div class="w-full md:w-1/2">
        <div class="text-sm muted tracking-wide">BILL TO</div>
        <div class="font-bold text-gray-900 mt-1"><?php echo htmlspecialchars($invoice['client_name'] ?? ''); ?></div>
        <?php if (!empty($invoice['client_address'])): ?>
          <div class="text-sm text-gray-600 mt-3 whitespace-pre-line"><?php echo htmlspecialchars($invoice['client_address']); ?></div>
        <?php endif; ?>
      </div>
      <div class="w-full md:w-1/2">
        <div class="flex justify-between md:justify-end gap-6 py-1 text-sm">
          <div class="font-bold text-gray-800">Invoice Number:</div>
          <div class="text-gray-700 md:w-40 md:text-left"><?php echo htmlspecialchars($invoiceNumber); ?></div>
        </div>
        <div class="flex justify-between md:justify-end gap-6 py-1 text-sm">
          <div class="font-bold text-gray-800">Invoice Date:</div>
          <div class="text-gray-700 md:w-40 md:text-left"><?php echo htmlspecialchars($invoiceDateLabel); ?></div>
        </div>
        <?php if ($dueDateLabel): ?>
          <div class="flex justify-between md:justify-end gap-6 py-1 text-sm">
            <div class="font-bold text-gray-800">Payment Due:</div>
            <div class="text-gray-700 md:w-40 md:text-left"><?php echo htmlspecialchars($dueDateLabel); ?></div>
          </div>
        <?php endif; ?>
        <?php
        $subtotal = 0;
        foreach ($items as $itRow) { $subtotal += (float) $itRow['total']; }
        $documentTotal = max(0, $subtotal - $discount);
        $amountDue = max(0, $documentTotal - $paidAmount);
        ?>
        <div class="amount-due-band flex justify-between md:justify-end gap-6 py-2 px-3 mt-1 text-sm">
          <div class="font-bold text-gray-800">Amount Due (<?php echo htmlspecialchars($currencyCode); ?>):</div>
          <div class="font-bold text-gray-900 md:w-40 md:text-left"><?php echo amountf($amountDue); ?></div>
        </div>
      </div>
    </div>

    <!-- Line items -->
    <div class="overflow-x-auto mt-8">
      <table class="w-full text-sm min-w-full">
        <thead class="items-head">
          <tr>
            <th class="text-left px-4 py-3">Items</th>
            <th class="text-center px-4 py-3 w-28">Quantity</th>
            <th class="text-right px-4 py-3 w-32">Price</th>
            <th class="text-right px-4 py-3 w-32">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
          <tr class="item-row">
            <td class="px-4 py-4 text-gray-700"><?php echo nl2br(htmlspecialchars($it['description'])); ?></td>
            <td class="px-4 py-4 text-center text-gray-700"><?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.')); ?></td>
            <td class="px-4 py-4 text-right text-gray-700"><?php echo amountf($it['price']); ?></td>
            <td class="px-4 py-4 text-right text-gray-700"><?php echo amountf($it['total']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="flex justify-end mt-6">
      <div class="w-full md:w-1/2">
        <?php if ($discount > 0): ?>
          <div class="flex justify-between gap-6 py-2 text-sm">
            <div class="text-gray-600 flex-1 text-right">Subtotal:</div>
            <div class="text-gray-700 w-32 text-right"><?php echo amountf($subtotal); ?></div>
          </div>
          <div class="flex justify-between gap-6 py-2 text-sm">
            <div class="text-gray-600 flex-1 text-right">Discount:</div>
            <div class="text-red-600 w-32 text-right">-<?php echo amountf($discount); ?></div>
          </div>
        <?php endif; ?>
        <div class="flex justify-between gap-6 py-2 text-sm">
          <div class="font-bold text-gray-800 flex-1 text-right">Total:</div>
          <div class="text-gray-800 w-32 text-right"><?php echo amountf($documentTotal); ?></div>
        </div>
        <?php if ($paidAmount > 0): ?>
          <div class="flex justify-between gap-6 py-2 text-sm">
            <div class="text-gray-600 flex-1 text-right">Paid:</div>
            <div class="text-gray-700 w-32 text-right">-<?php echo amountf($paidAmount); ?></div>
          </div>
        <?php endif; ?>
        <div class="totals-rule mt-1 pt-3 flex justify-between gap-6 text-sm">
          <div class="font-bold text-gray-900 flex-1 text-right">Amount Due (<?php echo htmlspecialchars($currencyCode); ?>):</div>
          <div class="font-bold text-gray-900 w-32 text-right"><?php echo amountf($amountDue); ?></div>
        </div>
      </div>
    </div>

    <!-- Notes / Terms -->
    <div class="mt-12">
      <div class="font-bold text-gray-800 text-sm">Notes / Terms</div>
      <div class="mt-2 space-y-1 text-sm text-gray-600">
        <?php if ($bankPaymentType): ?><div>Payment Type : <?php echo htmlspecialchars($bankPaymentType); ?></div><?php endif; ?>
        <?php if ($bankAccountNo): ?><div>Bank Account no. : <?php echo htmlspecialchars($bankAccountNo); ?></div><?php endif; ?>
        <?php if ($bankName): ?><div>Bank Name : <?php echo htmlspecialchars($bankName); ?></div><?php endif; ?>
        <?php if ($bankSwift): ?><div>Swift Code : <?php echo htmlspecialchars($bankSwift); ?></div><?php endif; ?>
        <?php if ($bankAddress): ?><div>Bank Address : <?php echo htmlspecialchars($bankAddress); ?></div><?php endif; ?>
        <div>Currency : <?php echo htmlspecialchars($currencyCode); ?></div>
        <?php if ($bankIban): ?><div>IBAN : <?php echo htmlspecialchars($bankIban); ?></div><?php endif; ?>
      </div>
    </div>

    <div class="mt-10 text-center text-sm muted">
      <?php echo htmlspecialchars($closingNote); ?>
    </div>
    <div class="mt-4 text-center text-sm muted">
      Invoice <?php echo htmlspecialchars($invoiceNumber); ?>
    </div>

    <div class="mt-8 text-center md:text-right no-print">
      <button onclick="window.print()" class="w-full md:w-auto px-4 py-2 bg-gray-800 text-white rounded text-sm">Print</button>
      <a href="../index.php?page=invoices" class="w-full md:w-auto ml-0 md:ml-2 mt-2 md:mt-0 inline-block px-4 py-2 border rounded text-center text-sm">Back</a>
    </div>
  </div>
</body>
</html>
