<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name'])) {
        throw new Exception('Client name is required');
    }

    $name = trim($input['name']);
    $contact = trim($input['contact'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $address = trim($input['address'] ?? '');

    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO clients (name, contact, email, phone, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $contact, $email, $phone, $address]);
    
    $clientId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Client added successfully',
        'client' => [
            'id' => $clientId,
            'name' => $name
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
