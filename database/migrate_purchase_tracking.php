<?php
/**
 * Migration Script for Purchase Tracking Feature
 * Run this file once to add the new purchase tracking tables
 * Usage: php database/migrate_purchase_tracking.php
 */

require_once dirname(__DIR__) . '/includes/db.php';

echo "Starting Purchase Tracking Migration...\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Create purchases table
    echo "Creating purchases table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        vendor_id INTEGER,
        purchase_date TEXT NOT NULL,
        description TEXT,
        invoice_number TEXT,
        attachment_path TEXT,
        subtotal REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        total_amount REAL DEFAULT 0,
        status TEXT DEFAULT 'draft',
        created_by TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        approved_by TEXT,
        approved_at TEXT,
        rejection_reason TEXT,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(vendor_id) REFERENCES vendors(id)
    )");
    echo "✓ Done\n";
    
    // 2. Create purchase_items table
    echo "Creating purchase_items table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        description TEXT NOT NULL,
        quantity REAL DEFAULT 1,
        unit_price REAL DEFAULT 0,
        total REAL DEFAULT 0,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    )");
    echo "✓ Done\n";
    
    // 3. Create purchase_payments table
    echo "Creating purchase_payments table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        payment_date TEXT NOT NULL,
        amount REAL NOT NULL,
        payment_method TEXT NOT NULL,
        payment_account TEXT,
        paid_by TEXT,
        employee_id INTEGER,
        is_reimbursable INTEGER DEFAULT 0,
        reimbursement_status TEXT DEFAULT 'pending',
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    )");
    echo "✓ Done\n";
    
    // 4. Create reimbursements table
    echo "Creating reimbursements table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS reimbursements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_payment_id INTEGER NOT NULL,
        employee_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        request_date TEXT NOT NULL,
        approval_date TEXT,
        payment_date TEXT,
        status TEXT DEFAULT 'pending',
        approved_by TEXT,
        payment_method TEXT,
        rejection_reason TEXT,
        notes TEXT,
        FOREIGN KEY(purchase_payment_id) REFERENCES purchase_payments(id) ON DELETE CASCADE,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    )");
    echo "✓ Done\n";
    
    // 5. Create purchase_audit_log table
    echo "Creating purchase_audit_log table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        performed_by TEXT NOT NULL,
        performed_at TEXT DEFAULT CURRENT_TIMESTAMP,
        old_values TEXT,
        new_values TEXT,
        notes TEXT,
        FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
    )");
    echo "✓ Done\n";
    
    // 6. Create indexes for better performance
    echo "Creating indexes... ";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchases_project ON purchases(project_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchases_status ON purchases(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchase_payments_purchase ON purchase_payments(purchase_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reimbursements_status ON reimbursements(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reimbursements_employee ON reimbursements(employee_id)");
    echo "✓ Done\n";
    
    $pdo->commit();
    
    echo "\n✅ Migration completed successfully!\n\n";
    echo "Summary:\n";
    echo "- purchases table created\n";
    echo "- purchase_items table created\n";
    echo "- purchase_payments table created\n";
    echo "- reimbursements table created\n";
    echo "- purchase_audit_log table created\n";
    echo "- Performance indexes created\n\n";
    echo "You can now use the Purchase Tracking features in the CRM.\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

