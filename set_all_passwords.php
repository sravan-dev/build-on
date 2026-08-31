<?php
/**
 * Set password for first employee - DELETE AFTER USE
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

$password = 'Qatar123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Set password for ALL employees so any can login for testing
$stmt = $pdo->prepare("UPDATE employees SET password = ?");
$stmt->execute([$hash]);
$count = $stmt->rowCount();

// Get employees with password set
$employees = $pdo->query("SELECT id, name, emp_id FROM employees LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'message' => "Password 'Qatar123' set for $count employees",
    'test_accounts' => $employees,
    'note' => 'DELETE THIS FILE NOW!'
], JSON_PRETTY_PRINT);
