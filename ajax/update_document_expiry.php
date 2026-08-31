<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['employee_id']) || empty($input['document_type']) || empty($input['new_expiry_date'])) {
        throw new Exception('Missing required fields');
    }

    $employee_id = intval($input['employee_id']);
    $document_type = $input['document_type'];
    $new_expiry_date = $input['new_expiry_date'];

    // Map document type to database column
    $column_map = [
        'Qatar ID' => 'qatar_id_expiry',
        'Passport' => 'passport_expiry',
        'Visa' => 'visa_expiry'
    ];

    if (!array_key_exists($document_type, $column_map)) {
        throw new Exception('Invalid document type');
    }

    $column = $column_map[$document_type];

    // Update the specific column for the employee
    // Use a safe column name from the whitelist map
    $sql = "UPDATE employees SET $column = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_expiry_date, $employee_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Document expiry updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
