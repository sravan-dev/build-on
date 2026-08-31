<?php
/**
 * Check Users Table - DELETE AFTER USE
 */
header('Content-Type: application/json');
require_once 'includes/db.php';

$response = [];

try {
    // 1. List all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $response['tables'] = $tables;

    // 2. If 'users' table exists, inspect it
    if (in_array('users', $tables)) {
        $stmt = $pdo->query("SHOW COLUMNS FROM users");
        $response['users_columns'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Search for b0001 in users
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? LIMIT 5");
        $term = "%b0001%";
        $stmt->execute([$term, $term]);
        $response['users_match'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Also dump first 3 users
        $start = $pdo->query("SELECT * FROM users LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $response['users_sample'] = $start;
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
