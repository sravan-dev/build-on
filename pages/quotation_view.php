<?php
require_once dirname(__DIR__) . '/includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Missing id'); }

$q = $pdo->prepare("SELECT q.*, c.name as client_name, c.address as client_address, p.name as project_name
                    FROM quotations q
                    LEFT JOIN clients c ON q.client_id = c.id
                    LEFT JOIN projects p ON q.project_id = p.id
                    WHERE q.id = ?");
$q->execute([$id]);
$quotation = $q->fetch(PDO::FETCH_ASSOC);
if (!$quotation) { die('Quotation not found'); }

$it = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$it->execute([$id]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

$print = isset($_GET['print']);
$uploadDir = dirname(__DIR__) . '/uploads';

// Printed documents use the full logo lockup (mark + wordmark) when present,
// falling back to whatever company_logo.* the app has.
$logoFs = null;
$logoUrl = null;
$logoCandidates = ['company_logo_full.png'];
foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
    $logoCandidates[] = 'company_logo.' . $ext;
}
foreach ($logoCandidates as $candidate) {
    $testPath = $uploadDir . '/' . $candidate;
    if (file_exists($testPath)) {
        $logoFs = $testPath;
        $logoUrl = '../uploads/' . $candidate;
        break;
    }
}

function envv($k){ return getenv($k) ?: ''; }

$companyName = envv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
$companyAddress = envv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
$companyPhone = envv('COMPANY_PHONE') ?: '+947 30659993';
$companyTollFree = envv('COMPANY_TOLL_FREE') ?: '77721423';
$companyWebsite = envv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';
$currencySymbol = getenv('CURRENCY_SYMBOL') ?: 'ريال';
$currencyCode = getenv('CURRENCY_CODE') ?: 'QAR';
$closingNote = getenv('QUOTE_CLOSING_NOTE') ?: 'We hope that the above quotation is submitted in line with your requirements. If you need any further information, please do not hesitate to contact us. Yours faithfully.';

// Quotation terms print as a starred list, as in the approved format.
$quoteTerms = getenv('QUOTE_TERMS') ?: "Payment type cheque and cash\nQuotation valid only 15 days\nAdvance payment 60%\nMiddle payment 25%\nLast payment after submitting invoice\nAny difference in dimensions and materials apart from this offer will be charged extra";

$validDays = (int) (getenv('QUOTE_VALID_DAYS') ?: 15);
$quoteDateTs = strtotime((string) ($quotation['date'] ?? ''));
$quoteDateLabel = $quoteDateTs ? date('F j, Y', $quoteDateTs) : (string) ($quotation['date'] ?? '');
$validUntilLabel = $quoteDateTs ? date('F j, Y', strtotime('+' . $validDays . ' days', $quoteDateTs)) : '';

$estimateNumber = 'QT-' . str_pad((string) $quotation['id'], 5, '0', STR_PAD_LEFT);

$termsImageFs = $uploadDir . '/terms.jpg';

function amountf($n){
    return number_format((float) $n, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Quotation <?php echo htmlspecialchars($estimateNumber); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    body { background:#f3f4f6; color:#3f4551; }
    .sheet { background:#fff; max-width:8.5in; margin:0 auto; }
    .doc-title { font-size:44px; line-height:1; font-weight:300; letter-spacing:.02em; color:#2f3542; }
    .rule { border-top:1px solid #e5e7eb; }
    .items-head { background:#fbbf5c; color:#3f3a33; }
    .items-head th { font-weight:700; }
    .item-row td { border-bottom:1px solid #eceef1; vertical-align:top; }
    .total-band { background:#f1f2f4; }
    .totals-rule { border-top:1px solid #cbd0d6; }
    .muted { color:#8a9099; }
    @media print {
      .no-print { display:none !important; }
      body { background:#fff; }
      .sheet { box-shadow:none; max-width:none; margin:0; }
      .items-head { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      .total-band { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      @page { size:A4; margin:14mm; }
    }
  </style>
</head>
<body class="py-4 md:py-8">
  <div class="sheet shadow">

    <!-- Action bar (screen only) -->
    <div class="p-6 border-b flex flex-col md:flex-row items-center justify-between gap-4 no-print">
      <h1 class="text-2xl font-bold text-gray-900">Quotation</h1>
      <div class="flex flex-wrap gap-2">
        <a href="?id=<?php echo $quotation['id']; ?>&print=1" class="px-4 py-2 rounded bg-gray-800 text-white text-sm">Print</a>
        <?php if (($quotation['status'] ?? '') === 'approved'): ?>
          <a href="../index.php?page=quotations&convert_to_invoice=<?php echo $quotation['id']; ?>" class="px-4 py-2 rounded bg-green-600 text-white text-sm">Convert to Invoice</a>
        <?php else: ?>
          <button class="px-4 py-2 rounded border text-gray-400 text-sm" disabled title="Convert disabled until quotation is approved">Convert to Invoice</button>
        <?php endif; ?>
        <a href="../index.php?page=quotations" class="px-4 py-2 rounded border text-sm">Back</a>
      </div>
    </div>

    <div class="p-6 md:p-10">
      <!-- Masthead -->
      <div class="flex flex-col md:flex-row justify-between items-start gap-6">
        <div class="w-full md:w-1/2">
          <?php if ($logoFs && file_exists($logoFs)): ?>
            <img src="<?php echo $logoUrl; ?>?t=<?php echo filemtime($logoFs); ?>" alt="Company Logo"
                 class="w-40 md:w-52 h-auto object-contain object-left">
          <?php else: ?>
            <div class="text-xl font-bold"><?php echo htmlspecialchars($companyName); ?></div>
          <?php endif; ?>
        </div>
        <div class="w-full md:w-1/2 text-left md:text-right">
          <div class="doc-title">QUOTATION</div>
          <div class="text-sm muted mt-1">
            Project name : <span class="text-gray-700"><?php echo htmlspecialchars($quotation['project_name'] ?? ''); ?></span>
          </div>
          <div class="mt-5 space-y-0.5">
            <div class="text-sm font-bold text-gray-900"><?php echo strtoupper(htmlspecialchars($companyName)); ?></div>
            <?php foreach (explode("\n", $companyAddress) as $line): ?>
              <?php if (trim($line)): ?>
                <div class="text-sm text-gray-600"><?php echo htmlspecialchars(trim($line)); ?></div>
              <?php endif; ?>
            <?php endforeach; ?>
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

      <!-- Bill to + estimate meta -->
      <?php
      $subtotal = 0;
      foreach ($items as $itRow) { $subtotal += (float) $itRow['total']; }
      $quoteDiscount = (float) ($quotation['discount'] ?? 0);
      // quotations.total_amount is stored net of the discount; rebuild the gross
      // for quotations that carry no line items.
      if (!$items) {
          $subtotal = (float) ($quotation['total_amount'] ?? 0) + $quoteDiscount;
      }
      $grandTotal = max(0, $subtotal - $quoteDiscount);
      ?>
      <div class="flex flex-col md:flex-row justify-between gap-6">
        <div class="w-full md:w-1/2">
          <div class="text-sm muted tracking-wide">BILL TO</div>
          <div class="font-bold text-gray-900 mt-1"><?php echo htmlspecialchars($quotation['client_name'] ?? ''); ?></div>
          <?php if (!empty($quotation['client_address'])): ?>
            <div class="text-sm text-gray-600 mt-3 whitespace-pre-line"><?php echo htmlspecialchars($quotation['client_address']); ?></div>
          <?php endif; ?>
        </div>
        <div class="w-full md:w-1/2">
          <div class="flex justify-between md:justify-end gap-6 py-1 text-sm">
            <div class="font-bold text-gray-800">Estimate Number:</div>
            <div class="text-gray-700 md:w-40 md:text-left"><?php echo htmlspecialchars($estimateNumber); ?></div>
          </div>
          <div class="flex justify-between md:justify-end gap-6 py-1 text-sm">
            <div class="font-bold text-gray-800">Estimate Date:</div>
            <div class="text-gray-700 md:w-40 md:text-left"><?php echo htmlspecialchars($quoteDateLabel); ?></div>
          </div>
          <?php if ($validUntilLabel): ?>
            <div class="flex justify-between md:justify-end gap-6 py-1 text-sm">
              <div class="font-bold text-gray-800">Valid Until:</div>
              <div class="text-gray-700 md:w-40 md:text-left"><?php echo htmlspecialchars($validUntilLabel); ?></div>
            </div>
          <?php endif; ?>
          <div class="total-band flex justify-between md:justify-end gap-6 py-2 px-3 mt-1 text-sm">
            <div class="font-bold text-gray-800">Grand Total (<?php echo htmlspecialchars($currencyCode); ?>):</div>
            <div class="font-bold text-gray-900 md:w-40 md:text-left"><?php echo amountf($grandTotal); ?></div>
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

      <!-- Grand total -->
      <div class="flex justify-end mt-6">
        <div class="w-full md:w-1/2">
          <?php if ($quoteDiscount > 0): ?>
            <div class="flex justify-between gap-6 py-2 text-sm">
              <div class="text-gray-600 flex-1 text-right">Subtotal:</div>
              <div class="text-gray-700 w-32 text-right"><?php echo amountf($subtotal); ?></div>
            </div>
            <div class="flex justify-between gap-6 py-2 text-sm">
              <div class="text-gray-600 flex-1 text-right">Discount:</div>
              <div class="text-red-600 w-32 text-right">-<?php echo amountf($quoteDiscount); ?></div>
            </div>
            <div class="totals-rule mt-1 pt-3 flex justify-between gap-6 text-sm">
              <div class="font-bold text-gray-900 flex-1 text-right">Grand Total (<?php echo htmlspecialchars($currencyCode); ?>):</div>
              <div class="font-bold text-gray-900 w-32 text-right"><?php echo amountf($grandTotal); ?></div>
            </div>
          <?php else: ?>
            <div class="flex justify-between gap-6 py-2 text-sm">
              <div class="font-bold text-gray-900 flex-1 text-right">Grand Total (<?php echo htmlspecialchars($currencyCode); ?>):</div>
              <div class="font-bold text-gray-900 w-32 text-right"><?php echo amountf($grandTotal); ?></div>
            </div>
            <div class="totals-rule"></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Notes / Terms -->
      <div class="mt-12">
        <div class="font-bold text-gray-800 text-sm">Notes / Terms</div>
        <div class="mt-2 space-y-1 text-sm text-gray-600">
          <?php foreach (explode("\n", $quoteTerms) as $term): ?>
            <?php if (trim($term)): ?>
              <div>* <?php echo htmlspecialchars(trim($term)); ?></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php if (file_exists($termsImageFs)): ?>
          <div class="mt-4">
            <img src="../uploads/terms.jpg?t=<?php echo filemtime($termsImageFs); ?>" alt="Terms" class="max-h-48 object-contain">
          </div>
        <?php endif; ?>
      </div>

      <?php $footerText = getenv('QUOTE_FOOTER_TEXT') ?: ''; $footerText = str_replace('\\n', "\n", $footerText); ?>
      <?php if (trim($footerText) !== ''): ?>
        <div class="mt-6 text-sm text-gray-600 whitespace-pre-line"><?php echo htmlspecialchars($footerText); ?></div>
      <?php endif; ?>

      <div class="mt-10 text-center text-sm muted">
        <?php echo htmlspecialchars($closingNote); ?>
      </div>
      <div class="mt-4 text-center text-sm muted">
        Quotation <?php echo htmlspecialchars($estimateNumber); ?>
      </div>
    </div>
  </div>

  <?php if ($print): ?>
  <script>window.print()</script>
  <?php endif; ?>
</body>
</html>
