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
        throw new Exception('Project name is required');
    }

    $name = trim($input['name']);
    $clientId = (int) ($input['client_id'] ?? 0);
    $clientId = $clientId > 0 ? $clientId : null;
    $startDate = trim($input['start_date'] ?? '');
    $startDate = $startDate !== '' ? $startDate : null;
    $status = trim($input['status'] ?? '') ?: 'Active';

    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO projects (name, client_id, start_date, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $clientId, $startDate, $status]);

    $projectId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Project added successfully',
        'project' => [
            'id' => $projectId,
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
