<?php
/**
 * Test Login directly - DELETE AFTER USE
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

$emp_id = 'BUE010';
$password = 'Qatar123';

try {
    // 1. Manual check
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ? LIMIT 1");
    $stmt->execute([$emp_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode(['status' => 'error', 'message' => 'Employee not found']);
        exit;
    }

    $verify = password_verify($password, $employee['password']);

    // 2. Simulate API function (paste of authenticateEmployee logic)
    $auth_result = false;
    try {
        $stmt2 = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ? AND status = 'active' LIMIT 1");
        $stmt2->execute([$emp_id]);
        $emp2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($emp2 && password_verify($password, $emp2['password'])) {
            $auth_result = true;
        }
    } catch (Exception $e) {
        $auth_result = $e->getMessage();
    }

    echo json_encode([
        'user' => $emp_id,
        'manual_verify_result' => $verify ? 'SUCCESS' : 'FAILURE',
        'api_logic_result' => $auth_result ? 'SUCCESS' : 'FAILURE',
        'employee_status' => $employee['status'] ?? 'unknown'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
