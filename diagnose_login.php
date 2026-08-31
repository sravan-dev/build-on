<?php
/**
 * Login Diagnostic Script
 * Checks DB structure and Employee BUE010 status
 * DELETE IMMEDIATELY AFTER USE
 */

header('Content-Type: application/json');
require_once 'includes/db.php';

$response = [];

try {
    // 1. Check Table Structure
    $stmt = $pdo->query("SHOW COLUMNS FROM employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $response['structure'] = [
        'has_emp_id' => in_array('emp_id', $columns),
        'has_password' => in_array('password', $columns),
        'all_columns' => $columns
    ];

    // 2. Check Employee b0001
    if (in_array('emp_id', $columns)) {
        $target_user = 'b0001';
        $target_pass = 'b0001';

        $stmt = $pdo->prepare("SELECT id, name, emp_id, password, status FROM employees WHERE emp_id = ?");
        $stmt->execute([$target_user]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            $response['employee_status'] = [
                'found' => true,
                'id' => $employee['id'],
                'name' => $employee['name'],
                'status' => $employee['status'],
                'has_password_set' => !empty($employee['password']),
                'password_hash_sample' => substr($employee['password'] ?? '', 0, 10) . '...'
            ];

            // Verify 'b0001' against hash
            if (!empty($employee['password'])) {
                $response['employee_status']['password_verify_test'] = password_verify($target_pass, $employee['password']) ? 'MATCH' : 'FAIL';

                // Also test if it's plain text (insecure legacy)
                if ($employee['password'] === $target_pass) {
                    $response['employee_status']['password_type'] = 'PLAIN TEXT (INSECURE)';
                }
            }
        } else {
            $response['employee_status'] = ['found' => false, 'message' => "User $target_user not found"];
        }
    } else {
        $response['employee_status'] = 'Skipped - No emp_id column';
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
