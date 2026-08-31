<?php
/**
 * Find User Script
 * Searches for 'b0001' in relevant columns
 * DELETE IMMEDIATELY AFTER USE
 */

header('Content-Type: application/json');
require_once 'includes/db.php';

$response = [];
$term = 'b0001';

try {
    // 1. Search in various columns
    $sql = "SELECT id, name, emp_id, employee_id, email, password FROM employees 
            WHERE 
            emp_id LIKE ? OR 
            name LIKE ? OR 
            email LIKE ? OR 
            employee_id LIKE ?";

    $stmt = $pdo->prepare($sql);
    $search = "%$term%";
    $stmt->execute([$search, $search, $search, $search]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['search_term'] = $term;
    $response['found_count'] = count($users);
    $response['users'] = array_map(function ($u) {
        $u['password_set'] = !empty($u['password']);
        $u['password_sample'] = substr($u['password'] ?? '', 0, 10);
        return $u;
    }, $users);

    // 2. Dump first 5 users to see pattern
    $stmt = $pdo->query("SELECT id, emp_id, name, employee_id FROM employees LIMIT 5");
    $response['sample_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
