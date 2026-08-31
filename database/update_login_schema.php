<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
loadEnv(__DIR__ . '/../.env');

try {
    // 1. Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'employee', -- superadmin, supervisor, employee
        employee_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE SET NULL
    )");
    echo "✅ Created users table\n";

    // 2. Insert Superadmin if not exists
    $adminUser = getenv('LOGIN_USERNAME') ?: 'admin';
    $adminPass = getenv('LOGIN_PASSWORD') ?: 'META#@$123'; // Default fallback
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$adminUser]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'superadmin')");
        $stmt->execute([$adminUser, $hash]);
        echo "✅ Created default superadmin user ($adminUser)\n";
    }

    // 3. Update daily_attendance (SQLite safe add column)
    $columns = [
        'break_start' => 'TIME',
        'break_end' => 'TIME',
        'site_id' => 'INTEGER REFERENCES projects(id)',
        'activity_description' => 'TEXT',
        'is_offsite' => 'INTEGER DEFAULT 0',
        'approval_status' => "TEXT DEFAULT 'pending'",
        'supervisor_note' => 'TEXT',
        'approved_by' => 'INTEGER REFERENCES users(id)',
        'approved_at' => 'DATETIME'
    ];

    foreach ($columns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE daily_attendance ADD COLUMN $col $def");
            echo "✅ Added column $col\n";
        } catch (PDOException $e) {
            // Ignore if column exists (SQLite throws error if column exists)
        }
    }

    echo "✅ Database schema updated successfully\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
