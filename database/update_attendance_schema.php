<?php
/**
 * Update Database Schema for Enhanced Attendance & Advance Payments
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // 1. Create daily_attendance table
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        attendance_date DATE NOT NULL,
        in_time TIME,
        out_time TIME,
        working_hours DECIMAL(5,2),
        status TEXT DEFAULT 'Present',
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE(employee_id, attendance_date)
    )");
    echo "✅ Created daily_attendance table\n";

    // 2. Create advance_payments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS advance_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reason TEXT,
        approved_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    )");
    echo "✅ Created advance_payments table\n";

    // 3. Add index for performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_date ON daily_attendance(attendance_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_advance_date ON advance_payments(payment_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_daily_att_emp_month ON daily_attendance(employee_id, attendance_date)");

} catch (PDOException $e) {
    echo "❌ Error updating database: " . $e->getMessage() . "\n";
}
