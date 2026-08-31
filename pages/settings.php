<?php

// Settings page: change login username/password stored in .env
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/auth.php';
require_role('superadmin');



$message = null;
$error = null;

// Credentials management moved to pages/users.php

$currentUser = getenv('LOGIN_USERNAME') ?: '';
$companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
$companyAddress = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
$companyPhone = getenv('COMPANY_PHONE') ?: '+947 30659993';
$companyToll = getenv('COMPANY_TOLL_FREE') ?: '77721423';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';
// Ensure uploads dir exists for logo
$uploadDir = dirname(__DIR__) . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

// Find existing logo with any extension
$companyLogoPath = null;
$companyLogoUrl = null;
$possibleExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/company_logo.' . $ext;
    if (file_exists($testPath)) {
        $companyLogoPath = $testPath;
        $companyLogoUrl = 'uploads/company_logo.' . $ext;
        break;
    }
}
// Default if not found
if (!$companyLogoPath) {
    $companyLogoPath = $uploadDir . '/company_logo.png';
    $companyLogoUrl = 'uploads/company_logo.png';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business'])) {
    $ok = updateEnv(dirname(__DIR__) . '/.env', [
        'COMPANY_NAME' => trim($_POST['COMPANY_NAME'] ?? ''),
        'COMPANY_ADDRESS' => trim($_POST['COMPANY_ADDRESS'] ?? ''),
        'COMPANY_PHONE' => trim($_POST['COMPANY_PHONE'] ?? ''),
        'COMPANY_TOLL_FREE' => trim($_POST['COMPANY_TOLL_FREE'] ?? ''),
        'COMPANY_WEBSITE' => trim($_POST['COMPANY_WEBSITE'] ?? ''),
    ]);
    if ($ok) {
        $message = ($message ? $message . ' ' : '') . 'Business profile updated.';
        $companyName = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
        $companyAddress = getenv('COMPANY_ADDRESS') ?: "158176\nAl Majed Centre, Jabr Bin Mohamed St.\nDOHA, Ar Rayyan\nQatar";
        $companyPhone = getenv('COMPANY_PHONE') ?: '+947 30659993';
        $companyToll = getenv('COMPANY_TOLL_FREE') ?: '77721423';
        $companyWebsite = getenv('COMPANY_WEBSITE') ?: 'www.buildonqatar.com';
    } else {
        $error = ($error ? $error . ' ' : '') . 'Failed to update business profile.';
    }
}

// Handle logo upload/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_logo'])) {
        if (file_exists($companyLogoPath)) {
            @unlink($companyLogoPath);
            $message = ($message ? $message . ' ' : '') . 'Company logo removed successfully.';
        }
    }
    if (!empty($_FILES['company_logo']['tmp_name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
        // Check for upload errors
        if ($_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
            $error = ($error ? $error . ' ' : '') . 'Upload failed. Error code: ' . $_FILES['company_logo']['error'];
        } else {
            // Validate file size (2MB max)
            $maxSize = 2 * 1024 * 1024; // 2MB in bytes
            if ($_FILES['company_logo']['size'] > $maxSize) {
                $error = ($error ? $error . ' ' : '') . 'File too large. Maximum size is 2MB.';
            } else {
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = $_FILES['company_logo']['type'];

                if (in_array($fileType, $allowedTypes)) {
                    // Get file extension
                    $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
                    $targetPath = $uploadDir . '/company_logo.' . $ext;

                    // Remove old logos with any extension
                    foreach ($possibleExtensions as $oldExt) {
                        $oldPath = $uploadDir . '/company_logo.' . $oldExt;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    // Move uploaded file
                    if (@move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetPath)) {
                        // Update the logo path variable for current request
                        $companyLogoPath = $targetPath;
                        $companyLogoUrl = 'uploads/company_logo.' . $ext;
                        $message = ($message ? $message . ' ' : '') . 'Company logo uploaded successfully.';
                    } else {
                        $error = ($error ? $error . ' ' : '') . 'Failed to upload logo. Check uploads folder permissions.';
                    }
                } else {
                    $error = ($error ? $error . ' ' : '') . 'Invalid file type. Please upload an image (JPEG, PNG, GIF, or WebP).';
                }
            }
        }
    }
}

// Find existing receipt logo
$receiptLogoPath = null;
$receiptLogoUrl = null;
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/receipt_logo.' . $ext;
    if (file_exists($testPath)) {
        $receiptLogoPath = $testPath;
        $receiptLogoUrl = 'uploads/receipt_logo.' . $ext;
        break;
    }
}

// Find existing company seal
$companySealPath = null;
$companySealUrl = null;
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/company_seal.' . $ext;
    if (file_exists($testPath)) {
        $companySealPath = $testPath;
        $companySealUrl = 'uploads/company_seal.' . $ext;
        break;
    }
}

// Handle receipt logo upload/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_receipt_logo'])) {
        foreach ($possibleExtensions as $ext) {
            $path = $uploadDir . '/receipt_logo.' . $ext;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $message = ($message ? $message . ' ' : '') . 'Receipt logo removed successfully.';
    }
    if (!empty($_FILES['receipt_logo']['tmp_name']) && is_uploaded_file($_FILES['receipt_logo']['tmp_name'])) {
        if ($_FILES['receipt_logo']['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024;
            if ($_FILES['receipt_logo']['size'] <= $maxSize) {
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['receipt_logo']['type'], $allowedTypes)) {
                    $ext = strtolower(pathinfo($_FILES['receipt_logo']['name'], PATHINFO_EXTENSION));
                    foreach ($possibleExtensions as $oldExt) {
                        @unlink($uploadDir . '/receipt_logo.' . $oldExt);
                    }
                    $targetPath = $uploadDir . '/receipt_logo.' . $ext;
                    if (@move_uploaded_file($_FILES['receipt_logo']['tmp_name'], $targetPath)) {
                        $receiptLogoPath = $targetPath;
                        $receiptLogoUrl = 'uploads/receipt_logo.' . $ext;
                        $message = ($message ? $message . ' ' : '') . 'Receipt logo uploaded successfully.';
                    }
                }
            }
        }
    }
}

// Handle company seal upload/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_company_seal'])) {
        foreach ($possibleExtensions as $ext) {
            $path = $uploadDir . '/company_seal.' . $ext;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $message = ($message ? $message . ' ' : '') . 'Company seal removed successfully.';
    }
    if (!empty($_FILES['company_seal']['tmp_name']) && is_uploaded_file($_FILES['company_seal']['tmp_name'])) {
        if ($_FILES['company_seal']['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024;
            if ($_FILES['company_seal']['size'] <= $maxSize) {
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['company_seal']['type'], $allowedTypes)) {
                    $ext = strtolower(pathinfo($_FILES['company_seal']['name'], PATHINFO_EXTENSION));
                    foreach ($possibleExtensions as $oldExt) {
                        @unlink($uploadDir . '/company_seal.' . $oldExt);
                    }
                    $targetPath = $uploadDir . '/company_seal.' . $ext;
                    if (@move_uploaded_file($_FILES['company_seal']['tmp_name'], $targetPath)) {
                        $companySealPath = $targetPath;
                        $companySealUrl = 'uploads/company_seal.' . $ext;
                        $message = ($message ? $message . ' ' : '') . 'Company seal uploaded successfully.';
                    }
                }
            }
        }
    }
}

// Find existing authorized signature
$signaturePath = null;
$signatureUrl = null;
foreach ($possibleExtensions as $ext) {
    $testPath = $uploadDir . '/authorized_signature.' . $ext;
    if (file_exists($testPath)) {
        $signaturePath = $testPath;
        $signatureUrl = 'uploads/authorized_signature.' . $ext;
        break;
    }
}

// Handle signature upload/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_signature'])) {
        foreach ($possibleExtensions as $ext) {
            $path = $uploadDir . '/authorized_signature.' . $ext;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $message = ($message ? $message . ' ' : '') . 'Authorized signature removed successfully.';
    }
    if (!empty($_FILES['authorized_signature']['tmp_name']) && is_uploaded_file($_FILES['authorized_signature']['tmp_name'])) {
        if ($_FILES['authorized_signature']['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024;
            if ($_FILES['authorized_signature']['size'] <= $maxSize) {
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['authorized_signature']['type'], $allowedTypes)) {
                    $ext = strtolower(pathinfo($_FILES['authorized_signature']['name'], PATHINFO_EXTENSION));
                    foreach ($possibleExtensions as $oldExt) {
                        @unlink($uploadDir . '/authorized_signature.' . $oldExt);
                    }
                    $targetPath = $uploadDir . '/authorized_signature.' . $ext;
                    if (@move_uploaded_file($_FILES['authorized_signature']['tmp_name'], $targetPath)) {
                        $signaturePath = $targetPath;
                        $signatureUrl = 'uploads/authorized_signature.' . $ext;
                        $message = ($message ? $message . ' ' : '') . 'Authorized signature uploaded successfully.';
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quotation_defaults'])) {
    $raw = trim($_POST['QUOTE_FOOTER_TEXT'] ?? '');
    // Store as single line with escaped newlines to avoid breaking .env parsing
    $escaped = str_replace(["\r\n", "\r", "\n"], "\\n", $raw);
    $updates = [
        'QUOTE_FOOTER_TEXT' => $escaped,
    ];
    if (isset($_POST['CURRENCY_SYMBOL'])) {
        $updates['CURRENCY_SYMBOL'] = trim($_POST['CURRENCY_SYMBOL']);
    }
    $ok = updateEnv(dirname(__DIR__) . '/.env', $updates);
    if ($ok) {
        $message = ($message ? $message . ' ' : '') . 'Quotation defaults updated.';
    } else {
        $error = ($error ? $error . ' ' : '') . 'Failed to update quotation defaults.';
    }
}

// Handle invoice defaults update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice_defaults'])) {
    $headerTitle = trim($_POST['INVOICE_HEADER_TITLE'] ?? '');
    $footerRaw = trim($_POST['INVOICE_FOOTER_TEXT'] ?? '');
    // Store as single line with escaped newlines
    $footerEscaped = str_replace(["\r\n", "\r", "\n"], "\\n", $footerRaw);

    $updates = [
        'INVOICE_HEADER_TITLE' => $headerTitle,
        'INVOICE_FOOTER_TEXT' => $footerEscaped,
    ];

    $ok = updateEnv(dirname(__DIR__) . '/.env', $updates);
    if ($ok) {
        $message = ($message ? $message . ' ' : '') . 'Invoice defaults updated.';
    } else {
        $error = ($error ? $error . ' ' : '') . 'Failed to update invoice defaults.';
    }
}

// Handle data reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_all_data'])) {
    $confirmation = trim($_POST['reset_confirmation'] ?? '');

    if ($confirmation !== 'RESET') {
        $error = ($error ? $error . ' ' : '') . 'Please type "RESET" to confirm data deletion.';
    } else {
        try {
            require_once dirname(__DIR__) . '/includes/db.php';

            // Get all table names
            $tables = [
                'purchase_audit_log',
                'reimbursements',
                'purchase_payments',
                'purchase_items',
                'purchases',
                'vendor_payments',
                'expenses',
                'account_balances',
                'transactions',
                'vehicles',
                'outside_labours',
                'attendance',
                'employees',
                'payments',
                'invoices',
                'quotation_items',
                'quotations',
                'projects',
                'vendors',
                'clients'
            ];

            // Disable foreign key checks temporarily
            $pdo->exec('PRAGMA foreign_keys = OFF');

            // Drop all tables
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS $table");
            }

            // Re-enable foreign key checks
            $pdo->exec('PRAGMA foreign_keys = ON');

            // Recreate all tables
            $sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
            $pdo->exec($sql);

            $message = ($message ? $message . ' ' : '') . 'All data has been reset successfully. Database is now empty and ready for new data.';

        } catch (Exception $e) {
            $error = ($error ? $error . ' ' : '') . 'Failed to reset data: ' . $e->getMessage();
        }
    }
}
?>

<div class="settings-page">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
        <p class="text-gray-600 mt-2">Manage application login credentials</p>
        <?php if ($message): ?>
            <div class="mt-4 rounded-lg border border-green-300 bg-green-50 text-green-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if (!is_writable(dirname(__DIR__) . '/.env')): ?>
            <div class="mt-4 rounded-lg border border-yellow-300 bg-yellow-50 text-yellow-800 text-sm px-4 py-3">
                Warning: .env file may not be writable. Changes could fail.
            </div>
        <?php endif; ?>
        <div class="mt-4 p-4 rounded border bg-white">Current user: <span
                class="font-mono"><?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></span></div>
    </div>

    <div class="space-y-6">
        <!-- Credentials Section Removed -->

        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Business Profile</h2>
                <p class="text-gray-600 text-sm">These details appear on quotation headers.</p>
            </div>
            <div class="p-6">
                <form method="post" enctype="multipart/form-data" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                            <input type="text" name="COMPANY_NAME"
                                value="<?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" name="COMPANY_PHONE"
                                value="<?php echo htmlspecialchars($companyPhone, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Logo</label>
                            <div class="flex items-start space-x-6">
                                <div
                                    class="w-32 h-24 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                                    <?php if (file_exists($companyLogoPath)): ?>
                                        <img src="<?php echo $companyLogoUrl; ?>?t=<?php echo time(); ?>" alt="Company Logo"
                                            style="max-width:100%;max-height:100%;object-fit:contain;" />
                                    <?php else: ?>
                                        <div class="text-center p-2">
                                            <i class="fas fa-image text-gray-400 text-2xl mb-1"></i>
                                            <p class="text-xs text-gray-500">No logo</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="mb-3">
                                        <label class="block">
                                            <span class="block text-sm font-medium text-gray-700 mb-2">Choose Logo
                                                File</span>
                                            <input type="file" name="company_logo"
                                                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                                class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </label>
                                        <p class="mt-1 text-xs text-gray-500">Accepted formats: JPG, PNG, GIF, WebP. Max
                                            size: 2MB</p>
                                    </div>
                                    <?php if (file_exists($companyLogoPath)): ?>
                                        <button type="submit" name="remove_logo" value="1"
                                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm transition-colors"
                                            onclick="return confirm('Remove company logo?')">
                                            <i class="fas fa-trash-alt mr-2"></i>Remove Logo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="COMPANY_ADDRESS" rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Toll Free</label>
                            <input type="text" name="COMPANY_TOLL_FREE"
                                value="<?php echo htmlspecialchars($companyToll, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                            <input type="text" name="COMPANY_WEBSITE"
                                value="<?php echo htmlspecialchars($companyWebsite, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="update_business"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">Save
                            Business Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Receipt Assets</h2>
                <p class="text-gray-600 text-sm">Customize logo and seal for payment receipts.</p>
            </div>
            <div class="p-6">
                <form method="post" enctype="multipart/form-data" class="space-y-6">
                    <!-- Receipt Logo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Logo</label>
                        <p class="text-xs text-gray-500 mb-3">This logo appears at the top of payment receipts.</p>
                        <div class="flex items-start space-x-6">
                            <div
                                class="w-32 h-24 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                                <?php if ($receiptLogoPath && file_exists($receiptLogoPath)): ?>
                                    <img src="<?php echo $receiptLogoUrl; ?>?t=<?php echo time(); ?>" alt="Receipt Logo"
                                        style="max-width:100%;max-height:100%;object-fit:contain;" />
                                <?php else: ?>
                                    <div class="text-center p-2">
                                        <i class="fas fa-receipt text-gray-400 text-2xl mb-1"></i>
                                        <p class="text-xs text-gray-500">No logo</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="receipt_logo"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                    class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                                <p class="mt-1 text-xs text-gray-500">Max size: 2MB. Formats: JPG, PNG, GIF, WebP</p>
                                <?php if ($receiptLogoPath && file_exists($receiptLogoPath)): ?>
                                    <button type="submit" name="remove_receipt_logo" value="1"
                                        class="mt-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs"
                                        onclick="return confirm('Remove receipt logo?')">
                                        <i class="fas fa-trash-alt mr-1"></i>Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Company Seal -->
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Seal / Stamp</label>
                        <p class="text-xs text-gray-500 mb-3">This seal appears at the bottom of payment receipts
                            (optional).</p>
                        <div class="flex items-start space-x-6">
                            <div
                                class="w-24 h-24 border-2 border-dashed border-gray-300 rounded-full overflow-hidden bg-gray-50 flex items-center justify-center">
                                <?php if ($companySealPath && file_exists($companySealPath)): ?>
                                    <img src="<?php echo $companySealUrl; ?>?t=<?php echo time(); ?>" alt="Company Seal"
                                        style="max-width:100%;max-height:100%;object-fit:contain;" />
                                <?php else: ?>
                                    <div class="text-center p-2">
                                        <i class="fas fa-stamp text-gray-400 text-xl mb-1"></i>
                                        <p class="text-xs text-gray-500">No seal</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="company_seal"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                    class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-600 file:text-white hover:file:bg-green-700 cursor-pointer">
                                <p class="mt-1 text-xs text-gray-500">Upload a transparent PNG for best results.</p>
                                <?php if ($companySealPath && file_exists($companySealPath)): ?>
                                    <button type="submit" name="remove_company_seal" value="1"
                                        class="mt-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs"
                                        onclick="return confirm('Remove company seal?')">
                                        <i class="fas fa-trash-alt mr-1"></i>Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Authorized Signature -->
                    <div class="border-t pt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Authorized Signature</label>
                        <p class="text-xs text-gray-500 mb-3">This signature appears on payment receipts (optional).</p>
                        <div class="flex items-start space-x-6">
                            <div
                                class="w-32 h-16 border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                                <?php if ($signaturePath && file_exists($signaturePath)): ?>
                                    <img src="<?php echo $signatureUrl; ?>?t=<?php echo time(); ?>" alt="Signature"
                                        style="max-width:100%;max-height:100%;object-fit:contain;" />
                                <?php else: ?>
                                    <div class="text-center p-2">
                                        <i class="fas fa-signature text-gray-400 text-xl mb-1"></i>
                                        <p class="text-xs text-gray-500">No sign</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="authorized_signature"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                    class="block w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-md shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-700 cursor-pointer">
                                <p class="mt-1 text-xs text-gray-500">Upload a transparent PNG for best results.</p>
                                <?php if ($signaturePath && file_exists($signaturePath)): ?>
                                    <button type="submit" name="remove_signature" value="1"
                                        class="mt-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs"
                                        onclick="return confirm('Remove signature?')">
                                        <i class="fas fa-trash-alt mr-1"></i>Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t">
                        <button type="submit" name="upload_receipt_assets"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">
                            <i class="fas fa-upload mr-2"></i>Upload Assets
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Quotation Defaults</h2>
                <p class="text-sm text-gray-600">Default footer text shown on quotations.</p>
            </div>
            <div class="p-6">
                <?php $quoteFooter = getenv('QUOTE_FOOTER_TEXT') ?: "";
                $currencySym = getenv('CURRENCY_SYMBOL') ?: '$'; ?>
                <form method="post" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Currency Symbol</label>
                        <input type="text" name="CURRENCY_SYMBOL"
                            value="<?php echo htmlspecialchars($currencySym, ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Examples: $, ﷼, ₹, €, QAR</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Footer Text</label>
                        <textarea name="QUOTE_FOOTER_TEXT" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($quoteFooter, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="update_quotation_defaults"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">Save
                            Defaults</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Invoice Defaults</h2>
                <p class="text-sm text-gray-600">Customize invoice header and footer details.</p>
            </div>
            <div class="p-6">
                <?php
                $invoiceHeaderTitle = getenv('INVOICE_HEADER_TITLE') ?: 'TAX INVOICE';
                $invoiceFooter = getenv('INVOICE_FOOTER_TEXT') ?: "";
                ?>
                <form method="post" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Header Title</label>
                        <input type="text" name="INVOICE_HEADER_TITLE"
                            value="<?php echo htmlspecialchars($invoiceHeaderTitle, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="e.g., TAX INVOICE, INVOICE, BILL"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">This appears at the top of the invoice</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Footer Text</label>
                        <textarea name="INVOICE_FOOTER_TEXT" rows="6"
                            placeholder="Enter bank details, payment terms, or other footer information..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($invoiceFooter, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Add bank details, payment terms, etc. This appears at the
                            bottom of invoices.</p>
                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-xs text-blue-800 font-medium mb-1">💡 Example:</p>
                            <p class="text-xs text-blue-700 font-mono">
                                Bank Name: Commercial Bank of Qatar<br>
                                Account Name: BUILDON TRADING & CONTRACTING W.L.L<br>
                                Account Number: 123456789<br>
                                IBAN: QA12CBQA000000000123456789<br>
                                Swift Code: CBQAQAQA
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="update_invoice_defaults"
                            class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md font-medium transition-colors">Save
                            Invoice Defaults</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-red-50 border-2 border-red-200 rounded-lg shadow-md">
            <div class="p-6 border-b border-red-200">
                <h2 class="text-xl font-semibold text-red-800 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Reset All Data
                </h2>
                <p class="text-sm text-red-700 mt-1">This action will permanently delete ALL data from the database.</p>
            </div>
            <div class="p-6">
                <div class="bg-red-100 border border-red-300 rounded-md p-4 mb-4">
                    <h3 class="font-semibold text-red-800 mb-2">⚠️ WARNING: This action is irreversible!</h3>
                    <p class="text-sm text-red-700 mb-2">This will delete ALL data including:</p>
                    <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
                        <li>All clients, vendors, and projects</li>
                        <li>All quotations, invoices, and payments</li>
                        <li>All employee records and attendance data</li>
                        <li>All purchase records and reimbursements</li>
                        <li>All financial transactions and account balances</li>
                        <li>All vehicles and outside labours</li>
                    </ul>
                    <p class="text-sm text-red-800 font-semibold mt-2">Only use this if you want to start completely
                        fresh!</p>
                </div>

                <form method="post" class="space-y-4" onsubmit="return confirmReset()">
                    <div>
                        <label class="block text-sm font-medium text-red-800 mb-2">
                            To confirm, type <span class="font-mono bg-red-200 px-2 py-1 rounded">RESET</span> in the
                            box below:
                        </label>
                        <input type="text" name="reset_confirmation" placeholder="Type RESET to confirm"
                            class="w-full px-3 py-2 border-2 border-red-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 font-mono"
                            required>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" name="reset_all_data"
                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-md font-medium transition-colors">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Reset All Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmReset() {
            const confirmation = document.querySelector('input[name="reset_confirmation"]').value;
            if (confirmation !== 'RESET') {
                alert('Please type "RESET" exactly to confirm data deletion.');
                return false;
            }

            return confirm('Are you absolutely sure you want to delete ALL data? This action cannot be undone!');
        }
    </script>