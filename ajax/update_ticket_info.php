<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['employee_id'])) {
        throw new Exception('Employee ID is required');
    }

    $employee_id = intval($input['employee_id']);
    $last_ticket_date = !empty($input['last_ticket_date']) ? $input['last_ticket_date'] : null;
    $next_ticket_date = !empty($input['next_ticket_date']) ? $input['next_ticket_date'] : null;
    $ticket_frequency = intval($input['ticket_frequency_years']);

    if ($ticket_frequency < 1) {
        $ticket_frequency = 2; // Default
    }

    $stmt = $pdo->prepare("UPDATE employees SET last_ticket_date = ?, next_ticket_date = ?, ticket_frequency_years = ? WHERE id = ?");
    $stmt->execute([$last_ticket_date, $next_ticket_date, $ticket_frequency, $employee_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Ticket information updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
