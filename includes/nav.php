<?php
$page = $_GET['page'] ?? 'dashboard';

// Find company logo with any extension
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
$role = $_SESSION['role'] ?? 'employee'; // Default to employee if not set

// Active style for nav links
$activeStyle = 'background: linear-gradient(90deg, rgba(240,125,0,0.4), rgba(240,125,0,0.2)); border-left: 3px solid #f07d00; font-weight: 600;';
$activeIconStyle = 'color: #f07d00;';
?>
<div class="sidebar flex flex-col p-4 min-h-full">
    <div class="sidebar-profile mb-4 p-4 rounded-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-gray-200 overflow-hidden mr-3">
                    <?php if ($companyLogoExists): ?>
                        <img src="<?php echo $companyLogoUrl; ?>" alt="Company Logo"
                            style="width:100%;height:100%;object-fit:cover;" />
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-primary text-white font-bold text-lg">
                            <?php echo strtoupper(substr(getenv('COMPANY_NAME') ?: 'B', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-sm font-semibold">Hello,</div>
                    <div class="text-xs text-muted">Buildon Admin</div>
                </div>
            </div>


        </div>
        <!-- <div class="mt-4 theme-toggle text-xs text-muted">
            <span class="mr-3">Theme</span>
            <button id="themeLight" class="px-3 py-1 rounded bg-white">Light</button>
            <button id="themeDark" class="px-3 py-1 rounded ml-2">Dark</button>
        </div> -->
    </div>
    <nav class="px-1 py-2 space-y-1">
        <?php if ($role === 'supervisor'): ?>
            <!-- Supervisor: Dashboard & Approvals -->
            <a href="index.php?page=supervisor_dashboard"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'supervisor_dashboard') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'supervisor_dashboard') ? $activeStyle : ''; ?>">
                <i class="fas fa-tachometer-alt nav-icon"
                    style="<?php echo ($page === 'supervisor_dashboard') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="index.php?page=attendance_approvals"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'attendance_approvals') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'attendance_approvals') ? $activeStyle : ''; ?>">
                <i class="fas fa-user-check nav-icon"
                    style="<?php echo ($page === 'attendance_approvals') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">User Requests</span>
            </a>
        <?php elseif ($role === 'accounts_manager'): ?>
            <!-- Accounts Manager: Cash Voucher, Expenses, Vehicles, Cash & Bank, Purchases -->
            <a href="index.php?page=vouchers"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'vouchers') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'vouchers') ? $activeStyle : ''; ?>">
                <i class="fas fa-book nav-icon"
                    style="<?php echo ($page === 'vouchers') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Journal Entries</span>
            </a>
            <a href="index.php?page=expenses"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'expenses') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'expenses') ? $activeStyle : ''; ?>">
                <i class="fas fa-receipt nav-icon"
                    style="<?php echo ($page === 'expenses') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Expenses</span>
            </a>
            <a href="index.php?page=vehicles"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo (strpos($page, 'vehicle') !== false) ? 'active' : ''; ?>"
                style="<?php echo (strpos($page, 'vehicle') !== false) ? $activeStyle : ''; ?>">
                <i class="fas fa-car nav-icon" style="<?php echo (strpos($page, 'vehicle') !== false) ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Vehicles</span>
            </a>
            <a href="index.php?page=cashbook"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'cashbook') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'cashbook') ? $activeStyle : ''; ?>">
                <i class="fas fa-cash-register nav-icon"
                    style="<?php echo ($page === 'cashbook') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Cash & Bank Book</span>
            </a>
            <a href="index.php?page=purchases"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'purchases') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'purchases') ? $activeStyle : ''; ?>">
                <i class="fas fa-shopping-cart nav-icon"
                    style="<?php echo ($page === 'purchases') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Project Purchases</span>
            </a>
            <a href="index.php?page=purchase_payments"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'purchase_payments') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'purchase_payments') ? $activeStyle : ''; ?>">
                <i class="fas fa-credit-card nav-icon"
                    style="<?php echo ($page === 'purchase_payments') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Purchase Payments</span>
            </a>
        <?php elseif ($role === 'driver'): ?>
            <!-- Driver: Only Vehicles -->
            <?php $isVehiclesActive = (strpos($page, 'vehicle') !== false); ?>
            <a href="index.php?page=vehicles"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo $isVehiclesActive ? 'active' : ''; ?>"
                style="<?php echo $isVehiclesActive ? $activeStyle : ''; ?>">
                <i class="fas fa-car nav-icon" style="<?php echo $isVehiclesActive ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Vehicles</span>
            </a>
            <a href="index.php?page=vehicle_expenses"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'vehicle_expenses') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'vehicle_expenses') ? $activeStyle : ''; ?>">
                <i class="fas fa-gas-pump nav-icon"
                    style="<?php echo ($page === 'vehicle_expenses') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Vehicle Expenses</span>
            </a>
        <?php elseif ($role === 'superadmin'): ?>
            <!-- Superadmin: Full Menu -->
            <a href="index.php?page=dashboard"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'dashboard') ? $activeStyle : ''; ?>">
                <i class="fas fa-tachometer-alt nav-icon"
                    style="<?php echo ($page === 'dashboard') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="index.php?page=credit_cards"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'credit_cards') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'credit_cards') ? $activeStyle : ''; ?>">
                <i class="fas fa-credit-card nav-icon"
                    style="<?php echo ($page === 'credit_cards') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Credit Cards</span>
            </a>

            <a href="index.php?page=users"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'users') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'users') ? $activeStyle : ''; ?>">
                <i class="fas fa-users-cog nav-icon" style="<?php echo ($page === 'users') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Users</span>
            </a>

            <a href="index.php?page=active_users"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'active_users') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'active_users') ? $activeStyle : ''; ?>">
                <i class="fas fa-signal nav-icon" style="<?php echo ($page === 'active_users') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Active Users</span>
            </a>

            <a href="index.php?page=clients"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'clients') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'clients') ? $activeStyle : ''; ?>">
                <i class="fas fa-users nav-icon" style="<?php echo ($page === 'clients') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Clients</span>
            </a>
            <a href="index.php?page=vendors"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'vendors') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'vendors') ? $activeStyle : ''; ?>">
                <i class="fas fa-store nav-icon" style="<?php echo ($page === 'vendors') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Vendors</span>
            </a>
            <a href="index.php?page=projects"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'projects') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'projects') ? $activeStyle : ''; ?>">
                <i class="fas fa-project-diagram nav-icon"
                    style="<?php echo ($page === 'projects') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Projects</span>
            </a>
            <a href="index.php?page=quotations"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'quotations') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'quotations') ? $activeStyle : ''; ?>">
                <i class="fas fa-file-invoice nav-icon"
                    style="<?php echo ($page === 'quotations') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Quotations</span>
            </a>
            <a href="index.php?page=invoices"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'invoices') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'invoices') ? $activeStyle : ''; ?>">
                <i class="fas fa-file-invoice-dollar nav-icon"
                    style="<?php echo ($page === 'invoices') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Invoices</span>
            </a>
            <a href="index.php?page=payments"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'payments') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'payments') ? $activeStyle : ''; ?>">
                <i class="fas fa-dollar-sign nav-icon"
                    style="<?php echo ($page === 'payments') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Payments</span>
            </a>
            <a href="index.php?page=account_statements"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'account_statements') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'account_statements') ? $activeStyle : ''; ?>">
                <i class="fas fa-file-alt nav-icon"
                    style="<?php echo ($page === 'account_statements') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Account Statements</span>
            </a>
            <a href="index.php?page=vouchers"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'vouchers') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'vouchers') ? $activeStyle : ''; ?>">
                <i class="fas fa-book nav-icon"
                    style="<?php echo ($page === 'vouchers') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Journal Entries</span>
            </a>
            <a href="index.php?page=ledger"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'ledger') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'ledger') ? $activeStyle : ''; ?>">
                <i class="fas fa-book nav-icon" style="<?php echo ($page === 'ledger') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">General Ledger</span>
            </a>
            <a href="index.php?page=payroll"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'payroll') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'payroll') ? $activeStyle : ''; ?>">
                <i class="fas fa-money-check-alt nav-icon"
                    style="<?php echo ($page === 'payroll') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Payroll</span>
            </a>

            <a href="index.php?page=attendance"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'attendance') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'attendance') ? $activeStyle : ''; ?>">
                <i class="fas fa-user-check nav-icon"
                    style="<?php echo ($page === 'attendance') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Attendance</span>
            </a>

            <a href="index.php?page=renewal_tracking"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'renewal_tracking') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'renewal_tracking') ? $activeStyle : ''; ?>">
                <i class="fas fa-passport nav-icon"
                    style="<?php echo ($page === 'renewal_tracking') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">HR Renewals</span>
            </a>
            <a href="index.php?page=labours"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'labours') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'labours') ? $activeStyle : ''; ?>">
                <i class="fas fa-hard-hat nav-icon"
                    style="<?php echo ($page === 'labours') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Outside Labours</span>
            </a>
            <a href="index.php?page=labour_payments"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'labour_payments') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'labour_payments') ? $activeStyle : ''; ?>">
                <i class="fas fa-money-bill-wave nav-icon"
                    style="<?php echo ($page === 'labour_payments') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Labour Payments</span>
            </a>
            <a href="index.php?page=vehicles"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'vehicles') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'vehicles') ? $activeStyle : ''; ?>">
                <i class="fas fa-car nav-icon" style="<?php echo ($page === 'vehicles') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Vehicles</span>
            </a>
            <a href="index.php?page=expenses"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'expenses') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'expenses') ? $activeStyle : ''; ?>">
                <i class="fas fa-receipt nav-icon"
                    style="<?php echo ($page === 'expenses') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Expenses</span>
            </a>
            <?php $isFollowupsActive = in_array($page, ['followups', 'followup_templates']); ?>
            <a href="index.php?page=followups"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo $isFollowupsActive ? 'active' : ''; ?>"
                style="<?php echo $isFollowupsActive ? $activeStyle : ''; ?>">
                <i class="fas fa-paper-plane nav-icon"
                    style="<?php echo $isFollowupsActive ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Follow-ups</span>
            </a>
            <a href="index.php?page=subcontracts"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'subcontracts') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'subcontracts') ? $activeStyle : ''; ?>">
                <i class="fas fa-handshake nav-icon"
                    style="<?php echo ($page === 'subcontracts') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Subcontracts</span>
            </a>
            <?php $isLposActive = in_array($page, ['lpos', 'lpo_view', 'lpo_print']); ?>
            <a href="index.php?page=lpos"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo $isLposActive ? 'active' : ''; ?>"
                style="<?php echo $isLposActive ? $activeStyle : ''; ?>">
                <i class="fas fa-file-contract nav-icon" style="<?php echo $isLposActive ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">LPOs</span>
            </a>
            <a href="index.php?page=cashbook"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'cashbook') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'cashbook') ? $activeStyle : ''; ?>">
                <i class="fas fa-cash-register nav-icon"
                    style="<?php echo ($page === 'cashbook') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Cash & Bank Book</span>
            </a>
            <a href="index.php?page=purchases"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'purchases') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'purchases') ? $activeStyle : ''; ?>">
                <i class="fas fa-shopping-cart nav-icon"
                    style="<?php echo ($page === 'purchases') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Project Purchases</span>
            </a>
            <a href="index.php?page=purchase_payments"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'purchase_payments') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'purchase_payments') ? $activeStyle : ''; ?>">
                <i class="fas fa-credit-card nav-icon"
                    style="<?php echo ($page === 'purchase_payments') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Purchase Payments</span>
            </a>
            <a href="index.php?page=reimbursements"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'reimbursements') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'reimbursements') ? $activeStyle : ''; ?>">
                <i class="fas fa-money-bill-wave nav-icon"
                    style="<?php echo ($page === 'reimbursements') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Reimbursements</span>
            </a>
            <a href="index.php?page=purchase_reports"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'purchase_reports') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'purchase_reports') ? $activeStyle : ''; ?>">
                <i class="fas fa-chart-bar nav-icon"
                    style="<?php echo ($page === 'purchase_reports') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Purchase Reports</span>
            </a>
            <a href="index.php?page=tools"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'tools') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'tools') ? $activeStyle : ''; ?>">
                <i class="fas fa-tools nav-icon" style="<?php echo ($page === 'tools') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Tools & Inventory</span>
                <span class="float-right bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full"
                    style="font-size: 0.65rem;">NEW</span>
            </a>
        <?php elseif ($role === 'employee'): ?>
            <!-- Employee: Only Attendance -->
            <a href="index.php?page=attendance"
                class="block px-4 py-3 mb-1 rounded nav-link <?php echo ($page === 'attendance') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'attendance') ? $activeStyle : ''; ?>">
                <i class="fas fa-user-check nav-icon"
                    style="<?php echo ($page === 'attendance') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">My Dashboard</span>
            </a>
        <?php endif; ?>
    </nav>
    <div class="px-2 py-4 border-t mt-3">
        <?php if ($role === 'superadmin'): ?>
            <h6 class="text-xs text-muted uppercase font-bold px-4 mb-2">Account Pages</h6>
            <a href="index.php?page=settings"
                class="block px-4 py-3 rounded nav-link <?php echo ($page === 'settings') ? 'active' : ''; ?>"
                style="<?php echo ($page === 'settings') ? $activeStyle : ''; ?>">
                <i class="fas fa-cog nav-icon" style="<?php echo ($page === 'settings') ? $activeIconStyle : ''; ?>"></i>
                <span class="ml-3">Settings</span>
            </a>
        <?php endif; ?>
        <a href="pages/logout.php" class="block px-4 py-3 text-muted rounded nav-link">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="ml-3">Logout</span>
        </a>
    </div>
</div>