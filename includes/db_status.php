<?php
// Lightweight DB status endpoint used by the footer to show connection state
// It should return JSON { ok: true } when a simple query runs.
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/db.php';
    // run a very small query to validate connection
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetchColumn();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
