<?php
require_once __DIR__ . '/../includes/db.php';

echo "Creating sample data...\n";

$password = password_hash('password123', PASSWORD_DEFAULT);

$samples = [
    [
        'name' => 'Super Admin',
        'role' => 'superadmin',
        'username' => 'admin_test',
        'position' => 'System Administrator'
    ],
    [
        'name' => 'Jane Supervisor',
        'role' => 'supervisor',
        'username' => 'supervisor_test',
        'position' => 'Site Manager'
    ],
    [
        'name' => 'John Employee',
        'role' => 'employee',
        'username' => 'employee_test',
        'position' => 'Worker'
    ]
];

foreach ($samples as $s) {
    try {
        // 1. Create Employee
        // Check if employee exists by name to avoid dupes (rough check)
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE name = ?");
        $stmt->execute([$s['name']]);
        $emp = $stmt->fetch();

        if (!$emp) {
            $stmt = $pdo->prepare("INSERT INTO employees (name, position, status, hire_date) VALUES (?, ?, 'active', DATE('now'))");
            $stmt->execute([$s['name'], $s['position']]);
            $empId = $pdo->lastInsertId();
            echo "Created employee: {$s['name']} (ID: $empId)\n";
        } else {
            $empId = $emp['id'];
            echo "Employee exists: {$s['name']} (ID: $empId)\n";
        }

        // 2. Create/Update User
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$s['username']]);
        $user = $stmt->fetch();

        if ($user) {
            // Update
            $stmt = $pdo->prepare("UPDATE users SET employee_id = ?, role = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$empId, $s['role'], $password, $user['id']]);
            echo "Updated user: {$s['username']}\n";
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, employee_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$s['username'], $password, $s['role'], $empId]);
            echo "Created user: {$s['username']}\n";
        }

    } catch (PDOException $e) {
        echo "Error processing {$s['name']}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
echo "---------------------------------------------------\n";
echo "User Credentials (Password for all: password123)\n";
echo "1. Super Admin: admin_test\n";
echo "2. Supervisor:  supervisor_test\n";
echo "3. Employee:    employee_test\n";
echo "---------------------------------------------------\n";
