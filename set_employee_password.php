<?php
/**
 * One-time script to set password for employee BUE010
 * Upload this to the live server and run it once, then DELETE it
 */

header('Content-Type: application/json');
require_once 'includes/db.php';

$search = 'BUE010';
$new_password = 'Qatar123';

try {
    // First, get table columns to detect structure
    $columns = [];

    // Try MySQL way first
    try {
        $result = $pdo->query("SHOW COLUMNS FROM employees");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
    } catch (Exception $e) {
        // Try SQLite way
        $result = $pdo->query("PRAGMA table_info(employees)");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
    }

    // Find the right column for employee ID
    $emp_id_col = null;
    foreach (['emp_id', 'employee_id', 'empid', 'emp_code'] as $possible) {
        if (in_array($possible, $columns)) {
            $emp_id_col = $possible;
            break;
        }
    }

    // Search for employee
    if ($emp_id_col) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE $emp_id_col = ? LIMIT 1");
        $stmt->execute([$search]);
    } else {
        // Try by name or ID
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE name LIKE ? OR id = ? LIMIT 1");
        $stmt->execute(["%$search%", $search]);
    }

    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        // Show available employees
        $all = $pdo->query("SELECT id, name FROM employees LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found',
            'columns' => $columns,
            'available_employees' => $all
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Set password
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
    $result = $update->execute([$hash, $employee['id']]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Password set successfully for {$employee['name']} (ID: {$employee['id']})",
            'note' => 'DELETE THIS FILE IMMEDIATELY!'
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

