<?php
// Standalone Profile Settings page (slug: #profile)
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = null;
$error = null;

$companyName = getenv('COMPANY_NAME') ?: '';
$companyAddress = getenv('COMPANY_ADDRESS') ?: '';
$companyPhone = getenv('COMPANY_PHONE') ?: '';
$companyToll = getenv('COMPANY_TOLL_FREE') ?: '';
$companyWebsite = getenv('COMPANY_WEBSITE') ?: '';

// Ensure uploads dir exists for logo
$uploadDir = dirname(__DIR__) . '/uploads';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
$companyLogoPath = $uploadDir . '/company_logo.png';
$companyLogoUrl = 'uploads/company_logo.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business'])) {
    $ok = updateEnv(dirname(__DIR__) . '/.env', [
        'COMPANY_NAME' => trim($_POST['COMPANY_NAME'] ?? ''),
        'COMPANY_ADDRESS' => trim($_POST['COMPANY_ADDRESS'] ?? ''),
        'COMPANY_PHONE' => trim($_POST['COMPANY_PHONE'] ?? ''),
        'COMPANY_TOLL_FREE' => trim($_POST['COMPANY_TOLL_FREE'] ?? ''),
        'COMPANY_WEBSITE' => trim($_POST['COMPANY_WEBSITE'] ?? ''),
    ]);
    if ($ok) {
        $message = 'Business profile updated.';
        $companyName = getenv('COMPANY_NAME') ?: '';
        $companyAddress = getenv('COMPANY_ADDRESS') ?: '';
        $companyPhone = getenv('COMPANY_PHONE') ?: '';
        $companyToll = getenv('COMPANY_TOLL_FREE') ?: '';
        $companyWebsite = getenv('COMPANY_WEBSITE') ?: '';
    } else {
        $error = 'Failed to update business profile.';
    }
}

// Handle logo upload/remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_logo'])) {
        if (file_exists($companyLogoPath)) { @unlink($companyLogoPath); }
    }
    if (!empty($_FILES['company_logo']['tmp_name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
        @move_uploaded_file($_FILES['company_logo']['tmp_name'], $companyLogoPath);
    }
}

?>

<div id="profile" class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Profile</h1>
    <p class="text-gray-600 mt-2">Company address, contact and logo</p>
</div>

<?php if ($message): ?><div class="mt-4 rounded-lg border border-green-300 bg-green-50 text-green-700 text-sm px-4 py-3"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mt-4 rounded-lg border border-red-300 bg-red-50 text-red-700 text-sm px-4 py-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="bg-white rounded-lg shadow-md p-6">
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                <input type="text" name="COMPANY_NAME" value="<?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="COMPANY_PHONE" value="<?php echo htmlspecialchars($companyPhone, ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 border rounded">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Company Logo</label>
                <div class="flex items-center space-x-4">
                    <div class="w-28 h-20 border rounded overflow-hidden bg-gray-50 flex items-center justify-center">
                        <?php if (file_exists($companyLogoPath)): ?>
                            <img src="<?php echo $companyLogoUrl; ?>?t=<?php echo time(); ?>" alt="Company Logo" style="max-width:100%;max-height:100%;object-fit:contain;" />
                        <?php else: ?>
                            <span class="text-xs text-gray-500">No logo</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="file" name="company_logo" accept="image/*">
                        <?php if (file_exists($companyLogoPath)): ?>
                            <div class="mt-2"><button name="remove_logo" value="1" class="px-3 py-1 bg-red-600 text-white rounded text-sm" onclick="return confirm('Remove company logo?')">Remove Logo</button></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="COMPANY_ADDRESS" rows="3" class="w-full px-3 py-2 border rounded"><?php echo htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Toll Free</label>
                <input type="text" name="COMPANY_TOLL_FREE" value="<?php echo htmlspecialchars($companyToll, ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                <input type="text" name="COMPANY_WEBSITE" value="<?php echo htmlspecialchars($companyWebsite, ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-3 py-2 border rounded">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" name="update_business" class="bg-primary hover:bg-secondary text-white px-6 py-2 rounded-md">Save Business Profile</button>
        </div>
    </form>
</div>
