<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid purchase ID']);
    exit;
}

$purchase_id = (int)$_GET['id'];

try {
    // Fetch purchase details
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        http_response_code(404);
        echo json_encode(['error' => 'Purchase not found']);
        exit;
    }

    // Fetch purchase items
    $stmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
    $stmt->execute([$purchase_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $purchase['items'] = $items;

    echo json_encode($purchase);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
