<?php


// Ensure uploads directory exists for logo
$uploadDir = dirname(__DIR__) . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}
$companyLogoPath = $uploadDir . '/company_logo.png';
$companyLogoUrl = 'uploads/company_logo.png';

$quotations = $pdo->query("SELECT q.*, c.name as client_name, p.name as project_name, (SELECT id FROM invoices WHERE quotation_id = q.id LIMIT 1) as invoice_id FROM quotations q LEFT JOIN clients c ON q.client_id = c.id LEFT JOIN projects p ON q.project_id = p.id")->fetchAll();
$currencySym = function_exists('currency_symbol') ? currency_symbol() : (getenv('CURRENCY_SYMBOL') ?: '$');

$clients = $pdo->query("SELECT id, name FROM clients")->fetchAll();

$projects = $pdo->query("SELECT id, name FROM projects")->fetchAll();
$vendors = $pdo->query("SELECT id, name, contact, email, phone FROM vendors ORDER BY name ASC")->fetchAll();
$newClientId = isset($_GET['new_client_id']) ? intval($_GET['new_client_id']) : null;

// Prefill state for duplicate → opens add form with items
$duplicateQuote = null;
$duplicateItems = [];
$editQuote = null;
$editItems = [];
$prefillClientId = null;
$prefillProjectId = null;
$prefillDate = null;
$showAddForm = false;
$isEditMode = false;
if (isset($_GET['duplicate'])) {
    $showAddForm = true;
    $dupId = (int) $_GET['duplicate'];
    $q = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $q->execute([$dupId]);
    $duplicateQuote = $q->fetch(PDO::FETCH_ASSOC);
    if ($duplicateQuote) {
        $prefillClientId = $duplicateQuote['client_id'] ?? null;
        $prefillProjectId = $duplicateQuote['project_id'] ?? null;
        $prefillDate = $duplicateQuote['date'] ?? null;
        $it = $pdo->prepare("SELECT description, quantity, price FROM quotation_items WHERE quotation_id = ?");
        $it->execute([$dupId]);
        $duplicateItems = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// Edit mode: open full composer with existing data
if (isset($_GET['edit'])) {
    $showAddForm = true;
    $isEditMode = true;
    $editId = (int) $_GET['edit'];
    $q = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $q->execute([$editId]);
    $editQuote = $q->fetch(PDO::FETCH_ASSOC);
    if ($editQuote) {
        $prefillClientId = $editQuote['client_id'] ?? null;
        $prefillProjectId = $editQuote['project_id'] ?? null;
        $prefillDate = $editQuote['date'] ?? null;
        $it = $pdo->prepare("SELECT description, quantity, price FROM quotation_items WHERE quotation_id = ?");
        $it->execute([$editId]);
        $editItems = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// Decide which set of items to prefill (duplicate or edit)
$prefillItems = !empty($editItems) ? $editItems : $duplicateItems;

?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Quotations</h1>
    <p class="text-gray-600 mt-2">Manage project quotations and track their approval status</p>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-900">Quotation Management</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                        class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="openQuickClientModal()">
                        <i class="fas fa-user-plus mr-1 md:mr-2"></i>Add Client
                    </button>
                    <button type="button"
                        class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="openQuickProjectModal()">
                        <i class="fas fa-folder-plus mr-1 md:mr-2"></i>Add Project
                    </button>
                    <button
                        class="bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors"
                        onclick="document.getElementById('addForm').classList.toggle('hidden')">
                        <i class="fas fa-plus mr-1 md:mr-2"></i>Add Quotation
                    </button>
                </div>
            </div>
        </div>

        <div id="addForm" class="<?php echo $showAddForm ? '' : 'hidden '; ?>p-6 border-b bg-gray-50">
            <form method="post" enctype="multipart/form-data" class="space-y-6" id="quotationAddForm">
                <!-- Header: business, title, summary, and logo -->
                <div class="border rounded-xl bg-white">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="font-semibold text-gray-700">Business address and contact details, title, summary,
                            and logo</div>
                        <button type="button" class="text-gray-500"
                            onclick="this.closest('.border').classList.toggle('collapsed')"><i
                                class="fas fa-angle-down"></i></button>
                    </div>
                    <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                        <div>
                            <div
                                class="border rounded-lg p-4 flex items-center justify-center min-h-[150px] bg-gray-50">
                                <?php if (file_exists($companyLogoPath)): ?>
                                    <img src="<?php echo $companyLogoUrl; ?>?t=<?php echo time(); ?>" alt="Company Logo"
                                        class="max-h-32 object-contain">
                                <?php else: ?>
                                    <span class="text-gray-400">No logo uploaded</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 flex items-center space-x-3">
                                <input type="file" name="company_logo" accept="image/*" class="text-sm">
                                <?php if (file_exists($companyLogoPath)): ?>
                                    <button name="remove_logo" value="1" class="text-primary text-sm"
                                        onclick="return confirm('Remove current logo?')">Remove logo</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="lg:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
                                    <input type="text" class="w-full px-3 py-2 border rounded-md" value="Quotation"
                                        readonly>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Project name :</label>
                                    <input type="text" class="w-full px-3 py-2 border rounded-md"
                                        placeholder="Optional">
                                </div>
                            </div>
                            <?php
                            $cn = getenv('COMPANY_NAME') ?: '';
                            $ca = getenv('COMPANY_ADDRESS') ?: '';
                            $cp = getenv('COMPANY_PHONE') ?: '';
                            $ct = getenv('COMPANY_TOLL_FREE') ?: '';
                            $cw = getenv('COMPANY_WEBSITE') ?: '';
                            ?>
                            <div class="mt-4 text-center">
                                <div class="font-semibold text-gray-900 uppercase"><?php echo htmlspecialchars($cn); ?>
                                </div>
                                <div class="text-gray-700 whitespace-pre-line">
                                    <?php echo nl2br(htmlspecialchars($ca)); ?>
                                </div>
                                <?php if ($cp): ?>
                                    <div class="text-gray-700">Mobile: <?php echo htmlspecialchars($cp); ?></div>
                                <?php endif; ?>
                                <?php if ($ct): ?>
                                    <div class="text-gray-700">Toll free: <?php echo htmlspecialchars($ct); ?></div>
                                <?php endif; ?>
                                <?php if ($cw): ?>
                                    <div class="text-gray-700"><?php echo htmlspecialchars($cw); ?></div><?php endif; ?>
                                <div class="mt-2"><a class="text-primary text-sm" href="index.php?page=settings">Edit
                                        your business address and contact details</a></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-1">
                        <div class="border rounded-xl bg-white p-4 h-full">
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-semibold text-gray-700">Customer</div>
                                <button type="button" class="text-primary text-sm" onclick="openVendorModal()">+ Add
                                    customer from Vendors</button>
                            </div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Customer</label>
                            <select id="client_id" name="client_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                required>
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo (($newClientId && (int) $client['id'] === $newClientId) || ($prefillClientId && (int) $client['id'] === $prefillClientId)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label class="block text-sm font-medium text-gray-700 mt-4 mb-1">Project</label>
                            <select name="project_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" <?php echo ($prefillProjectId && (int) $project['id'] === $prefillProjectId) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estimate number</label>
                            <input type="text" id="estimate_no"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer ref</label>
                            <input type="text" id="customer_ref"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="#optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="date" id="q_date"
                                value="<?php echo htmlspecialchars($prefillDate ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valid until</label>
                            <input type="date" id="valid_until"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <p class="text-xs text-gray-500">Within 15 days</p>
                        </div>
                    </div>
                </div>

                <div class="border rounded-xl bg-white">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="font-semibold text-gray-700">Items</div>
                        <button type="button" class="text-primary text-sm" onclick="addItemRow()">+ Add item</button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-500">
                                <tr>
                                    <th class="text-left pb-2">Description</th>
                                    <th class="text-right pb-2 w-28">Qty</th>
                                    <th class="text-right pb-2 w-32">Price</th>
                                    <th class="text-right pb-2 w-32">Amount</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody"></tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-t">
                        <div class="flex items-center justify-end space-x-6">
                            <div class="flex items-center space-x-2">
                                <label class="text-sm text-gray-700">Discount</label>
                                <input type="number" step="0.01" name="discount" id="discount" value="0"
                                    class="w-28 px-2 py-1 border rounded text-right">
                            </div>
                            <div class="hidden md:flex items-center space-x-2">
                                <label class="text-sm text-gray-700">Total</label>
                                <select id="currency" class="px-2 py-1 border rounded text-sm">
                                    <option>QAR (﷼) - Qatari riyal</option>
                                </select>
                            </div>
                            <div class="text-sm text-gray-600">Subtotal: <span id="subtotal"
                                    class="font-semibold text-gray-900">0.00</span></div>
                            <div class="text-sm text-gray-600">Total: <span id="total"
                                    class="font-semibold text-gray-900">0.00</span></div>
                        </div>
                    </div>
                </div>

                <!-- Footer / Notes section -->
                <div class="border rounded-xl bg-white">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="font-semibold text-gray-700">Footer</div>
                        <button type="button" class="text-gray-500"
                            onclick="this.closest('.border').classList.toggle('collapsed')"><i
                                class="fas fa-angle-up"></i></button>
                    </div>
                    <div class="p-4">
                        <div class="mb-4">
                            <div class="px-4 py-3 bg-gray-50 border rounded font-semibold text-gray-700">Notes / Terms
                            </div>
                            <div class="p-4 text-gray-800 whitespace-pre-line border rounded mt-2">
                                Payment type cheque and cash
                                Quotation valid only 15 days
                                Advance payment 60%
                                Middle payment 25%
                                Last payment after submitting invoice
                                Any difference in dimensions and materials apart from this offer will be charged extra
                            </div>
                        </div>
                        <?php
                        $defaultFooter = getenv('QUOTE_FOOTER_TEXT') ?: 'We hope that the above quotation is submitted in line with your requirements. If you need any further information, please do not hesitate to contact us. Yours faithfully.';
                        $defaultFooter = str_replace('\\n', "\n", $defaultFooter);
                        $termsMarker = '';
                        if (strpos($defaultFooter, $termsMarker) === false) {
                            $defaultFooter = rtrim($defaultFooter) . "\n" . $termsMarker;
                        }
                        ?>
                        <textarea name="footer_text" rows="3" class="w-full px-3 py-2 border rounded-md"
                            placeholder="Enter footer text..."><?php echo htmlspecialchars($defaultFooter, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                        onclick="document.getElementById('addForm').classList.add('hidden')">Cancel</button>
                    <?php if ($isEditMode && isset($editId) && $editId): ?>
                        <input type="hidden" name="edit_id" value="<?php echo (int) $editId; ?>">
                        <button type="submit" name="add"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                            <i class="fas fa-save mr-2"></i>Update Quotation
                        </button>
                    <?php else: ?>
                        <button type="submit" name="add"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                            <i class="fas fa-save mr-2"></i>Add Quotation
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Client ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Discount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($quotations as $quotation): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-mono text-gray-500">
                                        <?php echo !empty($quotation['client_id']) ? '#' . (int) $quotation['client_id'] : '<span class="text-gray-400">&mdash;</span>'; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($quotation['client_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="text-gray-900">
                                        <?php echo htmlspecialchars($quotation['project_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($quotation['date'])); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    switch ($quotation['status']) {
                                        case 'approved':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'rejected':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                        <?php echo ucfirst($quotation['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <?php if ((float) ($quotation['discount'] ?? 0) > 0): ?>
                                        <span class="text-red-600 font-medium">-<?php echo money($quotation['discount']); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo money($quotation['total_amount']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-1">
                                        <a href="pages/quotation_view.php?id=<?php echo $quotation['id']; ?>"
                                            class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="pages/quotation_view.php?id=<?php echo $quotation['id']; ?>&print=1"
                                            target="_blank" class="text-green-600 hover:text-green-900" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="?page=quotations&duplicate=<?php echo $quotation['id']; ?>"
                                            class="text-purple-600 hover:text-purple-900" title="Duplicate"
                                            onclick="return confirm('Duplicate this quotation?')">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        <a href="?page=quotations&edit=<?php echo $quotation['id']; ?>"
                                            class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (($quotation['status'] ?? '') === 'approved'): ?>
                                            <?php if (!empty($quotation['invoice_id'])): ?>
                                                <span class="text-gray-400 cursor-not-allowed" title="Already Converted to Invoice">
                                                    <i class="fas fa-file-invoice"></i>
                                                </span>
                                            <?php else: ?>
                                                <a href="?page=quotations&convert_to_invoice=<?php echo $quotation['id']; ?>"
                                                    class="text-green-600 hover:text-green-800" title="Convert to Invoice"
                                                    onclick="return confirm('Convert this approved quotation to an invoice?')">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="?page=quotations&approve=<?php echo $quotation['id']; ?>"
                                                class="text-green-600 hover:text-green-900" title="Approve"
                                                onclick="return confirm('Approve this quotation?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?page=quotations&delete=<?php echo $quotation['id']; ?>"
                                            class="text-red-600 hover:text-red-900" title="Delete"
                                            onclick="return confirm('Are you sure you want to delete this quotation?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="editModal" class="hidden fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form method="post">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Quotation</h3>
                            <input type="hidden" id="edit-id" name="id">
                            <div class="mb-4">
                                <label for="edit-client_id"
                                    class="block text-sm font-medium text-gray-700">Client</label>
                                <select name="client_id" id="edit-client_id"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>"><?php echo $client['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="edit-project_id"
                                    class="block text-sm font-medium text-gray-700">Project</label>
                                <select name="project_id" id="edit-project_id"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>"><?php echo $project['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="edit-date" class="block text-sm font-medium text-gray-700">Date</label>
                                <input type="date" name="date" id="edit-date"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-total_amount" class="block text-sm font-medium text-gray-700">Total
                                    Amount</label>
                                <input type="number" step="0.01" name="total_amount" id="edit-total_amount"
                                    class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div class="mb-4">
                                <label for="edit-status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="edit-status"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" name="update"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update
                            </button>
                            <button type="button" id="convertToInvoiceBtn" onclick="convertToInvoiceFromModal()"
                                disabled
                                class="mt-3 ml-3 inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-200 text-base font-medium text-gray-700 hover:bg-gray-300 sm:ml-3 sm:w-auto sm:text-sm">
                                Convert to Invoice
                            </button>
                            <button type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                onclick="closeEditModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            // Prefill meta
            // Duplicate payload from server (if present)
            window.prefillItems = <?php echo json_encode($prefillItems); ?>;
            (function () {
                const d = new Date();
                const pad = n => String(n).padStart(2, '0');
                const today = d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
                const valid = new Date(d.getTime() + 15 * 24 * 60 * 60 * 1000);
                const validStr = valid.getFullYear() + "-" + pad(valid.getMonth() + 1) + "-" + pad(valid.getDate());
                const est = 'QBU' + d.getFullYear().toString().slice(-2) + pad(d.getMonth() + 1) + pad(d.getDate()) + '-' + Math.floor(Math.random() * 90 + 10);
                if (document.getElementById('q_date') && !document.getElementById('q_date').value) document.getElementById('q_date').value = today;
                if (document.getElementById('valid_until')) document.getElementById('valid_until').value = validStr;
                if (document.getElementById('estimate_no')) document.getElementById('estimate_no').value = est;
            })();

            function currency(n) { return (Number(n) || 0).toFixed(2) }
            function recalc() {
                let subtotal = 0;
                document.querySelectorAll('#itemsBody tr').forEach(tr => {
                    const q = parseFloat(tr.querySelector('.it-qty')?.value || '0');
                    const p = parseFloat(tr.querySelector('.it-price')?.value || '0');
                    const amt = q * p; subtotal += amt;
                    const el = tr.querySelector('.it-amount'); if (el) el.textContent = currency(amt);
                });
                const subEl = document.getElementById('subtotal'); if (subEl) subEl.textContent = currency(subtotal);
                const disc = parseFloat(document.getElementById('discount')?.value || '0');
                const totEl = document.getElementById('total'); if (totEl) totEl.textContent = currency(Math.max(0, subtotal - disc));
            }
            function addItemRow(desc = '', qty = '', price = '') {
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td class="py-1 pr-2"><input name="item_description[]" value="${desc}" class="w-full px-2 py-1 border rounded" placeholder="Description"></td>
            <td class="py-1 pr-2 text-right"><input name="item_quantity[]" value="${qty}" type="number" step="0.01" class="it-qty w-24 px-2 py-1 border rounded text-right"></td>
            <td class="py-1 pr-2 text-right"><input name="item_price[]" value="${price}" type="number" step="0.01" class="it-price w-28 px-2 py-1 border rounded text-right"></td>
            <td class="py-1 pr-2 text-right"><span class="it-amount">0.00</span></td>
            <td class="py-1 text-right"><button type="button" class="text-red-600 hover:text-red-800 flex items-center space-x-1" onclick="this.closest('tr').remove(); recalc();"><i class="fas fa-trash"></i><span class="hidden sm:inline">Remove</span></button></td>`;
                document.getElementById('itemsBody')?.appendChild(tr);
                tr.querySelector('.it-qty')?.addEventListener('input', recalc);
                tr.querySelector('.it-price')?.addEventListener('input', recalc);
                recalc();
            }
            document.getElementById('discount')?.addEventListener('input', recalc);
            if (document.getElementById('itemsBody')) {
                if (Array.isArray(window.prefillItems) && window.prefillItems.length) {
                    window.prefillItems.forEach(function (it) {
                        addItemRow(it.description || '', it.quantity || '', it.price || '');
                    });
                } else {
                    addItemRow(); addItemRow(); addItemRow(); addItemRow();
                }
            }

            function openVendorModal() { document.getElementById('vendorModal')?.classList.remove('hidden'); }
            function closeVendorModal() { document.getElementById('vendorModal')?.classList.add('hidden'); }
            window.addItemRow = addItemRow; window.openVendorModal = openVendorModal; window.closeVendorModal = closeVendorModal;

            function editQuotation(quotation) {
                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('edit-id').value = quotation.id;
                document.getElementById('edit-client_id').value = quotation.client_id;
                document.getElementById('edit-project_id').value = quotation.project_id;
                document.getElementById('edit-date').value = quotation.date;
                document.getElementById('edit-total_amount').value = quotation.total_amount;
                document.getElementById('edit-status').value = quotation.status;
                // enable/disable convert button based on status
                const btn = document.getElementById('convertToInvoiceBtn');
                if (btn) {
                    btn.disabled = !(quotation.status && quotation.status === 'approved');
                    btn.dataset.qid = quotation.id;
                }
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
            }

            function convertToInvoiceFromModal() {
                const btn = document.getElementById('convertToInvoiceBtn');
                if (!btn) return;
                const qid = btn.dataset.qid;
                if (!qid) return alert('Quotation id missing');
                if (!confirm('Convert this approved quotation to an invoice?')) return;
                // navigate to convert handler
                window.location = 'index.php?page=quotations&convert_to_invoice=' + encodeURIComponent(qid);
            }

        </script>

        <!-- Quick Add Client Modal -->
        <div id="quickClientModal" class="hidden fixed z-30 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeQuickClientModal()"></div>
                </div>
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="font-semibold text-gray-800">Add Client</div>
                        <button type="button" class="text-gray-500" onclick="closeQuickClientModal()"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client Name <span class="text-red-500">*</span></label>
                            <input type="text" id="quick-client-name" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Company or person">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                                <input type="text" id="quick-client-contact" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" id="quick-client-phone" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="quick-client-email" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea id="quick-client-address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        <p id="quick-client-error" class="hidden text-sm text-red-600"></p>
                    </div>
                    <div class="px-4 py-3 border-t flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 bg-white border rounded-md" onclick="closeQuickClientModal()">Cancel</button>
                        <button type="button" id="quick-client-save" class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-md" onclick="saveQuickClient()">Save Client</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Add Project Modal -->
        <div id="quickProjectModal" class="hidden fixed z-30 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeQuickProjectModal()"></div>
                </div>
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="font-semibold text-gray-800">Add Project</div>
                        <button type="button" class="text-gray-500" onclick="closeQuickProjectModal()"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Project Name <span class="text-red-500">*</span></label>
                            <input type="text" id="quick-project-name" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g. Villa 54 Fit-out">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                            <select id="quick-project-client" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">No Client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">#<?php echo (int) $client['id']; ?> &mdash; <?php echo htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" id="quick-project-start" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <p id="quick-project-error" class="hidden text-sm text-red-600"></p>
                    </div>
                    <div class="px-4 py-3 border-t flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 bg-white border rounded-md" onclick="closeQuickProjectModal()">Cancel</button>
                        <button type="button" id="quick-project-save" class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-md" onclick="saveQuickProject()">Save Project</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function openQuickClientModal() { document.getElementById('quickClientModal').classList.remove('hidden'); document.getElementById('quick-client-name').focus(); }
            function closeQuickClientModal() { document.getElementById('quickClientModal').classList.add('hidden'); }
            function openQuickProjectModal() { document.getElementById('quickProjectModal').classList.remove('hidden'); document.getElementById('quick-project-name').focus(); }
            function closeQuickProjectModal() { document.getElementById('quickProjectModal').classList.add('hidden'); }

            function showQuickError(id, message) {
                const el = document.getElementById(id);
                el.textContent = message;
                el.classList.remove('hidden');
            }

            // Add the new record to every select that lists it, and select it there.
            function addOptionToSelects(selectors, value, label) {
                selectors.forEach(function (selector) {
                    document.querySelectorAll(selector).forEach(function (select) {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = label;
                        select.appendChild(option);
                        select.value = value;
                    });
                });
            }

            async function postJson(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json().catch(function () { return {}; });
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Request failed');
                }
                return data;
            }

            async function saveQuickClient() {
                const button = document.getElementById('quick-client-save');
                const name = document.getElementById('quick-client-name').value.trim();
                document.getElementById('quick-client-error').classList.add('hidden');
                if (!name) {
                    showQuickError('quick-client-error', 'Client name is required.');
                    return;
                }
                button.disabled = true;
                try {
                    const data = await postJson('ajax/add_client.php', {
                        name: name,
                        contact: document.getElementById('quick-client-contact').value.trim(),
                        phone: document.getElementById('quick-client-phone').value.trim(),
                        email: document.getElementById('quick-client-email').value.trim(),
                        address: document.getElementById('quick-client-address').value.trim()
                    });
                    addOptionToSelects(['#client_id', '#edit-client_id'], data.client.id, data.client.name);
                    const projectClient = document.getElementById('quick-project-client');
                    if (projectClient) {
                        const option = document.createElement('option');
                        option.value = data.client.id;
                        option.textContent = '#' + data.client.id + ' \u2014 ' + data.client.name;
                        projectClient.appendChild(option);
                    }
                    closeQuickClientModal();
                    document.getElementById('addForm').classList.remove('hidden');
                } catch (error) {
                    showQuickError('quick-client-error', error.message);
                } finally {
                    button.disabled = false;
                }
            }

            async function saveQuickProject() {
                const button = document.getElementById('quick-project-save');
                const name = document.getElementById('quick-project-name').value.trim();
                document.getElementById('quick-project-error').classList.add('hidden');
                if (!name) {
                    showQuickError('quick-project-error', 'Project name is required.');
                    return;
                }
                button.disabled = true;
                try {
                    const data = await postJson('ajax/add_project.php', {
                        name: name,
                        client_id: document.getElementById('quick-project-client').value,
                        start_date: document.getElementById('quick-project-start').value
                    });
                    addOptionToSelects(['select[name="project_id"]'], data.project.id, data.project.name);
                    closeQuickProjectModal();
                    document.getElementById('addForm').classList.remove('hidden');
                } catch (error) {
                    showQuickError('quick-project-error', error.message);
                } finally {
                    button.disabled = false;
                }
            }
        </script>

        <!-- Vendor -> Customer Modal -->
        <div id="vendorModal" class="hidden fixed z-20 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeVendorModal()"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Add customer from Vendors</h3>
                        <button class="text-gray-500" onclick="closeVendorModal()"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="p-6 max-h-[60vh] overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-500">
                                <tr>
                                    <th class="text-left pb-2">Vendor</th>
                                    <th class="text-left pb-2">Contact</th>
                                    <th class="text-left pb-2">Email</th>
                                    <th class="text-left pb-2">Phone</th>
                                    <th class="w-32"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $v): ?>
                                    <tr class="border-t">
                                        <td class="py-2"><?php echo htmlspecialchars($v['name']); ?></td>
                                        <td class="py-2"><?php echo htmlspecialchars($v['contact'] ?? ''); ?></td>
                                        <td class="py-2"><?php echo htmlspecialchars($v['email'] ?? ''); ?></td>
                                        <td class="py-2"><?php echo htmlspecialchars($v['phone'] ?? ''); ?></td>
                                        <td class="py-2 text-right">
                                            <form method="post">
                                                <input type="hidden" name="vendor_id" value="<?php echo $v['id']; ?>">
                                                <button name="create_client_from_vendor" value="1"
                                                    class="px-3 py-1 bg-primary text-white rounded">Use as customer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 text-right">
                        <button class="px-4 py-2 bg-white border rounded" onclick="closeVendorModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>