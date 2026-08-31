<?php
/**
 * Migration script to add authentication columns
 * RUN ONCE then DELETE IMMEDIATELY
 */

header('Content-Type: application/json');
require_once 'includes/db.php';

$results = [];

try {
    // Step 1: Add emp_id column if not exists
    try {
        $pdo->exec("ALTER TABLE employees ADD COLUMN emp_id VARCHAR(50) NULL");
        $results[] = "Added emp_id column";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "emp_id column already exists";
        } else {
            $results[] = "emp_id error: " . $e->getMessage();
        }
    }

    // Step 2: Add password column if not exists
    try {
        $pdo->exec("ALTER TABLE employees ADD COLUMN password VARCHAR(255) NULL");
        $results[] = "Added password column";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "password column already exists";
        } else {
            $results[] = "password error: " . $e->getMessage();
        }
    }

    // Step 3: Generate emp_id for employees that don't have one
    $employees = $pdo->query("SELECT id, name FROM employees WHERE emp_id IS NULL OR emp_id = ''")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as $emp) {
        $emp_id = 'BUE' . str_pad($emp['id'], 3, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("UPDATE employees SET emp_id = ? WHERE id = ?");
        $stmt->execute([$emp_id, $emp['id']]);
        $results[] = "Set emp_id={$emp_id} for {$emp['name']}";
    }

    // Step 4: Set password for BUE010 (or the first employee if BUE010 doesn't exist)
    $password = 'Qatar123';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Try to find BUE010 or use ID 10
    $stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE emp_id = 'BUE010' OR id = 10");
    $stmt->execute([$hash]);
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        $results[] = "Password set for BUE010 (password: $password)";
    } else {
        // Set for first employee
        $pdo->exec("UPDATE employees SET password = '$hash' WHERE id = (SELECT MIN(id) FROM employees)");
        $results[] = "Password set for first employee (password: $password)";
    }

    // Step 5: Show summary
    $employees = $pdo->query("SELECT id, name, emp_id, IF(password IS NOT NULL AND password != '', 'YES', 'NO') as has_password FROM employees LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'actions' => $results,
        'employees' => $employees,
        'note' => 'DELETE THIS FILE IMMEDIATELY!'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'actions' => $results
    ], JSON_PRETTY_PRINT);
}
